<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Internal\PhpDoc;

use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprFalseNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprFloatNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprIntegerNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprNullNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprStringNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprTrueNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstFetchNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\CallableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\CallableTypeParameterNode;
use PHPStan\PhpDocParser\Ast\Type\ConditionalTypeForParameterNode;
use PHPStan\PhpDocParser\Ast\Type\ConditionalTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ConstTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IntersectionTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ObjectShapeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use Typhoon\Reflection\Internal\TypeContext\NameParser;
use Typhoon\Reflection\Internal\TypeContext\TypeContext;
use Typhoon\Type\Parameter;
use Typhoon\Type\ShapeElement;
use Typhoon\Type\Type;
use Typhoon\Type\types;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection\Internal\PhpDoc
 */
final class ContextualPhpDocTypeReflector
{
    public function __construct(
        private readonly TypeContext $typeContext = new TypeContext(),
    ) {}

    /**
     * @return non-empty-string
     */
    public function resolveClass(IdentifierTypeNode $node): string
    {
        return $this->typeContext->resolveClass(NameParser::parse($node->name))->toString();
    }

    /**
     * @return ($node is null ? null : Type)
     * @throws InvalidPhpDocType
     */
    public function reflectType(?TypeNode $node): ?Type
    {
        if ($node === null) {
            return null;
        }

        if ($node instanceof NullableTypeNode) {
            return types::nullable($this->reflectType($node->type));
        }

        if ($node instanceof UnionTypeNode) {
            return types::union(...array_map($this->reflectType(...), $node->types));
        }

        if ($node instanceof IntersectionTypeNode) {
            return types::intersection(...array_map($this->reflectType(...), $node->types));
        }

        if ($node instanceof ArrayTypeNode) {
            return types::array(value: $this->reflectType($node->type));
        }

        if ($node instanceof ArrayShapeNode) {
            if ($node->kind === ArrayShapeNode::KIND_LIST) {
                return $this->reflectListShape($node);
            }

            return $this->reflectArrayShape($node);
        }

        if ($node instanceof ObjectShapeNode) {
            return $this->reflectObjectShape($node);
        }

        if ($node instanceof ConstTypeNode) {
            return $this->reflectConstExpr($node);
        }

        if ($node instanceof CallableTypeNode) {
            return $this->reflectCallable($node);
        }

        if ($node instanceof IdentifierTypeNode) {
            return $this->reflectIdentifier($node->name);
        }

        if ($node instanceof GenericTypeNode) {
            return $this->reflectIdentifier($node->type->name, $node->genericTypes);
        }

        if ($node instanceof ConditionalTypeNode || $node instanceof ConditionalTypeForParameterNode) {
            return $this->reflectConditional($node);
        }

        throw new InvalidPhpDocType(sprintf('Type node %s is not supported', $node::class));
    }

    /**
     * @param list<TypeNode> $genericTypes
     */
    private function reflectIdentifier(string $name, array $genericTypes = []): Type
    {
        return match ($name) {
            'null' => types::null,
            'true' => types::true,
            'false' => types::false,
            'bool', 'boolean' => types::bool,
            'float', 'double' => types::float,
            'positive-int' => types::positiveInt,
            'negative-int' => types::negativeInt,
            'non-negative-int' => types::nonNegativeInt,
            'non-positive-int' => types::nonPositiveInt,
            'non-zero-int' => types::nonZeroInt,
            'int', 'integer' => match (\count($genericTypes)) {
                0 => types::int,
                2 => types::intRange(
                    min: $this->reflectIntLimit($genericTypes[0], 'min'),
                    max: $this->reflectIntLimit($genericTypes[1], 'max'),
                ),
                default => throw new InvalidPhpDocType(sprintf('int range type should have 2 arguments, got %d', \count($genericTypes)))
            },
            'int-mask', 'int-mask-of' => types::intMaskOf(types::union(...array_map($this->reflectType(...), $genericTypes))),
            'numeric' => types::numeric,
            'non-empty-string' => types::nonEmptyString,
            'string' => types::string,
            'non-falsy-string', 'truthy-string' => types::truthyString,
            'numeric-string' => types::numericString,
            'lowercase-string' => types::lowercaseString,
            'non-empty-lowercase-string' => types::intersection(types::nonEmptyString, types::lowercaseString),
            'class-string' => match (\count($genericTypes)) {
                0 => types::classString,
                1 => types::classString($this->reflectType($genericTypes[0])),
                default => throw new InvalidPhpDocType(),
            },
            'array-key' => types::arrayKey,
            'key-of' => match ($number = \count($genericTypes)) {
                1 => types::key($this->reflectType($genericTypes[0])),
                default => throw new InvalidPhpDocType(sprintf('key-of type should have 1 argument, got %d', $number)),
            },
            'value-of' => match ($number = \count($genericTypes)) {
                1 => types::value($this->reflectType($genericTypes[0])),
                default => throw new InvalidPhpDocType(sprintf('value-of type should have 1 argument, got %d', $number)),
            },
            'literal-int' => types::literalInt,
            'literal-string' => types::literalString,
            'literal-float' => types::literalFloat,
            'callable-string' => types::callableString,
            'interface-string', 'enum-string', 'trait-string' => types::classString,
            'callable-array' => types::intersection(types::callable, types::array),
            'resource', 'closed-resource', 'open-resource' => types::resource,
            'list' => match ($number = \count($genericTypes)) {
                0 => types::list(),
                1 => types::list($this->reflectType($genericTypes[0])),
                default => throw new InvalidPhpDocType(sprintf('list type should have at most 1 argument, got %d', $number)),
            },
            'non-empty-list' => match ($number = \count($genericTypes)) {
                0 => types::nonEmptyList(),
                1 => types::nonEmptyList($this->reflectType($genericTypes[0])),
                default => throw new InvalidPhpDocType(sprintf('list type should have at most 1 argument, got %d', $number)),
            },
            'array' => match ($number = \count($genericTypes)) {
                0 => types::array,
                1 => types::array(value: $this->reflectType($genericTypes[0])),
                2 => types::array($this->reflectType($genericTypes[0]), $this->reflectType($genericTypes[1])),
                default => throw new InvalidPhpDocType(sprintf('array type should have at most 2 arguments, got %d', $number)),
            },
            'non-empty-array' => match ($number = \count($genericTypes)) {
                0 => types::nonEmptyArray(),
                1 => types::nonEmptyArray(value: $this->reflectType($genericTypes[0])),
                2 => types::nonEmptyArray($this->reflectType($genericTypes[0]), $this->reflectType($genericTypes[1])),
                default => throw new InvalidPhpDocType(sprintf('array type should have at most 2 arguments, got %d', $number)),
            },
            'iterable' => match ($number = \count($genericTypes)) {
                0 => types::iterable,
                1 => types::iterable(value: $this->reflectType($genericTypes[0])),
                2 => types::iterable(...array_map($this->reflectType(...), $genericTypes)),
                default => throw new InvalidPhpDocType(sprintf('iterable type should have at most 2 arguments, got %d', $number)),
            },
            'object' => types::object,
            'callable' => types::callable,
            'mixed' => types::mixed,
            'void' => types::void,
            'scalar' => types::scalar,
            'never' => types::never,
            default => $this->typeContext->resolveType(NameParser::parse($name), array_map($this->reflectType(...), $genericTypes)),
        };
    }

    /**
     * @param 'min'|'max' $parameterName
     */
    private function reflectIntLimit(TypeNode $type, string $parameterName): ?int
    {
        if ($type instanceof IdentifierTypeNode && $type->name === $parameterName) {
            return null;
        }

        if (!$type instanceof ConstTypeNode) {
            throw new InvalidPhpDocType(sprintf('Invalid int range %s argument: %s', $parameterName, $type));
        }

        if (!$type->constExpr instanceof ConstExprIntegerNode) {
            throw new InvalidPhpDocType(sprintf('Invalid int range %s argument: %s', $parameterName, $type));
        }

        return (int) $type->constExpr->value;
    }

    private function reflectListShape(ArrayShapeNode $node): Type
    {
        $elements = [];

        foreach ($node->items as $item) {
            $keyName = $item->keyName;
            $type = new ShapeElement($this->reflectType($item->valueType), $item->optional);

            if ($keyName === null) {
                $elements[] = $type;

                continue;
            }

            if ($keyName instanceof ConstExprIntegerNode) {
                $key = (int) $keyName->value;

                if ($key < 0) {
                    throw new InvalidPhpDocType(sprintf('Unexpected negative key %d in a list shape', $key));
                }

                $elements[$key] = $type;

                continue;
            }

            throw new InvalidPhpDocType(sprintf('Unexpected key "%s" in a list shape', $keyName::class));
        }

        if ($node->sealed) {
            if (!array_is_list($elements)) {
                throw new InvalidPhpDocType(sprintf(
                    'Keys in a sealed shape must be a list, got %s',
                    implode(', ', array_keys($elements)),
                ));
            }

            return types::listShape($elements);
        }

        return types::unsealedListShape($elements);
    }

    private function reflectArrayShape(ArrayShapeNode $node): Type
    {
        $elements = [];

        foreach ($node->items as $item) {
            $keyName = $item->keyName;
            $type = new ShapeElement($this->reflectType($item->valueType), $item->optional);

            if ($keyName === null) {
                $elements[] = $type;

                continue;
            }

            $key = match (true) {
                $keyName instanceof ConstExprIntegerNode => (int) $keyName->value,
                $keyName instanceof ConstExprStringNode => $keyName->value,
                $keyName instanceof IdentifierTypeNode => $keyName->name,
            };
            $elements[$key] = $type;
        }

        if ($node->sealed) {
            return types::arrayShape($elements);
        }

        return types::unsealedArrayShape($elements);
    }

    private function reflectObjectShape(ObjectShapeNode $node): Type
    {
        $properties = [];

        foreach ($node->items as $item) {
            $keyName = $item->keyName;

            $name = match ($keyName::class) {
                ConstExprStringNode::class => $keyName->value,
                IdentifierTypeNode::class => $keyName->name,
                default => throw new InvalidPhpDocType(sprintf('%s is not supported', $keyName::class)),
            };

            $properties[$name] = new ShapeElement($this->reflectType($item->valueType), $item->optional);
        }

        return types::objectShape($properties);
    }

    private function reflectConstExpr(ConstTypeNode $node): Type
    {
        $exprNode = $node->constExpr;

        if ($exprNode instanceof ConstExprIntegerNode) {
            return types::int((int) $exprNode->value);
        }

        if ($exprNode instanceof ConstExprFloatNode) {
            return types::float((float) $exprNode->value);
        }

        if ($exprNode instanceof ConstExprStringNode) {
            return types::string($exprNode->value);
        }

        if ($exprNode instanceof ConstExprTrueNode) {
            return types::true;
        }

        if ($exprNode instanceof ConstExprFalseNode) {
            return types::false;
        }

        if ($exprNode instanceof ConstExprNullNode) {
            return types::null;
        }

        if ($exprNode instanceof ConstFetchNode) {
            if ($exprNode->className === '') {
                return types::constant($exprNode->name);
            }

            $class = $this->typeContext->resolveType(NameParser::parse($exprNode->className));

            if ($exprNode->name === 'class') {
                return types::classString($class);
            }

            return types::classConstant($class, $exprNode->name);
        }

        throw new InvalidPhpDocType(sprintf('PhpDoc node %s is not supported', $exprNode::class));
    }

    private function reflectCallable(CallableTypeNode $node): Type
    {
        if ($node->identifier->name === 'callable') {
            return types::callable(
                parameters: $this->reflectCallableParameters($node->parameters),
                return: $this->reflectType($node->returnType),
            );
        }

        $class = $this->resolveClass($node->identifier);

        if ($class === \Closure::class) {
            return types::closure(
                parameters: $this->reflectCallableParameters($node->parameters),
                return: $this->reflectType($node->returnType),
            );
        }

        throw new InvalidPhpDocType(sprintf('PhpDoc type "%s" is not supported', $node));
    }

    /**
     * @param list<CallableTypeParameterNode> $nodes
     * @return list<Parameter>
     */
    private function reflectCallableParameters(array $nodes): array
    {
        return array_map(
            fn(CallableTypeParameterNode $parameter): Parameter => types::param(
                type: $this->reflectType($parameter->type),
                hasDefault: $parameter->isOptional,
                variadic: $parameter->isVariadic,
                byReference: $parameter->isReference,
                name: $parameter->parameterName ?: null,
            ),
            $nodes,
        );
    }

    private function reflectConditional(ConditionalTypeNode|ConditionalTypeForParameterNode $node): Type
    {
        if ($node instanceof ConditionalTypeNode) {
            $subject = $this->reflectType($node->subjectType);
        } else {
            $name = ltrim($node->parameterName, '$');
            \assert($name !== '');
            $subject = types::arg($name);
        }

        if ($node->negated) {
            return types::conditional(
                subject: $subject,
                if: $this->reflectType($node->targetType),
                then: $this->reflectType($node->else),
                else: $this->reflectType($node->if),
            );
        }

        return types::conditional(
            subject: $subject,
            if: $this->reflectType($node->targetType),
            then: $this->reflectType($node->if),
            else: $this->reflectType($node->else),
        );
    }
}
