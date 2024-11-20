<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration\ConstantExpression;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 * @implements ConstantExpression<mixed>
 */
final class UnaryOperation implements ConstantExpression
{
    /**
     * @param non-empty-string $operator
     */
    public function __construct(
        private readonly ConstantExpression $expression,
        private readonly string $operator,
    ) {}

    public function rebuild(ConstantExpressionContext $context): ConstantExpression
    {
        return new self(
            expression: $this->expression->rebuild($context),
            operator: $this->operator,
        );
    }

    public function evaluate(EvaluationContext $context): mixed
    {
        return match ($this->operator) {
            '+' => +$this->expression->evaluate($context),
            '-' => -$this->expression->evaluate($context),
            '!' => !$this->expression->evaluate($context),
            '~' => ~$this->expression->evaluate($context),
        };
    }
}
