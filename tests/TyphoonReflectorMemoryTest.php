<?php

declare(strict_types=1);

namespace Typhoon\Reflection;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class TyphoonReflectorMemoryTest extends TestCase
{
    public function testItIsGarbageCollected(): void
    {
        if (gc_enabled()) {
            gc_disable();
            defer($_, gc_enable(...));
        }

        $reflector = TyphoonReflector::build();
        $weakReflector = \WeakReference::create($reflector);
        $reflection = $reflector->reflectClass(\BackedEnum::class);
        $weakReflection = \WeakReference::create($reflection);

        unset($reflection, $reflector);

        // assertTrue() is used instead of assertNull() to avoid huge reflector dump in the diff
        self::assertTrue($weakReflector->get() === null, 'Reflector is not garbage collected.');
        self::assertTrue($weakReflection->get() === null, 'ClassReflection is not garbage collected.');
    }
}
