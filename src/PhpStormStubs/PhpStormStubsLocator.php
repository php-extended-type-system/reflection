<?php

declare(strict_types=1);

namespace Typhoon\Reflection\PhpStormStubs;

use JetBrains\PHPStormStub\PhpStormStubsMap;
use Typhoon\DeclarationId\NamedClassId;
use Typhoon\DeclarationId\NamedFunctionId;
use Typhoon\Reflection\File;
use Typhoon\Reflection\Locator\ClassLocator;
use Typhoon\Reflection\Locator\FunctionLocator;

/**
 * @todo move to package
 * @api
 */
final class PhpStormStubsLocator implements FunctionLocator, ClassLocator
{
    public function locateFunction(NamedFunctionId $id): ?File
    {
        if (!isset(PhpStormStubsMap::FUNCTIONS[$id->name])) {
            return null;
        }

        return new File(PhpStormStubsMap::DIR . '/' . PhpStormStubsMap::FUNCTIONS[$id->name]);
    }

    public function locateClass(NamedClassId $id): ?File
    {
        if (!isset(PhpStormStubsMap::CLASSES[$id->name])) {
            return null;
        }

        return new File(PhpStormStubsMap::DIR . '/' . PhpStormStubsMap::CLASSES[$id->name]);
    }
}
