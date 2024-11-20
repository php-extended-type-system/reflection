<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration\ConstantExpression;

use Typhoon\DeclarationId\ClassConstantId;
use Typhoon\DeclarationId\ConstantId;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 */
interface EvaluationContext
{
    public function evaluateConstant(ConstantId $id): mixed;

    public function evaluateClassConstant(ClassConstantId $id): mixed;
}
