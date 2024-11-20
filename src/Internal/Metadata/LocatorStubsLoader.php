<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Internal\Metadata;

use Typhoon\DeclarationId\ConstantId;
use Typhoon\DeclarationId\NamedClassId;
use Typhoon\DeclarationId\NamedFunctionId;
use Typhoon\Reflection\File;
use Typhoon\Reflection\Internal\PhpParser\CodeParser;
use Typhoon\Reflection\Locator\ClassLocator;
use Typhoon\Reflection\Locator\ConstantLocator;
use Typhoon\Reflection\Locator\FunctionLocator;
use Typhoon\Reflection\Metadata\ClassMetadata;
use Typhoon\Reflection\Metadata\ConstantMetadata;
use Typhoon\Reflection\Metadata\FunctionMetadata;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection\Internal\Metadata
 */
final class LocatorStubsLoader
{
    /**
     * @var array<non-empty-string, FileStubsLoader>
     */
    private array $fileStubs = [];

    public function __construct(
        private readonly CodeParser $fileParser,
        private readonly MetadataLoader $metadataLoader,
        private readonly ConstantLocator|FunctionLocator|ClassLocator $locator,
    ) {}

    public function loadConstantStubs(ConstantId $id): ConstantMetadata
    {
        if (!$this->locator instanceof ConstantLocator) {
            return new ConstantMetadata();
        }

        $file = $this->locator->locateConstant($id);

        if ($file === null) {
            return new ConstantMetadata();
        }

        return $this->loadFile($file)->loadConstantStubs($id);
    }

    public function loadFunctionStubs(NamedFunctionId $id): FunctionMetadata
    {
        if (!$this->locator instanceof FunctionLocator) {
            return new FunctionMetadata();
        }

        $file = $this->locator->locateFunction($id);

        if ($file === null) {
            return new FunctionMetadata();
        }

        return $this->loadFile($file)->loadFunctionStubs($id);
    }

    public function loadClassStubs(NamedClassId $id): ClassMetadata
    {
        if (!$this->locator instanceof ClassLocator) {
            return new ClassMetadata();
        }

        $file = $this->locator->locateClass($id);

        if ($file === null) {
            return new ClassMetadata();
        }

        return $this->loadFile($file)->loadClassStubs($id);
    }

    private function loadFile(File $file): FileStubsLoader
    {
        return $this->fileStubs[$file->path] ??= new FileStubsLoader($this->fileParser, $this->metadataLoader, $file);
    }
}
