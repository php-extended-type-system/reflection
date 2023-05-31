<?php

declare(strict_types=1);

namespace ExtendedTypeSystem\TypeParser;

use ExtendedTypeSystem\Type;
use ExtendedTypeSystem\types;
use PhpParser\NameContext;
use PhpParser\Node\Name;

/**
 * @internal
 * @psalm-internal ExtendedTypeSystem
 */
final class ClassLikeScope implements Scope
{
    /**
     * @var array<non-empty-string, true>
     */
    private readonly array $templateNamesMap;

    /**
     * @param class-string $name
     * @param ?class-string $parent
     * @param list<non-empty-string> $templateNames
     */
    public function __construct(
        private readonly NameContext $nameContext,
        private readonly string $name,
        private readonly ?string $parent,
        private readonly bool $final,
        array $templateNames,
    ) {
        $this->templateNamesMap = array_fill_keys($templateNames, true);
    }

    public function self(): string
    {
        return $this->name;
    }

    public function parent(): ?string
    {
        return $this->parent;
    }

    public function isSelfFinal(): bool
    {
        return $this->final;
    }

    public function resolveClassName(Name $name): string
    {
        return TypeParser::nameToClass($this->nameContext->getResolvedClassName($name));
    }

    public function tryResolveTemplateType(string $name): ?Type
    {
        if (isset($this->templateNamesMap[$name])) {
            return types::classTemplate($name, $this->name);
        }

        return null;
    }
}
