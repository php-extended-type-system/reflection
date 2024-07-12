<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Internal;

use Psr\SimpleCache\CacheInterface;
use Typhoon\DeclarationId\Id;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 */
final class Cache
{
    /**
     * This version should be incremented before release if any of the cacheable elements have changed.
     * This should help to avoid deserialization errors.
     */
    private const VERSION = 0;

    private const PREFIX = 'typhoon/reflection';

    public function __construct(
        private readonly CacheInterface $cache,
    ) {}

    public function get(Id $id): ?DataCacheItem
    {
        $value = $this->cache->get(self::key($id));

        if ($value instanceof DataCacheItem) {
            return $value;
        }

        return null;
    }

    /**
     * @param IdMap<Id, DataCacheItem> $data
     */
    public function set(IdMap $data): void
    {
        $values = [];

        foreach ($data as $id => $item) {
            $values[self::key($id)] = $item;
        }

        if ($values !== []) {
            $this->cache->setMultiple($values);
        }
    }

    private static function key(Id $id): string
    {
        return hash('xxh128', self::PREFIX . $id->encode() . self::VERSION);
    }
}
