<?php

declare(strict_types=1);

namespace Typhoon\Reflection;

use PHPUnit\Framework\TestCase;
use Typhoon\DeclarationId\Id;

return static function (TyphoonReflector $reflector, TestCase $test): void {
    $file = File::fromContents('<?php new class {}; new class {};');
    $reflector = $reflector->withFile($file);

    $test->expectExceptionObject(new \RuntimeException(
        "Cannot reflect anonymous class at {$file->path}:1, because 2 anonymous classes are declared at columns 11, 25. " .
        'Use TyphoonReflector::reflectAnonymousClass() with a $column argument to reflect the exact class you need',
    ));

    $reflector->reflectClass(Id::anonymousClass($file->path, 1));
};
