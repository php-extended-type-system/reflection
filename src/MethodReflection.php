<?php

declare(strict_types=1);

namespace Typhoon\Reflection;

use Typhoon\DeclarationId\AnonymousClassId;
use Typhoon\DeclarationId\Id;
use Typhoon\DeclarationId\MethodId;
use Typhoon\DeclarationId\NamedClassId;
use Typhoon\Reflection\Declaration\Context;
use Typhoon\Reflection\Declaration\MethodDeclaration;
use Typhoon\Reflection\Declaration\Visibility;
use Typhoon\Reflection\Internal\NativeAdapter\MethodAdapter;
use Typhoon\Reflection\Internal\Reflection\ModifierReflection;
use Typhoon\Reflection\Internal\Reflection\TypeReflection;
use Typhoon\Reflection\Metadata\MethodMetadata;
use Typhoon\Type\Type;
use Typhoon\Type\types;

/**
 * @api
 * @psalm-import-type Attributes from TyphoonReflector
 * @psalm-import-type Templates from TyphoonReflector
 * @psalm-import-type Parameters from TyphoonReflector
 */
final class MethodReflection
{
    /**
     * @var non-empty-string
     */
    public readonly string $name;

    /**
     * @param Templates $templates
     * @param Attributes $attributes
     * @param Parameters $parameters
     */
    private function __construct(
        public readonly MethodId $id,
        public readonly MethodId $declarationId,
        private readonly SourceCode|Extension $source,
        private readonly Collection $templates,
        private readonly Collection $parameters,
        private readonly Collection $attributes,
        private readonly ?SourceCodeSnippet $phpDoc,
        private readonly ?SourceCodeSnippet $snippet,
        private readonly bool $abstract,
        private readonly bool $static,
        private readonly bool $generator,
        private readonly bool $returnsReference,
        public readonly TypeReflection $returnType,
        private readonly ?Visibility $visibility,
        private readonly ?Type $throwsType,
        private readonly ModifierReflection $final,
        private readonly ?Deprecation $deprecation,
        private readonly bool $native,
        private readonly ?TyphoonReflector $reflector = null,
    ) {
        $this->name = $id->name;
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

    public function class(): ClassReflection
    {
        return $this->reflector()->reflectClass($this->id->class);
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

    public function snippet(): ?SourceCodeSnippet
    {
        return $this->snippet;
    }

    public function isNative(): bool
    {
        return $this->native;
    }

    public function isAnnotated(): bool
    {
        return !$this->native;
    }

    public function isAbstract(): bool
    {
        return $this->abstract;
    }

    public function isFinal(ModifierKind $kind = ModifierKind::Resolved): bool
    {
        return $this->final->byKind($kind);
    }

    public function isGenerator(): bool
    {
        return $this->generator;
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

    public function isStatic(): bool
    {
        return $this->static;
    }

    public function isVariadic(): bool
    {
        return $this->parameters()->last()?->isVariadic() ?? false;
    }

    public function returnsReference(): bool
    {
        return $this->returnsReference;
    }

    /**
     * @return ($kind is TypeKind::Resolved ? Type : ?Type)
     */
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

    public function toNativeReflection(): \ReflectionMethod
    {
        return new MethodAdapter($this, $this->reflector());
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
        MethodDeclaration $declaration,
        MethodMetadata $metadata = new MethodMetadata(),
        bool $interface = false,
    ): self {
        return new self(
            id: $declaration->context->id,
            declarationId: $declaration->context->id,
            source: $declaration->context->source,
            templates: TemplateReflection::from($declaration->id, $metadata->templates),
            parameters: ParameterReflection::from($declaration->parameters, $metadata->parameters),
            attributes: AttributeReflection::from($declaration->id, $declaration->attributes),
            phpDoc: $declaration->phpDoc,
            snippet: $declaration->snippet,
            abstract: $interface || $declaration->abstract,
            static: $declaration->static,
            generator: $declaration->generator,
            returnsReference: $declaration->returnsReference,
            returnType: new TypeReflection(
                native: $declaration->returnType,
                annotated: $metadata->returnType,
                tentative: $declaration->tentativeReturnType,
            ),
            visibility: $declaration->visibility,
            throwsType: $metadata->throwsTypes === [] ? null : types::union(...$metadata->throwsTypes),
            final: new ModifierReflection($declaration->final, $metadata->final),
            deprecation: $metadata->deprecation ?? ($declaration->internallyDeprecated ? new Deprecation() : null),
            native: true,
        );
    }

    /**
     * @internal
     * @psalm-internal Typhoon\Reflection
     */
    public function __inherit(NamedClassId|AnonymousClassId $classId, TypeReflection $returnType): self
    {
        $id = Id::method($classId, $this->name);

        return new self(
            id: $id,
            declarationId: $this->declarationId,
            source: $this->source,
            templates: $this->templates,
            parameters: $this->parameters->map(static fn(ParameterReflection $parameter): ParameterReflection => $parameter->__inherit($id, $parameter->type)),
            attributes: $this->attributes->map(static fn(AttributeReflection $attribute): AttributeReflection => $attribute->__withTargetId($id)),
            phpDoc: $this->phpDoc,
            snippet: $this->snippet,
            abstract: $this->abstract,
            static: $this->static,
            generator: $this->generator,
            returnsReference: $this->returnsReference,
            returnType: $returnType,
            visibility: $this->visibility,
            throwsType: $this->throwsType,
            final: $this->final,
            deprecation: $this->deprecation,
            native: $this->native,
        );
    }

    /**
     * @internal
     * @psalm-internal Typhoon\Reflection
     * @param Context<NamedClassId|AnonymousClassId> $newClassContext
     * @param ?non-empty-string $newName
     */
    public function __use(
        Context $newClassContext,
        TypeReflection $returnType,
        ?string $newName = null,
        ?Visibility $newVisibility = null,
    ): self {
        $newMethodContext = $newClassContext->enterMethodDeclaration($newName ?? $this->name, $this->templates()->keys());
        $id = $newMethodContext->id;

        return new self(
            id: $id,
            declarationId: $this->declarationId,
            source: $this->source,
            templates: $this->templates,
            parameters: $this->parameters->map(static fn(ParameterReflection $parameter): ParameterReflection => $parameter->__use($newMethodContext, $parameter->type)),
            attributes: $this->attributes->map(static fn(AttributeReflection $attribute): AttributeReflection => $attribute->__withTargetId($id)),
            phpDoc: $this->phpDoc,
            snippet: $this->snippet,
            abstract: $this->abstract,
            static: $this->static,
            generator: $this->generator,
            returnsReference: $this->returnsReference,
            returnType: $returnType,
            visibility: $newVisibility ?? $this->visibility,
            throwsType: $this->throwsType,
            final: $this->final,
            deprecation: $this->deprecation,
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
            id: $id = Id::method($classId, $this->name),
            declarationId: $this->declarationId,
            source: $this->source,
            templates: $this->templates,
            parameters: $this->parameters->map(static fn(ParameterReflection $parameter): ParameterReflection => $parameter->__load($reflector, $id)),
            attributes: $this->attributes->map(static fn(AttributeReflection $attribute): AttributeReflection => $attribute->__load($reflector, $id)),
            phpDoc: $this->phpDoc,
            snippet: $this->snippet,
            abstract: $this->abstract,
            static: $this->static,
            generator: $this->generator,
            returnsReference: $this->returnsReference,
            returnType: $this->returnType,
            visibility: $this->visibility,
            throwsType: $this->throwsType,
            final: $this->final,
            deprecation: $this->deprecation,
            native: $this->native,
            reflector: $reflector,
        );
    }
}
