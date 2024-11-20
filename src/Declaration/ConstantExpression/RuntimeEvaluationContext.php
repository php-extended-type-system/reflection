<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration\ConstantExpression;

use Typhoon\DeclarationId\ClassConstantId;
use Typhoon\DeclarationId\ConstantId;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 */
final class RuntimeEvaluationContext implements EvaluationContext
{
    public function evaluateConstant(ConstantId $id): mixed
    {
        return \constant($id->name);
    }

    public function evaluateClassConstant(ClassConstantId $id): mixed
    {
        return \constant(\sprintf(
            '%s::%s',
            $id->class->name ?? throw new \LogicException(),
            $id->name,
        ));
    }
}
