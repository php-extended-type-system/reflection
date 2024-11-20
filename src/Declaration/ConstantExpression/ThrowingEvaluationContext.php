<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration\ConstantExpression;

use Typhoon\DeclarationId\ClassConstantId;
use Typhoon\DeclarationId\ConstantId;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 */
final class ThrowingEvaluationContext implements EvaluationContext
{
    public function evaluateConstant(ConstantId $id): mixed
    {
        throw new \RuntimeException(\sprintf('%s cannot be evaluated without evaluation context', $id->describe()));
    }

    public function evaluateClassConstant(ClassConstantId $id): mixed
    {
        throw new \RuntimeException(\sprintf('%s cannot be evaluated without evaluation context', $id->describe()));
    }
}
