<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Metadata;

/**
 * @api
 */
final class TypeDeclarations
{
    /**
     * @param list<non-empty-string> $templateNames
     * @param list<non-empty-string> $aliasNames
     */
    public function __construct(
        public readonly array $templateNames = [],
        public readonly array $aliasNames = [],
    ) {}

    public function with(self $typeDeclarations): self
    {
        return new self(
            templateNames: $typeDeclarations->templateNames ?: $this->templateNames,
            aliasNames: $typeDeclarations->aliasNames ?: $this->aliasNames,
        );
    }
}
