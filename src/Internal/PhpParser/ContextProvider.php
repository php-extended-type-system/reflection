<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Internal\PhpParser;

use Typhoon\Reflection\Declaration\Context;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 */
interface ContextProvider
{
    public function get(): Context;
}
