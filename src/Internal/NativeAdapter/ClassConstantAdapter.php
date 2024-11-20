<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Internal\NativeAdapter;

use Typhoon\Reflection\ClassConstantReflection;
use Typhoon\Reflection\ModifierKind;
use Typhoon\Reflection\TypeKind;
use Typhoon\Reflection\TyphoonReflector;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 * @property-read non-empty-string $name
 * @property-read class-string $class
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class ClassConstantAdapter extends \ReflectionClassConstant
{
    public function __construct(
        private readonly ClassConstantReflection $reflection,
        private readonly TyphoonReflector $reflector,
    ) {
        unset($this->name, $this->class);
    }

    public function __get(string $name)
    {
        return match ($name) {
            'name' => $this->reflection->name,
            'class' => $this->getDeclaringClass()->name,
            default => new \LogicException(\sprintf('Undefined property %s::$%s', self::class, $name)),
        };
    }

    public function __isset(string $name): bool
    {
        return $name === 'name' || $name === 'class';
    }

    public function __toString(): string
    {
        $this->loadNative();

        return parent::__toString();
    }

    public function getAttributes(?string $name = null, int $flags = 0): array
    {
        return AttributeAdapter::fromList($this->reflection->attributes(), $name, $flags);
    }

    public function getDeclaringClass(): \ReflectionClass
    {
        $declaringClass = $this->reflector->reflectClass($this->reflection->declarationId->class);

        if ($declaringClass->isAnonymous() || $declaringClass->isTrait()) {
            return $this->reflection->class()->toNativeReflection();
        }

        return $declaringClass->toNativeReflection();
    }

    public function getDocComment(): string|false
    {
        return $this->reflection->phpDoc()?->toString() ?? false;
    }

    public function getModifiers(): int
    {
        return ($this->isPublic() ? self::IS_PUBLIC : 0)
            | ($this->isProtected() ? self::IS_PROTECTED : 0)
            | ($this->isPrivate() ? self::IS_PRIVATE : 0)
            | ($this->isFinal() ? self::IS_FINAL : 0);
    }

    public function getName(): string
    {
        return $this->reflection->id->name;
    }

    /**
     * @psalm-suppress PossiblyUnusedMethod, UnusedPsalmSuppress
     */
    public function getType(): ?\ReflectionType
    {
        return $this->reflection->type(TypeKind::Native)?->accept(new ToNativeTypeConverter());
    }

    public function getValue(): mixed
    {
        return $this->reflection->evaluate();
    }

    /**
     * @psalm-suppress PossiblyUnusedMethod, UnusedPsalmSuppress
     */
    public function hasType(): bool
    {
        return $this->reflection->type(TypeKind::Native) !== null;
    }

    public function isEnumCase(): bool
    {
        return $this->reflection->isEnumCase();
    }

    public function isFinal(): bool
    {
        return $this->reflection->isFinal(ModifierKind::Native);
    }

    public function isPrivate(): bool
    {
        return $this->reflection->isPrivate();
    }

    public function isProtected(): bool
    {
        return $this->reflection->isProtected();
    }

    public function isPublic(): bool
    {
        return $this->reflection->isPublic();
    }

    private bool $nativeLoaded = false;

    private function loadNative(): void
    {
        if ($this->nativeLoaded) {
            return;
        }

        $class = $this->reflection->id->class->name ?? throw new \LogicException(\sprintf(
            "Cannot natively reflect %s, because it's runtime name is not available",
            $this->reflection->id->class->describe(),
        ));

        /** @psalm-suppress ArgumentTypeCoercion */
        parent::__construct($class, $this->name);
        $this->nativeLoaded = true;
    }
}
