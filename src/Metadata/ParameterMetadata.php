<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Metadata;

use Typhoon\Reflection\Deprecation;
use Typhoon\Type\Type;

/**
 * @api
 */
final class ParameterMetadata
{
    public function __construct(
        public readonly ?Type $type = null,
        public readonly ?Deprecation $deprecation = null,
        public readonly bool $readonly = false,
    ) {}

    public function with(self $parameter): self
    {
        return new self(
            type: $parameter->type ?? $this->type,
            deprecation: $parameter->deprecation ?? $this->deprecation,
            readonly: $parameter->readonly || $this->readonly,
        );
    }
}
