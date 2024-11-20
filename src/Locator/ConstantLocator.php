<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Locator;

use Typhoon\DeclarationId\ConstantId;
use Typhoon\Reflection\File;

/**
 * @api
 */
interface ConstantLocator
{
    public function locateConstant(ConstantId $id): ?File;
}
