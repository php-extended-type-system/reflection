<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Internal\Metadata;

use Typhoon\DeclarationId\ConstantId;
use Typhoon\DeclarationId\NamedClassId;
use Typhoon\DeclarationId\NamedFunctionId;
use Typhoon\Reflection\Declaration\ConstantDeclaration;
use Typhoon\Reflection\Declaration\FunctionDeclaration;
use Typhoon\Reflection\File;
use Typhoon\Reflection\Internal\PhpParser\CodeParser;
use Typhoon\Reflection\Metadata\ClassMetadata;
use Typhoon\Reflection\Metadata\ConstantMetadata;
use Typhoon\Reflection\Metadata\FunctionMetadata;
use Typhoon\Reflection\SourceCode;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection\Internal\Metadata
 */
final class FileStubsLoader
{
    private bool $loaded = false;

    /**
     * @var array<non-empty-string, ConstantMetadata>
     */
    private array $constants = [];

    /**
     * @var array<non-empty-string, FunctionMetadata>
     */
    private array $functions = [];

    /**
     * @var array<non-empty-string, ClassMetadata>
     */
    private array $classes = [];

    public function __construct(
        private readonly CodeParser $codeParser,
        private readonly MetadataLoader $metadataLoader,
        private readonly File $file,
    ) {}

    public function loadConstantStubs(ConstantId $id): ConstantMetadata
    {
        $this->load();

        return $this->constants[$id->name] ?? new ConstantMetadata();
    }

    public function loadFunctionStubs(NamedFunctionId $id): FunctionMetadata
    {
        $this->load();

        return $this->functions[$id->name] ?? new FunctionMetadata();
    }

    public function loadClassStubs(NamedClassId $id): ClassMetadata
    {
        $this->load();

        return $this->classes[$id->name] ?? new ClassMetadata();
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        foreach ($this->codeParser->parseCode(SourceCode::fromFile($this->file)) as $declaration) {
            if ($declaration instanceof ConstantDeclaration) {
                $this->constants[$declaration->name] = $this->metadataLoader->loadConstantMetadata($declaration);

                continue;
            }

            if ($declaration instanceof FunctionDeclaration) {
                if ($declaration->name !== null) {
                    $this->functions[$declaration->name] = $this->metadataLoader->loadFunctionMetadata($declaration);
                }

                continue;
            }

            if ($declaration->name !== null) {
                $this->classes[$declaration->name] = $this->metadataLoader->loadClassMetadata($declaration);
            }
        }

        $this->loaded = true;
    }
}
