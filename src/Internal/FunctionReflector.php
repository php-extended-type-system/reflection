<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Internal;

use Typhoon\DeclarationId\NamedFunctionId;
use Typhoon\Reflection\Declaration\FunctionDeclaration;
use Typhoon\Reflection\Exception\DeclarationNotFound;
use Typhoon\Reflection\File;
use Typhoon\Reflection\FunctionReflection;
use Typhoon\Reflection\Internal\Metadata\MetadataLoader;
use Typhoon\Reflection\Locator\FunctionLocator;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 */
final class FunctionReflector
{
    /**
     * @var array<non-empty-string, FunctionDeclaration|FunctionReflection>
     */
    private array $data = [];

    public function __construct(
        private readonly FunctionLocator $locator,
        private readonly FileParser $fileParser,
        private readonly MetadataLoader $metadataLoader,
    ) {
        $weakReflector = \WeakReference::create($this);
        $fileParser->subscribe(static function (object $declaration) use ($weakReflector): void {
            if (!$declaration instanceof FunctionDeclaration) {
                return;
            }

            $reflector = $weakReflector->get() ?? throw new \LogicException('This should never happen');
            $reflector->data[$declaration->id->encode()] = $declaration;
        });
    }

    public function reflect(NamedFunctionId $id): FunctionReflection
    {
        $key = $id->encode();

        if (!isset($this->data[$key])) {
            $this->parse($id);
        }

        $function = $this->data[$key] ?? throw new DeclarationNotFound($id);

        if ($function instanceof FunctionReflection) {
            return $function;
        }

        $metadata = $this->metadataLoader->loadFunctionMetadata($function);

        return $this->data[$key] = FunctionReflection::from($function, $metadata);
    }

    private function parse(NamedFunctionId $id): void
    {
        if (!\function_exists($id->name)) {
            $file = $this->locator->locateFunction($id) ?? throw new DeclarationNotFound($id);
            $this->fileParser->parseFile($file);

            return;
        }

        $nativeReflection = new \ReflectionFunction($id->name);

        if ($nativeReflection->getFileName() !== false) {
            $this->fileParser->parseFile(new File($nativeReflection->getFileName()));

            return;
        }

        $this->data[$id->encode()] = NativeReflectionParser::parseFunction($nativeReflection);
    }
}
