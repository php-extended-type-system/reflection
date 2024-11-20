<?php

declare(strict_types=1);

namespace Typhoon\Reflection;

use Typhoon\ChangeDetector\ChangeDetector;
use Typhoon\ChangeDetector\ConstantChangeDetector;
use Typhoon\ChangeDetector\FileChangeDetector;

/**
 * @api
 */
final class SourceCode
{
    public static function fromFile(File $file): self
    {
        $contents = $file->read();

        return new self(
            file: $file,
            code: $contents,
            changeDetector: FileChangeDetector::fromFileAndContents($file->path, $contents),
        );
    }

    /**
     * @param non-empty-string $name
     */
    public static function fakeConstant(string $name): self
    {
        $code = \sprintf('<?php define(%s, null);', var_export($name, true));

        return new self(
            file: File::fromContents($code),
            code: $code,
            changeDetector: ConstantChangeDetector::fromName($name),
        );
    }

    /**
     * @var ?non-empty-list<non-negative-int>
     */
    private ?array $lineEndPositions = null;

    private function __construct(
        public readonly File $file,
        private readonly string $code,
        public readonly ChangeDetector $changeDetector,
    ) {}

    public function toString(): string
    {
        return $this->code;
    }

    public function __toString(): string
    {
        return $this->code;
    }

    /**
     * @return non-negative-int
     */
    public function length(): int
    {
        return \strlen($this->code);
    }

    /**
     * @return 0
     */
    public function startPosition(): int
    {
        return 0;
    }

    /**
     * @return non-negative-int
     */
    public function endPosition(): int
    {
        return $this->length();
    }

    /**
     * @param non-negative-int $position
     * @return positive-int
     */
    public function columnAt(int $position): int
    {
        \assert($position >= 0 && $position <= $this->endPosition());

        $lineEndPositions = $this->lineEndPositions();

        foreach ($lineEndPositions as $index => $lineEndPosition) {
            if ($position < $lineEndPosition) {
                break;
            }
        }

        if ($index === 0) {
            return $position + 1;
        }

        /** @var positive-int */
        return $position - $lineEndPositions[$index - 1] + 1;
    }

    /**
     * @return 1
     */
    public function startColumn(): int
    {
        return 1;
    }

    /**
     * @return positive-int
     */
    public function endColumn(): int
    {
        return $this->columnAt($this->length());
    }

    /**
     * @return positive-int
     */
    public function lineAt(int $position): int
    {
        \assert($position >= 0);
        \assert($position <= $this->endPosition());

        foreach ($this->lineEndPositions() as $lineIndex => $lineEndPosition) {
            if ($position < $lineEndPosition) {
                break;
            }
        }

        return $lineIndex + 1;
    }

    /**
     * @return 1
     */
    public function startLine(): int
    {
        return 1;
    }

    /**
     * @return positive-int
     */
    public function endLine(): int
    {
        return $this->lineAt($this->length());
    }

    /**
     * @return list<\PhpToken>
     */
    public function tokenize(): array
    {
        return \PhpToken::tokenize($this->code);
    }

    public function snippet(int $startPosition, int $endPosition): SourceCodeSnippet
    {
        return new SourceCodeSnippet($this, $startPosition, $endPosition);
    }

    /**
     * @return non-empty-list<non-negative-int>
     */
    public function lineEndPositions(): array
    {
        if ($this->lineEndPositions !== null) {
            return $this->lineEndPositions;
        }

        preg_match_all('~(*BSR_ANYCRLF)\R|$~', $this->code, $matches, PREG_OFFSET_CAPTURE);

        /** @var non-empty-list<non-negative-int> */
        $lineEndPositions = array_map(
            static fn(array $match): int => $match[1] + \strlen($match[0]),
            $matches[0],
        );

        return $this->lineEndPositions = $lineEndPositions;
    }
}
