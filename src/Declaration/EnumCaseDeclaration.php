<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration;

use Typhoon\DeclarationId\AnonymousClassId;
use Typhoon\DeclarationId\NamedClassId;
use Typhoon\Reflection\SourceCodeSnippet;

/**
 * @api
 */
final class EnumCaseDeclaration
{
    /**
     * @param Context<AnonymousClassId|NamedClassId> $context
     * @param non-empty-string $name
     * @param list<AttributeDeclaration> $attributes
     */
    public function __construct(
        public readonly Context $context,
        public readonly string $name,
        public readonly null|int|string $backingValue = null,
        public readonly ?SourceCodeSnippet $snippet = null,
        public readonly array $attributes = [],
        public readonly ?SourceCodeSnippet $phpDoc = null,
    ) {}
}
