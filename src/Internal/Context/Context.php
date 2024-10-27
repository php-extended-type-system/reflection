<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Internal\Context;

use PhpParser\ErrorHandler\Throwing;
use PhpParser\NameContext;
use PhpParser\Node\Stmt\Use_;
use Typhoon\DeclarationId\AliasId;
use Typhoon\DeclarationId\AnonymousClassId;
use Typhoon\DeclarationId\AnonymousFunctionId;
use Typhoon\DeclarationId\Id;
use Typhoon\DeclarationId\MethodId;
use Typhoon\DeclarationId\NamedClassId;
use Typhoon\DeclarationId\NamedFunctionId;
use Typhoon\DeclarationId\TemplateId;
use Typhoon\Reflection\Annotated\TypeContext;
use Typhoon\Reflection\Internal\PhpParser\NameParser;
use Typhoon\Type\Type;
use Typhoon\Type\types;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 */
final class Context implements TypeContext
{
    /**
     * @param ?non-empty-string $file
     * @param array<non-empty-string, AliasId> $aliases
     * @param array<non-empty-string, TemplateId> $templates
     */
    private function __construct(
        public readonly string $code,
        public readonly ?string $file,
        private NameContext $nameContext,
        public readonly null|NamedFunctionId|AnonymousFunctionId|NamedClassId|AnonymousClassId|MethodId $currentId = null,
        public readonly null|NamedClassId|AnonymousClassId $self = null,
        public readonly ?NamedClassId $trait = null,
        public readonly ?NamedClassId $parent = null,
        private readonly array $aliases = [],
        private readonly array $templates = [],
    ) {}

    /**
     * @param ?non-empty-string $file
     */
    public static function start(string $code, ?string $file = null, ?NameContext $nameContext = null): self
    {
        if ($nameContext === null) {
            $nameContext = new NameContext(new Throwing());
            $nameContext->startNamespace();
        } else {
            $nameContext = clone $nameContext;
        }

        return new self($code, $file, $nameContext);
    }

    /**
     * @param non-empty-string $name
     * @param list<non-empty-string> $templateNames
     */
    public function enterFunction(string $name, array $templateNames = []): self
    {
        $id = Id::namedFunction($name);

        return new self(
            code: $this->code,
            file: $this->file,
            nameContext: $this->nameContext,
            currentId: $id,
            aliases: $this->aliases,
            templates: self::templatesFromNames($id, $templateNames),
        );
    }

    /**
     * @param positive-int $line
     * @param positive-int $column
     * @param list<non-empty-string> $templateNames
     */
    public function enterAnonymousFunction(int $line, int $column, array $templateNames = []): self
    {
        \assert($this->file !== null);
        $id = Id::anonymousFunction($this->file, $line, $column);

        return new self(
            code: $this->code,
            file: $this->file,
            nameContext: $this->nameContext,
            currentId: $id,
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
     * @param non-empty-string $name
     * @param ?non-empty-string $parentName
     * @param list<non-empty-string> $aliasNames
     * @param list<non-empty-string> $templateNames
     */
    public function enterClass(
        string $name,
        ?string $parentName = null,
        array $aliasNames = [],
        array $templateNames = [],
    ): self {
        $id = Id::namedClass($name);

        return new self(
            code: $this->code,
            file: $this->file,
            nameContext: $this->nameContext,
            currentId: $id,
            self: $id,
            parent: $parentName === null ? null : Id::namedClass($parentName),
            aliases: [
                ...$this->aliases,
                ...self::aliasesFromNames($id, $aliasNames),
            ],
            templates: self::templatesFromNames($id, $templateNames),
        );
    }

    /**
     * @param positive-int $line
     * @param positive-int $column
     * @param ?non-empty-string $parentName
     * @param list<non-empty-string> $aliasNames
     * @param list<non-empty-string> $templateNames
     */
    public function enterAnonymousClass(
        int $line,
        int $column,
        ?string $parentName = null,
        array $aliasNames = [],
        array $templateNames = [],
    ): self {
        \assert($this->file !== null);
        $id = Id::anonymousClass($this->file, $line, $column);

        return new self(
            code: $this->code,
            file: $this->file,
            nameContext: $this->nameContext,
            currentId: $id,
            self: $id,
            parent: $parentName === null ? null : Id::namedClass($parentName),
            aliases: [
                ...$this->aliases,
                ...self::aliasesFromNames($id, $aliasNames),
            ],
            templates: self::templatesFromNames($id, $templateNames),
        );
    }

    /**
     * @param non-empty-string $name
     * @param list<non-empty-string> $aliasNames
     * @param list<non-empty-string> $templateNames
     */
    public function enterInterface(string $name, array $aliasNames = [], array $templateNames = []): self
    {
        $id = Id::namedClass($name);

        return new self(
            code: $this->code,
            file: $this->file,
            nameContext: $this->nameContext,
            currentId: $id,
            self: $id,
            aliases: [
                ...$this->aliases,
                ...self::aliasesFromNames($id, $aliasNames),
            ],
            templates: self::templatesFromNames($id, $templateNames),
        );
    }

    /**
     * @param non-empty-string $name
     * @param list<non-empty-string> $aliasNames
     * @param list<non-empty-string> $templateNames
     */
    public function enterEnum(string $name, array $aliasNames = [], array $templateNames = []): self
    {
        $id = Id::namedClass($name);

        return new self(
            code: $this->code,
            file: $this->file,
            nameContext: $this->nameContext,
            currentId: $id,
            self: $id,
            aliases: [
                ...$this->aliases,
                ...self::aliasesFromNames($id, $aliasNames),
            ],
            templates: self::templatesFromNames($id, $templateNames),
        );
    }

    /**
     * @param non-empty-string $name
     * @param list<non-empty-string> $aliasNames
     * @param list<non-empty-string> $templateNames
     */
    public function enterTrait(string $name, array $aliasNames = [], array $templateNames = []): self
    {
        $id = Id::namedClass($name);

        return new self(
            code: $this->code,
            file: $this->file,
            nameContext: $this->nameContext,
            currentId: $id,
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
     */
    public function enterMethod(string $name, array $templateNames): self
    {
        \assert($this->currentId instanceof NamedClassId || $this->currentId instanceof AnonymousClassId);
        $id = Id::method($this->currentId, $name);

        return new self(
            code: $this->code,
            file: $this->file,
            nameContext: $this->nameContext,
            currentId: $id,
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
     * @param non-negative-int $position
     * @return positive-int
     */
    public function column(int $position): int
    {
        if ($position === 0) {
            return 1;
        }

        $lineStartPosition = strrpos($this->code, "\n", $position - \strlen($this->code) - 1);

        if ($lineStartPosition === false) {
            return $position + 1;
        }

        $column = $position - $lineStartPosition;
        \assert($column > 0);

        return $column;
    }

    public function directory(): ?string
    {
        if ($this->file === null) {
            return null;
        }

        return \dirname($this->file);
    }

    public function namespace(): string
    {
        return $this->nameContext->getNamespace()?->toString() ?? '';
    }

    public function resolveConstantName(string $unresolvedName): array
    {
        $resolved = $this->nameContext->getResolvedName(NameParser::parse($unresolvedName), Use_::TYPE_CONSTANT);

        if ($resolved !== null) {
            return [$resolved->toString(), null];
        }

        return [$this->namespace() . '\\' . $unresolvedName, $unresolvedName];
    }

    public function resolveClassName(string $unresolvedName): string
    {
        return $this->nameContext->getResolvedClassName(NameParser::parse($unresolvedName))->toString();
    }

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
}
