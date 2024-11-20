<?php

declare(strict_types=1);

namespace Typhoon\Reflection;

use Typhoon\ChangeDetector\ChangeDetector;
use Typhoon\DeclarationId\ConstantId;
use Typhoon\Reflection\Declaration\ConstantDeclaration;
use Typhoon\Reflection\Declaration\ConstantExpression\ConstantExpression;
use Typhoon\Reflection\Declaration\ConstantExpression\ReflectorEvaluationContext;
use Typhoon\Reflection\Metadata\ConstantMetadata;
use Typhoon\Type\Type;
use Typhoon\Type\types;
use function Typhoon\Reflection\Internal\get_namespace;

/**
 * @api
 */
final class ConstantReflection
{
    public static function from(ConstantDeclaration $constant, ConstantMetadata $metadata): self
    {
        return new self(
            id: $constant->id,
            value: $constant->value,
            extension: $constant->context->source instanceof Extension ? $constant->context->source : null,
            snippet: $constant->snippet,
            phpDoc: $constant->phpDoc,
            annotatedType: $metadata->type,
            deprecation: $metadata->deprecation,
            changeDetector: $constant->context->source->changeDetector,
        );
    }

    /**
     * @var non-empty-string
     */
    public readonly string $name;

    private function __construct(
        public readonly ConstantId $id,
        private readonly ConstantExpression $value,
        private readonly ?Extension $extension,
        private readonly ?SourceCodeSnippet $snippet,
        private readonly ?SourceCodeSnippet $phpDoc,
        private readonly ?Type $annotatedType,
        private readonly ?Deprecation $deprecation,
        private readonly ChangeDetector $changeDetector,
        private readonly ?TyphoonReflector $reflector = null,
    ) {
        $this->name = $id->name;
    }

    public function extension(): ?string
    {
        return $this->extension?->name;
    }

    public function namespace(): string
    {
        return get_namespace($this->name);
    }

    public function changeDetector(): ChangeDetector
    {
        return $this->changeDetector;
    }

    public function isInternallyDefined(): bool
    {
        return $this->extension !== null;
    }

    public function phpDoc(): ?SourceCodeSnippet
    {
        return $this->phpDoc;
    }

    public function snippet(): ?SourceCodeSnippet
    {
        return $this->snippet;
    }

    public function evaluate(): mixed
    {
        \assert($this->reflector !== null);

        return $this->value->evaluate(new ReflectorEvaluationContext($this->reflector));
    }

    public function type(TypeKind $kind = TypeKind::Resolved): ?Type
    {
        return match ($kind) {
            TypeKind::Annotated => $this->annotatedType,
            TypeKind::Resolved => $this->annotatedType ?? types::mixed,
            default => null,
        };
    }

    public function isDeprecated(): bool
    {
        return $this->deprecation !== null;
    }

    public function deprecation(): ?Deprecation
    {
        return $this->deprecation;
    }

    /**
     * @internal
     * @psalm-internal Typhoon\Reflection
     */
    public function __load(TyphoonReflector $reflector): self
    {
        \assert($this->reflector === null);

        $arguments = get_object_vars($this);
        unset($arguments['name']);
        $arguments['reflector'] = $reflector;

        return new self(...$arguments);
    }
}
