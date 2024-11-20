<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration\ConstantExpression;

use Typhoon\DeclarationId\ConstantId;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 * @implements ConstantExpression<mixed>
 */
final class ConstantFetch implements ConstantExpression
{
    public function __construct(
        private readonly ConstantId $namespacedId,
        private readonly ?ConstantId $globalId = null,
    ) {}

    public function rebuild(ConstantExpressionContext $context): ConstantExpression
    {
        return $this;
    }

    /**
     * @throws \Throwable
     */
    public function evaluate(EvaluationContext $context): mixed
    {
        try {
            return $context->evaluateConstant($this->namespacedId);
        } catch (\Throwable $exception) {
            if ($this->globalId === null) {
                throw $exception;
            }
        }

        return $context->evaluateConstant($this->globalId);
    }
}
