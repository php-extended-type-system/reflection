<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Internal\PhpParser;

use PhpParser\Node;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeVisitorAbstract;
use function Typhoon\Reflection\Internal\array_value_last;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 */
final class NodeLocationFixingVisitor extends NodeVisitorAbstract
{
    /**
     * @param list<\PhpToken> $tokens
     */
    public function __construct(
        private readonly array $tokens,
    ) {}

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Function_
         || $node instanceof ArrowFunction
         || $node instanceof Closure
         || $node instanceof Param
         || $node instanceof ClassLike
         || $node instanceof ClassConst
         || $node instanceof EnumCase
         || $node instanceof Property
         || $node instanceof ClassMethod
        ) {
            $this->fixNode($node, $node->attrGroups);

            return null;
        }

        return null;
    }

    /**
     * @param array<AttributeGroup> $attrGroups
     */
    private function fixNode(Node $node, array $attrGroups): void
    {
        if ($attrGroups === []) {
            return;
        }

        $lastAttrPosition = array_value_last($attrGroups)->getEndFilePos() + 1;

        foreach ($this->tokens as $index => $token) {
            if ($token->pos >= $lastAttrPosition && !$token->isIgnorable()) {
                $node->setAttribute('startLine', $token->line);
                $node->setAttribute('startFilePos', $token->pos);
                $node->setAttribute('startTokenPos', $index);

                return;
            }
        }

        throw new \LogicException();
    }
}
