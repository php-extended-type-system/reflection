<?php

declare(strict_types=1);

namespace Typhoon\Reflection;

use Typhoon\DeclarationId\PropertyId;
use Typhoon\Reflection\Internal\Data;
use Typhoon\Reflection\Internal\Data\Visibility;
use Typhoon\Reflection\Internal\NativeAdapter\PropertyAdapter;
use Typhoon\Reflection\Internal\TypedMap\TypedMap;
use Typhoon\Type\Type;

/**
 * @api
 */
final class PropertyReflection
{
    public readonly PropertyId $id;

    /**
     * This internal property is public for testing purposes.
     * It will likely be available as part of the API in the near future.
     *
     * @internal
     * @psalm-internal Typhoon
     */
    public readonly TypedMap $data;

    /**
     * @var ?ListOf<AttributeReflection>
     */
    private ?ListOf $attributes = null;

    /**
     * @internal
     * @psalm-internal Typhoon\Reflection
     */
    public function __construct(
        PropertyId $id,
        TypedMap $data,
        private readonly TyphoonReflector $reflector,
    ) {
        $this->id = $id;
        $this->data = $data;
    }

    /**
     * @return AttributeReflection[]
     * @psalm-return ListOf<AttributeReflection>
     * @phpstan-return ListOf<AttributeReflection>
     */
    public function attributes(): ListOf
    {
        return $this->attributes ??= (new ListOf($this->data[Data::Attributes]))->map(
            fn(TypedMap $data, int $index): AttributeReflection => new AttributeReflection($this->id, $index, $data, $this->reflector),
        );
    }

    /**
     * @return ?non-empty-string
     */
    public function phpDoc(): ?string
    {
        return $this->data[Data::PhpDoc]?->getText();
    }

    public function location(): ?Location
    {
        return $this->data[Data::Location];
    }

    public function class(): ClassReflection
    {
        return $this->reflector->reflect($this->id->class);
    }

    public function isNative(): bool
    {
        return !$this->isAnnotated();
    }

    public function isAnnotated(): bool
    {
        return $this->data[Data::Annotated];
    }

    public function isStatic(): bool
    {
        return $this->data[Data::Static];
    }

    public function isPromoted(): bool
    {
        return $this->data[Data::Promoted];
    }

    public function defaultValue(): mixed
    {
        return $this->data[Data::DefaultValueExpression]?->evaluate($this->reflector);
    }

    public function hasDefaultValue(): bool
    {
        return $this->data[Data::DefaultValueExpression] !== null;
    }

    public function isPrivate(): bool
    {
        return $this->data[Data::Visibility] === Visibility::Private;
    }

    public function isProtected(): bool
    {
        return $this->data[Data::Visibility] === Visibility::Protected;
    }

    public function isPublic(): bool
    {
        $visibility = $this->data[Data::Visibility];

        return $visibility === null || $visibility === Visibility::Public;
    }

    public function isReadonly(?DeclarationKind $kind = null): bool
    {
        return match ($kind) {
            DeclarationKind::Native => $this->data[Data::NativeReadonly],
            DeclarationKind::Annotated => $this->data[Data::AnnotatedReadonly],
            null => $this->data[Data::NativeReadonly] || $this->data[Data::AnnotatedReadonly],
        };
    }

    /**
     * @return ($kind is null ? Type : ?Type)
     */
    public function type(?DeclarationKind $kind = null): ?Type
    {
        return $this->data[Data::Type]->get($kind);
    }

    private ?PropertyAdapter $native = null;

    public function native(): \ReflectionProperty
    {
        return $this->native ??= new PropertyAdapter($this, $this->reflector);
    }
}
