<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Metadata;

use Doctrine\Instantiator\Instantiator;
use Doctrine\Instantiator\InstantiatorInterface;
use Doctrine\ORM\Mapping\NamingStrategy;
use Doctrine\ORM\Mapping\ReflectionEmbeddedProperty;
use Doctrine\ORM\Mapping\ClassMetadata as DoctrineClassMetadata;

class ClassMetadata extends DoctrineClassMetadata
{
    /** @var InstantiatorInterface|null */
    private $instantiator;

    public function __construct($entityName, ?NamingStrategy $namingStrategy = null)
    {
        $this->instantiator = new Instantiator();
        parent::__construct($entityName, $namingStrategy);
    }

    /**
     * Creates a new instance of the mapped class, without invoking the constructor.
     *
     * @return object
     */
    public function newInstance()
    {
        return $this->instantiator->instantiate($this->name);
    }

    public function wakeupReflection($reflService): void
    {
        // Restore ReflectionClass and properties
        $this->reflClass    = $reflService->getClass($this->name);
        $this->instantiator = $this->instantiator ?: new Instantiator();

        $parentReflFields = [];

        foreach ($this->embeddedClasses as $property => $embeddedClass) {
            if (isset($embeddedClass['declaredField'])) {
                $parentReflFields[$property] = new ReflectionEmbeddedProperty(
                    $parentReflFields[$embeddedClass['declaredField']],
                    $reflService->getAccessibleProperty(
                        $this->embeddedClasses[$embeddedClass['declaredField']]['class'],
                        $embeddedClass['originalField']
                    ),
                    $this->embeddedClasses[$embeddedClass['declaredField']]['class']
                );

                continue;
            }

            $fieldRefl = $reflService->getAccessibleProperty(
                $embeddedClass['declared'] ?? $this->name,
                $property
            );

            $parentReflFields[$property] = $fieldRefl;
            $this->reflFields[$property] = $fieldRefl;
        }

        foreach ($this->fieldMappings as $field => $mapping) {
            if (isset($mapping['declaredField']) && isset($parentReflFields[$mapping['declaredField']])) {
                $this->reflFields[$field] = new ReflectionEmbeddedProperty(
                    $parentReflFields[$mapping['declaredField']],
                    $reflService->getAccessibleProperty($mapping['originalClass'], $mapping['originalField']),
                    $mapping['originalClass']
                );
                continue;
            }

            try {
                $this->reflFields[$field] = isset($mapping['declared'])
                    ? $reflService->getAccessibleProperty($mapping['declared'], $field)
                    : $reflService->getAccessibleProperty($this->name, $field);
            } catch (\Throwable $e) {}
        }

        foreach ($this->associationMappings as $field => $mapping) {
            $this->reflFields[$field] = isset($mapping['declared'])
                ? $reflService->getAccessibleProperty($mapping['declared'], $field)
                : $reflService->getAccessibleProperty($this->name, $field);
        }
    }
}
