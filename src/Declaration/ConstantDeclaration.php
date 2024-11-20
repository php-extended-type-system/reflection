<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration;

use Typhoon\DeclarationId\ConstantId;
use Typhoon\DeclarationId\Id;
use Typhoon\Reflection\Declaration\ConstantExpression\ConstantExpression;
use Typhoon\Reflection\SourceCodeSnippet;

/**
 * @api
 */
final class ConstantDeclaration
{
    public readonly ConstantId $id;

    /**
     * @param Context<null> $context
     * @param non-empty-string $name
     */
    public function __construct(
        public readonly Context $context,
        public readonly string $name,
        public readonly ConstantExpression $value,
        public readonly ?SourceCodeSnippet $snippet = null,
        public readonly ?SourceCodeSnippet $phpDoc = null,
    ) {
        $this->id = Id::constant($name);
    }
}
