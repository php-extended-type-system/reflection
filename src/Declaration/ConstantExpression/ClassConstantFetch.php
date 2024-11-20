<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration\ConstantExpression;

use Typhoon\DeclarationId\Id;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 * @implements ConstantExpression<mixed>
 */
final class ClassConstantFetch implements ConstantExpression
{
    public function __construct(
        private readonly ConstantExpression $class,
        private readonly ConstantExpression $name,
    ) {}

    public function rebuild(ConstantExpressionContext $context): ConstantExpression
    {
        return new self(
            class: $this->class->rebuild($context),
            name: $this->name->rebuild($context),
        );
    }

    public function evaluate(EvaluationContext $context): mixed
    {
        $class = $this->class->evaluate($context);
        \assert(\is_string($class) && $class !== '');

        $name = $this->name->evaluate($context);
        \assert(\is_string($name) && $name !== '');

        if ($name === 'class') {
            return $class;
        }

        return $context->evaluateClassConstant(Id::classConstant($class, $name));
    }
}
