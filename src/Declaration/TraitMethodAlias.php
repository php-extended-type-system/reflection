<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration;

/**
 * @api
 */
final class TraitMethodAlias
{
    /**
     * @param non-empty-string $trait
     * @param non-empty-string $method
     * @param ?non-empty-string $newName
     */
    public function __construct(
        public string $trait,
        public string $method,
        public ?string $newName = null,
        public ?Visibility $newVisibility = null,
    ) {}
}
