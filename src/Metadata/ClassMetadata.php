<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Metadata;

use Typhoon\Reflection\Deprecation;

/**
 * @api
 */
final class ClassMetadata
{
    /**
     * @param array<non-empty-string, ClassConstantMetadata> $constants
     * @param array<non-empty-string, PropertyMetadata> $properties
     * @param array<non-empty-string, MethodMetadata> $methods
     * @param array<non-empty-string, TemplateDeclaration> $templates
     */
    public function __construct(
        public readonly bool $readonly = false,
        public readonly bool $final = false,
        public readonly array $constants = [],
        public readonly array $properties = [],
        public readonly array $methods = [],
        public readonly ?Deprecation $deprecation = null,
        public readonly array $templates = [],
    ) {}

    public function with(self $class): self
    {
        $constants = $this->constants;

        foreach ($class->constants as $name => $constant) {
            $constants[$name] = isset($constants[$name]) ? $constants[$name]->with($constant) : $constant;
        }

        $properties = $this->properties;

        foreach ($class->properties as $name => $property) {
            $properties[$name] = isset($properties[$name]) ? $properties[$name]->with($property) : $property;
        }

        $properties = $this->properties;

        foreach ($class->properties as $name => $property) {
            $properties[$name] = isset($properties[$name]) ? $properties[$name]->with($property) : $property;
        }

        $methods = $this->methods;

        foreach ($class->methods as $name => $method) {
            $methods[$name] = isset($methods[$name]) ? $methods[$name]->with($method) : $method;
        }

        return new self(
            readonly: $class->readonly || $this->readonly,
            final: $class->final || $this->final,
            constants: $constants,
            properties: $properties,
            methods: $methods,
            deprecation: $class->deprecation ?? $this->deprecation,
            templates: $class->templates ?: $this->templates,
        );
    }
}
