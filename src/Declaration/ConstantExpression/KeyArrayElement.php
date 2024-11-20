<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration\ConstantExpression;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 */
final class KeyArrayElement
{
    public function __construct(
        public readonly ConstantExpression $key,
        public readonly ConstantExpression $value,
    ) {}

    public function rebuild(ConstantExpressionContext $context): self
    {
        return new self($this->key->rebuild($context), $this->value->rebuild($context));
    }
}
