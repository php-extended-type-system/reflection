<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration\ConstantExpression;

/**
 * @internal
 * @psalm-internal Typhoon
 * @template-covariant T
 */
interface ConstantExpression
{
    /**
     * @return ConstantExpression<T>
     */
    public function rebuild(ConstantExpressionContext $context): self;

    /**
     * @return T
     */
    public function evaluate(EvaluationContext $context): mixed;
}
