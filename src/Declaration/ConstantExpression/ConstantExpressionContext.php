<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Declaration\ConstantExpression;

use Typhoon\DeclarationId\AnonymousFunctionId;
use Typhoon\DeclarationId\MethodId;
use Typhoon\DeclarationId\NamedFunctionId;
use Typhoon\Reflection\Declaration\Context;
use Typhoon\Reflection\SourceCode;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 * @todo add full anonymous classes and parent::class support
 */
final class ConstantExpressionContext
{
    private const ANONYMOUS_FUNCTION_NAME = '{closure}';

    public function __construct(
        private readonly Context $context,
    ) {}

    /**
     * @return ConstantExpression<string>
     */
    public function __FILE__(): ConstantExpression
    {
        if (!$this->context->source instanceof SourceCode) {
            throw new \LogicException();
        }

        return new Value($this->context->source->file->path);
    }

    /**
     * @return ConstantExpression<string>
     */
    public function __DIR__(): ConstantExpression
    {
        if (!$this->context->source instanceof SourceCode) {
            throw new \LogicException();
        }

        return new Value($this->context->source->file->directory());
    }

    /**
     * @return ConstantExpression<string>
     */
    public function __NAMESPACE__(): ConstantExpression
    {
        return new Value($this->context->namespace());
    }

    /**
     * @return ConstantExpression<string>
     */
    public function __FUNCTION__(): ConstantExpression
    {
        $id = $this->context->id;

        if ($id instanceof NamedFunctionId) {
            return new Value($id->name);
        }

        if ($id instanceof AnonymousFunctionId) {
            $namespace = $this->context->namespace();

            if ($namespace === '') {
                return new Value(self::ANONYMOUS_FUNCTION_NAME);
            }

            return new Value($namespace . '\\' . self::ANONYMOUS_FUNCTION_NAME);
        }

        if ($id instanceof MethodId) {
            return new Value($id->name);
        }

        return new Value('');
    }

    /**
     * @return Value<string>|MagicClassInTrait
     */
    public function __CLASS__(): Value|MagicClassInTrait
    {
        if ($this->context->self !== null) {
            // todo anonymous
            return new Value($this->context->self->name ?? throw new \LogicException('anonymous'));
        }

        if ($this->context->trait !== null) {
            return new MagicClassInTrait($this->context->trait->name);
        }

        return new Value('');
    }

    /**
     * @return ConstantExpression<string>
     */
    public function __TRAIT__(): ConstantExpression
    {
        return new Value($this->context->trait?->name ?? '');
    }

    /**
     * @return ConstantExpression<string>
     */
    public function __METHOD__(): ConstantExpression
    {
        $id = $this->context->id;

        if (!$id instanceof MethodId) {
            return new Value('');
        }

        return new Value(\sprintf('%s::%s', $id->class->name ?? '', $id->name));
    }

    /**
     * @return ConstantExpression<non-empty-string>
     */
    public function self(): ConstantExpression
    {
        if ($this->context->self !== null) {
            return new Value($this->context->self->name ?? throw new \LogicException('Anonymous'));
        }

        if ($this->context->trait !== null) {
            return new SelfInTrait($this->context->trait);
        }

        throw new \LogicException('No parent!');
    }

    /**
     * @return ConstantExpression<non-empty-string>
     */
    public function parent(): ConstantExpression
    {
        if ($this->context->parent !== null) {
            return new Value($this->context->parent->name);
        }

        if ($this->context->trait !== null) {
            return ParentClassInTrait::Instance;
        }

        throw new \LogicException('No parent!');
    }

    public function static(): never
    {
        throw new \LogicException('Unexpected static type usage in a constant expression');
    }
}
