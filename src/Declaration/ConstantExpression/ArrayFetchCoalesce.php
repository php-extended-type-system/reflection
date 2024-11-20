<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration\ConstantExpression;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 * @implements ConstantExpression<mixed>
 */
final class ArrayFetchCoalesce implements ConstantExpression
{
    public function __construct(
        private readonly ConstantExpression $array,
        private readonly ConstantExpression $key,
        private readonly ConstantExpression $default,
    ) {}

    public function rebuild(ConstantExpressionContext $context): ConstantExpression
    {
        return new self(
            array: $this->array->rebuild($context),
            key: $this->key->rebuild($context),
            default: $this->default->rebuild($context),
        );
    }

    public function evaluate(EvaluationContext $context): mixed
    {
        /** @psalm-suppress MixedArrayOffset */
        return $this->array->evaluate($context)[$this->key->evaluate($context)] ?? $this->default->evaluate($context);
    }
}
