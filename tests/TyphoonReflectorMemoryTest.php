<?php

declare(strict_types=1);

namespace Typhoon\Reflection;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class TyphoonReflectorMemoryTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testItIsGarbageCollected(): void
    {
        gc_disable();
        $reflector = TyphoonReflector::build();
        $reflection = $reflector->reflectClass(\AppendIterator::class);
        $weakReflector = \WeakReference::create($reflector);
        $weakReflection = \WeakReference::create($reflection);

        unset($reflection, $reflector);

        // assertTrue() is used instead of assertNull() to avoid huge reflector dump in the diff
        self::assertTrue($weakReflector->get() === null, 'Reflector is not garbage collected.');
        self::assertTrue($weakReflection->get() === null, 'ClassReflection is not garbage collected.');
    }
}
