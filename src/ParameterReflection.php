<?php

declare(strict_types=1);

namespace Typhoon\Reflection;

use Typhoon\DeclarationId\AnonymousFunctionId;
use Typhoon\DeclarationId\Id;
use Typhoon\DeclarationId\MethodId;
use Typhoon\DeclarationId\NamedFunctionId;
use Typhoon\DeclarationId\ParameterId;
use Typhoon\Reflection\Declaration\ConstantExpression\ConstantExpression;
use Typhoon\Reflection\Declaration\ConstantExpression\ConstantExpressionContext;
use Typhoon\Reflection\Declaration\ConstantExpression\ReflectorEvaluationContext;
use Typhoon\Reflection\Declaration\Context;
use Typhoon\Reflection\Declaration\ParameterDeclaration;
use Typhoon\Reflection\Declaration\PassedBy;
use Typhoon\Reflection\Internal\NativeAdapter\ParameterAdapter;
use Typhoon\Reflection\Internal\Reflection\TypeReflection;
use Typhoon\Reflection\Metadata\ParameterMetadata;
use Typhoon\Type\Type;

/**
 * @api
 * @psalm-import-type Parameters from TyphoonReflector
 * @psalm-import-type Attributes from TyphoonReflector
 */
final class ParameterReflection
{
    /**
     * @internal
     * @psalm-internal Typhoon\Reflection
     * @param list<ParameterDeclaration> $declarations
     * @param array<non-empty-string, ParameterMetadata> $metadatas
     * @return Parameters
     */
    public static function from(array $declarations, array $metadatas): Collection
    {
        $reflections = [];

        foreach ($declarations as $index => $declaration) {
            $id = Id::parameter($declaration->context->id, $declaration->name);
            $metadata = $metadatas[$declaration->name] ?? null;

            $reflections[$declaration->name] = new self(
                id: $id,
                declarationId: $id,
                type: new TypeReflection($declaration->type, $metadata?->type),
                default: $declaration->default,
                variadic: $declaration->variadic,
                passedBy: $declaration->passedBy,
                promoted: $declaration->isPromoted(),
                attributes: AttributeReflection::from($id, $declaration->attributes),
                phpDoc: $declaration->phpDoc,
                internallyOptional: $declaration->internallyOptional,
                index: $index,
                annotated: false,
                deprecation: $metadata?->deprecation,
                snippet: $declaration->snippet,
            );
        }

        return new Collection($reflections);
    }

    /**
     * @var non-empty-string
     */
    public readonly string $name;

    /**
     * @param Attributes $attributes
     * @param non-negative-int $index
     */
    private function __construct(
        public readonly ParameterId $id,
        public readonly ParameterId $declarationId,
        public readonly TypeReflection $type,
        private readonly ?ConstantExpression $default,
        private readonly bool $variadic,
        private readonly PassedBy $passedBy,
        private readonly bool $promoted,
        private readonly Collection $attributes,
        private readonly ?SourceCodeSnippet $phpDoc,
        private readonly bool $internallyOptional,
        private readonly int $index,
        private readonly bool $annotated,
        private readonly ?Deprecation $deprecation,
        private readonly ?SourceCodeSnippet $snippet,
        private readonly ?TyphoonReflector $reflector = null,
    ) {
        $this->name = $id->name;
    }

    /**
     * @return non-negative-int
     */
    public function index(): int
    {
        return $this->index;
    }

    public function snippet(): ?SourceCodeSnippet
    {
        return $this->snippet;
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

    /**
     * @todo refactor to reflector->reflect($id)
     */
    public function function(): FunctionReflection|MethodReflection
    {
        $functionId = $this->id->function;

        if ($functionId instanceof MethodId) {
            return $this->reflector()->reflectClass($functionId->class)->methods()[$functionId->name];
        }

        \assert($functionId instanceof NamedFunctionId);

        return $this->reflector()->reflectFunction($functionId);
    }

    public function class(): ?ClassReflection
    {
        if ($this->id->function instanceof MethodId) {
            return $this->reflector()->reflectClass($this->id->function->class);
        }

        return null;
    }

    public function hasDefaultValue(): bool
    {
        return $this->default !== null;
    }

    /**
     * This method returns the actual parameter's default value and thus might trigger autoloading or throw errors.
     */
    public function evaluateDefault(): mixed
    {
        return $this->default?->evaluate(new ReflectorEvaluationContext($this->reflector()));
    }

    public function isNative(): bool
    {
        return !$this->annotated;
    }

    public function isAnnotated(): bool
    {
        return $this->annotated;
    }

    public function isOptional(): bool
    {
        return $this->internallyOptional
            || $this->variadic
            || $this->default !== null;
    }

    public function canBePassedByValue(): bool
    {
        return $this->passedBy === PassedBy::Value || $this->passedBy === PassedBy::ValueOrReference;
    }

    public function canBePassedByReference(): bool
    {
        return $this->passedBy === PassedBy::Reference || $this->passedBy === PassedBy::ValueOrReference;
    }

    /**
     * @psalm-assert-if-true !null $this->promotedParameter()
     */
    public function isPromoted(): bool
    {
        return $this->promoted;
    }

    public function isVariadic(): bool
    {
        return $this->variadic;
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

    public function toNativeReflection(): \ReflectionParameter
    {
        return new ParameterAdapter($this, $this->reflector(), $this->default);
    }

    private function reflector(): TyphoonReflector
    {
        return $this->reflector ?? throw new \LogicException('No reflector');
    }

    /**
     * @internal
     * @psalm-internal Typhoon\Reflection
     */
    public function __inherit(MethodId $methodId, TypeReflection $type): self
    {
        $id = Id::parameter($methodId, $this->name);

        return new self(
            id: $id,
            declarationId: $this->declarationId,
            type: $type,
            default: $this->default,
            variadic: $this->variadic,
            passedBy: $this->passedBy,
            promoted: $this->promoted,
            attributes: $this->attributes->map(static fn(AttributeReflection $attribute): AttributeReflection => $attribute->__withTargetId($id)),
            phpDoc: $this->phpDoc,
            internallyOptional: $this->internallyOptional,
            index: $this->index,
            annotated: $this->annotated,
            deprecation: $this->deprecation,
            snippet: $this->snippet,
        );
    }

    /**
     * @internal
     * @psalm-internal Typhoon\Reflection
     * @param Context<MethodId> $newMethodContext
     */
    public function __use(Context $newMethodContext, TypeReflection $type): self
    {
        $id = Id::parameter($newMethodContext->id, $this->name);

        return new self(
            id: $id,
            declarationId: $this->declarationId,
            type: $type,
            default: $this->default?->rebuild(new ConstantExpressionContext($newMethodContext)),
            variadic: $this->variadic,
            passedBy: $this->passedBy,
            promoted: $this->promoted,
            attributes: $this->attributes->map(static fn(AttributeReflection $attribute): AttributeReflection => $attribute->__withTargetId($id)),
            phpDoc: $this->phpDoc,
            internallyOptional: $this->internallyOptional,
            index: $this->index,
            annotated: $this->annotated,
            deprecation: $this->deprecation,
            snippet: $this->snippet,
        );
    }

    /**
     * @internal
     * @psalm-internal Typhoon\Reflection
     */
    public function __load(TyphoonReflector $reflector, NamedFunctionId|AnonymousFunctionId|MethodId $functionId): self
    {
        \assert($this->reflector === null);

        $id = Id::parameter($functionId, $this->name);

        return new self(
            id: $id,
            declarationId: $this->declarationId,
            type: $this->type,
            default: $this->default,
            variadic: $this->variadic,
            passedBy: $this->passedBy,
            promoted: $this->promoted,
            attributes: $this->attributes->map(static fn(AttributeReflection $attribute): AttributeReflection => $attribute->__load($reflector, $id)),
            phpDoc: $this->phpDoc,
            internallyOptional: $this->internallyOptional,
            index: $this->index,
            annotated: $this->annotated,
            deprecation: $this->deprecation,
            snippet: $this->snippet,
            reflector: $reflector,
        );
    }
}
