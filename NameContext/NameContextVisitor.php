<?php

declare(strict_types=1);

namespace Typhoon\Reflection\NameContext;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 */
final class NameContextVisitor extends NodeVisitorAbstract
{
    public function __construct(
        private readonly NameContext $nameContext,
    ) {}

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Stmt\Namespace_) {
            $this->nameContext->enterNamespace($node->name?->toCodeString());

            return null;
        }

        if ($node instanceof Stmt\Use_) {
            foreach ($node->uses as $use) {
                $this->addUse(
                    type: $node->type,
                    name: $use->name,
                    alias: $use->alias,
                );
            }

            return null;
        }

        if ($node instanceof Stmt\GroupUse) {
            foreach ($node->uses as $use) {
                $this->addUse(
                    type: $node->type | $use->type,
                    name: Name::concat($node->prefix, $use->name),
                    alias: $use->alias,
                );
            }

            return null;
        }

        if ($node instanceof Stmt\ClassLike) {
            if ($node->name === null) {
                return null;
            }

            $this->nameContext->enterClass(
                unresolvedName: $node->name->name,
                unresolvedParentName: $node instanceof Stmt\Class_ ? $node->extends?->toCodeString() : null,
                trait: $node instanceof Stmt\Trait_,
                final: $node instanceof Stmt\Class_ && $node->isFinal(),
            );

            return null;
        }

        return null;
    }

    public function leaveNode(Node $node): ?int
    {
        if ($node instanceof Stmt\Namespace_) {
            $this->nameContext->leaveNamespace();

            return null;
        }

        if ($node instanceof Stmt\ClassLike) {
            if ($node->name === null) {
                return null;
            }

            $this->nameContext->leaveClass();

            return null;
        }

        return null;
    }

    private function addUse(int $type, Name $name, ?Node\Identifier $alias): void
    {
        if ($type === Stmt\Use_::TYPE_NORMAL) {
            $this->nameContext->addUse($name->toCodeString(), $alias?->name);

            return;
        }

        if ($type === Stmt\Use_::TYPE_CONSTANT) {
            $this->nameContext->addConstantUse($name->toCodeString(), $alias?->name);

            return;
        }

        if ($type === Stmt\Use_::TYPE_FUNCTION) {
            $this->nameContext->addFunctionUse($name->toCodeString(), $alias?->name);

            return;
        }
    }
}
