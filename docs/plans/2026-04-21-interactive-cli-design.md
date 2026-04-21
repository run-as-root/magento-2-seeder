# Interactive CLI & `db:seed:make` — Design

**Date:** 2026-04-21
**Status:** Approved, ready for implementation plan

## Problem

First-run UX is a dead end. A new user installs the module, runs `bin/magento db:seed`, sees `No seeders found in dev/seeders/`, and has to read the README to figure out what to do next. The `dev/seeders/` directory doesn't exist yet, and there's no in-CLI path to create a seeder file. The tool knows exactly what's missing and could offer to fix it, but today it just exits.

Secondary: existing CLI output is bare `writeln` — functional, not polished. The project already ships a demo GIF, so visual quality matters.

## Goals

- Eliminate the empty-`dev/seeders/` dead end.
- Provide an always-available scaffolder for new seeder files.
- Uniform, polished output across all three commands.
- Zero regression for CI/scripted use of `db:seed` and `db:seed:status`.

## Non-goals

- Subtype-aware scaffolding (`product.bundle: 5`) — deferred.
- Scaffolding fluent `Seeder` / `SeederInterface` class files — scaffolder only emits array/JSON/YAML.
- Multi-seeder batch scaffolding in one run.
- Opening the scaffolded file in an editor.

## Architecture

Three commands, one shared output layer.

```
bin/magento db:seed         → run seeders   (non-interactive, Prompts output)
bin/magento db:seed:make    → scaffold file (interactive OR flag-driven)
bin/magento db:seed:status  → counts table  (non-interactive, Prompts output)
```

`laravel/prompts` is added to `composer.json:require`. It supplies `intro()`, `outro()`, `info()`, `warning()`, `error()`, `note()`, `select()`, `search()`, `text()`, `confirm()`, `table()`, and `progress()`. Non-TTY fallback is automatic.

## Components

### New: `src/Console/Command/SeedMakeCommand.php`

Registered as `seeder_db_seed_make` in `src/etc/di.xml` under `Magento\Framework\Console\CommandListInterface`.

**Flags:**

| Flag | Required (non-TTY) | Default |
|------|-------------------|---------|
| `--type` | yes | — |
| `--count` | yes | — |
| `--format` | no | `php` |
| `--name` | no | `{Type}Seeder` |
| `--locale` | no | `en_US` |
| `--seed` | no | none |
| `--force` | no | `false` (overwrite without prompt) |

**Decision tree:**

```
TTY + missing required flags  → run prompts
TTY + all flags present       → skip prompts, write file
non-TTY + required flags ok   → write silently
non-TTY + missing flags       → error: "run interactively or pass --type and --count"
```

**Interactive flow:**

1. `intro('Scaffold a seeder')`
2. `select('Entity type', choices from DataGeneratorPool::getAll())`
3. `text('File name', default: "{Type}Seeder", validate: ends in 'Seeder')`
4. `text('How many?', default: '10', validate: positive int)`
5. `search('Faker locale', ~15 hardcoded common locales, default en_US)`
6. `text('Faker seed (blank = random)')` — optional
7. `select('File format', ['php', 'json', 'yaml'], default php)`
8. `confirm('File exists. Overwrite?')` — only if target exists; default no
9. Ensure `dev/seeders/` exists (mkdir auto), write file
10. `note("Created dev/seeders/{Name}.{ext}")` + `outro('Run bin/magento db:seed to execute')`

### New: `src/Service/Scaffold/SeederFileBuilder.php`

Single service: `build(string $type, int $count, string $locale, ?int $seed, string $format): string`.

Unit-testable without Magento bootstrap. Outputs must round-trip cleanly through `ArraySeederAdapter`:

**PHP** (matches `examples/GenerateOrderSeeder.php`):
```php
<?php

declare(strict_types=1);

return [
    'type' => 'order',
    'count' => 100,
    'locale' => 'en_US',
    // 'seed' => 42,
];
```

**JSON:**
```json
{
    "type": "order",
    "count": 100,
    "locale": "en_US"
}
```

**YAML:**
```yaml
type: order
count: 100
locale: en_US
```

`seed` is only emitted when provided (PHP keeps the commented `seed` line as documentation; JSON/YAML omit entirely). YAML uses `Symfony\Component\Yaml\Yaml::dump()`; JSON uses `json_encode(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)`; PHP is a small inline template.

### Updated: `src/Console/Command/SeedCommand.php`

Output-only rewrite. No new interactivity — command must remain usable from Makefiles, post-install hooks, CI.

- Start: `intro('Seeder')`; `note('Fresh mode: cleaning existing data')` if `--fresh`.
- Per-seeder success: `info("✓ {type}")`; failure: `error("✗ {type}: {message}")`.
- **Empty/missing `dev/seeders/` hint** — the flagged UX issue:
  `warning('No seeders found in dev/seeders/')` + `note('Run bin/magento db:seed:make to scaffold one.')`
- `--generate` progress: swap Symfony `ProgressBar` for Laravel Prompts `Progress`, via a thin `ProgressReporter` adapter that translates the push-style `onProgress(type, done, total)` callback to Prompts' `advance()` API.
- End: `outro('Done. N seeder(s) completed.')` or `outro('Done with errors.')`.

### Updated: `src/Console/Command/SeedStatusCommand.php`

Swap the hand-rolled counts table for Prompts `table()`. Output-only; no interactivity.

### Progress adapter: `src/Service/ProgressReporter.php`

Wraps `Laravel\Prompts\Progress`. Holds one `Progress` instance per entity type; starts on first push, `advance()`s on subsequent pushes, `finish()`s on type transition. `GenerateRunner::run()` is not modified — the adapter plugs into the existing `?callable $onProgress` parameter.

## Data flow (interactive scaffold)

```
User runs: bin/magento db:seed:make
  ↓
SeedMakeCommand::execute()
  ↓
Detect TTY + read flags
  ↓
If TTY + missing flags: prompts (type → name → count → locale → seed → format)
  ↓
Validate + resolve filename
  ↓
If file exists: confirm overwrite (or --force)
  ↓
mkdir -p dev/seeders/
  ↓
SeederFileBuilder.build(...) → string
  ↓
file_put_contents + note() + outro()
```

## Error handling

| Case | Behavior |
|------|----------|
| Invalid `--type` | `error()` with list of available types from pool; exit 1 |
| Invalid `--count` (non-positive) | `error('Count must be a positive integer')`; exit 1 |
| Invalid `--format` | `error()` with `[php, json, yaml]`; exit 1 |
| Non-writable `dev/seeders/` | `error()` with path + reason; exit 1 |
| Non-TTY + missing `--type`/`--count` | `error('db:seed:make needs --type and --count for non-interactive use')`; exit 1 |
| File exists + no `--force` + non-TTY | `error('Target file exists; pass --force to overwrite')`; exit 1 |
| File exists + TTY | `confirm('Overwrite?')` default no |

## Testing

**Unit (PHPUnit, `final` classes, `snake_case` methods):**
- `SeederFileBuilderTest` — one test per format × (with-seed | without-seed). Assert PHP output is a valid returnable array; JSON parseable; YAML parseable. Each must pass through `ArraySeederAdapter` without error.
- `SeedMakeCommandTest` — flag paths via `CommandTester`:
  - all flags → writes expected file
  - invalid type → exit 1 with helpful message
  - non-positive count → exit 1
  - non-TTY + missing required → exit 1
  - overwrite confirm (TTY path) — mocked filesystem + Prompts fake
  - auto-creates `dev/seeders/` when missing

**Integration (`Test/Integration/`):**
- Run `db:seed:make --type=order --count=5 --format=php --name=SmokeOrder` in a Mage-OS env; then `db:seed`; assert 5 orders exist. Round-trip coverage.

## Risks

- `laravel/prompts` requires `symfony/console ^6.2 || ^7.0`. Current `require-dev` allows `^6.0`. Magento 2.4.7+ ships `^6.4`, so the tightening is cosmetic in practice, but it's a published-package-level change worth calling out.
- TTY detection through `bin/magento` wrappers: Prompts uses `stream_isatty(STDIN)`; the flag-driven path is the guaranteed fallback for any edge case.
- Prompts' `Progress` is an iterator helper; we use the underlying class directly for push-style callbacks. Stable API but slightly off the documented path — covered by adapter unit tests.
- Adding a runtime dependency increases install footprint (~50KB pure PHP). Acceptable for the UX gain.

## YAGNI rejected

- Subtype selection (`product.bundle` multiselect) — advanced, low-traffic, easy to add later.
- Editor open after scaffold.
- Multi-file scaffolding in one command run.
- Rewriting SeederInterface class generation (fluent `Seeder` subclass, etc.).
- Top-level interactive menu on bare `db:seed` (rejected in Q1 — breaks scripted use).

## Out-of-session follow-ups

- README update: document `db:seed:make` in the Quick Start section, so the "empty dir" hint lands users somewhere documented.
- CHANGELOG entry for the minor release that ships this.
- Migrate SeedStatusCommand output to Laravel Prompts table().
