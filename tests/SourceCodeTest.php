<?php

declare(strict_types=1);

namespace Typhoon\Reflection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceCode::class)]
final class SourceCodeTest extends TestCase
{
    #[TestWith([''])]
    #[TestWith(['abc'])]
    #[TestWith(['привет'])]
    public function testToStringReturnsOriginalString(string $string): void
    {
        $code = SourceCode::fromFile(File::fromContents($string));

        self::assertSame($string, $code->toString());
        self::assertSame($string, (string) $code);
    }

    public function testSnippet(): void
    {
        $code = SourceCode::fromFile(File::fromContents('abc'));

        $snippet = $code->snippet(1, 2);

        self::assertSame('b', $snippet->toString());
    }

    /**
     * @param non-negative-int $position
     */
    #[TestWith(['', 0, 1])]
    #[TestWith(['abc', 1, 2])]
    #[TestWith(["\n\n", 1, 1])]
    #[TestWith(["\r\r", 1, 1])]
    #[TestWith(["\r\n\r\n", 1, 2])]
    #[TestWith(["\nabc", 1, 1])]
    #[TestWith(["\rabc", 1, 1])]
    #[TestWith(["\r\nabc", 2, 1])]
    #[TestWith(["\nabc", 2, 2])]
    #[TestWith(["\rabc", 2, 2])]
    #[TestWith(["\r\nabc", 3, 2])]
    public function testColumnAt(string $code, int $position, int $expectedColumn): void
    {
        $code = SourceCode::fromFile(File::fromContents($code));

        $column = $code->columnAt($position);

        self::assertSame($expectedColumn, $column);
    }

    /**
     * @param non-negative-int $position
     */
    #[TestWith(['', 0, 1])]
    #[TestWith(['abc', 1, 1])]
    #[TestWith(["\n\n", 0, 1])]
    #[TestWith(["\r\r", 0, 1])]
    #[TestWith(["\r\n\r\n", 0, 1])]
    #[TestWith(["\n\n", 1, 2])]
    #[TestWith(["\r\r", 1, 2])]
    #[TestWith(["\r\n\r\n", 1, 1])]
    #[TestWith(["\r\n\r\n", 2, 2])]
    #[TestWith(["\r\n\r\n", 3, 2])]
    #[TestWith(["\r\n\r\n", 4, 3])]
    #[TestWith(["\nabc", 1, 2])]
    #[TestWith(["\rabc", 1, 2])]
    #[TestWith(["\r\nabc", 1, 1])]
    #[TestWith(["\r\nabc", 2, 2])]
    #[TestWith(["\r\r\r", 3, 4])]
    #[TestWith(["\na\nb\nc", 3, 3])]
    #[TestWith(["\ra\rb\rc", 3, 3])]
    #[TestWith(["\na\nb\nc", 5, 4])]
    #[TestWith(["\ra\rb\rc", 5, 4])]
    public function testLineAt(string $code, int $position, int $expectedLine): void
    {
        $code = SourceCode::fromFile(File::fromContents($code));

        $column = $code->lineAt($position);

        self::assertSame($expectedLine, $column);
    }
}
