<?php

declare(strict_types=1);

namespace Typhoon\Reflection;

use function PHPUnit\Framework\assertNull;

return static function (TyphoonReflector $reflector): void {
    $constant = $reflector
        ->withFile(File::fromContents(
            <<<'PHP'
                <?php
                
                namespace X;
                
                define('Y\\A', 123);
                PHP,
        ))
        ->reflectConstant('Y\A');

    assertNull($constant->type(TypeKind::Native));
    assertNull($constant->type(TypeKind::Tentative));
    assertNull($constant->type(TypeKind::Annotated));
};
