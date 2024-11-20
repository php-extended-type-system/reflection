<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration;

use Typhoon\DeclarationId\AnonymousClassId;
use Typhoon\DeclarationId\NamedClassId;
use Typhoon\Reflection\Declaration\ConstantExpression\ConstantExpression;
use Typhoon\Reflection\SourceCodeSnippet;
use Typhoon\Type\Type;
use Typhoon\Type\types;

/**
 * @api
 */
final class PropertyDeclaration
{
    /**
     * @param Context<NamedClassId> $context
     */
    public static function enumNameProperty(Context $context): self
    {
        return new self(
            context: $context,
            name: 'name',
            visibility: Visibility::Public,
            readonly: true,
            type: types::string,
        );
    }

    /**
     * @param Context<NamedClassId> $context
     */
    public static function enumValueProperty(Context $context, Type $backingType = types::arrayKey): self
    {
        return new self(
            context: $context,
            name: 'value',
            visibility: Visibility::Public,
            readonly: true,
            type: $backingType,
        );
    }

    /**
     * @param Context<AnonymousClassId|NamedClassId> $context
     * @param non-empty-string $name
     * @param list<AttributeDeclaration> $attributes
     */
    public function __construct(
        public readonly Context $context,
        public readonly string $name,
        public readonly ?Visibility $visibility = null,
        public readonly bool $static = false,
        public readonly bool $readonly = false,
        public readonly ?Type $type = null,
        public readonly ?ConstantExpression $default = null,
        public readonly ?SourceCodeSnippet $phpDoc = null,
        public readonly ?SourceCodeSnippet $snippet = null,
        public readonly array $attributes = [],
    ) {}
}
