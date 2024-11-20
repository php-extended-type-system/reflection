<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Internal;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 * @psalm-pure
 * @template TValue
 * @param array<TValue> $array
 * @return ($array is non-empty-array ? TValue : ?TValue)
 */
function array_value_first(array $array): mixed
{
    $key = array_key_first($array);

    return $key === null ? null : $array[$key];
}

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 * @psalm-pure
 * @template TValue
 * @param array<TValue> $array
 * @return ($array is non-empty-array ? TValue : ?TValue)
 */
function array_value_last(array $array): mixed
{
    $key = array_key_last($array);

    return $key === null ? null : $array[$key];
}

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 * @psalm-assert-if-true class-string $name
 */
function class_like_exists(string $name, bool $autoload = true): bool
{
    return class_exists($name, $autoload) || interface_exists($name, $autoload) || trait_exists($name, $autoload);
}

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 * @psalm-pure
 */
function get_namespace(string $name): string
{
    $lastSlashPosition = strrpos($name, '\\');

    if ($lastSlashPosition === false) {
        return '';
    }

    return substr($name, 0, $lastSlashPosition);
}

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 * @psalm-pure
 */
function get_short_name(string $name): string
{
    $lastSlashPosition = strrpos($name, '\\');

    if ($lastSlashPosition === false) {
        return $name;
    }

    return substr($name, $lastSlashPosition + 1);
}

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 * @param non-empty-string $name
 * @return ?non-empty-string
 */
function get_constant_extension(string $name): ?string
{
    foreach (get_defined_constants(categorize: true) as $category => $constants) {
        foreach ($constants as $constant => $_value) {
            if ($constant === $name) {
                return ($category === 'user' || $category === '') ? null : $category;
            }
        }
    }

    return null;
}
