<?php

declare(strict_types=1);

namespace Typhoon\Reflection;

use Typhoon\DeclarationId\AnonymousClassId;
use Typhoon\DeclarationId\Id;
use Typhoon\DeclarationId\MethodId;
use Typhoon\DeclarationId\NamedClassId;
use Typhoon\DeclarationId\PropertyId;
use Typhoon\Reflection\Declaration\ConstantExpression\ConstantExpression;
use Typhoon\Reflection\Declaration\ConstantExpression\ConstantExpressionContext;
use Typhoon\Reflection\Declaration\ConstantExpression\ReflectorEvaluationContext;
use Typhoon\Reflection\Declaration\Context;
use Typhoon\Reflection\Declaration\ParameterDeclaration;
use Typhoon\Reflection\Declaration\PropertyDeclaration;
use Typhoon\Reflection\Declaration\Visibility;
use Typhoon\Reflection\Internal\NativeAdapter\PropertyAdapter;
use Typhoon\Reflection\Internal\Reflection\ModifierReflection;
use Typhoon\Reflection\Internal\Reflection\TypeReflection;
use Typhoon\Reflection\Metadata\ParameterMetadata;
use Typhoon\Reflection\Metadata\PropertyMetadata;
use Typhoon\Type\Type;

/**
 * @api
 * @psalm-import-type Attributes from TyphoonReflector
 */
final class PropertyReflection
{
    /**
     * @var non-empty-string
     */
    public readonly string $name;

    /**
     * @param Attributes $attributes
     */
    private function __construct(
        public readonly PropertyId $id,
        public readonly PropertyId $declarationId,
        private readonly bool $static,
        private readonly ?Visibility $visibility,
        private readonly ModifierReflection $readonly,
        public readonly TypeReflection $type,
        private readonly ?ConstantExpression $default,
        private readonly Collection $attributes,
        private readonly ?SourceCodeSnippet $snippet,
        private readonly ?SourceCodeSnippet $phpDoc,
        private readonly ?Deprecation $deprecation,
        private readonly bool $promoted,
        private readonly bool $native,
        private readonly ?TyphoonReflector $reflector = null,
    ) {
        $this->name = $id->name;
    }

    /**
     * @return AttributeReflection[]
     * @psalm-return Attributes
     * @phpstan-return Attributes
     */
    public function attributes(): Collection
    {
        return $this->attributes;
    }

    public function phpDoc(): ?SourceCodeSnippet
    {
        return $this->phpDoc;
    }

    public function class(): ClassReflection
    {
        return $this->reflector()->reflectClass($this->id->class);
    }

    public function isNative(): bool
    {
        return $this->native;
    }

    public function isAnnotated(): bool
    {
        return !$this->native;
    }

    public function isStatic(): bool
    {
        return $this->static;
    }

    public function isPromoted(): bool
    {
        return $this->promoted;
    }

    /**
     * This method returns the actual property's default value and thus might trigger autoloading or throw errors.
     */
    public function evaluateDefault(): mixed
    {
        return $this->default?->evaluate(new ReflectorEvaluationContext($this->reflector()));
    }

    public function hasDefaultValue(): bool
    {
        return $this->default !== null;
    }

    public function isPrivate(): bool
    {
        return $this->visibility === Visibility::Private;
    }

    public function isProtected(): bool
    {
        return $this->visibility === Visibility::Protected;
    }

    public function isPublic(): bool
    {
        return $this->visibility === Visibility::Public || $this->visibility === null;
    }

    public function isReadonly(ModifierKind $kind = ModifierKind::Resolved): bool
    {
        return $this->readonly->byKind($kind);
    }

    public function type(TypeKind $kind = TypeKind::Resolved): ?Type
    {
        return $this->type->byKind($kind);
    }

    public function isDeprecated(): bool
    {
        return $this->deprecation !== null;
    }

    public function deprecation(): ?Deprecation
    {
        return $this->deprecation;
    }

    public function toNativeReflection(): \ReflectionProperty
    {
        return new PropertyAdapter($this, $this->reflector());
    }

    private function reflector(): TyphoonReflector
    {
        return $this->reflector ?? throw new \LogicException('No reflector');
    }

    /**
     * @internal
     * @psalm-internal Typhoon\Reflection
     */
    public static function __declare(
        PropertyDeclaration $declaration,
        PropertyMetadata $metadata = new PropertyMetadata(),
        ModifierReflection $classReadonly = new ModifierReflection(),
    ): self {
        $id = Id::property($declaration->context->id, $declaration->name);

        return new self(
            id: $id,
            declarationId: $id,
            static: $declaration->static,
            visibility: $declaration->visibility,
            readonly: new ModifierReflection(
                native: $classReadonly->native || $declaration->readonly,
                annotated: $classReadonly->annotated || $metadata->readonly,
            ),
            type: new TypeReflection(
                native: $declaration->type,
                annotated: $metadata->type,
            ),
            default: $declaration->default,
            attributes: AttributeReflection::from($id, $declaration->attributes),
            snippet: $declaration->snippet,
            phpDoc: $declaration->phpDoc,
            deprecation: $metadata->deprecation,
            promoted: false,
            native: true,
        );
    }

    /**
     * @internal
     * @psalm-internal Typhoon\Reflection
     * @param ParameterDeclaration<MethodId> $declaration
     */
    public static function __declarePromoted(
        ParameterDeclaration $declaration,
        ParameterMetadata $metadata = new ParameterMetadata(),
        ModifierReflection $classReadonly = new ModifierReflection(),
    ): self {
        $id = Id::property($declaration->context->id->class, $declaration->name);

        return new self(
            id: $id,
            declarationId: $id,
            static: false,
            visibility: $declaration->visibility,
            readonly: new ModifierReflection(
                native: $classReadonly->native || $declaration->readonly,
                annotated: $classReadonly->annotated || $metadata->readonly,
            ),
            type: new TypeReflection(
                native: $declaration->type,
                annotated: $metadata->type,
            ),
            default: null,
            attributes: AttributeReflection::from($id, $declaration->attributes),
            snippet: $declaration->snippet,
            phpDoc: $declaration->phpDoc,
            deprecation: $metadata->deprecation,
            promoted: true,
            native: true,
        );
    }

    /**
     * @internal
     * @psalm-internal Typhoon\Reflection
     */
    public function __inherit(NamedClassId|AnonymousClassId $classId, TypeReflection $type): self
    {
        $id = Id::property($classId, $this->name);

        return new self(
            id: $id,
            declarationId: $this->declarationId,
            static: $this->static,
            visibility: $this->visibility,
            readonly: $this->readonly,
            type: $type,
            default: $this->default,
            attributes: $this->attributes->map(static fn(AttributeReflection $attribute): AttributeReflection => $attribute->__withTargetId($id)),
            snippet: $this->snippet,
            phpDoc: $this->phpDoc,
            deprecation: $this->deprecation,
            promoted: $this->promoted,
            native: $this->native,
        );
    }

    /**
     * @internal
     * @psalm-internal Typhoon\Reflection
     * @param Context<NamedClassId|AnonymousClassId> $newClassContext
     */
    public function __use(Context $newClassContext, TypeReflection $type): self
    {
        $id = Id::property($newClassContext->id, $this->name);

        return new self(
            id: $id,
            declarationId: $this->declarationId,
            static: $this->static,
            visibility: $this->visibility,
            readonly: $this->readonly,
            type: $type,
            default: $this->default?->rebuild(new ConstantExpressionContext($newClassContext)),
            attributes: $this->attributes->map(static fn(AttributeReflection $attribute): AttributeReflection => $attribute->__withTargetId($id)),
            snippet: $this->snippet,
            phpDoc: $this->phpDoc,
            deprecation: $this->deprecation,
            promoted: $this->promoted,
            native: $this->native,
        );
    }

    /**
     * @internal
     * @psalm-internal Typhoon\Reflection
     */
    public function __load(TyphoonReflector $reflector, NamedClassId|AnonymousClassId $classId): self
    {
        \assert($this->reflector === null);

        return new self(
            id: $id = Id::property($classId, $this->name),
            declarationId: $this->declarationId,
            static: $this->static,
            visibility: $this->visibility,
            readonly: $this->readonly,
            type: $this->type,
            default: $this->default,
            attributes: $this->attributes->map(static fn(AttributeReflection $attribute): AttributeReflection => $attribute->__load($reflector, $id)),
            snippet: $this->snippet,
            phpDoc: $this->phpDoc,
            deprecation: $this->deprecation,
            promoted: $this->promoted,
            native: $this->native,
            reflector: $reflector,
        );
    }
}
