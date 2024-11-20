<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration;

use Typhoon\DeclarationId\AnonymousClassId;
use Typhoon\DeclarationId\NamedClassId;
use Typhoon\Reflection\Declaration\ConstantExpression\ConstantExpression;
use Typhoon\Reflection\SourceCodeSnippet;
use Typhoon\Type\Type;

/**
 * @api
 */
final class ClassConstantDeclaration
{
    /**
     * @param Context<AnonymousClassId|NamedClassId> $context
     * @param non-empty-string $name
     * @param list<AttributeDeclaration> $attributes
     */
    public function __construct(
        public readonly Context $context,
        public readonly string $name,
        public readonly ConstantExpression $value,
        public readonly ?SourceCodeSnippet $snippet = null,
        public readonly ?SourceCodeSnippet $phpDoc = null,
        public readonly bool $final = false,
        public readonly ?Type $type = null,
        public readonly ?Visibility $visibility = null,
        public readonly array $attributes = [],
    ) {}
}
