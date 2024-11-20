<?php

declare(strict_types=1);

namespace Typhoon\Reflection;

use Typhoon\ChangeDetector\ChangeDetector;
use Typhoon\DeclarationId\AnonymousFunctionId;
use Typhoon\DeclarationId\NamedFunctionId;
use Typhoon\Reflection\Declaration\FunctionDeclaration;
use Typhoon\Reflection\Internal\NativeAdapter\FunctionAdapter;
use Typhoon\Reflection\Internal\Reflection\TypeReflection;
use Typhoon\Reflection\Metadata\FunctionMetadata;
use Typhoon\Type\Type;
use Typhoon\Type\types;

/**
 * @api
 * @psalm-import-type Attributes from TyphoonReflector
 * @psalm-import-type Templates from TyphoonReflector
 * @psalm-import-type Parameters from TyphoonReflector
 */
final class FunctionReflection
{
    public static function from(FunctionDeclaration $function, FunctionMetadata $functionMetadata): self
    {
        return new self(
            id: $function->id,
            templates: TemplateReflection::from($function->id, $functionMetadata->templates),
            attributes: AttributeReflection::from($function->id, $function->attributes),
            parameters: ParameterReflection::from($function->parameters, $functionMetadata->parameters),
            snippet: $function->snippet,
            phpDoc: $function->phpDoc,
            source: $function->context->source,
            namespace: $function->context->namespace(),
            changeDetector: $function->context->source->changeDetector,
            generator: $function->generator,
            returnsReference: $function->returnsReference,
            deprecation: $functionMetadata->deprecation ?? ($function->internallyDeprecated ? new Deprecation() : null),
            returnType: new TypeReflection($function->returnType, $functionMetadata->returnType),
            throwsType: $functionMetadata->throwsTypes === [] ? null : types::union(...$functionMetadata->throwsTypes),
        );
    }

    /**
     * @var ?non-empty-string
     */
    public readonly ?string $name;

    /**
     * @param Templates $templates
     * @param Attributes $attributes
     * @param Parameters $parameters
     */
    private function __construct(
        public readonly NamedFunctionId|AnonymousFunctionId $id,
        private readonly Collection $templates,
        private Collection $attributes,
        private Collection $parameters,
        private readonly ?SourceCodeSnippet $snippet,
        private readonly ?SourceCodeSnippet $phpDoc,
        private readonly Extension|SourceCode $source,
        private readonly string $namespace,
        private readonly ChangeDetector $changeDetector,
        private readonly bool $generator,
        private readonly bool $returnsReference,
        private readonly ?Deprecation $deprecation,
        private readonly TypeReflection $returnType,
        private readonly ?Type $throwsType,
    ) {
        $this->name = $id instanceof NamedFunctionId ? $id->name : null;
    }

    /**
     * @return TemplateReflection[]
     * @psalm-return Templates
     * @phpstan-return Templates
     */
    public function templates(): Collection
    {
        return $this->templates;
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

    /**
     * @return ParameterReflection[]
     * @psalm-return Parameters
     * @phpstan-return Parameters
     */
    public function parameters(): Collection
    {
        return $this->parameters;
    }

    public function phpDoc(): ?SourceCodeSnippet
    {
        return $this->phpDoc;
    }

    public function isInternallyDefined(): bool
    {
        return $this->source instanceof Extension;
    }

    /**
     * @return ?non-empty-string
     */
    public function extension(): ?string
    {
        return $this->source instanceof Extension ? $this->source->name : null;
    }

    public function file(): ?File
    {
        return $this->source instanceof SourceCode ? $this->source->file : null;
    }

    public function namespace(): string
    {
        return $this->namespace;
    }

    public function snippet(): ?SourceCodeSnippet
    {
        return $this->snippet;
    }

    public function changeDetector(): ChangeDetector
    {
        return $this->changeDetector;
    }

    public function isGenerator(): bool
    {
        return $this->generator;
    }

    public function isAnonymous(): bool
    {
        return $this->id instanceof AnonymousFunctionId;
    }

    public function isStatic(): bool
    {
        return false;
    }

    public function isVariadic(): bool
    {
        return $this->parameters()->last()?->isVariadic() ?? false;
    }

    public function returnsReference(): bool
    {
        return $this->returnsReference;
    }

    public function returnType(TypeKind $kind = TypeKind::Resolved): ?Type
    {
        return $this->returnType->byKind($kind);
    }

    public function throwsType(): ?Type
    {
        return $this->throwsType;
    }

    public function isDeprecated(): bool
    {
        return $this->deprecation !== null;
    }

    public function deprecation(): ?Deprecation
    {
        return $this->deprecation;
    }

    public function toNativeReflection(): \ReflectionFunction
    {
        return new FunctionAdapter($this);
    }

    /**
     * @internal
     * @psalm-internal Typhoon\Reflection
     */
    public function __load(TyphoonReflector $reflector): self
    {
        $arguments = get_object_vars($this);
        unset($arguments['name']);
        $arguments['attributes'] = $this->attributes->map(
            fn(AttributeReflection $attribute): AttributeReflection => $attribute->__load($reflector, $this->id),
        );
        $arguments['parameters'] = $this->parameters->map(
            fn(ParameterReflection $parameter): ParameterReflection => $parameter->__load($reflector, $this->id),
        );

        return new self(...$arguments);
    }
}
