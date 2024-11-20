<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Internal\Metadata;

use Typhoon\Reflection\Declaration\ClassDeclaration;
use Typhoon\Reflection\Declaration\ConstantDeclaration;
use Typhoon\Reflection\Declaration\FunctionDeclaration;
use Typhoon\Reflection\Metadata\ClassMetadata;
use Typhoon\Reflection\Metadata\ConstantMetadata;
use Typhoon\Reflection\Metadata\FunctionMetadata;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 */
interface MetadataLoader
{
    public function loadConstantMetadata(ConstantDeclaration $declaration): ConstantMetadata;

    public function loadFunctionMetadata(FunctionDeclaration $declaration): FunctionMetadata;

    public function loadClassMetadata(ClassDeclaration $declaration): ClassMetadata;
}
