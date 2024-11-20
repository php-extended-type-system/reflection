<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Locator;

use Composer\Autoload\ClassLoader;
use Typhoon\DeclarationId\NamedClassId;
use Typhoon\Reflection\File;

/**
 * @api
 */
final class ComposerLocator implements ClassLocator
{
    public function locateClass(NamedClassId $id): ?File
    {
        if (!class_exists(ClassLoader::class)) {
            return null;
        }

        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            $file = $loader->findFile($id->name);

            if ($file !== false) {
                return new File($file);
            }
        }

        return null;
    }
}
