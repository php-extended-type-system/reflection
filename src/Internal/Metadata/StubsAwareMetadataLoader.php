<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Internal\Metadata;

use Typhoon\DeclarationId\NamedClassId;
use Typhoon\DeclarationId\NamedFunctionId;
use Typhoon\Reflection\Declaration\ClassDeclaration;
use Typhoon\Reflection\Declaration\ConstantDeclaration;
use Typhoon\Reflection\Declaration\FunctionDeclaration;
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
 * @psalm-internal Typhoon\Reflection
 */
final class StubsAwareMetadataLoader implements MetadataLoader
{
    /**
     * @var list<FileStubsLoader|LocatorStubsLoader>
     */
    private array $loaders = [];

    /**
     * @param iterable<File|iterable<File>|ConstantLocator|FunctionLocator|ClassLocator> $locators
     */
    public function __construct(
        CodeParser $codeParser,
        private readonly MetadataLoader $metadataLoader,
        iterable $locators,
    ) {
        foreach ($locators as $locator) {
            if ($locator instanceof File) {
                $this->loaders[] = new FileStubsLoader($codeParser, $metadataLoader, $locator);

                continue;
            }

            if (is_iterable($locator)) {
                foreach ($locator as $file) {
                    $this->loaders[] = new FileStubsLoader($codeParser, $metadataLoader, $file);
                }

                continue;
            }

            $this->loaders[] = new LocatorStubsLoader($codeParser, $metadataLoader, $locator);
        }
    }

    public function loadConstantMetadata(ConstantDeclaration $declaration): ConstantMetadata
    {
        $metadata = $this->metadataLoader->loadConstantMetadata($declaration);

        foreach ($this->loaders as $loader) {
            $metadata = $metadata->with($loader->loadConstantStubs($declaration->id));
        }

        return $metadata;
    }

    public function loadFunctionMetadata(FunctionDeclaration $declaration): FunctionMetadata
    {
        $metadata = $this->metadataLoader->loadFunctionMetadata($declaration);

        if ($declaration->id instanceof NamedFunctionId) {
            foreach ($this->loaders as $loader) {
                $metadata = $metadata->with($loader->loadFunctionStubs($declaration->id));
            }
        }

        return $metadata;
    }

    public function loadClassMetadata(ClassDeclaration $declaration): ClassMetadata
    {
        $metadata = $this->metadataLoader->loadClassMetadata($declaration);

        if ($declaration->id instanceof NamedClassId) {
            foreach ($this->loaders as $loader) {
                $metadata = $metadata->with($loader->loadClassStubs($declaration->id));
            }
        }

        return $metadata;
    }
}
