<?php

declare(strict_types=1);

namespace Typhoon\Reflection;

use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertTrue;

return static function (TyphoonReflector $reflector): void {
    $parameters = $reflector
        ->withFile(File::fromContents(
            <<<'PHP'
                <?php
                function f(
                    $notDeprecated,
                    /** @deprecated */
                    $deprecated,
                    /** @deprecated Message */
                    $deprecatedWithMessage,
                ) {}
                PHP,
        ))
        ->reflectFunction('f')
        ->parameters();

    assertFalse($parameters['notDeprecated']->isDeprecated());
    assertNull($parameters['notDeprecated']->deprecation());
    assertTrue($parameters['deprecated']->isDeprecated());
    assertEquals(new Deprecation(), $parameters['deprecated']->deprecation());
    assertTrue($parameters['deprecatedWithMessage']->isDeprecated());
    assertEquals(new Deprecation('Message'), $parameters['deprecatedWithMessage']->deprecation());
};
