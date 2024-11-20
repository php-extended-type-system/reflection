<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Internal\PhpDoc;

use PHPStan\PhpDocParser\Ast\Attribute;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprArrayItemNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprArrayNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprFalseNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprFloatNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprIntegerNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprNullNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprStringNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprTrueNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstFetchNode;
use Typhoon\Reflection\Declaration\ConstantExpression\AppendedArrayElement;
use Typhoon\Reflection\Declaration\ConstantExpression\ArrayDeclaration;
use Typhoon\Reflection\Declaration\ConstantExpression\ClassConstantFetch;
use Typhoon\Reflection\Declaration\ConstantExpression\ConstantExpression;
use Typhoon\Reflection\Declaration\ConstantExpression\ConstantExpressionContext;
use Typhoon\Reflection\Declaration\ConstantExpression\ConstantFetch;
use Typhoon\Reflection\Declaration\ConstantExpression\KeyArrayElement;
use Typhoon\Reflection\Declaration\ConstantExpression\Value;
use Typhoon\Reflection\Declaration\Context;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 */
final class PhpDocConstantExpressionCompiler
{
    private readonly ConstantExpressionContext $constantExpressionContext;

    public function __construct(
        private readonly Context $context,
    ) {
        $this->constantExpressionContext = new ConstantExpressionContext($context);
    }

    /**
     * @return ($expr is null ? null : ConstantExpression)
     */
    public function compile(?ConstExprNode $expr): ?ConstantExpression
    {
        return match (true) {
            $expr === null => null,
            $expr instanceof ConstExprNullNode => new Value(null),
            $expr instanceof ConstExprTrueNode => new Value(true),
            $expr instanceof ConstExprFalseNode => new Value(false),
            $expr instanceof ConstExprIntegerNode => new Value((int) $expr->value),
            $expr instanceof ConstExprFloatNode => new Value((float) $expr->value),
            $expr instanceof ConstExprStringNode => new Value($expr->value),
            $expr instanceof ConstExprArrayNode => $this->compileArray($expr),
            $expr instanceof ConstFetchNode => $this->compileConstFetch($expr),
            default => throw new \LogicException(\sprintf('Unsupported expression %s', $expr::class)),
        };
    }

    /**
     * @return ConstantExpression<array>
     */
    private function compileArray(ConstExprArrayNode $expr): ConstantExpression
    {
        return new ArrayDeclaration(
            array_map(
                fn(ConstExprArrayItemNode $item): AppendedArrayElement|KeyArrayElement => $item->key === null
                    ? new AppendedArrayElement($this->compile($item->value))
                    : new KeyArrayElement($this->compile($item->key), $this->compile($item->value)),
                $expr->items,
            ),
        );
    }

    private function compileConstFetch(ConstFetchNode $expr): ConstantExpression
    {
        if ($expr->className !== '') {
            return new ClassConstantFetch(
                class: $this->compileClassName($expr->className),
                name: new Value($expr->name),
            );
        }

        return match ($expr->name) {
            '__LINE__' => self::compileNodeLine($expr),
            '__FILE__' => $this->constantExpressionContext->__FILE__(),
            '__DIR__' => $this->constantExpressionContext->__DIR__(),
            '__NAMESPACE__' => $this->constantExpressionContext->__NAMESPACE__(),
            '__FUNCTION__' => $this->constantExpressionContext->__FUNCTION__(),
            '__CLASS__' => $this->constantExpressionContext->__CLASS__(),
            '__TRAIT__' => $this->constantExpressionContext->__TRAIT__(),
            '__METHOD__' => $this->constantExpressionContext->__METHOD__(),
            default => new ConstantFetch(...$this->context->resolveConstantName($expr->name)),
        };
    }

    /**
     * @param non-empty-string $name
     */
    private function compileClassName(string $name): ConstantExpression
    {
        return match (strtolower($name)) {
            'self' => $this->constantExpressionContext->self(),
            'parent' => $this->constantExpressionContext->parent(),
            'static' => $this->constantExpressionContext->static(),
            default => new Value($this->context->resolveClassName($name)->name),
        };
    }

    /**
     * @return ConstantExpression<positive-int>
     */
    private static function compileNodeLine(ConstExprNode $expr): ConstantExpression
    {
        $line = $expr->getAttribute(Attribute::START_LINE);
        \assert(\is_int($line) && $line > 0);

        return new Value($line);
    }
}
