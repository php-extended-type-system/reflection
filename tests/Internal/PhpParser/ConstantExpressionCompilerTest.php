<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Internal\PhpParser;

use PhpParser\Node;
use PhpParser\Node\Const_;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Expression as StmtExpr;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Typhoon\Reflection\Declaration\ConstantExpression\ConstantExpression;
use Typhoon\Reflection\Declaration\ConstantExpression\RuntimeEvaluationContext;
use Typhoon\Reflection\Declaration\Context;
use Typhoon\Reflection\File;
use Typhoon\Reflection\SourceCode;

#[CoversClass(ConstantExpressionParser::class)]
final class ConstantExpressionCompilerTest extends TestCase
{
    private static ?Parser $parser = null;

    #[TestWith(['null'])]
    #[TestWith(['true'])]
    #[TestWith(['false'])]
    #[TestWith(['!false'])]
    #[TestWith(['!true'])]
    #[TestWith(['-1'])]
    #[TestWith(['0'])]
    #[TestWith(['1'])]
    #[TestWith(['1 + 2 + 3'])]
    #[TestWith(['1 + -2 + 3'])]
    #[TestWith(['-1 - 3 - 123.5'])]
    #[TestWith(['+10'])]
    #[TestWith(['0.543'])]
    #[TestWith(["''"])]
    #[TestWith(["'a'"])]
    #[TestWith(['"a"'])]
    #[TestWith(['"a"."b"'])]
    #[TestWith(['1 / 2'])]
    #[TestWith(['1 / 2 / 3'])]
    #[TestWith(['new stdClass()'])]
    #[TestWith(['new ArrayObject([1,2,3])'])]
    #[TestWith(["new Exception(code: 123, message: 'message')"])]
    #[TestWith(['new \stdClass()'])]
    #[TestWith(['new ("std"."Class")()'])]
    #[TestWith(['new ("std"."Class")()'])]
    #[TestWith(['PHP_VERSION_ID'])]
    #[TestWith(['ArrayObject::ARRAY_AS_PROPS'])]
    #[TestWith(['true ? 1 : 2'])]
    #[TestWith(['true ?: 2'])]
    #[TestWith(['(1 + 2) ?: 2'])]
    #[TestWith(['~0b01'])]
    #[TestWith(['0b11 & 0b1'])]
    #[TestWith(['0b11 << 0b1'])]
    #[TestWith(['0b1100000 >> 0b1'])]
    #[TestWith(['0b01 | 0b10'])]
    #[TestWith(['0b01 ^ 0b10'])]
    #[TestWith(['true && false'])]
    #[TestWith(['true and false'])]
    #[TestWith(['true || false'])]
    #[TestWith(['true or false'])]
    #[TestWith(['true xor false'])]
    #[TestWith(["1 == '1'"])]
    #[TestWith(["1 != '1'"])]
    #[TestWith(['10 < 2'])]
    #[TestWith(['10 > 2'])]
    #[TestWith(['10 >= 2'])]
    #[TestWith(['10 <= 2'])]
    #[TestWith(["10 === '2'"])]
    #[TestWith(["10 <=> '2'"])]
    #[TestWith(["10 !== '2'"])]
    #[TestWith(['10 % 2'])]
    #[TestWith(['10 * 2'])]
    #[TestWith(['10 ** 2'])]
    #[TestWith(['[]'])]
    #[TestWith(['[1 => 1]'])]
    #[TestWith(["[1 => 1 + 1, 'a' => 'b' . 'c']"])]
    #[TestWith(['[[1, 2, 3]]'])]
    #[TestWith(['[...[1, 2, 3]]'])]
    #[TestWith(['__LINE__'])]
    #[TestWith(['__CLASS__'])]
    #[TestWith(['__TRAIT__'])]
    #[TestWith(['__FUNCTION__'])]
    #[TestWith(['__METHOD__'])]
    #[TestWith(['null ?? 1'])]
    #[TestWith(['[1][0]'])]
    #[TestWith(['[1][1] ?? 2'])]
    #[TestWith(['stdClass::class'])]
    public function testItCompilesBasicExpressions(string $code): void
    {
        $expected = self::eval("return {$code};");
        $compiled = $this->compile("<?php {$code};", static fn(Node $node): \Generator => $node instanceof StmtExpr ? yield $node->expr : null);

        $evaluated = $compiled[0]->evaluate(new RuntimeEvaluationContext());

        self::assertEquals($expected, $evaluated);
    }

    public function testItCompilesDynamicClassConstantFetch(): void
    {
        $compiled = $this->compile(
            "<?php ('Array'.'Object')::{'ARRAY'.'_AS_PROPS'};",
            static fn(Node $node): \Generator => $node instanceof StmtExpr ? yield $node->expr : null,
        );

        $evaluated = $compiled[0]->evaluate(new RuntimeEvaluationContext());

        self::assertEquals(\ArrayObject::ARRAY_AS_PROPS, $evaluated);
    }

    public function testItCompilesConstantsInFunctionScope(): void
    {
        $compiled = $this->compile(
            <<<'PHP'
                <?php
                function a(
                    $__FUNCTION__ = __FUNCTION__,
                    $__CLASS__ = __CLASS__,
                    $__TRAIT__ = __TRAIT__,
                    $__METHOD__ = __METHOD__,
                ) {}
                PHP,
            static function (Node $node): \Generator {
                if ($node instanceof Param) {
                    \assert($node->var instanceof Variable && \is_string($node->var->name));
                    \assert($node->default !== null);
                    yield $node->var->name => $node->default;
                }
            },
        );

        $evaluated = array_map(
            static fn(ConstantExpression $expression): mixed => $expression->evaluate(new RuntimeEvaluationContext()),
            $compiled,
        );

        self::assertSame(
            [
                '__FUNCTION__' => 'a',
                '__CLASS__' => '',
                '__TRAIT__' => '',
                '__METHOD__' => '',
            ],
            $evaluated,
        );
    }

    public function testItCompilesConstantsInAnonymousFunctionInsideClassScope(): void
    {
        $compiled = $this->compile(
            <<<'PHP'
                <?php
                namespace NS;
                class B {
                    public function b() {
                        function (
                            $__FUNCTION__ = __FUNCTION__,
                            $__CLASS__ = __CLASS__,
                            $__TRAIT__ = __TRAIT__,
                            $__METHOD__ = __METHOD__,
                        ) {};
                    }
                }
                PHP,
            static function (Node $node): \Generator {
                if ($node instanceof Param) {
                    \assert($node->var instanceof Variable && \is_string($node->var->name));
                    \assert($node->default !== null);
                    yield $node->var->name => $node->default;
                }
            },
        );

        $evaluated = array_map(
            static fn(ConstantExpression $expression): mixed => $expression->evaluate(new RuntimeEvaluationContext()),
            $compiled,
        );

        self::assertSame(
            [
                '__FUNCTION__' => 'NS\{closure}',
                '__CLASS__' => 'NS\B',
                '__TRAIT__' => '',
                '__METHOD__' => '',
            ],
            $evaluated,
        );
    }

    public function testItCompilesConstantsInClassScope(): void
    {
        $compiled = $this->compile(
            <<<'PHP'
                <?php
                class A extends ArrayObject {
                    const self = self::class;
                    const parent = parent::class;
                    const __CLASS__ = __CLASS__;
                    const __TRAIT__ = __TRAIT__;
                    const __FUNCTION__ = __FUNCTION__;
                    const __METHOD__ = __METHOD__;
                }
                PHP,
            static fn(Node $node): \Generator => $node instanceof Const_ ? yield $node->name->name => $node->value : null,
        );

        $evaluated = array_map(
            static fn(ConstantExpression $expression): mixed => $expression->evaluate(new RuntimeEvaluationContext()),
            $compiled,
        );

        self::assertSame(
            [
                'self' => 'A',
                'parent' => 'ArrayObject',
                '__CLASS__' => 'A',
                '__TRAIT__' => '',
                '__FUNCTION__' => '',
                '__METHOD__' => '',
            ],
            $evaluated,
        );
    }

    public function testItDoesNotCompileStaticClassConstantInClassScope(): void
    {
        $this->expectExceptionMessage('Unexpected static type usage in a constant expression');

        $this->compile(
            <<<'PHP'
                <?php
                class A { const STATIC_ = static::class; }
                PHP,
            static fn(Node $node): \Generator => $node instanceof Const_ ? yield $node->value : null,
        );
    }

    public function testItCompilesConstantsInTraitScope(): void
    {
        $compiled = $this->compile(
            <<<'PHP'
                <?php
                trait A {
                    const self = self::class;
                    const __CLASS__ = __CLASS__;
                    const __TRAIT__ = __TRAIT__;
                    const __FUNCTION__ = __FUNCTION__;
                    const __METHOD__ = __METHOD__;
                }
                PHP,
            static fn(Node $node): \Generator => $node instanceof Const_ ? yield $node->name->name => $node->value : null,
        );

        $evaluated = array_map(
            static fn(ConstantExpression $expression): mixed => $expression->evaluate(new RuntimeEvaluationContext()),
            $compiled,
        );

        self::assertSame(
            [
                'self' => 'A',
                '__CLASS__' => 'A',
                '__TRAIT__' => 'A',
                '__FUNCTION__' => '',
                '__METHOD__' => '',
            ],
            $evaluated,
        );
    }

    public function testItDoesNotCompileStaticClassConstantInTraitScope(): void
    {
        $this->expectExceptionMessage('Unexpected static type usage in a constant expression');

        $this->compile(
            <<<'PHP'
                <?php
                trait A { const STATIC_ = static::class; }
                PHP,
            static fn(Node $node): \Generator => $node instanceof Const_ ? yield $node->value : null,
        );
    }

    /**
     * @param \Closure(Node): \Generator<array-key, Expr> $expressionFinder
     * @return array<ConstantExpression>
     */
    private function compile(string $code, \Closure $expressionFinder): array
    {
        self::$parser ??= (new ParserFactory())->createForHostVersion();
        $nodes = self::$parser->parse($code) ?? [];

        $nameResolver = new NameResolver();
        $contextVisitor = new ContextVisitor(
            baseContext: Context::start(SourceCode::fromFile(File::fromContents($code))),
            nameContext: $nameResolver->getNameContext(),
        );
        $findAndCompile = new FindAndCompileVisitor($contextVisitor, $expressionFinder);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($nameResolver);
        $traverser->addVisitor($contextVisitor);
        $traverser->addVisitor($findAndCompile);
        $traverser->traverse($nodes);

        return $findAndCompile->expressions;
    }

    private static function eval(string $code): mixed
    {
        /** @psalm-suppress ForbiddenCode */
        return eval($code);
    }
}
