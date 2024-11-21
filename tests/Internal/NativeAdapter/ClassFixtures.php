<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Internal\NativeAdapter;

final class ClassFixtures
{
    private function __construct() {}

    /**
     * @var ?array<string, array{class-string}>
     */
    private static ?array $classes = null;

    /**
     * @psalm-suppress PossiblyUnusedReturnValue
     * @return array<string, array{class-string}>
     */
    public static function get(): array
    {
        if (self::$classes !== null) {
            return self::$classes;
        }

        $classes = self::loadFromFile(__DIR__ . '/Fixtures/classes.php');

        if (\PHP_VERSION_ID >= 80200) {
            $classes = [...$classes, ...self::loadFromFile(__DIR__ . '/Fixtures/classes_php82.php')];
        }

        if (\PHP_VERSION_ID >= 80300) {
            $classes = [...$classes, ...self::loadFromFile(__DIR__ . '/Fixtures/classes_php83.php')];
        }

        self::$classes = [];

        foreach ($classes as $class) {
            self::$classes[str_replace("\0" . __DIR__, '', $class)] = [$class];
        }

        return self::$classes;
    }
    private const INTERNAL_CLASSES_TO_SKIP = [
        // has a private constructor that is available only via ReflectionClass::getConstructor()
        'IntlCodePointBreakIterator' => true,
        // ReflectionClass::getModifiers() returns 0 instead of 32 (enums have IS_FINAL)
        'Random\IntervalBoundary' => true,
        // is iterable, but does not implement Traversable
        'FFI\CData' => true,
        // has a lot of problems with __invoke()
        'Closure' => true,
        // getMethod(ispersistent).getNumberOfRequiredParameters(): failed asserting that 0 is identical to 2
        'ZMQContext' => true,
        // getMethod(remove).getNumberOfRequiredParameters(): failed asserting that 1 is identical to 2
        'ZMQPoll' => true,
        // Fatal error: OAuthProvider::__construct(): For the CLI sapi parameters must be set first via OAuthProvider::__construct(array("oauth_param" => "value", ...))
        'OAuthProvider' => true,
        // problems with readonly properties
        'AMQPTimestamp' => true,
        // problems with readonly properties
        'AMQPDecimal' => true,
    ];

    /**
     * @return \Generator<class-string, array{class-string}>
     */
    public static function internal(): \Generator
    {
        foreach (self::allDeclaredClasses() as $class) {
            /** @psalm-suppress InvalidArrayOffset */
            if (!isset(self::INTERNAL_CLASSES_TO_SKIP[$class]) && (new \ReflectionClass($class))->isInternal()) {
                yield $class => [$class];
            }
        }
    }

    /**
     * @param non-empty-string $file
     * @return array<class-string>
     */
    private static function loadFromFile(string $file): array
    {
        $declared = self::allDeclaredClasses();
        /** @psalm-suppress UnresolvableInclude */
        require_once $file;

        return array_diff(self::allDeclaredClasses(), $declared);
    }

    /**
     * @return list<class-string>
     */
    private static function allDeclaredClasses(): array
    {
        return [
            ...get_declared_classes(),
            ...get_declared_interfaces(),
            ...get_declared_traits(),
        ];
    }
}
