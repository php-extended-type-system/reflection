<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Metadata;

use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\ClassLike;

/**
 * @api
 */
final class TypesDiscoverers implements TypesDiscoverer
{
    /**
     * @param iterable<TypesDiscoverer> $discoverers
     */
    public function __construct(
        private readonly iterable $discoverers = [],
    ) {}

    public function discoverTypes(FunctionLike|ClassLike $node): TypeDeclarations
    {
        $declarations = new TypeDeclarations();

        foreach ($this->discoverers as $discoverer) {
            $declarations = $declarations->with($discoverer->discoverTypes($node));
        }

        return $declarations;
    }
}
