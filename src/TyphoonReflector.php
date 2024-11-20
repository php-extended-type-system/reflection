<?php

declare(strict_types=1);

namespace Typhoon\Reflection;

use PhpParser\ParserFactory;
use Typhoon\DeclarationId\AnonymousClassId;
use Typhoon\DeclarationId\ConstantId;
use Typhoon\DeclarationId\Id;
use Typhoon\DeclarationId\NamedClassId;
use Typhoon\DeclarationId\NamedFunctionId;
use Typhoon\Reflection\Internal\ClassReflector;
use Typhoon\Reflection\Internal\ConstantReflector;
use Typhoon\Reflection\Internal\FileParser;
use Typhoon\Reflection\Internal\FunctionReflector;
use Typhoon\Reflection\Internal\Metadata\MetadataLoader;
use Typhoon\Reflection\Internal\Metadata\MetadataParsers;
use Typhoon\Reflection\Internal\Metadata\StubsAwareMetadataLoader;
use Typhoon\Reflection\Internal\PhpDoc\PHPStanPhpDocDriver;
use Typhoon\Reflection\Internal\PhpParser\CodeParser;
use Typhoon\Reflection\Locator\ClassLocator;
use Typhoon\Reflection\Locator\ComposerLocator;
use Typhoon\Reflection\Locator\ConstantLocator;
use Typhoon\Reflection\Locator\FunctionLocator;
use Typhoon\Reflection\Locator\Locators;
use Typhoon\Reflection\Metadata\ClassMetadataParser;
use Typhoon\Reflection\Metadata\ConstantMetadataParser;
use Typhoon\Reflection\Metadata\CustomTypeResolver;
use Typhoon\Reflection\Metadata\CustomTypeResolvers;
use Typhoon\Reflection\Metadata\FunctionMetadataParser;
use Typhoon\Reflection\Metadata\TypesDiscoverer;
use Typhoon\Reflection\Metadata\TypesDiscoverers;
use Typhoon\Reflection\PhpStormStubs\PhpStormStubsLocator;

/**
 * @api
 * @psalm-type Attributes = Collection<non-negative-int, AttributeReflection>
 * @psalm-type Templates = Collection<non-empty-string, TemplateReflection>
 * @psalm-type ClassConstants = Collection<non-empty-string, ClassConstantReflection>
 * @psalm-type Properties = Collection<non-empty-string, PropertyReflection>
 * @psalm-type Parameters = Collection<non-empty-string, ParameterReflection>
 * @psalm-type Methods = Collection<non-empty-string, MethodReflection>
 */
final class TyphoonReflector
{
    /**
     * @return list<ConstantLocator|FunctionLocator|ClassLocator>
     */
    public static function defaultLocators(): array
    {
        return [new ComposerLocator()];
    }

    /**
     * @return list<File|iterable<File>|ConstantLocator|FunctionLocator|ClassLocator>
     */
    public static function defaultStubsLocators(): array
    {
        return [new PhpStormStubsLocator()];
    }

    /**
     * @return list<TypesDiscoverer>
     */
    public static function defaultTypesDiscoverers(): array
    {
        return [new PHPStanPhpDocDriver()];
    }

    /**
     * @return list<ConstantMetadataParser|FunctionMetadataParser|ClassMetadataParser>
     */
    public static function defaultMetadataParsers(): array
    {
        return [new PHPStanPhpDocDriver()];
    }

    /**
     * @param ?iterable<ConstantLocator|FunctionLocator|ClassLocator> $locators
     * @param ?iterable<File|iterable<File>|ConstantLocator|FunctionLocator|ClassLocator> $stubsLocators
     * @param ?iterable<TypesDiscoverer> $typesDiscoverers
     * @param ?iterable<ConstantMetadataParser|FunctionMetadataParser|ClassMetadataParser> $metadataParsers
     * @param iterable<CustomTypeResolver> $customTypeResolvers
     */
    public static function build(
        ?iterable $locators = null,
        ?iterable $stubsLocators = null,
        ?iterable $typesDiscoverers = null,
        ?iterable $metadataParsers = null,
        iterable $customTypeResolvers = [],
    ): self {
        $codeParser = new CodeParser(
            phpParser: (new ParserFactory())->createForHostVersion(),
            typesDiscoverer: new TypesDiscoverers($typesDiscoverers ?? self::defaultTypesDiscoverers()),
        );

        return new self(
            locator: new Locators($locators ?? self::defaultLocators()),
            codeParser: $codeParser,
            metadataLoader: new StubsAwareMetadataLoader(
                codeParser: $codeParser,
                metadataLoader: new MetadataParsers(
                    metadataParsers: $metadataParsers ?? self::defaultMetadataParsers(),
                    customTypeResolver: new CustomTypeResolvers($customTypeResolvers),
                ),
                locators: $stubsLocators ?? self::defaultStubsLocators(),
            ),
        );
    }

    private readonly FileParser $fileParser;

    private readonly ConstantReflector $constantReflector;

    private readonly FunctionReflector $functionReflector;

    private readonly ClassReflector $classReflector;

    private function __construct(
        private readonly Locators $locator,
        private readonly CodeParser $codeParser,
        private readonly MetadataLoader $metadataLoader,
    ) {
        $this->fileParser = new FileParser($codeParser);
        $this->constantReflector = new ConstantReflector(
            locator: $locator,
            fileParser: $this->fileParser,
            metadataLoader: $metadataLoader,
        );
        $this->functionReflector = new FunctionReflector(
            locator: $locator,
            fileParser: $this->fileParser,
            metadataLoader: $metadataLoader,
        );
        $this->classReflector = new ClassReflector(
            locator: $locator,
            fileParser: $this->fileParser,
            metadataLoader: $metadataLoader,
        );
    }

    public function withFile(File $file): self
    {
        $reflector = new self(
            locator: $this->locator,
            codeParser: $this->codeParser,
            metadataLoader: $this->metadataLoader,
        );
        $reflector->fileParser->parseFile($file);

        return $reflector;
    }

    /**
     * @param non-empty-string|ConstantId $id
     */
    public function reflectConstant(string|ConstantId $id): ConstantReflection
    {
        if (\is_string($id)) {
            $id = Id::constant($id);
        }

        return $this->constantReflector->reflect($id)->__load($this);
    }

    /**
     * @param non-empty-string|NamedFunctionId $id
     */
    public function reflectFunction(string|NamedFunctionId $id): FunctionReflection
    {
        if (\is_string($id)) {
            $id = Id::namedFunction($id);
        }

        return $this->functionReflector->reflect($id)->__load($this);
    }

    /**
     * @param non-empty-string|AnonymousClassId|NamedClassId $id
     */
    public function reflectClass(string|AnonymousClassId|NamedClassId $id): ClassReflection
    {
        if (\is_string($id)) {
            $id = Id::class($id);
        }

        /** @var NamedClassId<class-string>|AnonymousClassId<?class-string> $id */
        if ($id instanceof AnonymousClassId) {
            return $this->classReflector->reflectAnonymous($id)->__load($this, $id);
        }

        return $this->classReflector->reflectNamed($id)->__load($this, $id);
    }
}
