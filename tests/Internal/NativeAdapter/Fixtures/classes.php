<?php

declare(strict_types=1);

namespace
{
    class RootNamespaceClass {}
}

namespace A\B\C
{
    class ClassInDeepNamespace {}
}

namespace Simple
{
    class JustClass {}

    enum JustEnum {}

    enum UnitEnum
    {
        case X;
        case Y;
        case Z;
    }

    enum EmptyIntEnum: int {}

    enum IntEnum: int
    {
        case ONE = 1;
        case TWO = 2;
    }

    enum EmptyStringEnum: string {}

    enum StringEnum: string
    {
        case A = 'a';
        case B = 'b';
    }

    interface JustInterface {}

    trait JustTrait {}

    abstract class AbstractClass {}

    class PrivateConstructorClass
    {
        private function __construct() {}
    }

    class PrivateCloneClass
    {
        private function __clone(): void {}
    }

    /**
     * I am class!
     */
    class ClassWithPhpDoc {}
}

namespace Iterables
{
    interface InterfaceExtendingTraversable extends \Traversable {}

    interface InterfaceExtendingIterator extends \Iterator {}

    interface InterfaceExtendingIteratorAggregate extends \IteratorAggregate {}

    class ClassImplementingIterator implements \Iterator
    {
        public function current(): mixed {}
        public function next(): void {}
        public function key(): mixed {}
        public function valid(): bool {}
        public function rewind(): void {}
    }

    class ClassImplementingIteratorAggregate implements \IteratorAggregate
    {
        public function getIterator(): \Traversable {}
    }

    abstract class AbstractClassImplementingIterator implements \Iterator {}

    abstract class AbstractClassImplementingIteratorAggregate implements \IteratorAggregate {}

    enum EnumImplementingIterator implements \Iterator
    {
        public function current(): mixed {}
        public function next(): void {}
        public function key(): mixed {}
        public function valid(): bool {}
        public function rewind(): void {}
    }

    enum EnumImplementingIteratorAggregate implements \IteratorAggregate
    {
        public function getIterator(): \Traversable {}
    }
}

namespace Constants
{
    class ClassWithConstants
    {
        /**
         * Hello, I am constant.
         */
        const CONSTANT_WITH_PHPDOC = null;
        const CONSTANT_WITHOUT_VISIBILITY = null;
        public const PUBLIC_CONSTANT = 1;
        protected const PROTECTED_CONSTANT = 'protected';
        protected const PRIVATE_CONSTANT = 0.34;
        final protected const FINAL_CONSTANT = true;
    }

    class ClassWithInheritedConstants extends ClassWithConstants {}

    class ClassWithAlteredConstantVisibility extends ClassWithConstants
    {
        public const PROTECTED_CONSTANT = 'protected';
        public const PRIVATE_CONSTANT = 0.34;
    }
}

namespace Properties
{
    class ClassWithProperties
    {
        // todo with types
        // todo static without visibility
        var $varProperty;
        public $noType;
        public string $withDefault = 'abc';
        public string $public;
        protected string $protected;
        private string $private;
        public readonly string $publicReadonly;
        protected readonly string $protectedReadonly;
        private readonly string $privateReadonly;
        public static string $publicStatic;
        protected static string $protectedStatic;
        private static string $privateStatic;
        /**
         * I am property!
         */
        private string $withPhpDoc;

        public function __construct(
            public string $publicPromoted,
            protected string $protectedPromoted,
            private string $privatePromoted,
            public readonly string $publicReadonlyPromoted,
            protected readonly string $protectedReadonlyPromoted,
            private readonly string $privateReadonlyPromoted,
            /**
             * I am promoted property!
             */
            private readonly string $promotedWithPhpDoc,
            private readonly string $promotedWithDefault = '',
        ) {
        }
    }

    trait TraitWithProperties
    {
        public $noType;
        public string $withDefault = 'abc';
        public string $public;
        protected string $protected;
        private string $private;
        public readonly string $publicReadonly;
        protected readonly string $protectedReadonly;
        private readonly string $privateReadonly;
        public static string $publicStatic;
        protected static string $protectedStatic;
        private static string $privateStatic;
        /**
         * I am property!
         */
        private string $withPhpDoc;

        public function __construct(
            public string $publicPromoted,
            protected string $protectedPromoted,
            private string $privatePromoted,
            public readonly string $publicReadonlyPromoted,
            protected readonly string $protectedReadonlyPromoted,
            private readonly string $privateReadonlyPromoted,
            /**
             * I am promoted property!
             */
            private readonly string $promotedWithPhpDoc,
        ) {
        }
    }

    class ClassWithAPromotedProperty
    {
        public function __construct(
            public readonly string $promoted,
        ) {}
    }

    class ClassRedefiningInheritedPromotedPropertyAsPromoted extends ClassWithAPromotedProperty
    {
        public function __construct(
            public readonly string $promoted
        ) {}
    }

    class ClassRedefiningInheritedPromotedPropertyAsNonPromoted extends ClassWithAPromotedProperty
    {
        public readonly string $promoted;
    }
}

namespace Methods
{
    interface InterfaceWithMethod
    {
        public function a(): void;
    }

    interface InterfaceInheritsMethod extends InterfaceWithMethod
    {
    }

    class ClassWithMethods
    {
        public function public(string &$byRef): void {}
        protected function protected(): void {}
        private function private(): void {}
        static public function staticPublic(): void {}
        static protected function staticProtected(): void {}
        static private function staticPrivate(): void {}
        static private function &byRef(): string {}
        static private function variadic(string ...$strings): string {}
        static private function optionalArgs(int $a, string $b = 'abc', float $c = 0.2): string {}
        static private function generatorReturnType(): \Generator {}
        static private function yield() { yield 1; }
        /**
         * I am method!
         */
        static private function withPhpDoc() { yield 1; }
    }

    trait TraitWithMethods
    {
        public function public(string &$byRef): void {}
        protected function protected(): void {}
        private function private(): void {}
        static public function staticPublic(): void {}
        static protected function staticProtected(): void {}
        static private function staticPrivate(): void {}
        static private function &byRef(): string {}
        static private function variadic(string ...$strings): string {}
        static private function optionalArgs(int $a, string $b = 'abc', float $c = 0.2): string {}
        static private function generatorReturnType(): \Generator {}
        static private function yield() { yield 1; }
        /**
         * I am method!
         */
        static private function withPhpDoc() { yield 1; }
    }
}

namespace Hierarchy
{
    interface I1 {}
    interface I2 extends I1 {}
    interface I3 extends I1, I2 {}
    abstract class A implements I3, I1, I2 {}
    enum E implements I3, I1, I2 {}
}

namespace ComplexMethodOrder
{
    interface I
    {
        public function y(): void;
    }

    abstract class A implements I
    {
        public function x(): void {}
        public function y(): void {}
        public function z(): void {}
    }

    class C extends A
    {
        public function a(): void {}
        public function y(): void {}
        public function b(): void {}
    }
}

namespace Anonymous
{
    use Simple\JustClass;
    use Simple\JustInterface;


    new /** I am an anonym! */ class ('a') implements JustInterface {
        public function __construct(
            /**
             * I am promoted property!
             */
            private readonly string $promotedWithPhpDoc,
        ) {
        }
    };
    new class extends JustClass {
        public function nested()
        {
            new class {};
        }
    };
}

namespace Attributes
{
    #[\Attribute(\Attribute::TARGET_ALL|\Attribute::IS_REPEATABLE)]
    final class Attr
    {
        public function __construct(public readonly string $value) {}
        public function __toString(): string { return __FILE__; }
    }

    #[Attr('class')]
    #[Attr('class2')]
    final class ClassWithAttributes
    {
        #[Attr('constant')]
        #[Attr('constant2')]
        public const C = 1;

        #[Attr('property')]
        #[Attr('property2')]
        public string $prop;

        #[Attr('method')]
        #[Attr('method2')]
        public function __construct(
            #[Attr('param')]
            #[Attr('param2')]
            array $param,
            #[Attr('promoted')]
            #[Attr('promoted2')]
            public string $promoted,
        ){}
    }
}

namespace ParameterTypes
{
    abstract class Y {}

    final class X extends Y
    {
        public const A = 1;

        public function method(
            array $array,
            callable $callable,
            \Closure $closure,
            ?\Closure $nullable,
            \Closure|\Throwable $union,
            false|\Closure|\Throwable $anotherUnion,
            \Throwable&\Countable $intersection,
            $noType,
            self $self,
            parent $parent,
            Y $y,
            null|int|float $nullIntFloat,
            mixed $mixed,
            float $implicitlyNullable = null,
            int $defaultClassConstant = self::A,
            int $defaultConstant = \PHP_INT_MAX,
        ) {}
    }
}

namespace AbstractClassInheritance
{
    abstract class Base
    {
        private string $private;
        protected string $protected;
        public string $public;

        private function private(): void {}
        protected function protected(): void {}
        public function public(): void {}
    }

    final class Child extends Base
    {
    }
}

namespace AbstractClassAndInterfaceInheritance
{
    interface Interfac
    {
        public function public(): void;
    }

    abstract class Base implements Interfac
    {
        private function private(): void {}
        public function public(): void {}
        protected function protected(): void {}
    }

    final class ClassThatJustExtends extends Base
    {
    }

    final class ClassThatExtendsAndImplements extends Base implements Interfac
    {
    }

    trait PublicMethod
    {
        public function public(): void {}
    }

    final class ClassThatUsesExtendsAndImplements extends Base implements Interfac
    {
        use PublicMethod;
    }
}

namespace Traits
{
    trait Trait1
    {
        private string $private;
        protected string $protected;
        public string $public;

        private static string $privateStatic;
        protected static string $protectedStatic;
        public static string $publicStatic;

        private function t1(): void {}

        private function privateMethod(): void {}
        protected function protectedMethod(): void {}
        public function publicMethod(): void {}

        private static function privateStaticMethod(): void {}
        protected static function protectedStaticMethod(): void {}
        public static function publicStaticMethod(): void {}
    }

    final class ClassSimplyUsesTraitAsIs
    {
        use Trait1;
    }

    trait EmptyTrait {}

    final class ClassUsesTraitsMultipleTimes
    {
        use Trait1;
        use EmptyTrait;
        use EmptyTrait;
        use Trait1;
        use EmptyTrait;
    }

    final class ClassUsesTraitWithAlteredNames
    {
        use Trait1 {
            privateMethod as privateAsProtectedMethod;
            protectedMethod as protectedAsPublicMethod;
            publicMethod as publicAsPrivateMethod;

            privateStaticMethod as privateStaticAsProtectedMethod;
            protectedStaticMethod as protectedStaticAsPublicMethod;
            publicStaticMethod as publicStaticAsPrivateMethod;
        }
    }

    final class ClassUsesTraitWithAlteredVisibility
    {
        use Trait1 {
            privateMethod as protected;
            protectedMethod as public;
            publicMethod as private;

            privateStaticMethod as protected;
            protectedStaticMethod as public;
            publicStaticMethod as private;
        }
    }

    final class ClassUsesTraitWithAlteredVisibilityAndName
    {
        use Trait1 {
            privateMethod as protected privateAsProtectedMethod;
            protectedMethod as public protectedAsPublicMethod;
            publicMethod as private publicAsPrivateMethod;

            privateStaticMethod as protected privateStaticAsProtectedMethod;
            protectedStaticMethod as public protectedStaticAsPublicMethod;
            publicStaticMethod as private publicStaticAsPrivateMethod;
        }
    }

    trait Trait2
    {
        private function t2(): void {}

        private function privateMethod(): void {}
        protected function protectedMethod(): void {}
        public function publicMethod(): void {}
    }

    final class ClassUsesTraitsWithInsteadOf
    {
        use Trait1, Trait2 {
            Trait1::privateMethod insteadof Trait2;
            Trait2::protectedMethod insteadof Trait1;
        }
        use Trait2 {
            Trait2::publicMethod insteadof Trait1;
        }
    }

    final class ClassUsesTraitsWithIsnteadofAndAliasesAndOverridesMethods
    {
        use Trait1, Trait2 {
            Trait1::privateMethod insteadof Trait2;
            Trait2::protectedMethod insteadof Trait1;
        }
        use Trait2 {
            Trait2::publicMethod insteadof Trait1;
        }
        use Trait2 {
            Trait2::publicMethod as private t2PublicAsPrivate;
        }

        public function publicMethod(): void {}

        private function t2PublicAsPrivate(): void {}
    }
}

namespace Types
{
    class ClassWithAllTypes extends \stdClass
    {
        public $noType;
        public bool $bool;
        public int $int;
        public float $float;
        public string $string;
        public array $array;
        public mixed $mixed;
        public \Closure $closure;
        public object $object;
        public int|string $intOrString;
        public float|string $floatOrString;
        public null|string|object $nullOrStringOrObject;
        public iterable $iterable;
        // iterable is reflected differently in PHP 8.1 and 8.3 and breaks CI pipeline
        // public ?iterable $nullableIterable;
        // public iterable|int $iterableOrInt;
        public int|false $intOrFalse;
        public ?string $nullableString;
        public self $self;
        public parent $parent;
        public \Iterator&\Countable $iteratorAndCountable;
        public \Countable&\Iterator $countableAndIterator;
        public function static(): static {}
        public function callable(): callable {}
        public function never(): never {}
        public function void(): void {}
    }
}

namespace Stringables
{
    interface AnotherInterface {}

    trait TraitWithToString
    {
        public function __toString(): string { return ''; }
    }

    class ClassWithTraitWithToString
    {
        use TraitWithToString;
    }

    class ImplicitlyStringableClass implements AnotherInterface
    {
        public function __toString(): string { return 'string'; }
    }

    class ClassExtendingImplicitlyStringableClass extends ImplicitlyStringableClass {}

    interface ImplicitlyStringableInterface extends AnotherInterface
    {
        public function __toString(): string;
    }

    abstract class ExplicitlyStringableAbstractClass implements \Stringable {}

    class ExplicitlyStringableClass implements \Stringable
    {
        public function __toString(): string { return ''; }
    }
}

namespace ParameterInheritedFromTraitAndClass
{
    trait T
    {
        public function x(string $x): void {}
    }

    abstract class A
    {
        public function y(string $y): void {}
    }

    class B extends A
    {
        use T;

        public function z(string $z): void {}
    }
}

namespace TrickyConstantExpressions
{
    class ClassWithTrickyConstantExpressions extends \stdClass
    {
        const __LINE__ = __LINE__;
        const __FILE__ = __FILE__;
        const __DIR__ = __DIR__;
        const __FUNCTION__ = __FUNCTION__;
        const __CLASS__ = __CLASS__;
        const __TRAIT__ = __TRAIT__;
        const __METHOD__ = __METHOD__;
        const __NAMESPACE__ = __NAMESPACE__;
        const self = self::class;
        const parent = parent::class;

        public int $__LINE__ = __LINE__;
        public string $__FILE__ = __FILE__;
        public string $__DIR__ = __DIR__;
        public string $__FUNCTION__ = __FUNCTION__;
        public string $__CLASS__ = __CLASS__;
        public string $__TRAIT__ = __TRAIT__;
        public string $__METHOD__ = __METHOD__;
        public string $__NAMESPACE__ = __NAMESPACE__;
        public string $self = self::class;
        public string $parent = parent::class;

        public function method(
            int $__LINE__ = __LINE__,
            string $__FILE__ = __FILE__,
            string $__DIR__ = __DIR__,
            string $__FUNCTION__ = __FUNCTION__,
            string $__CLASS__ = __CLASS__,
            string $__TRAIT__ = __TRAIT__,
            string $__METHOD__ = __METHOD__,
            string $__NAMESPACE__ = __NAMESPACE__,
            string $self = self::class,
            string $parent = parent::class,
        ) {}
    }

    class ClassExtendsClassWithTrickyConstantExpressions extends ClassWithTrickyConstantExpressions {}

    trait TraitWithTrickyConstantExpressions
    {
        public int $__LINE__ = __LINE__;
        public string $__FILE__ = __FILE__;
        public string $__DIR__ = __DIR__;
        public string $__FUNCTION__ = __FUNCTION__;
        public string $__CLASS__ = __CLASS__;
        public string $__TRAIT__ = __TRAIT__;
        public string $__METHOD__ = __METHOD__;
        public string $__NAMESPACE__ = __NAMESPACE__;
        public string $self = self::class;

        public function method(
            int $__LINE__ = __LINE__,
            string $__FILE__ = __FILE__,
            string $__DIR__ = __DIR__,
            string $__FUNCTION__ = __FUNCTION__,
            string $__CLASS__ = __CLASS__,
            string $__TRAIT__ = __TRAIT__,
            string $__METHOD__ = __METHOD__,
            string $__NAMESPACE__ = __NAMESPACE__,
            string $self = self::class,
        ) {}
    }

    trait TraitUsesTraitWithTrickyConstantExpressions
    {
        use TraitWithTrickyConstantExpressions;
    }

    class ClassUsesTraitWithTrickyConstantExpressions
    {
        use TraitWithTrickyConstantExpressions;
    }
}
