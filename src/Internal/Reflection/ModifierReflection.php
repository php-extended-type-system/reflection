<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Internal\Reflection;

use Typhoon\Reflection\ModifierKind;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 */
final class ModifierReflection
{
    public function __construct(
        public readonly bool $native = false,
        public readonly bool $annotated = false,
    ) {}

    public function byKind(ModifierKind $kind = ModifierKind::Resolved): bool
    {
        return match ($kind) {
            ModifierKind::Resolved => $this->native || $this->annotated,
            ModifierKind::Native => $this->native,
            ModifierKind::Annotated => $this->annotated,
        };
    }
}
