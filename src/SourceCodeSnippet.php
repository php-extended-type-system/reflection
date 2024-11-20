<?php

declare(strict_types=1);

namespace Typhoon\Reflection;

/**
 * @api
 */
final class SourceCodeSnippet
{
    /**
     * @var non-negative-int
     */
    public readonly int $startPosition;

    /**
     * @var positive-int
     */
    public readonly int $endPosition;

    public function __construct(
        private readonly SourceCode $code,
        int $startPosition,
        int $endPosition,
    ) {
        \assert($startPosition >= 0);
        \assert($endPosition > 0 && $endPosition > $startPosition && $endPosition <= $this->code->length());

        $this->endPosition = $endPosition;
        $this->startPosition = $startPosition;
    }

    /**
     * @return non-empty-string
     */
    public function toString(): string
    {
        /** @var non-empty-string */
        return substr($this->code->toString(), $this->startPosition, $this->length());
    }

    /**
     * @return non-empty-string
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * @return positive-int
     */
    public function length(): int
    {
        /** @var positive-int */
        return $this->endPosition - $this->startPosition;
    }

    /**
     * @return non-negative-int
     */
    public function startPosition(): int
    {
        return $this->startPosition;
    }

    /**
     * @return positive-int
     */
    public function endPosition(): int
    {
        return $this->endPosition;
    }

    /**
     * @return positive-int
     */
    public function startColumn(): int
    {
        return $this->code->columnAt($this->startPosition);
    }

    /**
     * @return positive-int
     */
    public function endColumn(): int
    {
        return $this->code->columnAt($this->endPosition);
    }

    /**
     * @return positive-int
     */
    public function startLine(): int
    {
        return $this->code->lineAt($this->startPosition);
    }

    /**
     * @return positive-int
     */
    public function endLine(): int
    {
        return $this->code->lineAt($this->endPosition);
    }
}
