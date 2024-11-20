<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration\ConstantExpression;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 * @implements ConstantExpression<mixed>
 */
final class ArrayFetch implements ConstantExpression
{
    public function __construct(
        private readonly ConstantExpression $array,
        private readonly ConstantExpression $key,
    ) {}

    public function rebuild(ConstantExpressionContext $context): ConstantExpression
    {
        return new self(
            array: $this->array->rebuild($context),
            key: $this->key->rebuild($context),
        );
    }

    public function evaluate(EvaluationContext $context): mixed
    {
        /** @psalm-suppress MixedArrayAccess, MixedArrayOffset */
        return $this->array->evaluate($context)[$this->key->evaluate($context)];
    }
}
