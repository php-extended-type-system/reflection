<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Metadata;

use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\ClassLike;

/**
 * @api
 */
interface TypesDiscoverer
{
    public function discoverTypes(FunctionLike|ClassLike $node): TypeDeclarations;
}
