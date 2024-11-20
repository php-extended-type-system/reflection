<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Internal\PhpParser;

use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use Typhoon\Reflection\Declaration\ClassDeclaration;
use Typhoon\Reflection\Declaration\ConstantDeclaration;
use Typhoon\Reflection\Declaration\Context;
use Typhoon\Reflection\Declaration\FunctionDeclaration;
use Typhoon\Reflection\Metadata\TypesDiscoverer;
use Typhoon\Reflection\SourceCode;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 */
final class CodeParser
{
    public function __construct(
        private readonly Parser $phpParser,
        private readonly TypesDiscoverer $typesDiscoverer,
    ) {}

    /**
     * @return list<ConstantDeclaration|FunctionDeclaration|ClassDeclaration>
     */
    public function parseCode(SourceCode $code): array
    {
        $nodes = $this->phpParser->parse($code->toString()) ?? throw new \LogicException();

        /** @psalm-suppress MixedArgument, ArgumentTypeCoercion, UnusedPsalmSuppress */
        $linesFixer = new NodeLocationFixingVisitor(method_exists($this->phpParser, 'getTokens') ? $this->phpParser->getTokens() : $code->tokenize());
        $nameResolver = new NameResolver();
        $contextVisitor = new ContextVisitor(
            baseContext: Context::start($code),
            nameContext: $nameResolver->getNameContext(),
            typesDiscoverer: $this->typesDiscoverer,
        );
        $parsingVisitor = new ParsingVisitor($contextVisitor);

        $traverser = new NodeTraverser();

        if (!PhpParserChecker::isVisitorLeaveReversed()) {
            $traverser->addVisitor($parsingVisitor);
        }

        $traverser->addVisitor($linesFixer);
        $traverser->addVisitor(new GeneratorVisitor());
        $traverser->addVisitor($nameResolver);
        $traverser->addVisitor($contextVisitor);

        if (PhpParserChecker::isVisitorLeaveReversed()) {
            $traverser->addVisitor($parsingVisitor);
        }

        $traverser->traverse($nodes);

        return $parsingVisitor->data;
    }
}
