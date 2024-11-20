<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration\ConstantExpression;

use Typhoon\DeclarationId\NamedClassId;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 * @implements ConstantExpression<non-empty-string>
 */
final class SelfInTrait implements ConstantExpression
{
    public function __construct(
        private readonly NamedClassId $traitId,
    ) {}

    public function rebuild(ConstantExpressionContext $context): ConstantExpression
    {
        return $context->self();
    }

    public function evaluate(EvaluationContext $context): mixed
    {
        return $this->traitId->name;
    }
}
