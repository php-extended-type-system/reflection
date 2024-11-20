<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Metadata;

use Typhoon\Reflection\Declaration\FunctionDeclaration;

/**
 * @api
 */
interface FunctionMetadataParser
{
    public function parseFunctionMetadata(FunctionDeclaration $declaration, CustomTypeResolver $customTypeResolver): FunctionMetadata;
}
