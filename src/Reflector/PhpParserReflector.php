<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Reflector;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Yield_;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use Typhoon\Reflection\ClassReflection;
use Typhoon\Reflection\MethodReflection;
use Typhoon\Reflection\NameResolution\NameContext;
use Typhoon\Reflection\ParameterReflection;
use Typhoon\Reflection\PhpDocParser\PhpDoc;
use Typhoon\Reflection\PropertyReflection;
use Typhoon\Reflection\ReflectionContext;
use Typhoon\Reflection\ReflectionException;
use Typhoon\Reflection\TemplateReflection;
use Typhoon\Reflection\TypeReflection;
use Typhoon\Reflection\Variance;
use Typhoon\Type;
use Typhoon\Type\types;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 */
final class PhpParserReflector
{
    public function __construct(
        private readonly ReflectionContext $reflectionContext,
        private readonly NameContext $nameContext,
        private readonly Resource $resource,
    ) {}

    /**
     * @param class-string $name
     */
    public function reflectClass(Stmt\ClassLike $node, string $name): ClassReflection
    {
        $phpDoc = PhpDocParsingVisitor::fromNode($node);

        return new ClassReflection(
            reflectionContext: $this->reflectionContext,
            name: $name,
            changeDetector: $this->resource->changeDetector,
            internal: $this->resource->isInternal(),
            extensionName: $this->resource->extension,
            file: $this->resource->file,
            startLine: $node->getStartLine() > 0 ? $node->getStartLine() : null,
            endLine: $node->getEndLine() > 0 ? $node->getEndLine() : null,
            docComment: $this->reflectDocComment($node),
            templates: $this->reflectTemplates($phpDoc),
            interface: $node instanceof Stmt\Interface_,
            enum: $node instanceof Stmt\Enum_,
            trait: $node instanceof Stmt\Trait_,
            modifiers: $this->reflectClassModifiers($node),
            anonymous: $node->name === null,
            deprecated: $phpDoc->isDeprecated(),
            parentType: $this->reflectParent($node, $phpDoc),
            ownInterfaceTypes: $this->reflectOwnInterfaceTypes($node, $phpDoc),
            ownProperties: $this->reflectOwnProperties(class: $name, classNode: $node),
            ownMethods: $this->reflectOwnMethods(class: $name, classNode: $node),
        );
    }

    /**
     * @param class-string $class
     */
    private function reflectEnumNameProperty(string $class): PropertyReflection
    {
        return new PropertyReflection(
            name: 'name',
            class: $class,
            docComment: null,
            hasDefaultValue: false,
            promoted: false,
            modifiers: PropertyReflection::IS_PUBLIC + PropertyReflection::IS_READONLY,
            deprecated: false,
            type: new TypeReflection(native: types::string, phpDoc: types::nonEmptyString),
            startLine: null,
            endLine: null,
        );
    }

    /**
     * @return int-mask-of<ClassReflection::IS_*>
     */
    private function reflectClassModifiers(Stmt\ClassLike $node): int
    {
        if ($node instanceof Stmt\Enum_) {
            return ClassReflection::IS_FINAL;
        }

        if (!$node instanceof Stmt\Class_) {
            return 0;
        }
    
        $phpDoc = PhpDocParsingVisitor::fromNode($node);

        return ($node->isAbstract() ? ClassReflection::IS_EXPLICIT_ABSTRACT : 0)
            + ($node->isFinal() || $phpDoc->isFinal() ? ClassReflection::IS_FINAL : 0)
            + ($node->isReadonly() || $phpDoc->isReadonly() ? ClassReflection::IS_READONLY : 0);
    }

    private function reflectParent(Stmt\ClassLike $node, PhpDoc $phpDoc): ?Type\NamedObjectType
    {
        if (!$node instanceof Stmt\Class_ || $node->extends === null) {
            return null;
        }

        $parentClass = $this->nameContext->resolveNameAsClass($node->extends->toCodeString());

        foreach ($phpDoc->extendedTypes() as $phpDocExtendedType) {
            /** @var Type\NamedObjectType $extendedType */
            $extendedType = $this->safelyReflectPhpDocType($phpDocExtendedType);

            if ($extendedType->class === $parentClass) {
                return $extendedType;
            }
        }

        return types::object($parentClass);
    }

    /**
     * @return list<Type\NamedObjectType>
     */
    private function reflectOwnInterfaceTypes(Stmt\ClassLike $node, PhpDoc $phpDoc): array
    {
        if ($node instanceof Stmt\Interface_) {
            $interfaceNames = $node->extends;
            $phpDocInterfaceTypes = $phpDoc->extendedTypes();
        } elseif ($node instanceof Stmt\Class_) {
            $interfaceNames = $node->implements;
            $phpDocInterfaceTypes = $phpDoc->implementedTypes();
        } elseif ($node instanceof Stmt\Enum_) {
            $interfaceNames = [
                ...$node->implements,
                new Name\FullyQualified(\UnitEnum::class),
                ...($node->scalarType === null ? [] : [new Name\FullyQualified(\BackedEnum::class)]),
            ];
            $phpDocInterfaceTypes = $phpDoc->implementedTypes();
        } else {
            return [];
        }

        if ($interfaceNames === []) {
            return [];
        }

        $phpDocInterfaceTypesByClass = [];

        foreach ($phpDocInterfaceTypes as $phpDocInterfaceType) {
            /** @var Type\NamedObjectType $implementedType */
            $implementedType = $this->safelyReflectPhpDocType($phpDocInterfaceType);
            $phpDocInterfaceTypesByClass[$implementedType->class] = $implementedType;
        }

        $reflectedInterfaceTypes = [];

        foreach ($interfaceNames as $interfaceName) {
            $interfaceNameAsString = $interfaceName->toCodeString();

            // https://github.com/phpstan/phpstan/issues/8889
            if (\in_array($interfaceNameAsString, ['iterable', 'callable'], true)) {
                continue;
            }

            $interface = $this->nameContext->resolveNameAsClass($interfaceNameAsString);
            $reflectedInterfaceTypes[] = $phpDocInterfaceTypesByClass[$interface] ?? types::object($interface);
        }

        return $reflectedInterfaceTypes;
    }

    /**
     * @param class-string $class
     * @return list<PropertyReflection>
     */
    private function reflectOwnProperties(string $class, Stmt\ClassLike $classNode): array
    {
        $classReadOnly = $classNode instanceof Stmt\Class_ && $classNode->isReadonly();
        $properties = [];

        if ($classNode instanceof Stmt\Enum_) {
            $properties[] = $this->reflectEnumNameProperty($class);

            if ($classNode->scalarType !== null) {
                $properties[] = $this->reflectBackedEnumValueProperty($class, $classNode->scalarType);
            }
        }

        foreach ($classNode->getProperties() as $node) {
            $phpDoc = PhpDocParsingVisitor::fromNode($node);
            $type = $this->reflectType($node->type, $phpDoc->varType());

            foreach ($node->props as $property) {
                $properties[] = new PropertyReflection(
                    name: $property->name->name,
                    class: $class,
                    docComment: $this->reflectDocComment($node),
                    hasDefaultValue: $property->default !== null || $node->type === null,
                    promoted: false,
                    modifiers: $this->reflectPropertyModifiers($node, $classReadOnly),
                    deprecated: $phpDoc->isDeprecated(),
                    type: $type,
                    startLine: $node->getStartLine() > 0 ? $node->getStartLine() : null,
                    endLine: $node->getEndLine() > 0 ? $node->getEndLine() : null,
                );
            }
        }

        $constructorNode = $classNode->getMethod('__construct');

        if ($constructorNode === null) {
            return $properties;
        }

        $phpDoc = PhpDocParsingVisitor::fromNode($constructorNode);

        foreach ($constructorNode->params as $node) {
            $modifiers = $this->reflectPromotedPropertyModifiers($node, $classReadOnly);

            if ($modifiers === 0) {
                continue;
            }

            \assert($node->var instanceof Expr\Variable && \is_string($node->var->name));
            $name = $node->var->name;
            $properties[] = new PropertyReflection(
                name: $name,
                class: $class,
                docComment: $this->reflectDocComment($node),
                hasDefaultValue: $node->default !== null || $node->type === null,
                promoted: true,
                modifiers: $modifiers,
                deprecated: $phpDoc->isDeprecated(),
                type: $this->reflectType($node->type, $phpDoc->paramTypes()[$name] ?? null),
                startLine: $node->getStartLine() > 0 ? $node->getStartLine() : null,
                endLine: $node->getEndLine() > 0 ? $node->getEndLine() : null,
            );
        }

        return $properties;
    }

    /**
     * @return int-mask-of<PropertyReflection::IS_*>
     */
    private function reflectPropertyModifiers(Stmt\Property $node, bool $classReadOnly): int
    {
        return ($node->isStatic() ? PropertyReflection::IS_STATIC : 0)
            + ($node->isPublic() ? PropertyReflection::IS_PUBLIC : 0)
            + ($node->isProtected() ? PropertyReflection::IS_PROTECTED : 0)
            + ($node->isPrivate() ? PropertyReflection::IS_PRIVATE : 0)
            + ($classReadOnly || $node->isReadonly() ? PropertyReflection::IS_READONLY : 0);
    }

    /**
     * @return int-mask-of<PropertyReflection::IS_*>
     */
    private function reflectPromotedPropertyModifiers(Node\Param $node, bool $classReadOnly): int
    {
        return (($node->flags & Stmt\Class_::MODIFIER_PUBLIC) !== 0 ? PropertyReflection::IS_PUBLIC : 0)
            + (($node->flags & Stmt\Class_::MODIFIER_PROTECTED) !== 0 ? PropertyReflection::IS_PROTECTED : 0)
            + (($node->flags & Stmt\Class_::MODIFIER_PRIVATE) !== 0 ? PropertyReflection::IS_PRIVATE : 0)
            + (($classReadOnly || ($node->flags & Stmt\Class_::MODIFIER_READONLY) !== 0) ? PropertyReflection::IS_READONLY : 0);
    }

    /**
     * @return int-mask-of<MethodReflection::IS_*>
     */
    private function reflectMethodModifiers(Stmt\ClassMethod $node, bool $interface): int
    {
        return ($node->isStatic() ? MethodReflection::IS_STATIC : 0)
            + ($node->isPublic() ? MethodReflection::IS_PUBLIC : 0)
            + ($node->isProtected() ? MethodReflection::IS_PROTECTED : 0)
            + ($node->isPrivate() ? MethodReflection::IS_PRIVATE : 0)
            + (($interface || $node->isAbstract()) ? MethodReflection::IS_ABSTRACT : 0)
            + ($node->isFinal() ? MethodReflection::IS_FINAL : 0);
    }

    /**
     * @param class-string $class
     * @return list<MethodReflection>
     */
    private function reflectOwnMethods(string $class, Stmt\ClassLike $classNode): array
    {
        $interface = $classNode instanceof Stmt\Interface_;
        $methods = [];

        foreach ($classNode->getMethods() as $node) {
            $name = $node->name->name;
            $phpDoc = PhpDocParsingVisitor::fromNode($node);

            try {
                $this->nameContext->enterMethod($name, $phpDoc->templateNames());

                $methods[] = new MethodReflection(
                    class: $class,
                    name: $name,
                    templates: $this->reflectTemplates($phpDoc),
                    modifiers: $this->reflectMethodModifiers($node, $interface),
                    docComment: $this->reflectDocComment($node),
                    internal: $this->resource->isInternal(),
                    extensionName: $this->resource->extension,
                    file: $this->resource->file,
                    startLine: $node->getStartLine() > 0 ? $node->getStartLine() : null,
                    endLine: $node->getEndLine() > 0 ? $node->getEndLine() : null,
                    returnsReference: $node->byRef,
                    generator: $this->reflectIsGenerator($node),
                    deprecated: $phpDoc->isDeprecated(),
                    parameters: $this->reflectParameters([$class, $name], $node->params, $phpDoc),
                    returnType: $this->reflectType($node->returnType, $phpDoc->returnType()),
                );
            } finally {
                $this->nameContext->leaveMethod();
            }
        }

        if ($classNode instanceof Stmt\Enum_) {
            $methods[] = $this->reflectEnumCasesMethod($class);

            if ($classNode->scalarType !== null) {
                $methods = [...$methods, ...$this->reflectBackedEnumMethods($class, $classNode->scalarType)];
            }
        }

        return $methods;
    }

    /**
     * @return ?non-empty-string
     */
    private function reflectDocComment(Node $node): ?string
    {
        if ($this->resource->isInternal()) {
            return null;
        }

        return $node->getDocComment()?->getText() ?: null;
    }

    private function reflectIsGenerator(Stmt\ClassMethod $node): bool
    {
        $traverser = new NodeTraverser();
        $visitor = new class () extends NodeVisitorAbstract {
            /**
             * @psalm-readonly-allow-private-mutation
             */
            public bool $hasYield = false;

            public function enterNode(Node $node): ?int
            {
                if ($node instanceof Yield_) {
                    $this->hasYield = true;

                    return NodeTraverser::STOP_TRAVERSAL;
                }

                return null;
            }
        };
        $traverser->addVisitor($visitor);
        $traverser->traverse([$node]);

        return $visitor->hasYield;
    }

    /**
     * @param callable-string|array{class-string, non-empty-string} $function
     * @param array<Node\Param> $nodes
     * @return list<ParameterReflection>
     */
    private function reflectParameters(string|array $function, array $nodes, PhpDoc $methodPhpDoc): array
    {
        $parameters = [];
        $isOptional = false;

        foreach (array_values($nodes) as $position => $node) {
            \assert($node->var instanceof Expr\Variable && \is_string($node->var->name));
            $name = $node->var->name;
            $phpDoc = PhpDocParsingVisitor::fromNode($node);
            $isOptional = $isOptional || $node->default !== null || $node->variadic;
            $parameters[] = new ParameterReflection(
                function: $function,
                position: $position,
                name: $name,
                passedByReference: $node->byRef,
                defaultValueAvailable: $node->default !== null,
                optional: $isOptional,
                variadic: $node->variadic,
                promoted: $this->isParameterPromoted($node),
                deprecated: $phpDoc->isDeprecated(),
                type: $this->reflectType($node->type, $methodPhpDoc->paramTypes()[$name] ?? null),
                startLine: $node->getStartLine() > 0 ? $node->getStartLine() : null,
                endLine: $node->getEndLine() > 0 ? $node->getEndLine() : null,
            );
        }

        return $parameters;
    }

    /**
     * @return list<TemplateReflection>
     */
    private function reflectTemplates(PhpDoc $phpDoc): array
    {
        $templates = [];

        foreach ($phpDoc->templates() as $position => $template) {
            $variance = $template->getAttribute('variance');
            $templates[] = new TemplateReflection(
                position: $position,
                name: $template->name,
                constraint: $this->safelyReflectPhpDocType($template->bound) ?? types::mixed,
                variance: $variance instanceof Variance ? $variance : Variance::INVARIANT,
            );
        }

        return $templates;
    }

    private function reflectType(?Node $native, ?TypeNode $phpDoc): TypeReflection
    {
        return new TypeReflection(
            native: $this->safelyReflectNativeType($native),
            phpDoc: $this->safelyReflectPhpDocType($phpDoc),
        );
    }

    private function safelyReflectNativeType(?Node $node): ?Type\Type
    {
        try {
            return $this->reflectNativeType($node);
        } catch (ReflectionException) {
            // TODO logging

            return null;
        }
    }

    /**
     * @return ($node is null ? null : Type\Type)
     */
    private function reflectNativeType(?Node $node): ?Type\Type
    {
        if ($node === null) {
            return null;
        }

        if ($node instanceof Node\NullableType) {
            return types::nullable($this->reflectNativeType($node->type));
        }

        if ($node instanceof Node\UnionType) {
            return types::union(...array_map(
                fn (Node $child): Type\Type => $this->reflectNativeType($child),
                $node->types,
            ));
        }

        if ($node instanceof Node\IntersectionType) {
            return types::intersection(...array_map(
                fn (Node $child): Type\Type => $this->reflectNativeType($child),
                $node->types,
            ));
        }

        if ($node instanceof Node\Identifier) {
            return match ($node->name) {
                'never' => types::never,
                'void' => types::void,
                'null' => types::null,
                'true' => types::true,
                'false' => types::false,
                'bool' => types::bool,
                'int' => types::int,
                'float' => types::float,
                'string' => types::string,
                'array' => types::array(),
                'object' => types::object,
                'callable' => types::callable(),
                'iterable' => types::iterable(),
                'resource' => types::resource,
                'mixed' => types::mixed,
                default => throw new ReflectionException(sprintf(
                    '%s with name "%s" is not supported.',
                    $node->name,
                    $node::class,
                )),
            };
        }

        if ($node instanceof Name) {
            $resolvedName = $this->nameContext->resolveNameAsClass($node->toCodeString());

            if ($node->toString() === 'static') {
                return types::static($resolvedName);
            }

            return types::object($resolvedName);
        }

        throw new ReflectionException(sprintf('%s is not supported.', $node::class));
    }

    private function safelyReflectPhpDocType(?TypeNode $node): ?Type\Type
    {
        if ($node === null) {
            return null;
        }

        try {
            return PhpDocTypeReflector::reflect(
                nameContext: $this->nameContext,
                classExists: $this->reflectionContext->classExists(...),
                typeNode: $node,
            );
        } catch (\Throwable) {
            // TODO logging

            return null;
        }
    }

    private function isParameterPromoted(Node\Param $node): bool
    {
        return ($node->flags & Stmt\Class_::MODIFIER_PUBLIC) !== 0
            || ($node->flags & Stmt\Class_::MODIFIER_PROTECTED) !== 0
            || ($node->flags & Stmt\Class_::MODIFIER_PRIVATE) !== 0;
    }

    /**
     * @param class-string $class
     */
    private function reflectBackedEnumValueProperty(string $class, Node\Identifier $scalarType): PropertyReflection
    {
        return new PropertyReflection(
            name: 'value',
            class: $class,
            docComment: null,
            hasDefaultValue: false,
            promoted: false,
            modifiers: PropertyReflection::IS_PUBLIC + PropertyReflection::IS_READONLY,
            deprecated: false,
            type: $this->reflectType($scalarType, null),
            startLine: null,
            endLine: null,
        );
    }

    /**
     * @param class-string $class
     */
    private function reflectEnumCasesMethod(string $class): MethodReflection
    {
        return new MethodReflection(
            class: $class,
            name: 'cases',
            templates: [],
            modifiers: MethodReflection::IS_STATIC + MethodReflection::IS_PUBLIC,
            docComment: null,
            internal: true,
            extensionName: null,
            file: null,
            startLine: null,
            endLine: null,
            returnsReference: false,
            generator: false,
            deprecated: false,
            parameters: [],
            returnType: new TypeReflection(types::array(), types::list(types::object($class))),
        );
    }

    /**
     * @param class-string $class
     * @return list<MethodReflection>
     */
    private function reflectBackedEnumMethods(string $class, Node\Identifier $scalarType): array
    {
        $valueType = $this->reflectType($scalarType, null);

        return [
            new MethodReflection(
                class: $class,
                name: 'from',
                templates: [],
                modifiers: MethodReflection::IS_STATIC + MethodReflection::IS_PUBLIC,
                docComment: null,
                internal: true,
                extensionName: null,
                file: null,
                startLine: null,
                endLine: null,
                returnsReference: false,
                generator: false,
                deprecated: false,
                parameters: [
                    new ParameterReflection(
                        function: [$class, 'from'],
                        position: 0,
                        name: 'value',
                        passedByReference: false,
                        defaultValueAvailable: false,
                        optional: false,
                        variadic: false,
                        promoted: false,
                        deprecated: false,
                        type: $valueType,
                        startLine: null,
                        endLine: null,
                    ),
                ],
                returnType: new TypeReflection(types::array(), types::list(types::object($class))),
            ),
            new MethodReflection(
                class: $class,
                name: 'tryFrom',
                templates: [],
                modifiers: MethodReflection::IS_STATIC + MethodReflection::IS_PUBLIC,
                docComment: null,
                internal: true,
                extensionName: null,
                file: null,
                startLine: null,
                endLine: null,
                returnsReference: false,
                generator: false,
                deprecated: false,
                parameters: [
                    new ParameterReflection(
                        function: [$class, 'tryFrom'],
                        position: 0,
                        name: 'value',
                        passedByReference: false,
                        defaultValueAvailable: false,
                        optional: false,
                        variadic: false,
                        promoted: false,
                        deprecated: false,
                        type: $valueType,
                        startLine: null,
                        endLine: null,
                    ),
                ],
                returnType: new TypeReflection(
                    types::nullable(types::array()),
                    types::nullable(types::list(types::object($class))),
                ),
            ),
        ];
    }
}
