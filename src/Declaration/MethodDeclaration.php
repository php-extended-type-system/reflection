<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration;

use Typhoon\DeclarationId\MethodId;
use Typhoon\DeclarationId\NamedClassId;
use Typhoon\Reflection\SourceCodeSnippet;
use Typhoon\Type\Type;
use Typhoon\Type\types;

/**
 * @api
 */
final class MethodDeclaration
{
    /**
     * @param Context<NamedClassId> $context
     */
    public static function enumCases(Context $context): self
    {
        return new self(
            context: $context->enterMethodDeclaration('cases'),
            static: true,
            returnType: types::array,
            visibility: Visibility::Public,
        );
    }

    /**
     * @param Context<NamedClassId> $context
     */
    public static function enumFrom(Context $context): self
    {
        $context = $context->enterMethodDeclaration('from');

        return new self(
            context: $context,
            static: true,
            returnType: types::static(resolvedClass: $context->self),
            visibility: Visibility::Public,
            parameters: [
                new ParameterDeclaration(
                    context: $context,
                    name: 'value',
                    type: types::arrayKey,
                ),
            ],
        );
    }

    /**
     * @param Context<NamedClassId> $context
     */
    public static function enumTryFrom(Context $context): self
    {
        $context = $context->enterMethodDeclaration('tryFrom');

        return new self(
            context: $context,
            static: true,
            returnType: types::nullable(types::static(resolvedClass: $context->self)),
            visibility: Visibility::Public,
            parameters: [
                new ParameterDeclaration(
                    context: $context,
                    name: 'value',
                    type: types::arrayKey,
                ),
            ],
        );
    }

    public readonly MethodId $id;

    /**
     * @var non-empty-string
     */
    public readonly string $name;

    /**
     * @param Context<MethodId> $context
     * @param list<AttributeDeclaration> $attributes
     * @param list<ParameterDeclaration<MethodId>> $parameters
     */
    public function __construct(
        public readonly Context $context,
        public readonly ?SourceCodeSnippet $phpDoc = null,
        public readonly ?SourceCodeSnippet $snippet = null,
        public readonly array $attributes = [],
        public readonly bool $static = false,
        public readonly bool $returnsReference = false,
        public readonly bool $generator = false,
        public readonly bool $final = false,
        public readonly bool $abstract = false,
        public readonly ?Type $returnType = null,
        public readonly ?Type $tentativeReturnType = null,
        public readonly ?Visibility $visibility = null,
        public readonly array $parameters = [],
        public readonly bool $internallyDeprecated = false,
    ) {
        $this->id = $context->id;
        $this->name = $context->id->name;
    }
}
