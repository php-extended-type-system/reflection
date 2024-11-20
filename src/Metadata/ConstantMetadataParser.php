<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Metadata;

use Typhoon\Reflection\Declaration\ConstantDeclaration;

/**
 * @api
 */
interface ConstantMetadataParser
{
    public function parseConstantMetadata(ConstantDeclaration $declaration, CustomTypeResolver $customTypeResolver): ConstantMetadata;
}
