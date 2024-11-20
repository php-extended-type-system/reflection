<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Locator;

use Typhoon\DeclarationId\NamedFunctionId;
use Typhoon\Reflection\File;

/**
 * @api
 */
interface FunctionLocator
{
    public function locateFunction(NamedFunctionId $id): ?File;
}
