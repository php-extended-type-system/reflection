<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Internal;

use Typhoon\Reflection\Declaration\ClassDeclaration;
use Typhoon\Reflection\Declaration\ConstantDeclaration;
use Typhoon\Reflection\Declaration\FunctionDeclaration;
use Typhoon\Reflection\File;
use Typhoon\Reflection\Internal\PhpParser\CodeParser;
use Typhoon\Reflection\SourceCode;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 * @psalm-type Subscriber = callable(ConstantDeclaration|FunctionDeclaration|ClassDeclaration): void
 */
final class FileParser
{
    /**
     * @var list<Subscriber>
     */
    private array $subscribers = [];

    public function __construct(
        private readonly CodeParser $codeParser,
    ) {}

    /**
     * @param Subscriber $subscriber
     */
    public function subscribe(callable $subscriber): void
    {
        $this->subscribers[] = $subscriber;
    }

    public function parseFile(File $file): void
    {
        foreach ($this->codeParser->parseCode(SourceCode::fromFile($file)) as $declaration) {
            foreach ($this->subscribers as $subscriber) {
                $subscriber($declaration);
            }
        }
    }
}
