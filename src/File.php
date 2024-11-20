<?php

declare(strict_types=1);

namespace Typhoon\Reflection;

use Typhoon\Reflection\Exception\FileIsNotReadable;

/**
 * @api
 */
final class File
{
    public static function fromContents(string $contents): self
    {
        return new self('data://text/plain,' . urlencode($contents));
    }

    /**
     * @var non-empty-string
     */
    public readonly string $path;

    public function __construct(string $path)
    {
        \assert($path !== '');
        $this->path = $path;
    }

    public function directory(): string
    {
        return \dirname($this->path);
    }

    public function read(): string
    {
        $contents = @file_get_contents($this->path);

        if ($contents === false) {
            throw new FileIsNotReadable($this->path);
        }

        return $contents;
    }
}
