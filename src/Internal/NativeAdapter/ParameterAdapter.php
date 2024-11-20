<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Internal\NativeAdapter;

use Typhoon\DeclarationId\AnonymousFunctionId;
use Typhoon\DeclarationId\MethodId;
use Typhoon\DeclarationId\NamedFunctionId;
use Typhoon\Reflection\Declaration\ConstantExpression\ConstantExpression;
use Typhoon\Reflection\Internal\Type\IsNativeTypeNullable;
use Typhoon\Reflection\ParameterReflection;
use Typhoon\Reflection\TypeKind;
use Typhoon\Reflection\TyphoonReflector;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 * @property-read non-empty-string $name
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class ParameterAdapter extends \ReflectionParameter
{
    public function __construct(
        private readonly ParameterReflection $reflection,
        private readonly TyphoonReflector $reflector,
        private readonly ?ConstantExpression $default,
    ) {
        unset($this->name);
    }

    /**
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function __get(string $name): mixed
    {
        return match ($name) {
            'name' => $this->reflection->id->name,
            default => new \LogicException(\sprintf('Undefined property %s::$%s', self::class, $name)),
        };
    }

    public function __isset(string $name): bool
    {
        return $name === 'name';
    }

    public function __toString(): string
    {
        $this->loadNative();

        return parent::__toString();
    }

    public function allowsNull(): bool
    {
        $nativeType = $this->reflection->type(TypeKind::Native);

        return $nativeType === null || $nativeType->accept(new IsNativeTypeNullable());
    }

    public function canBePassedByValue(): bool
    {
        return $this->reflection->canBePassedByValue();
    }

    public function getAttributes(?string $name = null, int $flags = 0): array
    {
        return AttributeAdapter::fromList($this->reflection->attributes(), $name, $flags);
    }

    public function getClass(): ?\ReflectionClass
    {
        $this->loadNative();

        return parent::getClass();
    }

    public function getDeclaringClass(): ?\ReflectionClass
    {
        $methodId = $this->reflection->declarationId->function;

        if (!$methodId instanceof MethodId) {
            return null;
        }

        $declaringClass = $this->reflector->reflectClass($methodId->class);

        if ($declaringClass->isAnonymous() || $declaringClass->isTrait()) {
            return $this->reflection->class()?->toNativeReflection();
        }

        return $declaringClass->toNativeReflection();
    }

    public function getDeclaringFunction(): \ReflectionFunctionAbstract
    {
        return $this->reflection->function()->toNativeReflection();
    }

    public function getDefaultValue(): mixed
    {
        return $this->reflection->evaluateDefault();
    }

    public function getDefaultValueConstantName(): ?string
    {
        $this->loadNative();

        // TODO
        return parent::getDefaultValueConstantName();
    }

    public function getName(): string
    {
        return $this->reflection->id->name;
    }

    public function getPosition(): int
    {
        return $this->reflection->index();
    }

    public function getType(): ?\ReflectionType
    {
        return $this->reflection->type(TypeKind::Native)?->accept(new ToNativeTypeConverter());
    }

    public function hasType(): bool
    {
        return $this->reflection->type(TypeKind::Native) !== null;
    }

    public function isArray(): bool
    {
        $this->loadNative();

        return parent::isArray();
    }

    public function isCallable(): bool
    {
        $this->loadNative();

        return parent::isCallable();
    }

    public function isDefaultValueAvailable(): bool
    {
        return $this->reflection->hasDefaultValue();
    }

    public function isDefaultValueConstant(): bool
    {
        $this->loadNative();

        // TODO
        return parent::isDefaultValueConstant();
    }

    public function isOptional(): bool
    {
        return $this->reflection->isOptional();
    }

    public function isPassedByReference(): bool
    {
        return $this->reflection->canBePassedByReference();
    }

    public function isPromoted(): bool
    {
        return $this->reflection->isPromoted();
    }

    public function isVariadic(): bool
    {
        return $this->reflection->isVariadic();
    }

    private bool $nativeLoaded = false;

    private function loadNative(): void
    {
        if ($this->nativeLoaded) {
            return;
        }

        $functionId = $this->reflection->id->function;

        if ($functionId instanceof NamedFunctionId) {
            parent::__construct($functionId->name, $this->name);
            $this->nativeLoaded = true;

            return;
        }

        if ($functionId instanceof AnonymousFunctionId) {
            throw new \LogicException(\sprintf('Cannot natively reflect %s', $functionId->describe()));
        }

        $class = $functionId->class->name ?? throw new \LogicException(\sprintf(
            "Cannot natively reflect %s, because it's runtime name is not available",
            $functionId->class->describe(),
        ));

        parent::__construct([$class, $functionId->name], $this->name);
        $this->nativeLoaded = true;
    }
}
