<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Metadata;

use Typhoon\Reflection\Deprecation;
use Typhoon\Type\Type;

/**
 * @api
 */
final class FunctionMetadata
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
    ) {}

    public function with(self $function): self
    {
        $parameters = $this->parameters;

        foreach ($function->parameters as $name => $parameter) {
            $parameters[$name] = isset($parameters[$name]) ? $parameters[$name]->with($parameter) : $parameter;
        }

        return new self(
            returnType: $function->returnType ?? $this->returnType,
            throwsTypes: $function->throwsTypes ?: $this->throwsTypes,
            deprecation: $function->deprecation ?? $this->deprecation,
            parameters: $parameters,
            templates: $function->templates ?: $this->templates,
        );
    }
}
