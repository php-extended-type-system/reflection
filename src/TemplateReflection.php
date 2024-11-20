<?php

declare(strict_types=1);

namespace Typhoon\Reflection;

use Typhoon\DeclarationId\AnonymousClassId;
use Typhoon\DeclarationId\AnonymousFunctionId;
use Typhoon\DeclarationId\Id;
use Typhoon\DeclarationId\MethodId;
use Typhoon\DeclarationId\NamedClassId;
use Typhoon\DeclarationId\NamedFunctionId;
use Typhoon\DeclarationId\TemplateId;
use Typhoon\Reflection\Metadata\TemplateDeclaration;
use Typhoon\Type\Type;
use Typhoon\Type\Variance;

/**
 * @api
 * @psalm-import-type Templates from TyphoonReflector
 */
final class TemplateReflection
{
    /**
     * @param array<non-empty-string, TemplateDeclaration> $templates
     * @return Templates
     */
    public static function from(
        NamedFunctionId|AnonymousFunctionId|NamedClassId|AnonymousClassId|MethodId $declarationId,
        array $templates,
    ): Collection {
        $reflections = [];
        $index = 0;

        foreach ($templates as $name => $template) {
            $reflections[$name] = new self(
                id: Id::template($declarationId, $name),
                index: $index++,
                variance: $template->variance,
                constraint: $template->constraint,
                snippet: $template->snippet,
            );
        }

        return new Collection($reflections);
    }

    /**
     * @param non-negative-int $index
     */
    private function __construct(
        public readonly TemplateId $id,
        private readonly int $index,
        private readonly Variance $variance,
        private readonly Type $constraint,
        private readonly ?SourceCodeSnippet $snippet,
    ) {}

    /**
     * @return non-negative-int
     */
    public function index(): int
    {
        return $this->index;
    }

    public function variance(): Variance
    {
        return $this->variance;
    }

    public function constraint(): Type
    {
        return $this->constraint;
    }

    public function snippet(): ?SourceCodeSnippet
    {
        return $this->snippet;
    }
}
