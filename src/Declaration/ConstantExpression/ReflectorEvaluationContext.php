<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration\ConstantExpression;

use Typhoon\DeclarationId\ClassConstantId;
use Typhoon\DeclarationId\ConstantId;
use Typhoon\Reflection\TyphoonReflector;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 */
final class ReflectorEvaluationContext implements EvaluationContext
{
    public function __construct(
        private readonly TyphoonReflector $reflector,
    ) {}

    public function evaluateConstant(ConstantId $id): mixed
    {
        return $this->reflector->reflectConstant($id)->evaluate();
    }

    public function evaluateClassConstant(ClassConstantId $id): mixed
    {
        return $this->reflector->reflectClass($id->class)->constants()[$id->name]->evaluate();
    }
}
