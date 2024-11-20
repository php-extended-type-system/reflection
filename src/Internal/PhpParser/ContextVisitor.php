<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Internal\PhpParser;

use PhpParser\NameContext;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeVisitorAbstract;
use Typhoon\Reflection\Declaration\Context;
use Typhoon\Reflection\Metadata\TypesDiscoverer;
use Typhoon\Reflection\Metadata\TypesDiscoverers;
use function Typhoon\Reflection\Internal\array_value_last;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 */
final class ContextVisitor extends NodeVisitorAbstract implements ContextProvider
{
    private ?Context $namesAwareContext = null;

    /**
     * @var list<Context>
     */
    private array $symbolContextStack = [];

    public function __construct(
        private readonly Context $baseContext,
        private readonly NameContext $nameContext,
        private readonly TypesDiscoverer $typesDiscoverer = new TypesDiscoverers(),
    ) {}

    public function get(): Context
    {
        $this->namesAwareContext ??= $this->baseContext->withNames($this->nameContext);

        return array_value_last($this->symbolContextStack) ?? $this->namesAwareContext;
    }

    public function beforeTraverse(array $nodes): ?array
    {
        $this->symbolContextStack = [];

        return null;
    }

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Namespace_ || $node instanceof Use_ || $node instanceof GroupUse) {
            $this->namesAwareContext = null;

            return null;
        }

        if ($node instanceof Function_) {
            $this->symbolContextStack[] = $this->get()->enterFunctionDeclaration(
                shortName: $node->name->toString(),
                templateNames: $this->typesDiscoverer->discoverTypes($node)->templateNames,
            );

            return null;
        }

        if ($node instanceof Closure || $node instanceof ArrowFunction) {
            $position = $node->getStartFilePos();
            \assert($position >= 0);

            $this->symbolContextStack[] = $this->get()->enterAnonymousFunctionDeclaration(
                position: $position,
                templateNames: $this->typesDiscoverer->discoverTypes($node)->templateNames,
            );

            return null;
        }

        if ($node instanceof Class_) {
            $typeNames = $this->typesDiscoverer->discoverTypes($node);

            if ($node->name === null) {
                $position = $node->getStartFilePos();
                \assert($position >= 0);

                $this->symbolContextStack[] = $this->get()->enterAnonymousClassDeclaration(
                    position: $position,
                    unresolvedParentName: $node->extends?->toCodeString(),
                    aliasNames: $typeNames->aliasNames,
                    templateNames: $typeNames->templateNames,
                );

                return null;
            }

            $this->symbolContextStack[] = $this->get()->enterClassDeclaration(
                shortName: $node->name->toString(),
                unresolvedParentName: $node->extends?->toCodeString(),
                aliasNames: $typeNames->aliasNames,
                templateNames: $typeNames->templateNames,
            );

            return null;
        }

        if ($node instanceof Interface_) {
            \assert($node->name !== null);

            $typeNames = $this->typesDiscoverer->discoverTypes($node);

            $this->symbolContextStack[] = $this->get()->enterInterfaceDeclaration(
                shortName: $node->name->toString(),
                aliasNames: $typeNames->aliasNames,
                templateNames: $typeNames->templateNames,
            );

            return null;
        }

        if ($node instanceof Trait_) {
            \assert($node->name !== null);

            $typeNames = $this->typesDiscoverer->discoverTypes($node);

            $this->symbolContextStack[] = $this->get()->enterTrait(
                shortName: $node->name->toString(),
                aliasNames: $typeNames->aliasNames,
                templateNames: $typeNames->templateNames,
            );

            return null;
        }

        if ($node instanceof Enum_) {
            \assert($node->name !== null);

            $typeNames = $this->typesDiscoverer->discoverTypes($node);

            $this->symbolContextStack[] = $this->get()->enterEnumDeclaration(
                shortName: $node->name->toString(),
                aliasNames: $typeNames->aliasNames,
                templateNames: $typeNames->templateNames,
            );

            return null;
        }

        if ($node instanceof ClassMethod) {
            $typeNames = $this->typesDiscoverer->discoverTypes($node);

            $this->symbolContextStack[] = $this->get()->enterMethodDeclaration(
                name: $node->name->name,
                templateNames: $typeNames->templateNames,
            );

            return null;
        }

        return null;
    }

    public function leaveNode(Node $node): null|int|Node|array
    {
        if ($node instanceof FunctionLike || $node instanceof ClassLike) {
            array_pop($this->symbolContextStack);

            return null;
        }

        return null;
    }
}
