<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Internal\Context;

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
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeVisitorAbstract;
use Typhoon\Reflection\Internal\Annotated\AnnotatedDeclarationsDiscoverer;
use Typhoon\Reflection\Internal\Annotated\NullAnnotatedDeclarationsDiscoverer;
use function Typhoon\Reflection\Internal\array_value_last;
use function Typhoon\Reflection\Internal\column;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 */
final class ContextVisitor extends NodeVisitorAbstract implements ContextProvider
{
    private const ATTRIBUTE = 'context';

    private readonly Context $fileContext;

    /**
     * @var list<Context>
     */
    private array $contextStack = [];

    /**
     * @param ?non-empty-string $file
     */
    public function __construct(
        ?string $file,
        private readonly string $code,
        private readonly NameContext $nameContext,
        private readonly AnnotatedDeclarationsDiscoverer $annotatedDeclarationsDiscoverer = NullAnnotatedDeclarationsDiscoverer::Instance,
    ) {
        $this->fileContext = Context::start($file);
    }

    public static function fromNode(FunctionLike|ClassLike $node): Context
    {
        $context = $node->getAttribute(self::ATTRIBUTE);

        return $context instanceof Context ? $context : throw new \LogicException();
    }

    public function current(): Context
    {
        return array_value_last($this->contextStack)
            ?? $this->fileContext->withNameContext($this->nameContext);
    }

    public function beforeTraverse(array $nodes): ?array
    {
        $this->contextStack = [];

        return null;
    }

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Function_) {
            \assert($node->namespacedName !== null);

            $context = $this->current()->enterFunction(
                name: $node->namespacedName->toString(),
                templateNames: $this->annotatedDeclarationsDiscoverer->discoverAnnotatedDeclarations($node)->templateNames,
            );
            $this->contextStack[] = $context;
            $node->setAttribute(self::ATTRIBUTE, $context);

            return null;
        }

        if ($node instanceof Closure || $node instanceof ArrowFunction) {
            $line = $node->getStartLine();
            \assert($line > 0);
            $offset = $node->getStartFilePos();
            \assert($offset >= 0);

            $context = $this->current()->enterAnonymousFunction(
                line: $line,
                column: column($this->code, $offset),
                templateNames: $this->annotatedDeclarationsDiscoverer->discoverAnnotatedDeclarations($node)->templateNames,
            );
            $this->contextStack[] = $context;
            $node->setAttribute(self::ATTRIBUTE, $context);

            return null;
        }

        if ($node instanceof Class_) {
            $typeNames = $this->annotatedDeclarationsDiscoverer->discoverAnnotatedDeclarations($node);

            if ($node->name === null) {
                $line = $node->getStartLine();
                \assert($line > 0);
                $offset = $node->getStartFilePos();
                \assert($offset >= 0);

                $context = $this->current()->enterAnonymousClass(
                    line: $line,
                    column: column($this->code, $offset),
                    parentName: $node->extends?->toString(),
                    aliasNames: $typeNames->aliasNames,
                    templateNames: $typeNames->templateNames,
                );
                $this->contextStack[] = $context;
                $node->setAttribute(self::ATTRIBUTE, $context);

                return null;
            }

            \assert($node->namespacedName !== null);

            $context = $this->current()->enterClass(
                name: $node->namespacedName->toString(),
                parentName: $node->extends?->toString(),
                aliasNames: $typeNames->aliasNames,
                templateNames: $typeNames->templateNames,
            );
            $this->contextStack[] = $context;
            $node->setAttribute(self::ATTRIBUTE, $context);

            return null;
        }

        if ($node instanceof Interface_) {
            \assert($node->namespacedName !== null);
            $typeNames = $this->annotatedDeclarationsDiscoverer->discoverAnnotatedDeclarations($node);

            $context = $this->current()->enterInterface(
                name: $node->namespacedName->toString(),
                aliasNames: $typeNames->aliasNames,
                templateNames: $typeNames->templateNames,
            );
            $this->contextStack[] = $context;
            $node->setAttribute(self::ATTRIBUTE, $context);

            return null;
        }

        if ($node instanceof Trait_) {
            \assert($node->namespacedName !== null);
            $typeNames = $this->annotatedDeclarationsDiscoverer->discoverAnnotatedDeclarations($node);

            $context = $this->current()->enterTrait(
                name: $node->namespacedName->toString(),
                aliasNames: $typeNames->aliasNames,
                templateNames: $typeNames->templateNames,
            );
            $this->contextStack[] = $context;
            $node->setAttribute(self::ATTRIBUTE, $context);

            return null;
        }

        if ($node instanceof Enum_) {
            \assert($node->namespacedName !== null);
            $typeNames = $this->annotatedDeclarationsDiscoverer->discoverAnnotatedDeclarations($node);

            $context = $this->current()->enterEnum(
                name: $node->namespacedName->toString(),
                aliasNames: $typeNames->aliasNames,
                templateNames: $typeNames->templateNames,
            );
            $this->contextStack[] = $context;
            $node->setAttribute(self::ATTRIBUTE, $context);

            return null;
        }

        if ($node instanceof ClassMethod) {
            $typeNames = $this->annotatedDeclarationsDiscoverer->discoverAnnotatedDeclarations($node);

            $context = $this->current()->enterMethod(
                name: $node->name->name,
                templateNames: $typeNames->templateNames,
            );
            $this->contextStack[] = $context;
            $node->setAttribute(self::ATTRIBUTE, $context);

            return null;
        }

        return null;
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof FunctionLike || $node instanceof ClassLike) {
            array_pop($this->contextStack);

            return null;
        }

        return null;
    }
}
