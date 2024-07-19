<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Internal\Inheritance;

use Typhoon\Reflection\Internal\Data;
use Typhoon\Reflection\Internal\Data\Visibility;
use Typhoon\Reflection\Internal\TypedMap\TypedMap;

/**
 * Used for properties, class constants and method parameters.
 *
 * @internal
 * @psalm-internal Typhoon\Reflection\Internal\Inheritance
 */
final class PropertyInheritance
{
    private ?TypedMap $data = null;

    private readonly TypeInheritance $type;

    public function __construct()
    {
        $this->type = new TypeInheritance();
    }

    public function applyOwn(TypedMap $data): void
    {
        $this->data = $data;
        $this->type->applyOwn($data[Data::Type]);
    }

    public function applyUsed(TypedMap $data, TypeResolver $typeResolver): void
    {
        $this->data ??= $data;
        $this->type->applyInherited($data[Data::Type], $typeResolver);
    }

    public function applyInherited(TypedMap $data, TypeResolver $typeResolver): void
    {
        if ($data[Data::Visibility] === Visibility::Private) {
            return;
        }

        $this->data ??= $data;
        $this->type->applyInherited($data[Data::Type], $typeResolver);
    }

    public function build(): ?TypedMap
    {
        return $this->data?->with(Data::Type, $this->type->build());
    }
}
