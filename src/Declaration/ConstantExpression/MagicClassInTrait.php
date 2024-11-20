<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration\ConstantExpression;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 * @implements ConstantExpression<string>
 */
final class MagicClassInTrait implements ConstantExpression
{
    public function __construct(
        private readonly string $trait,
    ) {}

    public function rebuild(ConstantExpressionContext $context): ConstantExpression
    {
        return $context->__CLASS__();
    }

    public function evaluate(EvaluationContext $context): mixed
    {
        return $this->trait;
    }
}
