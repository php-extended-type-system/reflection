<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Locator;

use Typhoon\DeclarationId\NamedClassId;
use Typhoon\Reflection\File;

/**
 * @api
 */
interface ClassLocator
{
    public function locateClass(NamedClassId $id): ?File;
}
