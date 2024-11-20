<?php

declare(strict_types=1);

namespace Typhoon\Reflection;

use Typhoon\DeclarationId\AnonymousClassId;
use Typhoon\DeclarationId\AnonymousFunctionId;
use Typhoon\DeclarationId\ClassConstantId;
use Typhoon\DeclarationId\MethodId;
use Typhoon\DeclarationId\NamedClassId;
use Typhoon\DeclarationId\NamedFunctionId;
use Typhoon\DeclarationId\ParameterId;
use Typhoon\DeclarationId\PropertyId;
use Typhoon\Reflection\Declaration\AttributeDeclaration;
use Typhoon\Reflection\Declaration\ConstantExpression\ConstantExpression;
use Typhoon\Reflection\Declaration\ConstantExpression\ReflectorEvaluationContext;
use Typhoon\Reflection\Internal\NativeAdapter\AttributeAdapter;

/**
 * @api
 * @psalm-import-type Attributes from TyphoonReflector
 */
final class AttributeReflection
{
    /**
     * @param list<AttributeDeclaration> $attributes
     * @return Attributes
     */
    public static function from(
        NamedFunctionId|AnonymousFunctionId|ParameterId|NamedClassId|AnonymousClassId|ClassConstantId|MethodId|PropertyId $targetId,
        array $attributes,
    ): Collection {
        $repeated = array_count_values(array_column($attributes, 'class'));

        return (new Collection($attributes))
            ->map(static fn(AttributeDeclaration $attribute, int $index): self => new self(
                targetId: $targetId,
                index: $index,
                repeated: $repeated[$attribute->class] > 1,
                class: $attribute->class,
                arguments: $attribute->arguments,
                snippet: $attribute->snippet,
            ));
    }

    /**
     * @param non-negative-int $index
     * @param non-empty-string $class
     * @param ConstantExpression<array> $arguments
     */
    private function __construct(
        private readonly NamedFunctionId|AnonymousFunctionId|ParameterId|NamedClassId|AnonymousClassId|ClassConstantId|MethodId|PropertyId $targetId,
        private readonly int $index,
        private readonly bool $repeated,
        private readonly string $class,
        private readonly ConstantExpression $arguments,
        private readonly ?SourceCodeSnippet $snippet,
        private readonly ?TyphoonReflector $reflector = null,
    ) {}

    /**
     * @return non-negative-int
     */
    public function index(): int
    {
        return $this->index;
    }

    /**
     * Attribute's class.
     *
     * @return non-empty-string Not class-string, because the class might not exist. Same is true for {@see \ReflectionAttribute::getName()}.
     */
    public function className(): string
    {
        return $this->class;
    }

    /**
     * Attribute's class reflection.
     *
     * @return ClassReflection<object, NamedClassId<class-string>>
     */
    public function class(): ClassReflection
    {
        /** @var ClassReflection<object, NamedClassId<class-string>> */
        return $this->reflector()->reflectClass($this->className());
    }

    public function targetId(): NamedFunctionId|AnonymousFunctionId|ParameterId|NamedClassId|AnonymousClassId|ClassConstantId|MethodId|PropertyId
    {
        return $this->targetId;
    }

    // public function target(): FunctionReflection|ClassReflection|ClassConstantReflection|PropertyReflection|MethodReflection|ParameterReflection|AliasReflection|TemplateReflection
    // {
    //     return $this->reflector->reflect($this->targetId);
    // }

    public function snippet(): ?SourceCodeSnippet
    {
        return $this->snippet;
    }

    public function isRepeated(): bool
    {
        return $this->repeated;
    }

    /**
     * This method returns the actual attribute's constructor arguments and thus might trigger autoloading or throw errors.
     */
    public function evaluateArguments(): array
    {
        return $this->arguments->evaluate(new ReflectorEvaluationContext($this->reflector()));
    }

    /**
     * This method returns the actual attribute object and thus might trigger autoloading or throw errors.
     */
    public function evaluate(): object
    {
        /** @psalm-suppress InvalidStringClass */
        return new ($this->className())(...$this->evaluateArguments());
    }

    public function toNativeReflection(): \ReflectionAttribute
    {
        return new AttributeAdapter($this);
    }

    private function reflector(): TyphoonReflector
    {
        \assert($this->reflector !== null);

        return $this->reflector;
    }

    public function __withTargetId(
        NamedFunctionId|AnonymousFunctionId|ParameterId|NamedClassId|AnonymousClassId|ClassConstantId|MethodId|PropertyId $targetId,
    ): self {
        $arguments = get_object_vars($this);
        $arguments['targetId'] = $targetId;

        return new self(...$arguments);
    }

    /**
     * @internal
     * @psalm-internal Typhoon\Reflection
     */
    public function __load(
        TyphoonReflector $reflector,
        NamedFunctionId|AnonymousFunctionId|ParameterId|NamedClassId|AnonymousClassId|ClassConstantId|MethodId|PropertyId $targetId,
    ): self {
        \assert($this->reflector === null);

        $arguments = get_object_vars($this);
        $arguments['targetId'] = $targetId;
        $arguments['reflector'] = $reflector;

        return new self(...$arguments);
    }
}
