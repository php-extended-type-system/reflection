<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration\ConstantExpression;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 * @implements ConstantExpression<mixed>
 */
final class Ternary implements ConstantExpression
{
    public function __construct(
        private readonly ConstantExpression $if,
        private readonly ?ConstantExpression $then,
        private readonly ConstantExpression $else,
    ) {}

    public function rebuild(ConstantExpressionContext $context): ConstantExpression
    {
        return new self(
            if: $this->if->rebuild($context),
            then: $this->then?->rebuild($context),
            else: $this->else,
        );
    }

    public function evaluate(EvaluationContext $context): mixed
    {
        if ($this->then === null) {
            return ($this->if->evaluate($context)) ?: $this->else->evaluate($context);
        }

        return $this->if->evaluate($context)
            ? $this->then->evaluate($context)
            : $this->else->evaluate($context);
    }
}
