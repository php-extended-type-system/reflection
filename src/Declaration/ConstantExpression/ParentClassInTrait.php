<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration\ConstantExpression;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 * @implements ConstantExpression<non-empty-string>
 */
enum ParentClassInTrait implements ConstantExpression
{
    case Instance;

    public function rebuild(ConstantExpressionContext $context): ConstantExpression
    {
        return $context->parent();
    }

    public function evaluate(EvaluationContext $context): mixed
    {
        throw new \LogicException('Parent in trait!');
    }
}
