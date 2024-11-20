<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Metadata;

use Typhoon\Reflection\Declaration\ClassDeclaration;

/**
 * @api
 */
interface ClassMetadataParser
{
    public function parseClassMetadata(ClassDeclaration $declaration, CustomTypeResolver $customTypeResolver): ClassMetadata;
}
