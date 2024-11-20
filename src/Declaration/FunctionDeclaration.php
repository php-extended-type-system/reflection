<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration;

use Typhoon\DeclarationId\AnonymousFunctionId;
use Typhoon\DeclarationId\NamedFunctionId;
use Typhoon\Reflection\SourceCodeSnippet;
use Typhoon\Type\Type;

/**
 * @api
 */
final class FunctionDeclaration
{
    public readonly NamedFunctionId|AnonymousFunctionId $id;

    /**
     * @var ?non-empty-string
     */
    public readonly ?string $name;

    /**
     * @param Context<AnonymousFunctionId|NamedFunctionId> $context
     * @param list<AttributeDeclaration> $attributes
     * @param list<ParameterDeclaration<NamedFunctionId|AnonymousFunctionId>> $parameters
     */
    public function __construct(
        public readonly Context $context,
        public readonly bool $returnsReference = false,
        public readonly bool $generator = false,
        public readonly ?Type $returnType = null,
        public readonly ?Type $tentativeReturnType = null,
        public readonly array $parameters = [],
        public readonly ?SourceCodeSnippet $phpDoc = null,
        public readonly ?SourceCodeSnippet $snippet = null,
        public readonly bool $internallyDeprecated = false,
        public readonly array $attributes = [],
    ) {
        $this->id = $context->id;
        $this->name = $context->id instanceof NamedFunctionId ? $context->id->name : null;
    }
}
