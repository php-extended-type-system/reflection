<?php

declare(strict_types=1);

namespace Typhoon\Reflection\Locator;

use Typhoon\DeclarationId\ConstantId;
use Typhoon\DeclarationId\NamedClassId;
use Typhoon\DeclarationId\NamedFunctionId;
use Typhoon\Reflection\File;

/**
 * @api
 */
final class Locators implements ConstantLocator, FunctionLocator, ClassLocator
{
    /**
     * @var list<ConstantLocator>
     */
    private array $constantLocators = [];

    /**
     * @var list<FunctionLocator>
     */
    private array $functionLocators = [];

    /**
     * @var list<ClassLocator>
     */
    private array $classLocators = [];

    /**
     * @param iterable<ConstantLocator|FunctionLocator|ClassLocator> $locators
     */
    public function __construct(iterable $locators = [])
    {
        foreach ($locators as $locator) {
            if ($locator instanceof ConstantLocator) {
                $this->constantLocators[] = $locator;
            }

            if ($locator instanceof FunctionLocator) {
                $this->functionLocators[] = $locator;
            }

            if ($locator instanceof ClassLocator) {
                $this->classLocators[] = $locator;
            }
        }
    }

    public function locateConstant(ConstantId $id): ?File
    {
        foreach ($this->constantLocators as $locator) {
            $file = $locator->locateConstant($id);

            if ($file !== null) {
                return $file;
            }
        }

        return null;
    }

    public function locateFunction(NamedFunctionId $id): ?File
    {
        foreach ($this->functionLocators as $locator) {
            $file = $locator->locateFunction($id);

            if ($file !== null) {
                return $file;
            }
        }

        return null;
    }

    public function locateClass(NamedClassId $id): ?File
    {
        foreach ($this->classLocators as $locator) {
            $file = $locator->locateClass($id);

            if ($file !== null) {
                return $file;
            }
        }

        return null;
    }
}
