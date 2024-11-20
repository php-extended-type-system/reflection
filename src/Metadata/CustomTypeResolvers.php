<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Metadata;

use Typhoon\Reflection\Declaration\Context;
use Typhoon\Type\Type;

/**
 * @api
 */
final class CustomTypeResolvers implements CustomTypeResolver
{
    /**
     * @param iterable<CustomTypeResolver> $resolvers
     */
    public function __construct(
        private readonly iterable $resolvers,
    ) {}

    public function resolveCustomType(string $unresolvedName, array $typeArguments, Context $context): ?Type
    {
        foreach ($this->resolvers as $resolver) {
            $type = $resolver->resolveCustomType($unresolvedName, $typeArguments, $context);

            if ($type !== null) {
                return $type;
            }
        }

        return null;
    }
}
