<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Internal\Metadata;

use Typhoon\Reflection\Declaration\ClassDeclaration;
use Typhoon\Reflection\Declaration\ConstantDeclaration;
use Typhoon\Reflection\Declaration\FunctionDeclaration;
use Typhoon\Reflection\Metadata\ClassMetadata;
use Typhoon\Reflection\Metadata\ClassMetadataParser;
use Typhoon\Reflection\Metadata\ConstantMetadata;
use Typhoon\Reflection\Metadata\ConstantMetadataParser;
use Typhoon\Reflection\Metadata\CustomTypeResolver;
use Typhoon\Reflection\Metadata\FunctionMetadata;
use Typhoon\Reflection\Metadata\FunctionMetadataParser;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 */
final class MetadataParsers implements MetadataLoader
{
    /**
     * @var list<ConstantMetadataParser>
     */
    private array $constantParsers = [];

    /**
     * @var list<FunctionMetadataParser>
     */
    private array $functionParsers = [];

    /**
     * @var list<ClassMetadataParser>
     */
    private array $classParsers = [];

    /**
     * @param iterable<ConstantMetadataParser|FunctionMetadataParser|ClassMetadataParser> $metadataParsers
     */
    public function __construct(
        iterable $metadataParsers,
        private readonly CustomTypeResolver $customTypeResolver,
    ) {
        foreach ($metadataParsers as $parser) {
            if ($parser instanceof ConstantMetadataParser) {
                $this->constantParsers[] = $parser;
            }

            if ($parser instanceof FunctionMetadataParser) {
                $this->functionParsers[] = $parser;
            }

            if ($parser instanceof ClassMetadataParser) {
                $this->classParsers[] = $parser;
            }
        }
    }

    public function loadConstantMetadata(ConstantDeclaration $declaration): ConstantMetadata
    {
        $metadata = new ConstantMetadata();

        foreach ($this->constantParsers as $parser) {
            $metadata = $metadata->with($parser->parseConstantMetadata($declaration, $this->customTypeResolver));
        }

        return $metadata;
    }

    public function loadFunctionMetadata(FunctionDeclaration $declaration): FunctionMetadata
    {
        $metadata = new FunctionMetadata();

        foreach ($this->functionParsers as $parser) {
            $metadata = $metadata->with($parser->parseFunctionMetadata($declaration, $this->customTypeResolver));
        }

        return $metadata;
    }

    public function loadClassMetadata(ClassDeclaration $declaration): ClassMetadata
    {
        $metadata = new ClassMetadata();

        foreach ($this->classParsers as $parser) {
            $metadata = $metadata->with($parser->parseClassMetadata($declaration, $this->customTypeResolver));
        }

        return $metadata;
    }
}
