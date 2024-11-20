<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration\ConstantExpression;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 * @template-covariant TValue
 * @implements ConstantExpression<TValue>
 */
final class Value implements ConstantExpression
{
    /**
     * @param TValue $value
     */
    public function __construct(
        private readonly mixed $value,
    ) {}

    public function rebuild(ConstantExpressionContext $context): ConstantExpression
    {
        return $this;
    }

    public function evaluate(EvaluationContext $context = new ThrowingEvaluationContext()): mixed
    {
        return $this->value;
    }
}
