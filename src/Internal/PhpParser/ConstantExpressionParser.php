<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Internal\PhpParser;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\VariadicPlaceholder;
use Typhoon\DeclarationId\Id;
use Typhoon\Reflection\Declaration\ConstantExpression\AppendedArrayElement;
use Typhoon\Reflection\Declaration\ConstantExpression\ArrayDeclaration;
use Typhoon\Reflection\Declaration\ConstantExpression\ArrayFetch;
use Typhoon\Reflection\Declaration\ConstantExpression\ArrayFetchCoalesce;
use Typhoon\Reflection\Declaration\ConstantExpression\BinaryOperation;
use Typhoon\Reflection\Declaration\ConstantExpression\ClassConstantFetch;
use Typhoon\Reflection\Declaration\ConstantExpression\ConstantExpression;
use Typhoon\Reflection\Declaration\ConstantExpression\ConstantExpressionContext;
use Typhoon\Reflection\Declaration\ConstantExpression\ConstantFetch;
use Typhoon\Reflection\Declaration\ConstantExpression\Instantiation;
use Typhoon\Reflection\Declaration\ConstantExpression\KeyArrayElement;
use Typhoon\Reflection\Declaration\ConstantExpression\Ternary;
use Typhoon\Reflection\Declaration\ConstantExpression\UnaryOperation;
use Typhoon\Reflection\Declaration\ConstantExpression\UnpackedArrayElement;
use Typhoon\Reflection\Declaration\ConstantExpression\Value;
use Typhoon\Reflection\Declaration\Context;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 */
final class ConstantExpressionParser
{
    private readonly ConstantExpressionContext $context;

    public function __construct(Context $context)
    {
        $this->context = new ConstantExpressionContext($context);
    }

    /**
     * @return ($expr is null ? null : ConstantExpression)
     */
    public function parse(?Expr $expr): ?ConstantExpression
    {
        return match (true) {
            $expr === null => null,
            $expr instanceof Scalar\String_,
            $expr instanceof Scalar\LNumber,
            $expr instanceof Scalar\DNumber => new Value($expr->value),
            $expr instanceof Expr\Array_ => $this->parseArray($expr),
            $expr instanceof Scalar\MagicConst\Line => new Value($expr->getStartLine()),
            $expr instanceof Scalar\MagicConst\File => $this->context->__FILE__(),
            $expr instanceof Scalar\MagicConst\Dir => $this->context->__DIR__(),
            $expr instanceof Scalar\MagicConst\Namespace_ => $this->context->__NAMESPACE__(),
            $expr instanceof Scalar\MagicConst\Function_ => $this->context->__FUNCTION__(),
            $expr instanceof Scalar\MagicConst\Class_ => $this->context->__CLASS__(),
            $expr instanceof Scalar\MagicConst\Trait_ => $this->context->__TRAIT__(),
            $expr instanceof Scalar\MagicConst\Method => $this->context->__METHOD__(),
            $expr instanceof Coalesce && $expr->left instanceof Expr\ArrayDimFetch => new ArrayFetchCoalesce(
                array: $this->parse($expr->left->var),
                key: $this->parse($expr->left->dim ?? throw new \LogicException('Unexpected array append operation in a constant expression')),
                default: $this->parse($expr->right),
            ),
            $expr instanceof Expr\BinaryOp => new BinaryOperation(
                left: $this->parse($expr->left),
                right: $this->parse($expr->right),
                operator: $expr->getOperatorSigil(),
            ),
            $expr instanceof Expr\UnaryPlus => new UnaryOperation($this->parse($expr->expr), '+'),
            $expr instanceof Expr\UnaryMinus => new UnaryOperation($this->parse($expr->expr), '-'),
            $expr instanceof Expr\BooleanNot => new UnaryOperation($this->parse($expr->expr), '!'),
            $expr instanceof Expr\BitwiseNot => new UnaryOperation($this->parse($expr->expr), '~'),
            $expr instanceof Expr\ConstFetch => $this->parseConstant($expr->name),
            $expr instanceof Expr\ArrayDimFetch && $expr->dim !== null => new ArrayFetch(
                array: $this->parse($expr->var),
                key: $this->parse($expr->dim),
            ),
            $expr instanceof Expr\ClassConstFetch => new ClassConstantFetch(
                class: $this->compileClassName($expr->class),
                name: $expr->name instanceof Identifier ? new Value($expr->name->name) : $this->parse($expr->name),
            ),
            $expr instanceof Expr\New_ => new Instantiation(
                class: $this->compileClassName($expr->class),
                arguments: $this->parseArguments($expr->args),
            ),
            $expr instanceof Expr\Ternary => new Ternary(
                if: $this->parse($expr->cond),
                then: $this->parse($expr->if),
                else: $this->parse($expr->else),
            ),
            default => throw new \LogicException(\sprintf('Unsupported expression %s', $expr::class)),
        };
    }

    private function parseConstant(Name $name): ConstantExpression
    {
        $lowerStringName = $name->toLowerString();

        if ($lowerStringName === 'null') {
            return new Value(null);
        }

        if ($lowerStringName === 'true') {
            return new Value(true);
        }

        if ($lowerStringName === 'false') {
            return new Value(false);
        }

        $namespacedName = $name->getAttribute('namespacedName');

        if ($namespacedName instanceof FullyQualified) {
            return new ConstantFetch(
                namespacedId: Id::constant($namespacedName->toString()),
                globalId: Id::constant($name->toString()),
            );
        }

        return new ConstantFetch(Id::constant($name->toString()));
    }

    /**
     * @return ConstantExpression<array>
     */
    private function parseArray(Expr\Array_ $expr): ConstantExpression
    {
        /** @var list<Expr\ArrayItem> */
        $items = $expr->items;

        return new ArrayDeclaration(array_map(
            fn(Expr\ArrayItem $item): AppendedArrayElement|KeyArrayElement|UnpackedArrayElement => match (true) {
                $item->unpack => new UnpackedArrayElement($this->parse($item->value)),
                $item->key === null => new AppendedArrayElement($this->parse($item->value)),
                default => new KeyArrayElement($this->parse($item->key), $this->parse($item->value)),
            },
            $items,
        ));
    }

    private function compileClassName(Name|Expr|Class_ $name): ConstantExpression
    {
        if ($name instanceof Expr) {
            return $this->parse($name);
        }

        if ($name instanceof Name) {
            if ($name->isSpecialClassName()) {
                return match ($name->toLowerString()) {
                    'self' => $this->context->self(),
                    'parent' => $this->context->parent(),
                    'static' => $this->context->static(),
                };
            }

            return new Value($name->toString());
        }

        throw new \LogicException('Unexpected anonymous class in a constant expression');
    }

    /**
     * @param array<Arg|VariadicPlaceholder> $arguments
     * @return array<ConstantExpression>
     */
    private function parseArguments(array $arguments): array
    {
        $parsed = [];

        foreach ($arguments as $argument) {
            if ($argument instanceof VariadicPlaceholder) {
                throw new \LogicException('Unexpected variadic placeholder (...) in a constant expression');
            }

            if ($argument->name === null) {
                $parsed[] = $this->parse($argument->value);

                continue;
            }

            $parsed[$argument->name->name] = $this->parse($argument->value);
        }

        return $parsed;
    }
}
