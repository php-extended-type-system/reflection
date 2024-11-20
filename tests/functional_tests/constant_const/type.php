<?php

declare(strict_types=1);

namespace Typhoon\Reflection;

use Typhoon\Type\types;
use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertSame;

return static function (TyphoonReflector $reflector): void {
    $constant = $reflector
        ->withFile(File::fromContents(
            <<<'PHP'
                <?php
                
                namespace X;
                
                /**
                 * @var positive-int
                 */
                const A = 123;
                PHP,
        ))
        ->reflectConstant('X\A');

    assertSame(types::positiveInt, $constant->type());
    assertNull($constant->type(TypeKind::Native));
    assertNull($constant->type(TypeKind::Tentative));
    assertSame(types::positiveInt, $constant->type(TypeKind::Annotated));
};
