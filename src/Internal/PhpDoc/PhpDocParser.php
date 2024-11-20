<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Internal\PhpDoc;

use PHPStan\PhpDocParser\Ast\Attribute;
use PHPStan\PhpDocParser\Ast\Node;
use PHPStan\PhpDocParser\Ast\PhpDoc\MethodTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PropertyTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TemplateTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TypeAliasImportTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TypeAliasTagValueNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\PhpDocParser as PHPStanPhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection\Internal\PhpDoc
 */
final class PhpDocParser
{
    private const PRIORITY = 'priority';
    private const START_POSITION = 'startPosition';
    private const END_POSITION = 'endPosition';

    public function __construct(
        private readonly PhpDocTagPrioritizer $tagPrioritizer = new PrefixBasedPhpDocTagPrioritizer(),
        private readonly Lexer $lexer = new Lexer(),
        private readonly PHPStanPhpDocParser $parser = new PHPStanPhpDocParser(
            typeParser: new TypeParser(new AlwaysTrimmingConstExprParser(unescapeStrings: true)),
            constantExprParser: new AlwaysTrimmingConstExprParser(unescapeStrings: true, usedAttributes: ['lines' => true]),
            requireWhitespaceBeforeDescription: true,
            usedAttributes: ['lines' => true, 'indexes' => true],
        ),
    ) {}

    public static function priority(PhpDocTagNode $tag): int
    {
        $priority = $tag->getAttribute(self::PRIORITY);

        return \is_int($priority) ? $priority : 0;
    }

    /**
     * @return positive-int
     */
    public static function startLine(Node $node): int
    {
        $startLine = $node->getAttribute(Attribute::START_LINE);
        \assert(\is_int($startLine) && $startLine > 0);

        return $startLine;
    }

    /**
     * @return positive-int
     */
    public static function endLine(Node $node): int
    {
        $endLine = $node->getAttribute(Attribute::END_LINE);
        \assert(\is_int($endLine) && $endLine > 0);

        return $endLine;
    }

    /**
     * @return non-negative-int
     */
    public static function startPosition(Node $node): int
    {
        $startPosition = $node->getAttribute(self::START_POSITION);
        \assert(\is_int($startPosition) && $startPosition >= 0);

        return $startPosition;
    }

    /**
     * @return non-negative-int
     */
    public static function endPosition(Node $node): int
    {
        $endPosition = $node->getAttribute(self::END_POSITION);
        \assert(\is_int($endPosition) && $endPosition >= 0);

        return $endPosition;
    }

    /**
     * @psalm-suppress UnusedVariable
     * @param positive-int $startLine
     * @param non-negative-int $startPosition
     */
    public function parse(string $phpDoc, int $startLine = 1, int $startPosition = 0): PhpDoc
    {
        $tokens = $this->lexer->tokenize($phpDoc);
        $startPositions = [];

        --$startLine;

        foreach ($tokens as [$value,, &$line]) {
            $line += $startLine;
            $startPositions[] = $startPosition;
            $startPosition += \strlen($value);
        }

        $startPositions[] = $startPosition;

        $phpDocNode = $this->parser->parse(new TokenIterator($tokens));

        foreach ($phpDocNode->getTags() as $tag) {
            $tag->setAttribute(self::PRIORITY, $this->tagPrioritizer->priorityFor($tag->name));
            $tagValue = $tag->value;

            if ($tagValue instanceof TemplateTagValueNode
             || $tagValue instanceof TypeAliasTagValueNode
             || $tagValue instanceof TypeAliasImportTagValueNode
             || $tagValue instanceof PropertyTagValueNode
             || $tagValue instanceof MethodTagValueNode
            ) {
                $this->setPositions($startPositions, $tag);

                if ($tagValue instanceof MethodTagValueNode) {
                    foreach ($tagValue->templateTypes as $template) {
                        $this->setPositions($startPositions, $template);
                    }

                    foreach ($tagValue->parameters as $parameter) {
                        $this->setPositions($startPositions, $parameter);
                    }
                }
            }
        }

        return new PhpDoc($phpDocNode);
    }

    /**
     * @param list<non-negative-int> $startPositions
     */
    private function setPositions(array $startPositions, Node $node): void
    {
        $startIndex = $node->getAttribute(Attribute::START_INDEX);
        $endIndex = $node->getAttribute(Attribute::END_INDEX);
        \assert(\is_int($startIndex) && \is_int($endIndex));
        $node->setAttribute(self::START_POSITION, $startPositions[$startIndex]);
        $node->setAttribute(self::END_POSITION, $startPositions[$endIndex + 1]);
    }
}
