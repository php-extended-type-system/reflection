<?php

declare(strict_types=1);

namespace Typhoon\Reflection;

use PhpParser\Parser;
use PhpParser\ParserFactory;
use Psr\SimpleCache\CacheInterface;
use Typhoon\DeclarationId\AliasId;
use Typhoon\DeclarationId\AnonymousClassId;
use Typhoon\DeclarationId\ClassConstantId;
use Typhoon\DeclarationId\Id;
use Typhoon\DeclarationId\MethodId;
use Typhoon\DeclarationId\NamedClassId;
use Typhoon\DeclarationId\NamedFunctionId;
use Typhoon\DeclarationId\ParameterId;
use Typhoon\DeclarationId\PropertyId;
use Typhoon\DeclarationId\TemplateId;
use Typhoon\PhpStormReflectionStubs\PhpStormStubsLocator;
use Typhoon\Reflection\Cache\InMemoryCache;
use Typhoon\Reflection\Exception\DeclarationNotFound;
use Typhoon\Reflection\Internal\Cache;
use Typhoon\Reflection\Internal\CodeReflector;
use Typhoon\Reflection\Internal\CompleteReflection\CleanUpInternallyDefined;
use Typhoon\Reflection\Internal\CompleteReflection\CompleteEnum;
use Typhoon\Reflection\Internal\CompleteReflection\CopyPromotedParametersToProperties;
use Typhoon\Reflection\Internal\CompleteReflection\RemoveCode;
use Typhoon\Reflection\Internal\CompleteReflection\RemoveContext;
use Typhoon\Reflection\Internal\CompleteReflection\SetAttributesRepeated;
use Typhoon\Reflection\Internal\CompleteReflection\SetInterfaceMethodsAbstract;
use Typhoon\Reflection\Internal\CompleteReflection\SetParametersIndexes;
use Typhoon\Reflection\Internal\CompleteReflection\SetReadonlyClassPropertiesReadonly;
use Typhoon\Reflection\Internal\CompleteReflection\SetStringableInterface;
use Typhoon\Reflection\Internal\CompleteReflection\SetTemplatesIndexes;
use Typhoon\Reflection\Internal\Hooks;
use Typhoon\Reflection\Internal\Inheritance\ResolveClassInheritance;
use Typhoon\Reflection\Internal\Locators;
use Typhoon\Reflection\Internal\PhpDoc\PhpDocReflector;
use Typhoon\Reflection\Internal\ReflectorSession;
use Typhoon\Reflection\Locator\AnonymousLocator;
use Typhoon\Reflection\Locator\ComposerLocator;
use Typhoon\Reflection\Locator\ConstantLocator;
use Typhoon\Reflection\Locator\DeterministicLocator;
use Typhoon\Reflection\Locator\DontAutoloadClassLocator;
use Typhoon\Reflection\Locator\FileAnonymousLocator;
use Typhoon\Reflection\Locator\NamedClassLocator;
use Typhoon\Reflection\Locator\NamedFunctionLocator;
use Typhoon\Reflection\Locator\NativeReflectionClassLocator;
use Typhoon\Reflection\Locator\NativeReflectionFunctionLocator;
use Typhoon\Reflection\Locator\Resource;

/**
 * @api
 */
final class TyphoonReflector
{
    /**
     * @param ?list<ConstantLocator|NamedFunctionLocator|NamedClassLocator|AnonymousLocator> $locators
     */
    public static function build(
        ?array $locators = null,
        CacheInterface $cache = new InMemoryCache(),
        ?Parser $phpParser = null,
    ): self {
        $phpDocReflector = new PhpDocReflector();

        return new self(
            codeReflector: new CodeReflector(
                phpParser: $phpParser ?? (new ParserFactory())->createForHostVersion(),
                annotatedTypesDriver: $phpDocReflector,
            ),
            locators: new Locators($locators ?? self::defaultLocators()),
            cache: new Cache($cache),
            hooks: new Hooks([
                $phpDocReflector,
                CopyPromotedParametersToProperties::Instance,
                CompleteEnum::Instance,
                SetStringableInterface::Instance,
                SetInterfaceMethodsAbstract::Instance,
                SetReadonlyClassPropertiesReadonly::Instance,
                SetAttributesRepeated::Instance,
                SetParametersIndexes::Instance,
                SetTemplatesIndexes::Instance,
                RemoveContext::Instance,
                RemoveCode::Instance,
                CleanUpInternallyDefined::Instance,
                ResolveClassInheritance::Instance,
            ]),
        );
    }

    /**
     * @return list<ConstantLocator|NamedFunctionLocator|NamedClassLocator|AnonymousLocator>
     */
    public static function defaultLocators(): array
    {
        $locators = [];

        if (class_exists(PhpStormStubsLocator::class)) {
            $locators[] = new PhpStormStubsLocator();
        }

        if (ComposerLocator::isSupported()) {
            $locators[] = new ComposerLocator();
        }

        $locators[] = new DontAutoloadClassLocator(new NativeReflectionClassLocator());
        $locators[] = new NativeReflectionFunctionLocator();
        $locators[] = new FileAnonymousLocator();

        return $locators;
    }

    private function __construct(
        private readonly CodeReflector $codeReflector,
        private readonly Locators $locators,
        private readonly Cache $cache,
        private readonly Hooks $hooks,
    ) {}

    /**
     * @param non-empty-string $name
     * @psalm-assert-if-true callable-string $name
     */
    public function functionExists(string $name): bool
    {
        try {
            $this->reflectFunction($name);

            return true;
        } catch (DeclarationNotFound) {
            return false;
        }
    }

    /**
     * @template T of object
     * @param non-empty-string $name
     * @throws DeclarationNotFound
     */
    public function reflectFunction(string $name): FunctionReflection
    {
        return $this->reflect(Id::namedFunction($name));
    }

    /**
     * @param non-empty-string $class
     * @psalm-assert-if-true class-string $class
     */
    public function classExists(string $class): bool
    {
        try {
            $this->reflectClass($class);

            return true;
        } catch (DeclarationNotFound) {
            return false;
        }
    }

    /**
     * @template TObject of object
     * @param non-empty-string|class-string<TObject> $name
     * @return ($name is class-string<TObject>
     *     ? ClassReflection<TObject, NamedClassId<class-string<TObject>>|AnonymousClassId<class-string<TObject>>>
     *     : ClassReflection<object, NamedClassId<class-string>|AnonymousClassId<?class-string>>)
     * @throws DeclarationNotFound
     */
    public function reflectClass(string $name): ClassReflection
    {
        return $this->reflect(Id::class($name));
    }

    /**
     * @param non-empty-string $file
     * @param positive-int $line
     * @param ?positive-int $column
     * @return ClassReflection<object, AnonymousClassId<null>>
     * @throws DeclarationNotFound
     */
    public function reflectAnonymousClass(string $file, int $line, ?int $column = null): ClassReflection
    {
        /** @var ClassReflection<object, AnonymousClassId<null>> */
        return $this->reflect(Id::anonymousClass($file, $line, $column));
    }

    /**
     * @psalm-suppress InvalidReturnType, InvalidReturnStatement
     * @return (
     *     $id is NamedFunctionId ? FunctionReflection :
     *     $id is NamedClassId ? ClassReflection<object, NamedClassId<class-string>> :
     *     $id is AnonymousClassId<null> ? ClassReflection<object, AnonymousClassId<null>> :
     *     $id is AnonymousClassId<class-string> ? ClassReflection<object, AnonymousClassId<class-string>> :
     *     $id is ClassConstantId ? ClassConstantReflection :
     *     $id is PropertyId ? PropertyReflection :
     *     $id is MethodId ? MethodReflection :
     *     $id is ParameterId ? ParameterReflection :
     *     $id is AliasId ? AliasReflection :
     *     $id is TemplateId ? TemplateReflection :
     *     never
     * )
     * @throws DeclarationNotFound
     */
    public function reflect(Id $id): FunctionReflection|ClassReflection|ClassConstantReflection|PropertyReflection|MethodReflection|ParameterReflection|AliasReflection|TemplateReflection
    {
        if ($id instanceof NamedFunctionId) {
            $data = ReflectorSession::reflectId(
                codeReflector: $this->codeReflector,
                locators: $this->locators,
                cache: $this->cache,
                hooks: $this->hooks,
                id: $id,
            );

            return new FunctionReflection($id, $data, $this);
        }

        if ($id instanceof NamedClassId || $id instanceof AnonymousClassId) {
            $data = ReflectorSession::reflectId(
                codeReflector: $this->codeReflector,
                locators: $this->locators,
                cache: $this->cache,
                hooks: $this->hooks,
                id: $id,
            );

            /** @var NamedClassId<class-string>|AnonymousClassId<?class-string> $id */
            return new ClassReflection($id, $data, $this);
        }

        return match (true) {
            $id instanceof PropertyId => $this->reflect($id->class)->properties()[$id->name],
            $id instanceof ClassConstantId => $this->reflect($id->class)->constants()[$id->name],
            $id instanceof MethodId => $this->reflect($id->class)->methods()[$id->name],
            $id instanceof ParameterId => $this->reflect($id->function)->parameters()[$id->name],
            $id instanceof AliasId => $this->reflect($id->class)->aliases()[$id->name],
            $id instanceof TemplateId => $this->reflect($id->declaration)->templates()[$id->name],
            default => throw new \LogicException($id->describe() . ' is not supported yet'),
        };
    }

    public function withResource(Resource $resource): self
    {
        return new self(
            codeReflector: $this->codeReflector,
            locators: $this->locators->with(
                new DeterministicLocator(
                    ReflectorSession::reflectResource(
                        codeReflector: $this->codeReflector,
                        locators: $this->locators,
                        cache: $this->cache,
                        hooks: $this->hooks,
                        resource: $resource,
                    ),
                ),
            ),
            cache: $this->cache,
            hooks: $this->hooks,
        );
    }
}
