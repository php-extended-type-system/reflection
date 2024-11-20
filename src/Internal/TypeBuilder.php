<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Internal;

use Typhoon\Reflection\Internal\Reflection\TypeReflection;
use Typhoon\Type\Type;
use Typhoon\Type\TypeVisitor;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 */
final class TypeBuilder
{
    private ?TypeReflection $own = null;

    /**
     * @var list<array{TypeReflection, TypeVisitor<Type>}>
     */
    private array $inherited = [];

    public function setOwn(TypeReflection $type): void
    {
        $this->own = $type;
    }

    /**
     * @param TypeVisitor<Type> $typeResolver
     */
    public function addInherited(TypeReflection $type, TypeVisitor $typeResolver): void
    {
        $this->inherited[] = [$type, $typeResolver];
    }

    public function build(): TypeReflection
    {
        if ($this->own !== null) {
            if ($this->own->annotated !== null) {
                return $this->own;
            }

            if ($this->own->tentative !== null) {
                return $this->own;
            }

            $ownResolved = $this->own->resolved();

            foreach ($this->inherited as [$inherited, $typeResolver]) {
                // If own type is different (weakened parameter type or strengthened return type), we want to keep it.
                // This should be compared according to variance with a proper type comparator,
                // but for now simple inequality check should do the job 90% of the time.
                if (!self::typesEqual($inherited->native, $this->own->native)) {
                    continue;
                }

                // If inherited type resolves to equal own type, we should continue to look for something more interesting.
                if (self::typesEqual($inherited->resolved(), $ownResolved)) {
                    continue;
                }

                return new TypeReflection(
                    native: $inherited->native?->accept($typeResolver),
                    annotated: $inherited->annotated?->accept($typeResolver),
                );
            }

            return $this->own;
        }

        \assert($this->inherited !== []);

        if (\count($this->inherited) !== 1) {
            foreach ($this->inherited as [$inherited, $typeResolver]) {
                // If inherited type resolves to its native type, we should continue to look for something more interesting.
                if (self::typesEqual($inherited->resolved(), $inherited->native)) {
                    continue;
                }

                return new TypeReflection(
                    native: $inherited->native?->accept($typeResolver),
                    annotated: $inherited->annotated?->accept($typeResolver),
                    tentative: $inherited->tentative?->accept($typeResolver),
                );
            }
        }

        [$inherited, $typeResolver] = $this->inherited[0];

        return new TypeReflection(
            native: $inherited->native?->accept($typeResolver),
            annotated: $inherited->annotated?->accept($typeResolver),
            tentative: $inherited->tentative?->accept($typeResolver),
        );
    }

    private static function typesEqual(?Type $a, ?Type $b): bool
    {
        // Comparison operator == is intentionally used here.
        // Of course, we need a proper type comparator,
        // but for now simple equality check should do the job 90% of the time.
        return $a == $b;
    }
}
