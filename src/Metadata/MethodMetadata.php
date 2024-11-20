<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Metadata;

use Typhoon\Reflection\Deprecation;
use Typhoon\Type\Type;

/**
 * @api
 */
final class MethodMetadata
{
    /**
     * @param list<Type> $throwsTypes
     * @param array<non-empty-string, ParameterMetadata> $parameters
     * @param array<non-empty-string, TemplateDeclaration> $templates
     */
    public function __construct(
        public readonly ?Type $returnType = null,
        public readonly array $throwsTypes = [],
        public readonly ?Deprecation $deprecation = null,
        public readonly array $parameters = [],
        public readonly array $templates = [],
        public readonly bool $final = false,
    ) {}

    public function with(self $method): self
    {
        $parameters = $this->parameters;

        foreach ($method->parameters as $name => $parameter) {
            $parameters[$name] = isset($parameters[$name]) ? $parameters[$name]->with($parameter) : $parameter;
        }

        return new self(
            returnType: $method->returnType ?? $this->returnType,
            throwsTypes: $method->throwsTypes ?: $this->throwsTypes,
            deprecation: $method->deprecation ?? $this->deprecation,
            parameters: $parameters,
            templates: $method->templates ?: $this->templates,
            final: $method->final || $this->final,
        );
    }
}
