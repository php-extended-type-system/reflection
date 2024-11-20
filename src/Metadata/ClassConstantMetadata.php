<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Metadata;

use Typhoon\Reflection\Deprecation;
use Typhoon\Type\Type;

/**
 * @api
 */
final class ClassConstantMetadata
{
    public function __construct(
        public readonly ?Type $type = null,
        public readonly ?Deprecation $deprecation = null,
        public readonly bool $final = false,
    ) {}

    public function with(self $constant): self
    {
        return new self(
            type: $constant->type ?? $this->type,
            deprecation: $constant->deprecation ?? $this->deprecation,
            final: $constant->final || $this->final,
        );
    }
}
