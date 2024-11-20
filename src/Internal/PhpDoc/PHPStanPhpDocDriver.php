<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Internal\PhpDoc;

use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\ClassLike;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TemplateTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TypeAliasImportTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TypeAliasTagValueNode;
use Typhoon\Reflection\Declaration\ClassDeclaration;
use Typhoon\Reflection\Declaration\ConstantDeclaration;
use Typhoon\Reflection\Declaration\FunctionDeclaration;
use Typhoon\Reflection\Deprecation;
use Typhoon\Reflection\Metadata\ClassMetadata;
use Typhoon\Reflection\Metadata\ClassMetadataParser;
use Typhoon\Reflection\Metadata\ConstantMetadata;
use Typhoon\Reflection\Metadata\ConstantMetadataParser;
use Typhoon\Reflection\Metadata\CustomTypeResolver;
use Typhoon\Reflection\Metadata\FunctionMetadata;
use Typhoon\Reflection\Metadata\FunctionMetadataParser;
use Typhoon\Reflection\Metadata\ParameterMetadata;
use Typhoon\Reflection\Metadata\TemplateDeclaration;
use Typhoon\Reflection\Metadata\TypeDeclarations;
use Typhoon\Reflection\Metadata\TypesDiscoverer;
use Typhoon\Reflection\SourceCode;
use Typhoon\Reflection\SourceCodeSnippet;
use Typhoon\Type\Type;
use Typhoon\Type\types;
use Typhoon\Type\Variance;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 */
final class PHPStanPhpDocDriver implements TypesDiscoverer, ConstantMetadataParser, FunctionMetadataParser, ClassMetadataParser
{
    public function __construct(
        private readonly PhpDocParser $parser = new PhpDocParser(),
    ) {}

    public function discoverTypes(FunctionLike|ClassLike $node): TypeDeclarations
    {
        $docComment = $node->getDocComment();

        if ($docComment === null) {
            return new TypeDeclarations();
        }

        $phpDoc = $this->parser->parse($docComment->getText());

        return new TypeDeclarations(
            templateNames: array_map(
                /** @param PhpDocTagNode<TemplateTagValueNode> $tag */
                static fn(PhpDocTagNode $tag): string => $tag->value->name,
                $phpDoc->templateTags(),
            ),
            aliasNames: [
                ...array_map(
                    /** @param PhpDocTagNode<TypeAliasTagValueNode> $tag */
                    static fn(PhpDocTagNode $tag): string => $tag->value->alias,
                    $phpDoc->typeAliasTags(),
                ),
                ...array_map(
                    /** @param PhpDocTagNode<TypeAliasImportTagValueNode> $tag */
                    static fn(PhpDocTagNode $tag): string => $tag->value->importedAs ?? $tag->value->importedAlias,
                    $phpDoc->typeAliasImportTags(),
                ),
            ],
        );
    }

    public function parseConstantMetadata(ConstantDeclaration $declaration, CustomTypeResolver $customTypeResolver): ConstantMetadata
    {
        $typeReflector = new PhpDocTypeReflector($declaration->context, $customTypeResolver);
        $phpDoc = $this->parsePhpDoc($declaration->phpDoc);

        return new ConstantMetadata(
            type: $typeReflector->reflectType($phpDoc?->varType()),
            deprecation: $this->annotateDeprecation($phpDoc),
        );
    }

    public function parseFunctionMetadata(FunctionDeclaration $declaration, CustomTypeResolver $customTypeResolver): FunctionMetadata
    {
        $typeReflector = new PhpDocTypeReflector($declaration->context, $customTypeResolver);
        $phpDoc = $this->parsePhpDoc($declaration->phpDoc);

        return new FunctionMetadata(
            returnType: $typeReflector->reflectType($phpDoc?->returnType()),
            throwsTypes: $this->annotatedThrowsTypes($typeReflector, $phpDoc),
            deprecation: $this->annotateDeprecation($phpDoc),
            parameters: $this->annotateParameters($declaration, $typeReflector, $phpDoc),
            templates: $this->annotateTemplates($typeReflector, $phpDoc?->templateTags() ?? []),
        );
    }

    public function parseClassMetadata(ClassDeclaration $declaration, CustomTypeResolver $customTypeResolver): ClassMetadata
    {
        // $typeReflector = new PhpDocTypeReflector($class->context, $customTypeResolver);
        $phpDoc = $this->parsePhpDoc($declaration->phpDoc);

        return new ClassMetadata(
            readonly: $phpDoc?->hasReadonly() ?? false,
        );
    }

    /**
     * @return ($phpDoc is null ? null : PhpDoc)
     */
    private function parsePhpDoc(?SourceCodeSnippet $phpDoc): ?PhpDoc
    {
        if ($phpDoc === null) {
            return null;
        }

        return $this->parser->parse(
            phpDoc: $phpDoc->toString(),
            startLine: $phpDoc->startLine(),
            startPosition: $phpDoc->startPosition(),
        );
    }

    /**
     * @param list<PhpDocTagNode<TemplateTagValueNode>> $tags
     * @return array<non-empty-string, TemplateDeclaration>
     */
    private function annotateTemplates(PhpDocTypeReflector $typeReflector, array $tags): array
    {
        $source = $typeReflector->context->source;
        $templates = [];

        foreach ($tags as $tag) {
            $templates[$tag->value->name] = new TemplateDeclaration(
                variance: match (true) {
                    str_ends_with($tag->name, 'covariant') => Variance::Covariant,
                    str_ends_with($tag->name, 'contravariant') => Variance::Contravariant,
                    default => Variance::Invariant,
                },
                constraint: $typeReflector->reflectType($tag->value->bound) ?? types::mixed,
                snippet: $source instanceof SourceCode ? $source->snippet(PhpDocParser::startPosition($tag), PhpDocParser::endPosition($tag)) : null,
            );
        }

        return $templates;
    }

    /**
     * @return array<non-empty-string, ParameterMetadata>
     */
    private function annotateParameters(FunctionDeclaration $function, PhpDocTypeReflector $typeReflector, ?PhpDoc $functionPhpDoc): array
    {
        $annotatedParameters = [];
        $paramTypes = $functionPhpDoc?->paramTypes() ?? [];

        foreach ($function->parameters as $parameter) {
            $annotatedParameters[$parameter->name] = new ParameterMetadata(
                type: $typeReflector->reflectType($paramTypes[$parameter->name] ?? null),
                deprecation: $this->annotateDeprecation($this->parsePhpDoc($parameter->phpDoc)),
            );
        }

        return $annotatedParameters;
    }

    private function annotateDeprecation(?PhpDoc $phpDoc): ?Deprecation
    {
        $message = $phpDoc?->deprecatedMessage();

        if ($message === null) {
            return null;
        }

        return new Deprecation($message ?: null);
    }

    /**
     * @return list<Type>
     */
    private function annotatedThrowsTypes(PhpDocTypeReflector $typeReflector, ?PhpDoc $phpDoc): array
    {
        return array_map($typeReflector->reflectType(...), $phpDoc?->throwsTypes() ?? []);
    }
}
