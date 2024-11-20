<?php

declare(strict_types=1);

namespace Typhoon\Reflection;

use Typhoon\DeclarationId\AnonymousClassId;
use Typhoon\DeclarationId\Id;
use Typhoon\DeclarationId\NamedClassId;
use Typhoon\Reflection\Declaration\ClassDeclaration;
use Typhoon\Reflection\Declaration\ClassKind;
use Typhoon\Reflection\Declaration\Context;
use Typhoon\Reflection\Declaration\MethodDeclaration;
use Typhoon\Reflection\Declaration\PropertyDeclaration;
use Typhoon\Reflection\Internal\ClassReflector;
use Typhoon\Reflection\Internal\NativeAdapter\ClassAdapter;
use Typhoon\Reflection\Internal\NativeAdapter\EnumAdapter;
use Typhoon\Reflection\Internal\Reflection\ModifierReflection;
use Typhoon\Reflection\Internal\Type\TypeResolvers;
use Typhoon\Reflection\Metadata\ClassConstantMetadata;
use Typhoon\Reflection\Metadata\ClassMetadata;
use Typhoon\Reflection\Metadata\MethodMetadata;
use Typhoon\Reflection\Metadata\ParameterMetadata;
use Typhoon\Reflection\Metadata\PropertyMetadata;
use Typhoon\Type\Type;
use Typhoon\Type\Visitor\TemplateTypeResolver;

/**
 * @api
 * @template-covariant TObject of object
 * @template-covariant TId of NamedClassId<class-string<TObject>>|AnonymousClassId<?class-string<TObject>>
 * @psalm-import-type ClassConstants from TyphoonReflector
 * @psalm-import-type Properties from TyphoonReflector
 * @psalm-import-type Templates from TyphoonReflector
 * @psalm-import-type Attributes from TyphoonReflector
 * @psalm-import-type Methods from TyphoonReflector
 */
final class ClassReflection
{
    /**
     * @var ?class-string<TObject>
     */
    public readonly ?string $name;

    /**
     * @param TId $id
     * @param Properties $properties
     * @param Templates $templates
     * @param Attributes $attributes
     * @param ClassConstants $constants
     * @param Methods $methods
     * @param array<class-string, list<Type>> $parents
     * @param array<class-string, list<Type>> $interfaces
     */
    private function __construct(
        public readonly AnonymousClassId|NamedClassId $id,
        private readonly ClassKind $kind,
        private readonly Collection $templates,
        private readonly Collection $attributes,
        private readonly Collection $constants,
        private readonly Collection $properties,
        private readonly Collection $methods,
        private readonly ?Type $backingType,
        private readonly ?SourceCodeSnippet $snippet,
        private readonly ?SourceCodeSnippet $phpDoc,
        private readonly bool $abstract,
        private readonly ModifierReflection $readonly,
        private readonly ModifierReflection $final,
        private readonly string $namespace,
        private readonly SourceCode|Extension $source,
        private readonly ?Deprecation $deprecation,
        private readonly bool $internallyNonCloneable,
        private readonly array $parents,
        private readonly array $interfaces,
        private readonly ?TyphoonReflector $reflector = null,
    ) {
        $this->name = $id->name;
    }

    // /**
    //  * @return AliasReflection[]
    //  * @psalm-return Aliases
    //  * @phpstan-return Aliases
    //  */
    // public function aliases(): Collection
    // {
    //     return $this->aliases ??= (new Collection($this->data[Data::Aliases]))
    //         ->map(fn(TypedMap $data, string $name): AliasReflection => new AliasReflection(Id::alias($this->id, $name), $data));
    // }

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
     * @return ClassConstantReflection[]
     * @psalm-return ClassConstants
     * @phpstan-return ClassConstants
     */
    public function enumCases(): Collection
    {
        return $this->constants()->filter(static fn(ClassConstantReflection $reflection): bool => $reflection->isEnumCase());
    }

    /**
     * @return ClassConstantReflection[]
     * @psalm-return ClassConstants
     * @phpstan-return ClassConstants
     */
    public function constants(): Collection
    {
        return $this->constants;
    }

    /**
     * @return PropertyReflection[]
     * @psalm-return Properties
     * @phpstan-return Properties
     */
    public function properties(): Collection
    {
        return $this->properties;
    }

    /**
     * @return MethodReflection[]
     * @psalm-return Methods
     * @phpstan-return Methods
     */
    public function methods(): Collection
    {
        return $this->methods;
    }

    public function phpDoc(): ?SourceCodeSnippet
    {
        return $this->phpDoc;
    }

    /**
     * @param non-empty-string|NamedClassId|AnonymousClassId $class
     */
    public function isInstanceOf(string|NamedClassId|AnonymousClassId $class): bool
    {
        if (\is_string($class)) {
            $class = Id::class($class);
        }

        if ($this->id->equals($class)) {
            return true;
        }

        if ($class instanceof AnonymousClassId) {
            return false;
        }

        return \array_key_exists($class->name, $this->parents)
            || \array_key_exists($class->name, $this->interfaces);
    }

    public function isClass(): bool
    {
        return $this->kind === ClassKind::Class_;
    }

    /**
     * This method is different from {@see \ReflectionClass::isAbstract()}. It returns true only for explicitly
     * abstract classes:
     *     abstract class A {} -> true
     *     class C {} -> false
     *     interface I { public function m() {} } -> false
     *     trait T { abstract public function m() {} } -> false.
     */
    public function isAbstract(): bool
    {
        return $this->abstract;
    }

    public function isAnonymous(): bool
    {
        return $this->id instanceof AnonymousClassId;
    }

    public function isInterface(): bool
    {
        return $this->kind === ClassKind::Interface;
    }

    public function isTrait(): bool
    {
        return $this->kind === ClassKind::Trait;
    }

    public function isEnum(): bool
    {
        return $this->kind === ClassKind::Enum;
    }

    public function isBackedEnum(): bool
    {
        return $this->backingType !== null;
    }

    public function enumBackingType(): ?Type
    {
        return $this->backingType;
    }

    public function isFinal(ModifierKind $kind = ModifierKind::Resolved): bool
    {
        return $this->final->byKind($kind);
    }

    public function isReadonly(ModifierKind $kind = ModifierKind::Resolved): bool
    {
        return $this->readonly->byKind($kind);
    }

    public function isCloneable(): bool
    {
        if ($this->kind !== ClassKind::Class_ || $this->isAbstract()) {
            return false;
        }

        if ($this->internallyNonCloneable) {
            return false;
        }

        return ($this->methods()['__clone'] ?? null)?->isPublic() ?? true;
    }

    /**
     * Unlike {@see \ReflectionClass::getNamespaceName()}, this method returns the actual namespace
     * an anonymous class is defined in.
     */
    public function namespace(): string
    {
        return $this->namespace;
    }

    public function parent(): ?self
    {
        $parentName = $this->parentName();

        if ($parentName === null) {
            return null;
        }

        return $this->reflector()->reflectClass($parentName);
    }

    /**
     * @return ?class-string
     */
    public function parentName(): ?string
    {
        return array_key_first($this->parents);
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

    /**
     * @param list<Type> $typeArguments
     */
    public function createTemplateResolver(array $typeArguments): TemplateTypeResolver
    {
        return new TemplateTypeResolver(
            $this
                ->templates()
                ->map(static fn(TemplateReflection $template): array => [
                    $template->id,
                    $typeArguments[$template->index()] ?? $template->constraint(),
                ]),
        );
    }

    public function isDeprecated(): bool
    {
        return $this->deprecation !== null;
    }

    public function deprecation(): ?Deprecation
    {
        return $this->deprecation;
    }

    /**
     * @return \ReflectionClass<TObject>
     * @psalm-suppress InvalidReturnType, InvalidReturnStatement, ArgumentTypeCoercion, InvalidArgument
     */
    public function toNativeReflection(): \ReflectionClass
    {
        $adapter = new ClassAdapter($this, $this->reflector(), array_keys($this->interfaces));

        if ($this->isEnum()) {
            return new EnumAdapter($adapter, $this);
        }

        return $adapter;
    }

    private function reflector(): TyphoonReflector
    {
        return $this->reflector ?? throw new \LogicException('No reflector');
    }

    /**
     * @internal
     * @psalm-internal Typhoon\Reflection
     * @psalm-suppress UnusedVariable
     */
    public static function __declare(
        ClassReflector $classReflector,
        ClassDeclaration $declaration,
        ClassMetadata $metadata,
    ): self {
        $declaredAncestors = [
            ...($declaration->parent === null ? [] : [$declaration->parent]),
            ...$declaration->interfaces,
        ];
        $readonly = new ModifierReflection($declaration->readonly, $metadata->readonly);
        $parents = [];
        $interfaces = [];
        $constants = [];
        $properties = [];
        $methods = [];
        $inheritedConstantTypes = [];
        $inheritedPropertyTypes = [];
        $inheritedMethodTypes = [];

        foreach ($declaration->constants as $constant) {
            $constants[$constant->name] = ClassConstantReflection::__declare(
                declaration: $constant,
                metadata: $metadata->constants[$constant->name] ?? new ClassConstantMetadata(),
            );
        }

        foreach ($declaration->properties as $property) {
            $properties[$property->name] = PropertyReflection::__declare(
                declaration: $property,
                metadata: $metadata->properties[$property->name] ?? new PropertyMetadata(),
                classReadonly: $readonly,
            );
        }

        foreach ($declaration->methods as $method) {
            $metadata = $metadata->methods[$method->name] ?? new MethodMetadata();

            $methods[$method->name] = MethodReflection::__declare(
                declaration: $method,
                metadata: $metadata,
                interface: $declaration->kind === ClassKind::Interface,
            );

            if ($method->name !== '__construct') {
                continue;
            }

            foreach ($method->parameters as $parameter) {
                if (!$parameter->isPromoted()) {
                    continue;
                }

                $properties[$parameter->name] = PropertyReflection::__declarePromoted(
                    declaration: $parameter,
                    metadata: $metadata->parameters[$parameter->name] ?? new ParameterMetadata(),
                    classReadonly: $readonly,
                );
            }
        }

        foreach ($declaration->traits as $traitName) {
            $trait = $classReflector->reflectNamed(Id::namedClass($traitName));

            // $changeDetectors[] = $trait->changeDetector();

            // $resolvedTypeArguments = $trait
            //     ->templates()
            //     ->map(static fn(TemplateReflection $template): Type => $typeArguments[$template->index()] ?? $template->constraint());
            // $typeResolver = $createTypeResolvers($traitId, $resolvedTypeArguments);
            $typeResolver = new TypeResolvers();

            foreach ($trait->constants() as $constant) {
                $constants[$constant->name] ??= $constant->__use($declaration->context, $constant->type);
                $inheritedConstantTypes[$constant->name][] = [$constant->type, $typeResolver];
            }

            foreach ($trait->properties() as $property) {
                $properties[$property->name] ??= $property->__use($declaration->context, $property->type);
                $inheritedPropertyTypes[$property->name][] = [$property->type, $typeResolver];
            }

            foreach ($trait->methods() as $method) {
                $precedence = $declaration->traitMethodPrecedence[$method->name] ?? null;

                if ($precedence !== null && $precedence !== $trait->name) {
                    continue;
                }

                foreach ($declaration->traitMethodAliases as $alias) {
                    if ($alias->trait !== $trait->name || $alias->method !== $method->name) {
                        continue;
                    }

                    $name = $alias->newName ?? $method->name;

                    $methods[$name] ??= $method->__use(
                        newClassContext: $declaration->context,
                        returnType: $method->returnType,
                        newName: $name,
                        newVisibility: $alias->newVisibility,
                    );
                    $inheritedMethodTypes[$name][] = [$method->returnType, $typeResolver];
                }

                $methods[$method->name] ??= $method->__use($declaration->context, $method->returnType);
                $inheritedMethodTypes[$method->name][] = [$method->returnType, $typeResolver];
            }
        }

        if ($declaration->kind !== ClassKind::Trait
            && $declaration->id->name !== \Stringable::class
            && isset($methods['__toString'])
        ) {
            $declaredAncestors[] = \Stringable::class;
        }

        if ($declaration->kind === ClassKind::Enum) {
            \assert($declaration->name !== null);
            $enumContext = Context::start(Extension::core())->enterEnumDeclaration($declaration->name);
            $declaredAncestors[] = \UnitEnum::class;
            $properties['name'] = PropertyReflection::__declare(
                declaration: PropertyDeclaration::enumNameProperty($enumContext),
            );
            $methods['cases'] = MethodReflection::__declare(
                declaration: MethodDeclaration::enumCases($enumContext),
            );

            if ($declaration->backingType !== null) {
                $declaredAncestors[] = \BackedEnum::class;
                $properties['value'] = PropertyReflection::__declare(
                    declaration: PropertyDeclaration::enumValueProperty($enumContext, $declaration->backingType),
                );
                $methods['from'] = MethodReflection::__declare(
                    declaration: MethodDeclaration::enumFrom($enumContext),
                );
                $methods['tryFrom'] = MethodReflection::__declare(
                    declaration: MethodDeclaration::enumTryFrom($enumContext),
                );
            }
        }

        foreach (array_unique($declaredAncestors) as $ancestorName) {
            /** @var list<Type> */
            $typeArguments = []; // todo
            $ancestor = $classReflector->reflectNamed(Id::namedClass($ancestorName));
            \assert($ancestor->name !== null);

            // $changeDetectors[] = $class->changeDetector();

            $resolvedTypeArguments = $ancestor
                ->templates()
                ->map(static fn(TemplateReflection $template): Type => $typeArguments[$template->index()] ?? $template->constraint());
            $typeResolver = new TypeResolvers(); // $createTypeResolvers($classId, $resolvedTypeArguments);

            $interfaces = [
                ...$interfaces,
                ...array_map(
                    static fn(array $typeArguments): array => array_map(
                        static fn(Type $type): Type => $type->accept($typeResolver),
                        $typeArguments,
                    ),
                    $ancestor->interfaces,
                ),
            ];

            if ($ancestor->isInterface()) {
                $interfaces[$ancestor->name] ??= $resolvedTypeArguments->toList();
            } else {
                $parents = [
                    $ancestor->name => $resolvedTypeArguments->toList(),
                    ...array_map(
                        static fn(array $typeArguments): array => array_map(
                            static fn(Type $type): Type => $type->accept($typeResolver),
                            $typeArguments,
                        ),
                        $ancestor->parents,
                    ),
                ];
            }

            foreach ($ancestor->constants() as $constant) {
                if ($constant->isPrivate()) {
                    continue;
                }

                $constants[$constant->name] ??= $constant->__inherit($declaration->id, $constant->type);
                $inheritedConstantTypes[$constant->name][] = [$constant->type, $typeResolver];
            }

            foreach ($ancestor->properties() as $property) {
                if ($property->isPrivate()) {
                    continue;
                }

                $properties[$property->name] ??= $property->__inherit($declaration->id, $property->type);
                $inheritedPropertyTypes[$property->name][] = [$property->type, $typeResolver];
            }

            foreach ($ancestor->methods() as $method) {
                if ($method->isPrivate()) {
                    continue;
                }

                $methods[$method->name] ??= $method->__inherit($declaration->id, $method->returnType);
                $inheritedMethodTypes[$method->name][] = [$method->returnType, $typeResolver];
            }
        }

        return new self(
            id: $declaration->id,
            kind: $declaration->kind,
            templates: TemplateReflection::from($declaration->id, $metadata->templates),
            attributes: AttributeReflection::from($declaration->id, $declaration->attributes),
            constants: new Collection($constants), // todo types
            properties: new Collection($properties), // todo types
            methods: new Collection($methods), // todo types
            backingType: $declaration->backingType,
            snippet: $declaration->snippet,
            phpDoc: $declaration->phpDoc,
            abstract: $declaration->abstract,
            readonly: $readonly,
            final: new ModifierReflection($declaration->kind === ClassKind::Enum || $declaration->final, $metadata->final),
            namespace: $declaration->context->namespace(),
            source: $declaration->context->source,
            deprecation: $metadata->deprecation,
            internallyNonCloneable: $declaration->internallyNonCloneable,
            parents: $parents,
            interfaces: $interfaces,
        );
    }

    /**
     * @internal
     * @psalm-internal Typhoon\Reflection
     * @psalm-suppress InvalidTemplateParam
     * @param TId $id
     * @return self<TObject, TId>
     */
    public function __load(TyphoonReflector $reflector, NamedClassId|AnonymousClassId $id): self
    {
        \assert($this->reflector === null);

        return new self(
            id: $id,
            kind: $this->kind,
            templates: $this->templates,
            attributes: $this->attributes->map(static fn(AttributeReflection $attribute): AttributeReflection => $attribute->__load($reflector, $id)),
            constants: $this->constants->map(static fn(ClassConstantReflection $constant): ClassConstantReflection => $constant->__load($reflector, $id)),
            properties: $this->properties->map(static fn(PropertyReflection $property): PropertyReflection => $property->__load($reflector, $id)),
            methods: $this->methods->map(static fn(MethodReflection $method): MethodReflection => $method->__load($reflector, $id)),
            backingType: $this->backingType,
            snippet: $this->snippet,
            phpDoc: $this->phpDoc,
            abstract: $this->abstract,
            readonly: $this->readonly,
            final: $this->final,
            namespace: $this->namespace,
            source: $this->source,
            deprecation: $this->deprecation,
            internallyNonCloneable: $this->internallyNonCloneable,
            parents: $this->parents,
            interfaces: $this->interfaces,
            reflector: $reflector,
        );
    }
}
