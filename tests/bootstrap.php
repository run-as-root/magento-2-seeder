<?php

declare(strict_types=1);

/**
 * Stub Magento framework classes so unit tests can run outside a Magento installation.
 */
if (!class_exists(\Magento\Framework\Component\ComponentRegistrar::class)) {
    eval('
        namespace Magento\Framework\Component;
        class ComponentRegistrar {
            public const MODULE = "module";
            public static function register(string $type, string $name, string $path): void {}
        }
    ');
}

if (!interface_exists(\Magento\Framework\ObjectManagerInterface::class)) {
    eval('
        namespace Magento\Framework;
        interface ObjectManagerInterface {
            public function create(string $type, array $arguments = []): object;
            public function get(string $type): object;
            public function configure(array $configuration): void;
        }
    ');
}

if (!class_exists(\Magento\Framework\App\Filesystem\DirectoryList::class)) {
    eval('
        namespace Magento\Framework\App\Filesystem;
        class DirectoryList {
            public function getRoot(): string { return ""; }
            public function getPath(string $code): string { return ""; }
        }
    ');
}

if (!interface_exists(\Psr\Log\LoggerInterface::class)) {
    eval('
        namespace Psr\Log;
        interface LoggerInterface {
            public function emergency(string|\Stringable $message, array $context = []): void;
            public function alert(string|\Stringable $message, array $context = []): void;
            public function critical(string|\Stringable $message, array $context = []): void;
            public function error(string|\Stringable $message, array $context = []): void;
            public function warning(string|\Stringable $message, array $context = []): void;
            public function notice(string|\Stringable $message, array $context = []): void;
            public function info(string|\Stringable $message, array $context = []): void;
            public function debug(string|\Stringable $message, array $context = []): void;
            public function log($level, string|\Stringable $message, array $context = []): void;
        }
    ');
}

if (!class_exists(\Magento\Framework\App\State::class)) {
    eval('
        namespace Magento\Framework\App;
        class State {
            public function setAreaCode(string $code): void {}
            public function getAreaCode(): string { return ""; }
        }
    ');
}

if (!class_exists(\Magento\Framework\App\Area::class)) {
    eval('
        namespace Magento\Framework\App;
        class Area {
            public const AREA_ADMINHTML = "adminhtml";
            public const AREA_FRONTEND = "frontend";
            public const AREA_GLOBAL = "global";
        }
    ');
}

if (!class_exists(\Magento\Framework\Exception\LocalizedException::class)) {
    eval('
        namespace Magento\Framework\Exception;
        class LocalizedException extends \Exception {}
    ');
}

require dirname(__DIR__) . '/vendor/autoload.php';
