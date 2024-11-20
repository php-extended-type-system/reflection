<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration\ConstantExpression;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 * @implements ConstantExpression<mixed>
 */
final class BinaryOperation implements ConstantExpression
{
    public function __construct(
        private readonly ConstantExpression $left,
        private readonly ConstantExpression $right,
        private readonly string $operator,
    ) {}

    public function rebuild(ConstantExpressionContext $context): ConstantExpression
    {
        return new self(
            left: $this->left->rebuild($context),
            right: $this->right->rebuild($context),
            operator: $this->operator,
        );
    }

    public function evaluate(EvaluationContext $context): mixed
    {
        /** @psalm-suppress MixedOperand */
        return match ($this->operator) {
            '&' => $this->left->evaluate($context) & $this->right->evaluate($context),
            '|' => $this->left->evaluate($context) | $this->right->evaluate($context),
            '^' => $this->left->evaluate($context) ^ $this->right->evaluate($context),
            '&&' => $this->left->evaluate($context) && $this->right->evaluate($context),
            '||' => $this->left->evaluate($context) || $this->right->evaluate($context),
            '??' => $this->left->evaluate($context) ?? $this->right->evaluate($context),
            '.' => $this->left->evaluate($context) . $this->right->evaluate($context),
            '/' => $this->left->evaluate($context) / $this->right->evaluate($context),
            '==' => $this->left->evaluate($context) == $this->right->evaluate($context),
            '>' => $this->left->evaluate($context) > $this->right->evaluate($context),
            '>=' => $this->left->evaluate($context) >= $this->right->evaluate($context),
            '===' => $this->left->evaluate($context) === $this->right->evaluate($context),
            'and' => $this->left->evaluate($context) and $this->right->evaluate($context),
            'or' => $this->left->evaluate($context) or $this->right->evaluate($context),
            'xor' => $this->left->evaluate($context) xor $this->right->evaluate($context),
            '-' => $this->left->evaluate($context) - $this->right->evaluate($context),
            '%' => $this->left->evaluate($context) % $this->right->evaluate($context),
            '*' => $this->left->evaluate($context) * $this->right->evaluate($context),
            '!=' => $this->left->evaluate($context) != $this->right->evaluate($context),
            '!==' => $this->left->evaluate($context) !== $this->right->evaluate($context),
            '+' => $this->left->evaluate($context) + $this->right->evaluate($context),
            '**' => $this->left->evaluate($context) ** $this->right->evaluate($context),
            '<<' => $this->left->evaluate($context) << $this->right->evaluate($context),
            '>>' => $this->left->evaluate($context) >> $this->right->evaluate($context),
            '<' => $this->left->evaluate($context) < $this->right->evaluate($context),
            '<=' => $this->left->evaluate($context) <= $this->right->evaluate($context),
            '<=>' => $this->left->evaluate($context) <=> $this->right->evaluate($context),
        };
    }
}
