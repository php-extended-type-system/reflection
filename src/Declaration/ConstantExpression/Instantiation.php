<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration\ConstantExpression;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 * @implements ConstantExpression<object>
 */
final class Instantiation implements ConstantExpression
{
    /**
     * @param array<ConstantExpression> $arguments
     */
    public function __construct(
        private readonly ConstantExpression $class,
        private readonly array $arguments,
    ) {}

    public function rebuild(ConstantExpressionContext $context): ConstantExpression
    {
        return new self(
            class: $this->class->rebuild($context),
            arguments: array_map(
                static fn(ConstantExpression $expression): ConstantExpression => $expression->rebuild($context),
                $this->arguments,
            ),
        );
    }

    public function evaluate(EvaluationContext $context): mixed
    {
        /** @psalm-suppress MixedMethodCall */
        return new ($this->class->evaluate($context))(...array_map(
            static fn(ConstantExpression $expression): mixed => $expression->evaluate($context),
            $this->arguments,
        ));
    }
}
