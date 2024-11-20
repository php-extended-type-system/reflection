<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Metadata;

use Typhoon\Reflection\SourceCodeSnippet;
use Typhoon\Type\Type;
use Typhoon\Type\types;
use Typhoon\Type\Variance;

/**
 * @api
 */
final class TemplateDeclaration
{
    public function __construct(
        public readonly Variance $variance = Variance::Invariant,
        public readonly Type $constraint = types::mixed,
        public readonly ?SourceCodeSnippet $snippet = null,
    ) {}
}
