<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration\ConstantExpression;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 * @implements ConstantExpression<array>
 */
final class ArrayDeclaration implements ConstantExpression
{
    /**
     * @param list<AppendedArrayElement|KeyArrayElement|UnpackedArrayElement> $elements
     */
    public function __construct(
        private readonly array $elements,
    ) {}

    public function rebuild(ConstantExpressionContext $context): ConstantExpression
    {
        return new self(array_map(
            static fn(AppendedArrayElement|KeyArrayElement|UnpackedArrayElement $element): AppendedArrayElement|KeyArrayElement|UnpackedArrayElement => $element->rebuild($context),
            $this->elements,
        ));
    }

    public function evaluate(EvaluationContext $context): mixed
    {
        $array = [];

        foreach ($this->elements as $element) {
            $value = $element->value->evaluate($context);

            if ($element instanceof UnpackedArrayElement) {
                /** @psalm-suppress InvalidOperand */
                $array = [...$array, ...$value];

                continue;
            }

            if ($element instanceof AppendedArrayElement) {
                $array[] = $value;

                continue;
            }

            /** @psalm-suppress MixedArrayOffset */
            $array[$element->key->evaluate($context)] = $value;
        }

        return $array;
    }
}
