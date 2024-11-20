<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration;

use Typhoon\DeclarationId\AnonymousClassId;
use Typhoon\DeclarationId\NamedClassId;
use Typhoon\Reflection\SourceCodeSnippet;
use Typhoon\Type\Type;

/**
 * @api
 * @psalm-type MethodName = non-empty-string
 * @psalm-type TraitName = non-empty-string
 */
final class ClassDeclaration
{
    /**
     * @var AnonymousClassId<?class-string>|NamedClassId<class-string>
     */
    public readonly NamedClassId|AnonymousClassId $id;

    /**
     * @var ?class-string
     */
    public readonly ?string $name;

    /**
     * @param Context<AnonymousClassId|NamedClassId> $context
     * @param list<non-empty-string> $interfaces
     * @param list<non-empty-string> $traits
     * @param list<TraitMethodAlias> $traitMethodAliases
     * @param ?non-empty-string $parent
     * @param array<MethodName, TraitName> $traitMethodPrecedence
     * @param list<SourceCodeSnippet> $usePhpDocs
     * @param list<AttributeDeclaration> $attributes
     * @param list<PropertyDeclaration> $properties
     * @param list<MethodDeclaration> $methods
     * @param list<ClassConstantDeclaration|EnumCaseDeclaration> $constants
     * @param ?Type<int|string> $backingType
     */
    public function __construct(
        public readonly Context $context,
        public readonly ClassKind $kind,
        public readonly ?SourceCodeSnippet $phpDoc = null,
        public readonly ?SourceCodeSnippet $snippet = null,
        public readonly bool $readonly = false,
        public readonly bool $final = false,
        public readonly bool $abstract = false,
        public readonly ?string $parent = null,
        public readonly array $interfaces = [],
        public readonly array $traits = [],
        public readonly array $traitMethodAliases = [],
        public readonly array $traitMethodPrecedence = [],
        public readonly array $usePhpDocs = [],
        public readonly ?Type $backingType = null,
        public readonly array $attributes = [],
        public readonly array $properties = [],
        public readonly array $constants = [],
        public readonly array $methods = [],
        public readonly bool $internallyNonCloneable = false,
    ) {
        /** @var AnonymousClassId<?class-string>|NamedClassId<class-string> */
        $this->id = $context->id;
        $this->name = $this->id->name;
    }
}
