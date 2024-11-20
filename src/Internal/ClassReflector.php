<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Internal;

use Typhoon\DeclarationId\AnonymousClassId;
use Typhoon\DeclarationId\NamedClassId;
use Typhoon\Reflection\ClassReflection;
use Typhoon\Reflection\Declaration\ClassDeclaration;
use Typhoon\Reflection\Exception\DeclarationNotFound;
use Typhoon\Reflection\File;
use Typhoon\Reflection\Internal\Metadata\MetadataLoader;
use Typhoon\Reflection\Locator\ClassLocator;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 */
final class ClassReflector
{
    /**
     * @var array<non-empty-string, ClassDeclaration|ClassReflection>
     */
    private array $data = [];

    /**
     * @var array<non-empty-string, non-empty-list<positive-int>>
     */
    private array $anonymousClassColumns = [];

    public function __construct(
        private readonly ClassLocator $locator,
        private readonly FileParser $fileParser,
        private readonly MetadataLoader $metadataLoader,
    ) {
        $weakReflector = \WeakReference::create($this);
        $fileParser->subscribe(static function (object $declaration) use ($weakReflector): void {
            if (!$declaration instanceof ClassDeclaration) {
                return;
            }

            $reflector = $weakReflector->get() ?? throw new \LogicException('This should never happen');
            $reflector->data[$declaration->id->encode()] = $declaration;

            if ($declaration->id instanceof AnonymousClassId && $declaration->id->column !== null) {
                $reflector->anonymousClassColumns[$declaration->id->withoutColumn()->encode()][] = $declaration->id->column;
            }
        });
    }

    public function reflectNamed(NamedClassId $id): ClassReflection
    {
        if (!isset($this->data[$id->encode()])) {
            $this->parse($id);
        }

        return $this->doReflect($id);
    }

    public function reflectAnonymous(AnonymousClassId $id): ClassReflection
    {
        if ($id->column !== null) {
            if (!isset($this->data[$id->encode()])) {
                $this->fileParser->parseFile(new File($id->file));
            }

            return $this->doReflect($id);
        }

        $noColumnKey = $id->encode();

        if (!isset($this->anonymousClassColumns[$noColumnKey])) {
            $this->fileParser->parseFile(new File($id->file));
        }

        $columns = $this->anonymousClassColumns[$noColumnKey] ?? throw new DeclarationNotFound($id);

        if (\count($columns) === 1) {
            return $this->doReflect($id->withColumn($columns[0]));
        }

        throw new \RuntimeException(\sprintf(
            'Cannot reflect %s, because %d anonymous classes are declared at columns %s. ' .
            'Use TyphoonReflector::reflectAnonymousClass() with a $column argument to reflect the exact class you need',
            $id->describe(),
            \count($columns),
            implode(', ', $columns),
        ));
    }

    private function doReflect(NamedClassId|AnonymousClassId $id): ClassReflection
    {
        $key = $id->encode();
        $class = $this->data[$key] ?? throw new DeclarationNotFound($id);

        if ($class instanceof ClassReflection) {
            return $class;
        }

        $metadata = $this->metadataLoader->loadClassMetadata($class);

        return $this->data[$key] = ClassReflection::__declare($this, $class, $metadata);
    }

    private function parse(NamedClassId $id): void
    {
        if (!class_like_exists($id->name, autoload: false)) {
            $file = $this->locator->locateClass($id) ?? throw new DeclarationNotFound($id);
            $this->fileParser->parseFile($file);

            return;
        }

        $nativeReflection = new \ReflectionClass($id->name);

        if ($nativeReflection->getFileName() !== false) {
            $this->fileParser->parseFile(new File($nativeReflection->getFileName()));

            return;
        }

        $this->data[$id->encode()] = NativeReflectionParser::parseClass($nativeReflection);
    }
}
