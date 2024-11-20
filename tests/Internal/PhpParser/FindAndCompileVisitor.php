<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Internal\PhpParser;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeVisitorAbstract;
use Typhoon\Reflection\Declaration\ConstantExpression\ConstantExpression;

final class FindAndCompileVisitor extends NodeVisitorAbstract
{
    /**
     * @var array<ConstantExpression>
     */
    public array $expressions = [];

    /**
     * @param \Closure(Node): \Generator<array-key, Expr> $expressionFinder
     */
    public function __construct(
        private readonly ContextProvider $contextProvider,
        private readonly \Closure $expressionFinder,
    ) {}

    public function leaveNode(Node $node): ?int
    {
        $compiler = new ConstantExpressionParser($this->contextProvider->get());

        foreach (($this->expressionFinder)($node) as $key => $expr) {
            $this->expressions[$key] = $compiler->parse($expr);
        }

        return null;
    }
}
