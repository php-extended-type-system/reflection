<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Metadata;

use Typhoon\Reflection\Declaration\Context;
use Typhoon\Type\Type;

/**
 * @api
 */
interface CustomTypeResolver
{
    /**
     * @param non-empty-string $unresolvedName
     * @param list<Type> $typeArguments
     */
    public function resolveCustomType(string $unresolvedName, array $typeArguments, Context $context): ?Type;
}
