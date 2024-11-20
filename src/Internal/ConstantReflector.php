<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Internal;

use Typhoon\DeclarationId\ConstantId;
use Typhoon\Reflection\ConstantReflection;
use Typhoon\Reflection\Declaration\ConstantDeclaration;
use Typhoon\Reflection\Exception\DeclarationNotFound;
use Typhoon\Reflection\Internal\Metadata\MetadataLoader;
use Typhoon\Reflection\Locator\ConstantLocator;

/**
 * @internal
 * @psalm-internal Typhoon\Reflection
 */
final class ConstantReflector
{
    /**
     * @var array<non-empty-string, ConstantDeclaration|ConstantReflection>
     */
    private array $data = [];

    public function __construct(
        private readonly ConstantLocator $locator,
        private readonly FileParser $fileParser,
        private readonly MetadataLoader $metadataLoader,
    ) {
        $weakReflector = \WeakReference::create($this);
        $fileParser->subscribe(static function (object $declaration) use ($weakReflector): void {
            if (!$declaration instanceof ConstantDeclaration) {
                return;
            }

            $reflector = $weakReflector->get() ?? throw new \LogicException('This should never happen');
            $reflector->data[$declaration->name] = $declaration;
        });
    }

    public function reflect(ConstantId $id): ConstantReflection
    {
        if (!isset($this->data[$id->name])) {
            $this->parse($id);
        }

        $constant = $this->data[$id->name] ?? throw new DeclarationNotFound($id);

        if ($constant instanceof ConstantReflection) {
            return $constant;
        }

        $metadata = $this->metadataLoader->loadConstantMetadata($constant);

        return $this->data[$id->name] = ConstantReflection::from($constant, $metadata);
    }

    private function parse(ConstantId $id): void
    {
        if (\defined($id->name)) {
            $this->data[$id->name] = NativeReflectionParser::parseConstant($id->name);

            return;
        }

        $file = $this->locator->locateConstant($id) ?? throw new DeclarationNotFound($id);
        $this->fileParser->parseFile($file);
    }
}
