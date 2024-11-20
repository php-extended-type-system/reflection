<?php

declare(strict_types=1);

namespace Typhoon\Reflection;

use Typhoon\ChangeDetector\ChangeDetector;
use Typhoon\ChangeDetector\PhpExtensionVersionChangeDetector;
use Typhoon\ChangeDetector\PhpVersionChangeDetector;

/**
 * @api
 */
final class Extension
{
    private const CORE = 'Core';

    private static ?self $core = null;

    public static function core(): self
    {
        return self::$core ??= new self(self::CORE, new PhpVersionChangeDetector());
    }

    public static function fromName(string $name): self
    {
        if ($name === self::CORE) {
            return self::core();
        }

        \assert($name !== '');

        return new self($name, PhpExtensionVersionChangeDetector::fromName($name));
    }

    /**
     * @param non-empty-string $name
     */
    private function __construct(
        public readonly string $name,
        public readonly ChangeDetector $changeDetector,
    ) {}
}
