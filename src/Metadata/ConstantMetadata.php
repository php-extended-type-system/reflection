<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Metadata;

use Typhoon\Reflection\Deprecation;
use Typhoon\Type\Type;

/**
 * @api
 */
final class ConstantMetadata
{
    public function __construct(
        public readonly ?Type $type = null,
        public readonly ?Deprecation $deprecation = null,
    ) {}

    public function with(self $constant): self
    {
        return new self(
            type: $constant->type ?? $this->type,
            deprecation: $constant->deprecation ?? $this->deprecation,
        );
    }
}
