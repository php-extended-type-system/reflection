<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration;

use Typhoon\Reflection\Declaration\ConstantExpression\ConstantExpression;
use Typhoon\Reflection\SourceCodeSnippet;

/**
 * @api
 */
final class AttributeDeclaration
{
    /**
     * @param non-empty-string $class
     * @param ConstantExpression<array> $arguments
     */
    public function __construct(
        public readonly string $class,
        public readonly ConstantExpression $arguments,
        public readonly ?SourceCodeSnippet $snippet = null,
    ) {}
}
