<?php

declare(strict_types=1);

namespace ExtendedTypeSystem;

/**
 * @api
 * @psalm-immutable
 */
final class MethodDeclaration
{
    /**
     * @param non-empty-string $name
     * @param array<non-empty-string, TemplateDeclaration> $templates
     * @param array<non-empty-string, TypeDeclaration> $parameterTypes
     */
    public function __construct(
        public readonly string $name,
        public readonly bool $private,
        public readonly array $templates,
        public readonly array $parameterTypes,
        public readonly TypeDeclaration $returnType,
    ) {
    }
}
