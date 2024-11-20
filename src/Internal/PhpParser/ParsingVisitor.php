<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Internal\PhpParser;

use PhpParser\Comment\Doc;
use PhpParser\ConstExprEvaluationException;
use PhpParser\ConstExprEvaluator;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\ComplexType;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Const_;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\TraitUseAdaptation\Alias;
use PhpParser\Node\Stmt\TraitUseAdaptation\Precedence;
use PhpParser\Node\UnionType;
use PhpParser\NodeVisitorAbstract;
use Typhoon\DeclarationId\AnonymousClassId;
use Typhoon\DeclarationId\AnonymousFunctionId;
use Typhoon\DeclarationId\MethodId;
use Typhoon\DeclarationId\NamedClassId;
use Typhoon\DeclarationId\NamedFunctionId;
use Typhoon\Reflection\Declaration\AttributeDeclaration;
use Typhoon\Reflection\Declaration\ClassConstantDeclaration;
use Typhoon\Reflection\Declaration\ClassDeclaration;
use Typhoon\Reflection\Declaration\ClassKind;
use Typhoon\Reflection\Declaration\ConstantDeclaration;
use Typhoon\Reflection\Declaration\ConstantExpression\AppendedArrayElement;
use Typhoon\Reflection\Declaration\ConstantExpression\ArrayDeclaration;
use Typhoon\Reflection\Declaration\ConstantExpression\ConstantExpression;
use Typhoon\Reflection\Declaration\ConstantExpression\KeyArrayElement;
use Typhoon\Reflection\Declaration\ConstantExpression\Value;
use Typhoon\Reflection\Declaration\Context;
use Typhoon\Reflection\Declaration\EnumCaseDeclaration;
use Typhoon\Reflection\Declaration\FunctionDeclaration;
use Typhoon\Reflection\Declaration\MethodDeclaration;
use Typhoon\Reflection\Declaration\ParameterDeclaration;
use Typhoon\Reflection\Declaration\PassedBy;
use Typhoon\Reflection\Declaration\PropertyDeclaration;
use Typhoon\Reflection\Declaration\TraitMethodAlias;
use Typhoon\Reflection\Declaration\Visibility;
use Typhoon\Reflection\Internal\Type\IsNativeTypeNullable;
use Typhoon\Reflection\SourceCode;
use Typhoon\Reflection\SourceCodeSnippet;
use Typhoon\Type\Type;
use Typhoon\Type\types;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 * @psalm-type MethodName = non-empty-string
 * @psalm-type TraitName = non-empty-string
 */
final class ParsingVisitor extends NodeVisitorAbstract
{
    /**
     * @var list<ConstantDeclaration|FunctionDeclaration|ClassDeclaration>
     * @psalm-readonly-allow-private-mutation
     */
    public array $data = [];

    public function __construct(
        private readonly ContextProvider $contextProvider,
        private readonly ConstExprEvaluator $evaluator = new ConstExprEvaluator(),
    ) {}

    /**
     * @throws ConstExprEvaluationException
     */
    public function leaveNode(Node $node): ?int
    {
        if ($node instanceof Const_) {
            /** @var Context<null> */
            $context = $this->contextProvider->get();
            $compiler = new ConstantExpressionParser($context);

            foreach ($node->consts as $const) {
                \assert($const->namespacedName !== null);

                $this->data[] = new ConstantDeclaration(
                    context: $context,
                    name: $const->namespacedName->toString(),
                    value: $compiler->parse($const->value),
                    snippet: $this->parseSnippet($context, $node),
                    phpDoc: $this->parseSnippet($context, $node->getDocComment()),
                );
            }

            return null;
        }

        if ($node instanceof FuncCall
            && $node->name instanceof Name
            && strtolower($node->name->toString()) === 'define'
        ) {
            $nameArg = $node->args[0] ?? $node->args['constant_name'] ?? null;
            $valueArg = $node->args[1] ?? $node->args['value'] ?? null;

            if (!($nameArg instanceof Arg && $valueArg instanceof Arg)) {
                return null;
            }

            $name = $this->evaluator->evaluateSilently($nameArg->value);
            \assert(\is_string($name) && $name !== '');

            /** @var Context<null> */
            $context = $this->contextProvider->get();
            $compiler = new ConstantExpressionParser($context);

            $this->data[] = new ConstantDeclaration(
                context: $context,
                name: $name,
                value: $compiler->parse($valueArg->value),
                snippet: $this->parseSnippet($context, $node),
            );

            return null;
        }

        if ($node instanceof Function_) {
            $this->data[] = $this->parseFunction($node);

            return null;
        }

        if ($node instanceof ClassLike) {
            $this->data[] = $this->parseClassLike($node);

            return null;
        }

        if ($node instanceof ClassMethod) {
            $node->setAttribute(Context::class, $this->contextProvider->get());

            return null;
        }

        return null;
    }

    private function parseFunction(Function_ $node): FunctionDeclaration
    {
        /** @var Context<AnonymousFunctionId|NamedFunctionId> */
        $context = $this->contextProvider->get();

        return new FunctionDeclaration(
            context: $context,
            returnsReference: $node->returnsByRef(),
            generator: GeneratorVisitor::isGenerator($node),
            returnType: $this->parseType($context, $node->getReturnType()),
            parameters: $this->parseParameters($context, $node->getParams()),
            phpDoc: $this->parseSnippet($context, $node->getDocComment()),
            snippet: $this->parseSnippet($context, $node),
            attributes: $this->parseAttributes($context, $node->attrGroups),
        );
    }

    private function parseClassLike(ClassLike $node): ClassDeclaration
    {
        /** @var Context<AnonymousClassId|NamedClassId> */
        $context = $this->contextProvider->get();

        /** @var ?Type<int|string> */
        $backingType = $node instanceof Enum_ ? $this->parseType($context, $node->scalarType) : null;

        return new ClassDeclaration(
            context: $context,
            kind: match (true) {
                $node instanceof Interface_ => ClassKind::Interface,
                $node instanceof Enum_ => ClassKind::Enum,
                $node instanceof Stmt\Trait_ => ClassKind::Trait,
                default => ClassKind::Class_,
            },
            phpDoc: $this->parseSnippet($context, $node->getDocComment()),
            snippet: $this->parseSnippet($context, $node),
            readonly: $node instanceof Class_ && $node->isReadonly(),
            final: $node instanceof Class_ && $node->isFinal(),
            abstract: $node instanceof Class_ && $node->isAbstract(),
            parent: $node instanceof Class_ ? $node->extends?->toString() : null,
            interfaces: array_values(
                array_map(
                    static fn(Name $name): string => $name->toString(),
                    match (true) {
                        $node instanceof Class_, $node instanceof Enum_ => $node->implements,
                        $node instanceof Interface_ => $node->extends,
                        default => [],
                    },
                ),
            ),
            traits: $traits = $this->parseTraits($traitUseNodes = $node->getTraitUses()),
            traitMethodAliases: $this->parseTraitAliases($traitUseNodes, $traits),
            traitMethodPrecedence: $this->parseTraitMethodPrecedence($traitUseNodes),
            usePhpDocs: $this->parseUsePhpDocs($context, $traitUseNodes),
            backingType: $backingType,
            attributes: $this->parseAttributes($context, $node->attrGroups),
            properties: $this->parseProperties($context, $node->getProperties()),
            constants: $this->parseClassConstants($context, $node->stmts),
            methods: $this->parseMethods($node->getMethods()),
        );
    }

    /**
     * @param Context<AnonymousClassId|NamedClassId> $context
     * @param array<TraitUse> $nodes
     * @return list<SourceCodeSnippet>
     */
    private function parseUsePhpDocs(Context $context, array $nodes): array
    {
        $phpDocs = [];

        foreach ($nodes as $node) {
            $phpDoc = $node->getDocComment();

            if ($phpDoc !== null) {
                $phpDocs[] = $this->parseSnippet($context, $phpDoc);
            }
        }

        return $phpDocs;
    }

    /**
     * @param array<TraitUse> $nodes
     * @return list<non-empty-string>
     */
    private function parseTraits(array $nodes): array
    {
        $traits = [];

        foreach ($nodes as $node) {
            foreach ($node->traits as $name) {
                $traits[] = $name->toString();
            }
        }

        return $traits;
    }

    /**
     * @param array<TraitUse> $nodes
     * @return array<MethodName, TraitName>
     */
    private function parseTraitMethodPrecedence(array $nodes): array
    {
        $precedence = [];

        foreach ($nodes as $node) {
            foreach ($node->adaptations as $adaptation) {
                if ($adaptation instanceof Precedence) {
                    \assert($adaptation->trait !== null);
                    $precedence[$adaptation->method->name] = $adaptation->trait->toString();
                }
            }
        }

        return $precedence;
    }

    /**
     * @param array<TraitUse> $nodes
     * @param list<non-empty-string> $traits
     * @return list<TraitMethodAlias>
     */
    private function parseTraitAliases(array $nodes, array $traits): array
    {
        $aliases = [];

        foreach ($nodes as $node) {
            foreach ($node->adaptations as $adaptation) {
                if ($adaptation instanceof Alias) {
                    if ($adaptation->trait === null) {
                        $aliasTraits = $traits;
                    } else {
                        $aliasTraits = [$adaptation->trait->toString()];
                    }

                    foreach ($aliasTraits as $aliasTrait) {
                        $aliases[] = new TraitMethodAlias(
                            trait: $aliasTrait,
                            method: $adaptation->method->name,
                            newName: $adaptation->newName?->name,
                            newVisibility: $adaptation->newModifier === null ? null : $this->parseVisibility($adaptation->newModifier),
                        );
                    }
                }
            }
        }

        return $aliases;
    }

    /**
     * @param Context<AnonymousClassId|NamedClassId> $context
     * @param array<Stmt> $nodes
     * @return list<ClassConstantDeclaration|EnumCaseDeclaration>
     */
    private function parseClassConstants(Context $context, array $nodes): array
    {
        $compiler = new ConstantExpressionParser($context);
        $constants = [];

        foreach ($nodes as $node) {
            if ($node instanceof ClassConst) {
                $phpDoc = $this->parseSnippet($context, $node->getDocComment());
                $attributes = $this->parseAttributes($context, $node->attrGroups);
                $final = $node->isFinal();
                $visibility = $this->parseVisibility($node->flags);
                $type = $this->parseType($context, $node->type);

                foreach ($node->consts as $const) {
                    $constants[] = new ClassConstantDeclaration(
                        context: $context,
                        name: $const->name->name,
                        value: $compiler->parse($const->value),
                        snippet: $this->parseSnippet($context, $const),
                        phpDoc: $phpDoc,
                        final: $final,
                        type: $type,
                        visibility: $visibility,
                        attributes: $attributes,
                    );
                }

                continue;
            }

            if ($node instanceof EnumCase) {
                $constants[] = new EnumCaseDeclaration(
                    context: $context,
                    name: $node->name->name,
                    backingValue: match (true) {
                        $node->expr === null => null,
                        $node->expr instanceof Node\Scalar\String_,
                        $node->expr instanceof Node\Scalar\LNumber => $node->expr->value,
                    },
                    snippet: $this->parseSnippet($context, $node),
                    attributes: $this->parseAttributes($context, $node->attrGroups),
                    phpDoc: $this->parseSnippet($context, $node->getDocComment()),
                );

                continue;
            }
        }

        return $constants;
    }

    /**
     * @param Context<AnonymousClassId|NamedClassId> $context
     * @param array<Stmt\Property> $nodes
     * @return list<PropertyDeclaration>
     */
    private function parseProperties(Context $context, array $nodes): array
    {
        $compiler = new ConstantExpressionParser($context);
        $properties = [];

        foreach ($nodes as $node) {
            $phpDoc = $this->parseSnippet($context, $node->getDocComment());
            $attributes = $this->parseAttributes($context, $node->attrGroups);
            $static = $node->isStatic();
            $readonly = $node->isReadonly();
            $type = $this->parseType($context, $node->type);
            $visibility = $this->parseVisibility($node->flags);

            foreach ($node->props as $prop) {
                $default = $compiler->parse($prop->default);

                if ($default === null && $node->type === null) {
                    $default = new Value(null);
                }

                $properties[] = new PropertyDeclaration(
                    context: $context,
                    name: $prop->name->name,
                    visibility: $visibility,
                    static: $static,
                    readonly: $readonly,
                    type: $type,
                    default: $default,
                    phpDoc: $phpDoc,
                    snippet: $this->parseSnippet($context, $prop),
                    attributes: $attributes,
                );
            }
        }

        return $properties;
    }

    /**
     * @param array<ClassMethod> $nodes
     * @return list<MethodDeclaration>
     */
    private function parseMethods(array $nodes): array
    {
        $methods = [];

        foreach ($nodes as $node) {
            /** @var Context<MethodId> */
            $context = $node->getAttribute(Context::class);

            $methods[] = new MethodDeclaration(
                context: $context,
                phpDoc: $this->parseSnippet($context, $node->getDocComment()),
                snippet: $this->parseSnippet($context, $node),
                attributes: $this->parseAttributes($context, $node->attrGroups),
                static: $node->isStatic(),
                returnsReference: $node->returnsByRef(),
                generator: GeneratorVisitor::isGenerator($node),
                final: $node->isFinal(),
                abstract: $node->isAbstract(),
                returnType: $this->parseType($context, $node->getReturnType()),
                visibility: $this->parseVisibility($node->flags),
                parameters: $this->parseParameters($context, $node->getParams()),
            );
        }

        return $methods;
    }

    /**
     * @template TContextId of AnonymousFunctionId|NamedFunctionId|MethodId
     * @param Context<TContextId> $context
     * @param array<Node\Param> $nodes
     * @return list<ParameterDeclaration<TContextId>>
     */
    private function parseParameters(Context $context, array $nodes): array
    {
        $compiler = new ConstantExpressionParser($context);
        $parameters = [];

        foreach ($nodes as $node) {
            \assert($node->var instanceof Variable && \is_string($node->var->name));

            $default = $compiler->parse($node->default);

            $parameters[] = new ParameterDeclaration(
                context: $context,
                name: $node->var->name,
                type: $this->parseParameterType($context, $node->type, $this->isDefaultNull($default)),
                default: $default,
                variadic: $node->variadic,
                passedBy: $node->byRef ? PassedBy::Reference : PassedBy::Value,
                visibility: $this->parseVisibility($node->flags),
                readonly: (bool) ($node->flags & Class_::MODIFIER_READONLY),
                attributes: $this->parseAttributes($context, $node->attrGroups),
                phpDoc: $this->parseSnippet($context, $node->getDocComment()),
                snippet: $this->parseSnippet($context, $node),
                internallyOptional: false,
            );
        }

        return $parameters;
    }

    private function parseParameterType(Context $context, null|Name|Identifier|ComplexType $node, bool $defaultIsNull): ?Type
    {
        $type = $this->parseType($context, $node);

        if ($defaultIsNull && $type !== null && !$type->accept(new IsNativeTypeNullable())) {
            return types::nullable($type);
        }

        return $type;
    }

    /**
     * @param array<AttributeGroup> $attributeGroups
     * @return list<AttributeDeclaration>
     */
    private function parseAttributes(Context $context, array $attributeGroups): array
    {
        $compiler = new ConstantExpressionParser($context);
        $attributes = [];

        foreach ($attributeGroups as $attributeGroup) {
            foreach ($attributeGroup->attrs as $attr) {
                $attributes[] = new AttributeDeclaration(
                    class: $attr->name->toString(),
                    arguments: $this->parseArguments($compiler, $attr->args),
                    snippet: $this->parseSnippet($context, $attr),
                );
            }
        }

        return $attributes;
    }

    /**
     * @param array<Arg> $nodes
     * @return ConstantExpression<array>
     */
    private function parseArguments(ConstantExpressionParser $compiler, array $nodes): ConstantExpression
    {
        $elements = [];

        foreach ($nodes as $node) {
            if ($node->name === null) {
                $elements[] = new AppendedArrayElement($compiler->parse($node->value));

                continue;
            }

            $elements[] = new KeyArrayElement(
                new Value($node->name->name),
                $compiler->parse($node->value),
            );
        }

        return new ArrayDeclaration($elements);
    }

    /**
     * @return ($node is null ? null : Type)
     */
    private function parseType(Context $context, null|Name|Identifier|ComplexType $node): ?Type
    {
        if ($node === null) {
            return null;
        }

        if ($node instanceof NullableType) {
            return types::nullable($this->parseType($context, $node->type));
        }

        if ($node instanceof UnionType) {
            return types::union(...array_map(
                fn(Node $child): Type => $this->parseType($context, $child),
                $node->types,
            ));
        }

        if ($node instanceof IntersectionType) {
            return types::intersection(...array_map(
                fn(Node $child): Type => $this->parseType($context, $child),
                $node->types,
            ));
        }

        if ($node instanceof Identifier) {
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
                'array' => types::array,
                'object' => types::object,
                'callable' => types::callable,
                'iterable' => types::iterable,
                'resource' => types::resource,
                'mixed' => types::mixed,
                default => throw new \LogicException(\sprintf('Native type "%s" is not supported', $node->name)),
            };
        }

        if ($node instanceof Name) {
            return $context->resolveNameAsType($node->toCodeString());
        }

        /** @psalm-suppress MixedArgument */
        throw new \LogicException(\sprintf('Type node of class %s is not supported', $node::class));
    }

    /**
     * @return ($node is null ? null : SourceCodeSnippet)
     */
    private function parseSnippet(Context $context, null|Node|Doc $node): ?SourceCodeSnippet
    {
        if ($node === null) {
            return null;
        }

        if (!$context->source instanceof SourceCode) {
            return null;
        }

        return $context->source->snippet($node->getStartFilePos(), $node->getEndFilePos() + 1);
    }

    private function isDefaultNull(?ConstantExpression $default): bool
    {
        return $default instanceof Value && $default->evaluate() === null;
    }

    private function parseVisibility(int $flags): ?Visibility
    {
        return match (true) {
            (bool) ($flags & Class_::MODIFIER_PUBLIC) => Visibility::Public,
            (bool) ($flags & Class_::MODIFIER_PROTECTED) => Visibility::Protected,
            (bool) ($flags & Class_::MODIFIER_PRIVATE) => Visibility::Private,
            default => null,
        };
    }
}
