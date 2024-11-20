<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Internal\Reflection;

use Typhoon\Reflection\TypeKind;
use Typhoon\Type\Type;
use Typhoon\Type\types;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 * @todo add resolved as property
 */
final class TypeReflection
{
    public function __construct(
        public readonly ?Type $native = null,
        public readonly ?Type $annotated = null,
        public readonly ?Type $tentative = null,
    ) {}

    public function byKind(TypeKind $kind = TypeKind::Resolved): ?Type
    {
        return match ($kind) {
            TypeKind::Resolved => $this->resolved(),
            TypeKind::Native => $this->native,
            TypeKind::Tentative => $this->tentative,
            TypeKind::Annotated => $this->annotated,
            default => null,
        };
    }

    public function resolved(): Type
    {
        return $this->annotated ?? $this->tentative ?? $this->native ?? types::mixed;
    }
}
