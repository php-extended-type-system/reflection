<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Internal;

use PhpParser\ErrorHandler\Throwing;
use PhpParser\NameContext;
use Typhoon\DeclarationId\AnonymousFunctionId;
use Typhoon\DeclarationId\Id;
use Typhoon\DeclarationId\MethodId;
use Typhoon\DeclarationId\NamedClassId;
use Typhoon\DeclarationId\NamedFunctionId;
use Typhoon\Reflection\Declaration\AttributeDeclaration;
use Typhoon\Reflection\Declaration\ClassConstantDeclaration;
use Typhoon\Reflection\Declaration\ClassDeclaration;
use Typhoon\Reflection\Declaration\ClassKind;
use Typhoon\Reflection\Declaration\ConstantDeclaration;
use Typhoon\Reflection\Declaration\ConstantExpression\ClassConstantFetch;
use Typhoon\Reflection\Declaration\ConstantExpression\ConstantExpression;
use Typhoon\Reflection\Declaration\ConstantExpression\ConstantFetch;
use Typhoon\Reflection\Declaration\ConstantExpression\Value;
use Typhoon\Reflection\Declaration\Context;
use Typhoon\Reflection\Declaration\EnumCaseDeclaration;
use Typhoon\Reflection\Declaration\FunctionDeclaration;
use Typhoon\Reflection\Declaration\MethodDeclaration;
use Typhoon\Reflection\Declaration\ParameterDeclaration;
use Typhoon\Reflection\Declaration\PassedBy;
use Typhoon\Reflection\Declaration\PropertyDeclaration;
use Typhoon\Reflection\Declaration\Visibility;
use Typhoon\Reflection\Extension;
use Typhoon\Reflection\Internal\PhpParser\NameParser;
use Typhoon\Reflection\SourceCode;
use Typhoon\Type\Type;
use Typhoon\Type\types;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 */
final class NativeReflectionParser
{
    /**
     * @param non-empty-string $name
     */
    public static function parseConstant(string $name): ConstantDeclaration
    {
        $value = \constant($name);
        $extension = get_constant_extension($name);

        return new ConstantDeclaration(
            context: Context::start($extension === null ? SourceCode::fakeConstant($name) : Extension::fromName($extension)),
            name: $name,
            value: new Value($value),
        );
    }

    public static function parseFunction(\ReflectionFunction $function): FunctionDeclaration
    {
        $extension = $function->getExtensionName();
        \assert($extension !== false);

        $context = Context::start(Extension::fromName($extension))
            ->withNames(self::createNameContext($function->getNamespaceName()))
            ->enterFunctionDeclaration($function->getShortName());

        return new FunctionDeclaration(
            context: $context,
            returnsReference: $function->returnsReference(),
            generator: $function->isGenerator(),
            returnType: self::reflectType($context, $function->getReturnType()),
            tentativeReturnType: self::reflectType($context, $function->getTentativeReturnType()),
            parameters: self::reflectParameters($context, $function->getParameters()),
            internallyDeprecated: $function->isDeprecated(),
            attributes: self::reflectAttributes($function->getAttributes()),
        );
    }

    public static function parseClass(\ReflectionClass $class): ClassDeclaration
    {
        $extension = $class->getExtensionName();
        \assert($extension !== false);

        $context = Context::start(Extension::fromName($extension))
            ->withNames(self::createNameContext($class->getNamespaceName()));

        \assert(!$class->isAnonymous());
        $parent = $class->getParentClass() ?: null;

        $context = match (true) {
            $class->isInterface() => $context->enterInterfaceDeclaration($class->getShortName()),
            $class->isTrait() => $context->enterTrait($class->getShortName()),
            $class->isEnum() => $context->enterEnumDeclaration($class->getShortName()),
            default => $context->enterClassDeclaration($class->getShortName(), $parent === null ? null : '\\' . $parent->name),
        };

        return new ClassDeclaration(
            context: $context,
            kind: match (true) {
                $class->isInterface() => ClassKind::Interface,
                $class->isTrait() => ClassKind::Trait,
                $class->isEnum() => ClassKind::Enum,
                default => ClassKind::Class_,
            },
            readonly: self::reflectClassReadonly($class),
            final: $class->isFinal(),
            abstract: (bool) ($class->getModifiers() & \ReflectionClass::IS_EXPLICIT_ABSTRACT),
            parent: $parent?->name,
            interfaces: array_values(array_diff(
                $class->getInterfaceNames(),
                $parent?->getInterfaceNames() ?? [],
                ...array_map(
                    static fn(\ReflectionClass $interface): array => $interface->getInterfaceNames(),
                    array_values($class->getInterfaces()),
                ),
            )),
            backingType: self::reflectBackingType($class),
            attributes: self::reflectAttributes($class->getAttributes()),
            properties: self::reflectProperties($context, $class->getProperties()),
            constants: self::reflectClassConstants($context, $class->getReflectionConstants()),
            methods: self::reflectMethods($context, $class->getMethods()),
            internallyNonCloneable: !$class->isCloneable(),
        );
    }

    /**
     * @psalm-suppress RedundantCondition, UnusedPsalmSuppress
     */
    private static function reflectClassReadonly(\ReflectionClass $class): bool
    {
        return method_exists($class, 'isReadonly') && $class->isReadonly();
    }

    /**
     * @return ?Type<int|string>
     */
    private static function reflectBackingType(\ReflectionClass $class): ?Type
    {
        if (!$class->isEnum()) {
            return null;
        }

        $type = (new \ReflectionEnum($class->name))->getBackingType();

        if ($type === null) {
            return null;
        }

        \assert($type instanceof \ReflectionNamedType);

        return $type->getName() === 'int' ? types::int : types::string;
    }

    /**
     * @param Context<NamedClassId> $context
     * @param array<\ReflectionClassConstant> $constants
     * @return list<ClassConstantDeclaration|EnumCaseDeclaration>
     */
    private static function reflectClassConstants(Context $context, array $constants): array
    {
        $declarations = [];
        $backedEnum = null;

        foreach ($constants as $constant) {
            if ($constant->class !== $context->id->name) {
                continue;
            }

            if ($constant->isEnumCase()) {
                $backedEnum ??= (new \ReflectionEnum($constant->class))->isBacked();
                $declarations[] = new EnumCaseDeclaration(
                    context: $context,
                    name: $constant->name,
                    backingValue: $backedEnum ? (new \ReflectionEnumBackedCase($constant->class, $constant->name))->getBackingValue() : null,
                    attributes: self::reflectAttributes($constant->getAttributes()),
                );

                continue;
            }

            $declarations[] = new ClassConstantDeclaration(
                context: $context,
                name: $constant->name,
                value: new Value($constant->getValue()),
                final: (bool) $constant->isFinal(),
                type: self::reflectClassConstantType($context, $constant),
                visibility: self::reflectVisibility($constant),
                attributes: self::reflectAttributes($constant->getAttributes()),
            );
        }

        return $declarations;
    }

    private static function reflectClassConstantType(Context $context, \ReflectionClassConstant $constant): ?Type
    {
        if (method_exists($constant, 'getType')) {
            /** @var ?\ReflectionType */
            $type = $constant->getType();

            return self::reflectType($context, $type);
        }

        return null;
    }

    /**
     * @param Context<NamedClassId> $context
     * @param array<\ReflectionProperty> $properties
     * @return list<PropertyDeclaration>
     */
    private static function reflectProperties(Context $context, array $properties): array
    {
        $declarations = [];

        foreach ($properties as $property) {
            if ($property->name === '' || !$property->isDefault()) {
                continue;
            }

            if ($property->class !== $context->id->name) {
                continue;
            }

            $declarations[] = new PropertyDeclaration(
                context: $context,
                name: $property->name,
                visibility: self::reflectVisibility($property),
                static: $property->isStatic(),
                readonly: $property->isReadOnly(),
                type: self::reflectType($context, $property->getType()),
                default: $property->hasDefaultValue() ? new Value($property->getDefaultValue()) : null,
                attributes: self::reflectAttributes($property->getAttributes()),
            );
        }

        if ($context->id->name === \UnitEnum::class) {
            $declarations[] = PropertyDeclaration::enumNameProperty($context);
        }

        if ($context->id->name === \BackedEnum::class) {
            $declarations[] = PropertyDeclaration::enumValueProperty($context);
        }

        return $declarations;
    }

    /**
     * @param Context<NamedClassId> $context
     * @param array<\ReflectionMethod> $methods
     * @return list<MethodDeclaration>
     */
    private static function reflectMethods(Context $context, array $methods): array
    {
        $methodDeclarations = [];

        foreach ($methods as $method) {
            if ($method->class !== $context->id->name) {
                continue;
            }

            $methodContext = $context->enterMethodDeclaration($method->name);
            $methodDeclarations[] = new MethodDeclaration(
                context: $methodContext,
                attributes: self::reflectAttributes($method->getAttributes()),
                static: $method->isStatic(),
                returnsReference: $method->returnsReference(),
                generator: $method->isGenerator(),
                final: $method->isFinal(),
                abstract: $method->isAbstract(),
                returnType: self::reflectType($methodContext, $method->getReturnType()),
                tentativeReturnType: self::reflectType($methodContext, $method->getTentativeReturnType()),
                visibility: self::reflectVisibility($method),
                parameters: self::reflectParameters($methodContext, $method->getParameters()),
                internallyDeprecated: $method->isDeprecated(),
            );
        }

        return $methodDeclarations;
    }

    private static function reflectVisibility(\ReflectionClassConstant|\ReflectionProperty|\ReflectionMethod $reflection): Visibility
    {
        return match (true) {
            $reflection->isPrivate() => Visibility::Private,
            $reflection->isProtected() => Visibility::Protected,
            default => Visibility::Public,
        };
    }

    /**
     * @template TContextId of AnonymousFunctionId|NamedFunctionId|MethodId
     * @param Context<TContextId> $context
     * @param list<\ReflectionParameter> $parameters
     * @return list<ParameterDeclaration<TContextId>>
     */
    private static function reflectParameters(Context $context, array $parameters): array
    {
        $data = [];

        foreach ($parameters as $parameter) {
            $data[] = new ParameterDeclaration(
                context: $context,
                name: $parameter->name,
                type: self::reflectType($context, $parameter->getType()),
                default: self::reflectParameterDefaultValueExpression($parameter),
                variadic: $parameter->isVariadic(),
                passedBy: match (true) {
                    $parameter->canBePassedByValue() && $parameter->isPassedByReference() => PassedBy::ValueOrReference,
                    $parameter->canBePassedByValue() => PassedBy::Value,
                    default => PassedBy::Reference,
                },
                attributes: self::reflectAttributes($parameter->getAttributes()),
                internallyOptional: $parameter->isOptional(), // todo
            );
        }

        return $data;
    }

    private static function reflectParameterDefaultValueExpression(\ReflectionParameter $reflection): ?ConstantExpression
    {
        if (!$reflection->isDefaultValueAvailable()) {
            return null;
        }

        $constant = $reflection->getDefaultValueConstantName();

        if ($constant === null) {
            return new Value($reflection->getDefaultValue());
        }

        $parts = explode('::', $constant);

        if (\count($parts) === 1) {
            \assert($parts[0] !== '');

            return new ConstantFetch(Id::constant($parts[0]));
        }

        [$class, $name] = $parts;

        return new ClassConstantFetch(new Value($class), new Value($name));
    }

    /**
     * @param array<\ReflectionAttribute> $attributes
     * @return list<AttributeDeclaration>
     */
    private static function reflectAttributes(array $attributes): array
    {
        return array_values(
            array_map(
                static fn(\ReflectionAttribute $attribute): AttributeDeclaration => new AttributeDeclaration(
                    class: $attribute->getName(),
                    arguments: new Value($attribute->getArguments()),
                ),
                $attributes,
            ),
        );
    }

    /**
     * @return ($reflectionType is null ? null : Type)
     */
    private static function reflectType(Context $context, ?\ReflectionType $reflectionType): ?Type
    {
        if ($reflectionType === null) {
            return null;
        }

        if ($reflectionType instanceof \ReflectionUnionType) {
            return types::union(...array_map(
                static fn(\ReflectionType $child): Type => self::reflectType($context, $child),
                $reflectionType->getTypes(),
            ));
        }

        if ($reflectionType instanceof \ReflectionIntersectionType) {
            return types::intersection(...array_map(
                static fn(\ReflectionType $child): Type => self::reflectType($context, $child),
                $reflectionType->getTypes(),
            ));
        }

        if (!$reflectionType instanceof \ReflectionNamedType) {
            throw new \LogicException(\sprintf('Unknown reflection type %s', $reflectionType::class));
        }

        $name = $reflectionType->getName();
        $type = match ($name) {
            'never' => types::never,
            'void' => types::void,
            'null' => types::null,
            'true' => types::true,
            'false' => types::false,
            'bool' => types::bool,
            'int' => types::int,
            'float' => types::float,
            'string' => types::string,
            'array' => types::array,
            'object' => types::object,
            'Closure' => types::Closure,
            'callable' => types::callable,
            'iterable' => types::iterable,
            'resource' => types::resource,
            'mixed' => types::mixed,
            'self', 'parent', 'static' => $context->resolveNameAsType($name),
            default => $context->resolveNameAsType('\\' . $name),
        };

        if ($reflectionType->allowsNull() && $name !== 'null' && $name !== 'mixed') {
            return types::nullable($type);
        }

        return $type;
    }

    private static function createNameContext(string $namespace): NameContext
    {
        $nameContext = new NameContext(new Throwing());
        $nameContext->startNamespace($namespace === '' ? null : NameParser::parse($namespace));

        return $nameContext;
    }

    private function __construct() {}
}
