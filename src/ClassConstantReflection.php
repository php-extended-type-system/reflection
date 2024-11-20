<?php

declare(strict_types=1);

namespace Typhoon\Reflection;

use Typhoon\DeclarationId\AnonymousClassId;
use Typhoon\DeclarationId\ClassConstantId;
use Typhoon\DeclarationId\Id;
use Typhoon\DeclarationId\NamedClassId;
use Typhoon\Reflection\Declaration\ClassConstantDeclaration;
use Typhoon\Reflection\Declaration\ConstantExpression\ConstantExpression;
use Typhoon\Reflection\Declaration\ConstantExpression\ConstantExpressionContext;
use Typhoon\Reflection\Declaration\ConstantExpression\ReflectorEvaluationContext;
use Typhoon\Reflection\Declaration\Context;
use Typhoon\Reflection\Declaration\EnumCaseDeclaration;
use Typhoon\Reflection\Declaration\Visibility;
use Typhoon\Reflection\Internal\NativeAdapter\ClassConstantAdapter;
use Typhoon\Reflection\Internal\NativeAdapter\EnumBackedCaseAdapter;
use Typhoon\Reflection\Internal\NativeAdapter\EnumUnitCaseAdapter;
use Typhoon\Reflection\Internal\Reflection\ModifierReflection;
use Typhoon\Reflection\Internal\Reflection\TypeReflection;
use Typhoon\Reflection\Metadata\ClassConstantMetadata;
use Typhoon\Type\Type;

/**
 * @api
 * @psalm-import-type Attributes from TyphoonReflector
 * @psalm-import-type ClassConstants from TyphoonReflector
 */
final class ClassConstantReflection
{
    /**
     * @var non-empty-string
     */
    public readonly string $name;

    /**
     * @param Attributes $attributes
     */
    private function __construct(
        public readonly ClassConstantId $id,
        public readonly ClassConstantId $declarationId,
        private readonly ModifierReflection $final,
        private readonly ?Visibility $visibility,
        public readonly TypeReflection $type,
        private readonly ?ConstantExpression $value,
        private readonly null|int|string $backingValue,
        private readonly Collection $attributes,
        private readonly ?SourceCodeSnippet $snippet,
        private readonly ?SourceCodeSnippet $phpDoc,
        private readonly ?Deprecation $deprecation,
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

    public function snippet(): ?SourceCodeSnippet
    {
        return $this->snippet;
    }

    /*public function isInternallyDefined(): bool
    {
        return $this->extension !== null;
    }*/

    public function phpDoc(): ?SourceCodeSnippet
    {
        return $this->phpDoc;
    }

    public function class(): ClassReflection
    {
        return $this->reflector()->reflectClass($this->id->class);
    }

    /**
     * This method returns the actual class constant's value and thus might trigger autoloading or throw errors.
     *
     * @see https://github.com/typhoon-php/typhoon/issues/64
     */
    public function evaluate(): mixed
    {
        if ($this->value === null) {
            \assert($this->id->class instanceof NamedClassId, 'Enum cannot be an anonymous class');

            return \constant($this->id->class->name . '::' . $this->id->name);
        }

        return $this->value->evaluate(new ReflectorEvaluationContext($this->reflector()));
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
        return $this->visibility === null || $this->visibility === Visibility::Public;
    }

    public function isFinal(ModifierKind $kind = ModifierKind::Resolved): bool
    {
        return $this->final->byKind($kind);
    }

    public function isEnumCase(): bool
    {
        return $this->value === null;
    }

    public function isBackedEnumCase(): bool
    {
        return $this->backingValue !== null;
    }

    public function enumBackingValue(): null|int|string
    {
        return $this->backingValue;
    }

    /**
     * @return ($kind is TypeKind::Resolved ? Type : ?Type)
     */
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

    public function toNativeReflection(): \ReflectionClassConstant
    {
        $adapter = new ClassConstantAdapter($this, $this->reflector());

        if ($this->isBackedEnumCase()) {
            return new EnumBackedCaseAdapter($adapter, $this->backingValue);
        }

        if ($this->isEnumCase()) {
            return new EnumUnitCaseAdapter($adapter);
        }

        return $adapter;
    }

    private function reflector(): TyphoonReflector
    {
        \assert($this->reflector !== null);

        return $this->reflector;
    }

    /**
     * @internal
     * @psalm-internal Typhoon\Reflection
     */
    public static function __declare(
        ClassConstantDeclaration|EnumCaseDeclaration $declaration,
        ClassConstantMetadata $metadata = new ClassConstantMetadata(),
    ): self {
        $id = Id::classConstant($declaration->context->id, $declaration->name);

        if ($declaration instanceof EnumCaseDeclaration) {
            return new self(
                id: $id,
                declarationId: $id,
                final: new ModifierReflection(),
                visibility: Visibility::Public,
                type: new TypeReflection(),
                value: null,
                backingValue: $declaration->backingValue,
                attributes: AttributeReflection::from($id, $declaration->attributes),
                snippet: $declaration->snippet,
                phpDoc: $declaration->phpDoc,
                deprecation: $metadata->deprecation,
            );
        }

        return new self(
            id: $id,
            declarationId: $id,
            final: new ModifierReflection($declaration->final, $metadata->final),
            visibility: $declaration->visibility,
            type: new TypeReflection($declaration->type, $metadata->type),
            value: $declaration->value,
            backingValue: null,
            attributes: AttributeReflection::from($id, $declaration->attributes),
            snippet: $declaration->snippet,
            phpDoc: $declaration->phpDoc,
            deprecation: $metadata->deprecation,
        );
    }

    /**
     * @internal
     * @psalm-internal Typhoon\Reflection
     */
    public function __inherit(NamedClassId|AnonymousClassId $classId, TypeReflection $type): self
    {
        $id = Id::classConstant($classId, $this->name);

        return new self(
            id: $id,
            declarationId: $this->declarationId,
            final: $this->final,
            visibility: $this->visibility,
            type: $type,
            value: $this->value,
            backingValue: $this->backingValue,
            attributes: $this->attributes->map(static fn(AttributeReflection $attribute): AttributeReflection => $attribute->__withTargetId($id)),
            snippet: $this->snippet,
            phpDoc: $this->phpDoc,
            deprecation: $this->deprecation,
        );
    }

    /**
     * @internal
     * @psalm-internal Typhoon\Reflection
     * @param Context<NamedClassId|AnonymousClassId> $newClassContext
     */
    public function __use(Context $newClassContext, TypeReflection $type): self
    {
        $id = Id::classConstant($newClassContext->id, $this->name);

        return new self(
            id: $id,
            declarationId: $this->declarationId,
            final: $this->final,
            visibility: $this->visibility,
            type: $type,
            value: $this->value?->rebuild(new ConstantExpressionContext($newClassContext)),
            backingValue: $this->backingValue,
            attributes: $this->attributes->map(static fn(AttributeReflection $attribute): AttributeReflection => $attribute->__withTargetId($id)),
            snippet: $this->snippet,
            phpDoc: $this->phpDoc,
            deprecation: $this->deprecation,
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
            id: $id = Id::classConstant($classId, $this->name),
            declarationId: $this->declarationId,
            final: $this->final,
            visibility: $this->visibility,
            type: $this->type,
            value: $this->value,
            backingValue: $this->backingValue,
            attributes: $this->attributes->map(static fn(AttributeReflection $attribute): AttributeReflection => $attribute->__load($reflector, $id)),
            snippet: $this->snippet,
            phpDoc: $this->phpDoc,
            deprecation: $this->deprecation,
            reflector: $reflector,
        );
    }
}
