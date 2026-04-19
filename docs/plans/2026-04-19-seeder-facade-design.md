# Seeder Facade — Design (2026-04-19)

## Problem

Writing a class-based seeder is heavier than the Laravel equivalent. Today:

```php
class MassOrderSeeder implements SeederInterface
{
    public function __construct(private readonly EntityHandlerPool $handlerPool) {}

    public function getType(): string { return 'order'; }
    public function getOrder(): int { return 40; }

    public function run(): void
    {
        $handler = $this->handlerPool->get('order');
        for ($i = 0; $i < 50; $i++) {
            $handler->create([...]);
        }
    }
}
```

The indirection (`$handlerPool->get('order')->create([...])`) and the lack of a fluent entry point make the DX meaningfully worse than `Order::factory()->count(50)->create()`.

## Goal

Add a thin, additive facade layer — an abstract `Seeder` base class plus a `SeedBuilder` fluent entry point — that makes the happy path short and obvious, without renaming anything or breaking existing seeders.

## Non-goals

- Killing `getType()` / `getOrder()` boilerplate
- Batching / transactions inside `SeedBuilder` (use `--generate` for mass runs)
- Static `Seed::` facade
- Laravel-style `->state()`, `->for()`, `->has()`
- Faker locale / seed overrides inside `Seeder` subclasses
- Any rename of existing classes, interfaces, or namespaces

## Design

### Abstract `Seeder` base class

Located at `RunAsRoot\Seeder\Seeder`. Users extend it instead of implementing `SeederInterface` directly.

```php
abstract class Seeder implements SeederInterface
{
    public function __construct(
        protected readonly EntityHandlerPool $handlers,
        protected readonly DataGeneratorPool $generators,
        protected readonly FakerFactory $fakerFactory,
        protected readonly GeneratedDataRegistry $registry,
    ) {}

    protected function customers(): SeedBuilder  { return $this->makeBuilder('customer'); }
    protected function products(): SeedBuilder   { return $this->makeBuilder('product'); }
    protected function orders(): SeedBuilder     { return $this->makeBuilder('order'); }
    protected function categories(): SeedBuilder { return $this->makeBuilder('category'); }
    protected function cms(): SeedBuilder        { return $this->makeBuilder('cms'); }

    protected function seed(string $type): SeedBuilder { return $this->makeBuilder($type); }

    private function makeBuilder(string $type): SeedBuilder
    {
        return new SeedBuilder(
            $type,
            $this->handlers,
            $this->generators,
            $this->fakerFactory,
            $this->registry,
        );
    }

    abstract public function getType(): string;
    abstract public function getOrder(): int;
    abstract public function run(): void;
}
```

**Plurals** (`orders()`, `products()`) read naturally with `->count(50)` and avoid collision with the interface's `getOrder()` priority method.

`getType()` / `getOrder()` / `run()` stay abstract — out of scope for this change.

### `SeedBuilder` fluent API

Located at `RunAsRoot\Seeder\SeedBuilder`. Throwaway value object — one per call, not a DI service.

```php
final class SeedBuilder
{
    public function count(int $n): self;
    public function with(array $data): self;
    public function using(callable $fn): self;
    public function subtype(string $subtype): self;
    public function create(): array; // int[] of created ids
}
```

Behavior matrix:

| Call | Result |
|---|---|
| `$this->orders()->create()` | 1 order, Faker data from `OrderDataGenerator` |
| `$this->orders()->with([...])->create()` | 1 order, generator data merged with overrides |
| `$this->orders()->count(50)->create()` | 50 orders, each pure Faker |
| `$this->orders()->count(50)->with([...])->create()` | 50, each Faker-merged-with-static |
| `$this->orders()->count(50)->using(fn($i, $faker) => [...])->create()` | 50, callback merges over generator defaults |
| `$this->products()->subtype('bundle')->count(10)->create()` | equivalent to `seed('product.bundle')` path |

**Precedence (most specific wins):** `using()` return > `with()` data > generator data.

**Errors:**
- `create()` without `with()` or a registered generator for the type → `\RuntimeException('No data generator for type "foo"; pass data via ->with(...)')`.
- `with()` + no generator → writes raw data directly to the handler (mirrors current array-seeder behavior).

**Return value:** `int[]` of ids, aligning with `EntityHandlerInterface::create(): int`.

### Integration

- **DI.** `SeederDiscovery::processPhpFile()` already instantiates seeder classes via `ObjectManagerInterface::create()`, so the base class constructor's deps auto-wire. User subclasses that add their own deps override the constructor and call `parent::__construct(...)`.
- **`SeederRunner`.** Unchanged — calls `$seeder->run()` polymorphically.
- **`ArraySeederAdapter` / JSON / YAML.** Unchanged — those files do not extend `Seeder`.
- **`GenerateRunner` / `--generate` CLI.** Unchanged.
- **Registry writeback.** `SeedBuilder::create()` writes created ids into `GeneratedDataRegistry` so later builders within the same `run()` can reference them via generators. Matches `GenerateRunner` semantics minus the batching/transaction wrap.
- **No batching.** `SeedBuilder` runs row-by-row. Users who need transaction-batched mass creation use `--generate=product:5000` (which already has batching).

## Example — after

```php
class MassOrderSeeder extends Seeder
{
    public function getType(): string { return 'order'; }
    public function getOrder(): int { return 40; }

    public function run(): void
    {
        $this->orders()
            ->count(50)
            ->with(['items' => [['sku' => 'TSHIRT-001', 'qty' => 2]]])
            ->create();
    }
}
```

## Testing

- Unit-test `SeedBuilder` with mocked `EntityHandlerPool` + `DataGeneratorPool` + stub Faker. Cover the 6 matrix rows + 1 error case.
- Unit-test `Seeder` base class — the helper methods return a `SeedBuilder` bound to the correct type.
- Add one `examples/`-style class seeder extending `Seeder` and cover it in the existing `db:seed` CLI smoke test (commit `c67697b`) under the Graycore integration env.
- PHP unit tests: `final class`, snake_case method names (per `~/.claude/CLAUDE.md`).

## Docs

- Rewrite the README "Class-Based" section with the new style as primary; keep a short `implements SeederInterface` fallback paragraph for low-level users.
- Add `examples/FluentOrderSeeder.php` extending `Seeder`.
- No migration guide — existing seeders keep working verbatim.

## Release

- Minor version bump (additive, BC).
- `CHANGELOG.md` entry: `Added: abstract Seeder base class + SeedBuilder fluent API.`
