<?php

declare(strict_types=1);

namespace ExtendedTypeSystem;

/**
 * @psalm-api
 * @psalm-immutable
 * @template T of object
 */
final class MethodTypeReflection
{
    /**
     * @param non-empty-string $name
     * @param array<non-empty-string, TemplateReflection> $templates
     * @param array<non-empty-string, Type> $parameterTypes
     */
    public function __construct(
        public readonly string $name,
        public readonly array $templates,
        private readonly array $parameterTypes,
        public readonly Type $returnType,
    ) {
    }

    public function parameterType(string $name): Type
    {
        return $this->parameterTypes[$name] ?? throw new \LogicException('todo');
    }
}
