<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Metadata;

use Typhoon\Reflection\Deprecation;
use Typhoon\Type\Type;

/**
 * @api
 */
final class PropertyMetadata
{
    public function __construct(
        public readonly ?Type $type = null,
        public readonly bool $readonly = false,
        public readonly ?Deprecation $deprecation = null,
    ) {}

    public function with(self $property): self
    {
        return new self(
            type: $property->type ?? $this->type,
            readonly: $property->readonly || $this->readonly,
            deprecation: $property->deprecation ?? $this->deprecation,
        );
    }
}
