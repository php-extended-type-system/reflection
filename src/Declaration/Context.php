<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration;

use PhpParser\ErrorHandler\Throwing;
use PhpParser\NameContext;
use PhpParser\Node\Stmt\Use_;
use Typhoon\DeclarationId\AliasId;
use Typhoon\DeclarationId\AnonymousClassId;
use Typhoon\DeclarationId\AnonymousFunctionId;
use Typhoon\DeclarationId\ConstantId;
use Typhoon\DeclarationId\Id;
use Typhoon\DeclarationId\MethodId;
use Typhoon\DeclarationId\NamedClassId;
use Typhoon\DeclarationId\NamedFunctionId;
use Typhoon\DeclarationId\TemplateId;
use Typhoon\Reflection\Extension;
use Typhoon\Reflection\Internal\PhpParser\NameParser;
use Typhoon\Reflection\SourceCode;
use Typhoon\Type\Type;
use Typhoon\Type\types;

/**
 * @api
 * @template-covariant TId of null|NamedFunctionId|AnonymousFunctionId|NamedClassId|AnonymousClassId|MethodId
 */
final class Context
{
    /**
     * @return self<null>
     */
    public static function start(SourceCode|Extension $source): self
    {
        /** @var self<null> */
        return new self($source, self::createNameContext());
    }

    /**
     * @param TId $id
     * @param array<non-empty-string, AliasId> $aliases
     * @param array<non-empty-string, TemplateId> $templates
     */
    private function __construct(
        public readonly SourceCode|Extension $source,
        private NameContext $nameContext,
        public readonly null|NamedFunctionId|AnonymousFunctionId|NamedClassId|AnonymousClassId|MethodId $id = null,
        public readonly null|NamedClassId|AnonymousClassId $self = null,
        public readonly ?NamedClassId $trait = null,
        public readonly ?NamedClassId $parent = null,
        private readonly array $aliases = [],
        private readonly array $templates = [],
    ) {}

    /**
     * @return self<TId>
     */
    public function withNames(NameContext $nameContext): self
    {
        $context = clone $this;
        $context->nameContext = clone $nameContext;

        return $context;
    }

    /**
     * @param non-empty-string $shortName
     * @param list<non-empty-string> $templateNames
     * @return self<NamedFunctionId>
     */
    public function enterFunctionDeclaration(string $shortName, array $templateNames = []): self
    {
        $id = Id::namedFunction($this->resolveDeclarationName($shortName));

        return new self(
            source: $this->source,
            nameContext: $this->nameContext,
            id: $id,
            aliases: $this->aliases,
            templates: self::templatesFromNames($id, $templateNames),
        );
    }

    /**
     * @param non-negative-int $position
     * @param list<non-empty-string> $templateNames
     * @return self<AnonymousFunctionId>
     */
    public function enterAnonymousFunctionDeclaration(int $position, array $templateNames = []): self
    {
        if (!$this->source instanceof SourceCode) {
            throw new \LogicException();
        }

        $id = Id::anonymousFunction(
            file: $this->source->file->path,
            line: $this->source->lineAt($position),
            column: $this->source->columnAt($position),
        );

        return new self(
            source: $this->source,
            nameContext: $this->nameContext,
            id: $id,
            self: $this->self,
            trait: $this->trait,
            parent: $this->parent,
            aliases: $this->aliases,
            templates: [
                ...$this->templates,
                ...self::templatesFromNames($id, $templateNames),
            ],
        );
    }

    /**
     * @param non-empty-string $shortName
     * @param ?non-empty-string $unresolvedParentName
     * @param list<non-empty-string> $aliasNames
     * @param list<non-empty-string> $templateNames
     * @return self<NamedClassId>
     */
    public function enterClassDeclaration(
        string $shortName,
        ?string $unresolvedParentName = null,
        array $aliasNames = [],
        array $templateNames = [],
    ): self {
        $id = Id::namedClass($this->resolveDeclarationName($shortName));

        return new self(
            source: $this->source,
            nameContext: $this->nameContext,
            id: $id,
            self: $id,
            parent: $unresolvedParentName === null ? null : $this->resolveClassName($unresolvedParentName),
            aliases: [
                ...$this->aliases,
                ...self::aliasesFromNames($id, $aliasNames),
            ],
            templates: self::templatesFromNames($id, $templateNames),
        );
    }

    /**
     * @param non-negative-int $position
     * @param ?non-empty-string $unresolvedParentName
     * @param list<non-empty-string> $aliasNames
     * @param list<non-empty-string> $templateNames
     * @return self<AnonymousClassId<null>>
     */
    public function enterAnonymousClassDeclaration(
        int $position,
        ?string $unresolvedParentName = null,
        array $aliasNames = [],
        array $templateNames = [],
    ): self {
        if (!$this->source instanceof SourceCode) {
            throw new \LogicException();
        }

        $id = Id::anonymousClass(
            file: $this->source->file->path,
            line: $this->source->lineAt($position),
            column: $this->source->columnAt($position),
        );

        return new self(
            source: $this->source,
            nameContext: $this->nameContext,
            id: $id,
            self: $id,
            parent: $unresolvedParentName === null ? null : $this->resolveClassName($unresolvedParentName),
            aliases: [
                ...$this->aliases,
                ...self::aliasesFromNames($id, $aliasNames),
            ],
            templates: self::templatesFromNames($id, $templateNames),
        );
    }

    /**
     * @param non-empty-string $shortName
     * @param list<non-empty-string> $aliasNames
     * @param list<non-empty-string> $templateNames
     * @return self<NamedClassId>
     */
    public function enterInterfaceDeclaration(string $shortName, array $aliasNames = [], array $templateNames = []): self
    {
        $id = Id::namedClass($this->resolveDeclarationName($shortName));

        return new self(
            source: $this->source,
            nameContext: $this->nameContext,
            id: $id,
            self: $id,
            aliases: [
                ...$this->aliases,
                ...self::aliasesFromNames($id, $aliasNames),
            ],
            templates: self::templatesFromNames($id, $templateNames),
        );
    }

    /**
     * @param non-empty-string $shortName
     * @param list<non-empty-string> $aliasNames
     * @param list<non-empty-string> $templateNames
     * @return self<NamedClassId>
     */
    public function enterEnumDeclaration(string $shortName, array $aliasNames = [], array $templateNames = []): self
    {
        $id = Id::namedClass($this->resolveDeclarationName($shortName));

        return new self(
            source: $this->source,
            nameContext: $this->nameContext,
            id: $id,
            self: $id,
            aliases: [
                ...$this->aliases,
                ...self::aliasesFromNames($id, $aliasNames),
            ],
            templates: self::templatesFromNames($id, $templateNames),
        );
    }

    /**
     * @param non-empty-string $shortName
     * @param list<non-empty-string> $aliasNames
     * @param list<non-empty-string> $templateNames
     * @return self<NamedClassId>
     */
    public function enterTrait(string $shortName, array $aliasNames = [], array $templateNames = []): self
    {
        $id = Id::namedClass($this->resolveDeclarationName($shortName));

        return new self(
            source: $this->source,
            nameContext: $this->nameContext,
            id: $id,
            trait: $id,
            aliases: [
                ...$this->aliases,
                ...self::aliasesFromNames($id, $aliasNames),
            ],
            templates: self::templatesFromNames($id, $templateNames),
        );
    }

    /**
     * @param non-empty-string $name
     * @param list<non-empty-string> $templateNames
     * @return self<MethodId>
     */
    public function enterMethodDeclaration(string $name, array $templateNames = []): self
    {
        \assert($this->id instanceof NamedClassId || $this->id instanceof AnonymousClassId);
        $id = Id::method($this->id, $name);

        return new self(
            source: $this->source,
            nameContext: $this->nameContext,
            id: $id,
            self: $this->self,
            trait: $this->trait,
            parent: $this->parent,
            aliases: $this->aliases,
            templates: [
                ...$this->templates,
                ...self::templatesFromNames($id, $templateNames),
            ],
        );
    }

    public function namespace(): string
    {
        return $this->nameContext->getNamespace()?->toString() ?? '';
    }

    /**
     * @param non-empty-string $shortName
     * @return non-empty-string
     */
    private function resolveDeclarationName(string $shortName): string
    {
        $namespace = $this->nameContext->getNamespace();

        if ($namespace === null) {
            return $shortName;
        }

        return $namespace->toString() . '\\' . $shortName;
    }

    /**
     * @param non-empty-string $unresolvedName
     * @return array{ConstantId, ?ConstantId}
     */
    public function resolveConstantName(string $unresolvedName): array
    {
        $resolved = $this->nameContext->getResolvedName(NameParser::parse($unresolvedName), Use_::TYPE_CONSTANT);

        if ($resolved !== null) {
            return [Id::constant($resolved->toString()), null];
        }

        return [Id::constant($this->namespace() . '\\' . $unresolvedName), Id::constant($unresolvedName)];
    }

    /**
     * @param non-empty-string $unresolvedName
     */
    public function resolveClassName(string $unresolvedName): NamedClassId
    {
        return Id::namedClass($this->nameContext->getResolvedClassName(NameParser::parse($unresolvedName))->toString());
    }

    /**
     * @param non-empty-string $unresolvedName
     * @param list<Type> $arguments
     */
    public function resolveNameAsType(string $unresolvedName, array $arguments = []): Type
    {
        if (str_contains($unresolvedName, '\\')) {
            return types::object($this->resolveClassName($unresolvedName), $arguments);
        }

        $type = match (strtolower($unresolvedName)) {
            'self' => types::self($arguments, $this->self),
            'parent' => types::parent($arguments, $this->parent),
            'static' => types::static($arguments, $this->self),
            default => null,
        };

        if ($type !== null) {
            return $type;
        }

        if (isset($this->aliases[$unresolvedName])) {
            return types::alias($this->aliases[$unresolvedName], $arguments);
        }

        if (isset($this->templates[$unresolvedName])) {
            if ($arguments !== []) {
                throw new \LogicException('Template type arguments are not supported');
            }

            return types::template($this->templates[$unresolvedName]);
        }

        return types::object($this->resolveClassName($unresolvedName), $arguments);
    }

    /**
     * @param list<non-empty-string> $names
     * @return array<non-empty-string, AliasId>
     */
    private static function aliasesFromNames(NamedClassId|AnonymousClassId $class, array $names): array
    {
        return array_combine($names, array_map(
            static fn(string $templateName): AliasId => Id::alias($class, $templateName),
            $names,
        ));
    }

    /**
     * @param list<non-empty-string> $names
     * @return array<non-empty-string, TemplateId>
     */
    private static function templatesFromNames(
        NamedFunctionId|AnonymousFunctionId|NamedClassId|AnonymousClassId|MethodId $declaration,
        array $names,
    ): array {
        return array_combine($names, array_map(
            static fn(string $templateName): TemplateId => Id::template($declaration, $templateName),
            $names,
        ));
    }

    private static function createNameContext(): NameContext
    {
        $nameContext = new NameContext(new Throwing());
        $nameContext->startNamespace();

        return $nameContext;
    }
}
