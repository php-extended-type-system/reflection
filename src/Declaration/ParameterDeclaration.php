<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration;

use Typhoon\DeclarationId\AnonymousFunctionId;
use Typhoon\DeclarationId\MethodId;
use Typhoon\DeclarationId\NamedFunctionId;
use Typhoon\Reflection\Declaration\ConstantExpression\ConstantExpression;
use Typhoon\Reflection\SourceCodeSnippet;
use Typhoon\Type\Type;

/**
 * @api
 * @template-covariant TContextId of AnonymousFunctionId|NamedFunctionId|MethodId
 */
final class ParameterDeclaration
{
    /**
     * @param Context<TContextId> $context
     * @param non-empty-string $name
     * @param list<AttributeDeclaration> $attributes
     */
    public function __construct(
        public readonly Context $context,
        public readonly string $name,
        public readonly ?Type $type = null,
        public readonly ?ConstantExpression $default = null,
        public readonly bool $variadic = false,
        public readonly PassedBy $passedBy = PassedBy::Value,
        public readonly ?Visibility $visibility = null,
        public readonly bool $readonly = false,
        public readonly array $attributes = [],
        public readonly ?SourceCodeSnippet $phpDoc = null,
        public readonly ?SourceCodeSnippet $snippet = null,
        public readonly bool $internallyOptional = false,
    ) {}

    public function isPromoted(): bool
    {
        return $this->readonly || $this->visibility !== null;
    }
}
