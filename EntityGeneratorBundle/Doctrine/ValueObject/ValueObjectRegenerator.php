<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\ValueObject;

use Doctrine\ORM\Mapping\ClassMetadata;
use IvozDevTools\EntityGeneratorBundle\Doctrine\ManipulatorInterface;
use IvozDevTools\EntityGeneratorBundle\Generator;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Symfony\Bundle\MakerBundle\FileManager;

/**
 * @internal
 */
final class ValueObjectRegenerator
{
    private $fileManager;
    private $generator;
    private $doctrineHelper;

    public function __construct(
        FileManager $fileManager,
        Generator $generator,
        DoctrineHelper $doctrineHelper
    ) {
        $this->fileManager = $fileManager;
        $this->generator = $generator;
        $this->doctrineHelper = $doctrineHelper;
    }

    public function makeValueObject($classMetadata)
    {
        [$classPath, $content] = $this->getClassTemplate(
            $classMetadata,
            'doctrine/ValueObject.tpl.php'
        );

        $manipulator = $this->createClassManipulator(
            $classPath,
            $content
        );

        $this->addMethods(
            $manipulator,
            $classMetadata
        );

        $this->dumpFile(
            $classPath,
            $manipulator
        );
    }

    private function dumpFile(string $classPath, ManipulatorInterface $manipulator): void
    {
        $this->fileManager->dumpFile(
            $classPath,
            $manipulator->getSourceCode()
        );
    }

    private function getClassTemplate(
        ClassMetadata $metadata,
        $templateName
    ): array
    {
        [$path, $variables] = $this->generator->generateClassContentVariables(
            $metadata->name,
            $templateName,
            []
        );

        if (file_exists($variables['relative_path'])) {
            $variables['relative_path'] = realpath($variables['relative_path']);
        } else {
            $variables['relative_path'] = str_replace(
                'vendor/composer/../../',
                '',
                $variables['relative_path']
            );
        }

        return [
            $variables['relative_path'],
            $this->fileManager->parseTemplate(
                $path,
                $variables
            )
        ];
    }

    private function createClassManipulator(
        string $classPath,
        ?string $content
    ): ManipulatorInterface
    {
        $classContent = $content ?? $this->fileManager->getFileContents($classPath);
        return new ValueObjectManipulator(
            $classContent,
            $this->doctrineHelper
        );
    }

    private function getMappedFieldsInEntity(ClassMetadata $classMetadata)
    {
        /* @var $classReflection \ReflectionClass */
        $classReflection = $classMetadata->reflClass;

        $targetFields = array_merge(
            array_keys($classMetadata->fieldMappings),
            array_keys($classMetadata->associationMappings)
        );

        if ($classReflection) {
            // exclude traits
            $traitProperties = [];

            foreach ($classReflection->getTraits() as $trait) {
                foreach ($trait->getProperties() as $property) {
                    $traitProperties[] = $property->getName();
                }
            }

            $targetFields = array_diff($targetFields, $traitProperties);

            // exclude inherited properties
            $targetFields = array_filter($targetFields, function ($field) use ($classReflection) {
                return $classReflection->hasProperty($field) &&
                    $classReflection->getProperty($field)->getDeclaringClass()->getName() == $classReflection->getName();
            });
        }

        return $targetFields;
    }

    /**
     * @param $metadata
     * @param array $operations
     * @return array
     * @throws \Exception
     */
    private function addMethods($manipulator, $classMetadata): void
    {
        $mappedFields = $this->getMappedFieldsInEntity($classMetadata);

        foreach ($classMetadata->fieldMappings as $fieldName => $mapping) {

            if (!\in_array($fieldName, $mappedFields)) {
                continue;
            }

            $manipulator->addEntityField($fieldName, $mapping, [], $classMetadata);
        }

        $manipulator->updateSourceCode();
    }
}
