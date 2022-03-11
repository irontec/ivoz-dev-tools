<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Entity;

use Doctrine\ORM\Mapping\ClassMetadata;
use IvozDevTools\EntityGeneratorBundle\Doctrine\ManipulatorInterface;
use IvozDevTools\EntityGeneratorBundle\Generator;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Symfony\Bundle\MakerBundle\Doctrine\RelationManyToOne;
use Symfony\Bundle\MakerBundle\Doctrine\RelationOneToOne;
use Symfony\Bundle\MakerBundle\FileManager;

/**
 * @internal
 */
final class EntityRegenerator
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

    public function makeAbstractEntity($classMetadata)
    {
        [$classPath, $content] = $this->getClassTemplate(
            $classMetadata,
            'doctrine/AbstractEntity.tpl.php'
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

    public function makeEmptyInterface($classMetadata)
    {
        if (class_exists($classMetadata->name)) {
            return;
        }

        [$classPath, $content] = $this->getClassTemplate(
            $classMetadata,
            'doctrine/EntityInterface.tpl.php'
        );

        $manipulator = $this->createClassManipulator(
            $classPath,
            $content
        );

        $this->dumpFile(
            $classPath,
            $manipulator
        );
    }

    public function makeEntity($classMetadata)
    {
        try {
            if (class_exists($classMetadata->name)) {
                return;
            }
        } catch (\Exception $e) {}

        [$classPath, $content] = $this->getClassTemplate(
            $classMetadata,
            'doctrine/Entity.tpl.php'
        );

        $manipulator = $this->createClassManipulator(
            $classPath,
            $content
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
        return new EntityManipulator(
            $classContent,
            $this->doctrineHelper
        );
    }

    private function getMappedFieldsInEntity(ClassMetadata $classMetadata)
    {
        /* @var $classReflection \ReflectionClass */
        $classReflection = $classMetadata->reflClass;
        if (is_null($classReflection)) {
            return [];
        }

        $targetFields = array_merge(
            array_keys($classMetadata->fieldMappings),
            array_keys($classMetadata->associationMappings)
        );

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

            if (!$classReflection->getParentClass()) {
                return true;
            }

            return $classReflection->hasProperty($field) &&
                $classReflection->getProperty($field)->getDeclaringClass()->getName() == $classReflection->getName();
        });

        return $targetFields;
    }

    private function addMethods($manipulator, $classMetadata): void
    {
        $mappedFields = $this->getMappedFieldsInEntity($classMetadata);

        foreach ($classMetadata->fieldMappings as $fieldName => $mapping) {

            if (!\in_array($fieldName, $mappedFields)) {
                continue;
            }

            $manipulator->addEntityField($fieldName, $mapping, $classMetadata);
        }

        foreach ($classMetadata->embeddedClasses as $fieldName => $mapping) {
            if (false !== strpos($fieldName, '.')) {
                continue;
            }

            $className = $mapping['class'];
            $manipulator->addEmbeddedEntity($fieldName, $className, $classMetadata);
        }

        $getIsNullable = function (array $mapping) {
            if (!isset($mapping['joinColumns'][0]['nullable'])) {
                // the default for relationships IS nullable
                return true;
            }

            if ($mapping['cascadePersisted'] ?? false) {
                return true;
            }

            if ($mapping['inversedBy'] ?? false) {
                $targetMetadata = $this->doctrineHelper->getMetadata(
                    $mapping['targetEntity']
                );

                $targetFld = $targetMetadata->associationMappings[
                    $mapping['inversedBy']
                ];

                $isCascadePersisted = in_array(
                    'persist',
                    $targetFld['cascade'] ?? []
                );

                if ($isCascadePersisted) {
                    return true;
                }
            }

            return $mapping['joinColumns'][0]['nullable'];
        };

        foreach ($classMetadata->associationMappings as $fieldName => $mapping) {
            if (!\in_array($fieldName, $mappedFields)) {
                continue;
            }

            switch ($mapping['type']) {
                case ClassMetadata::MANY_TO_ONE:
                    $relation = new RelationManyToOne();
                    $relation
                        ->setPropertyName($mapping['fieldName']);
                    $relation
                        ->setIsNullable($getIsNullable($mapping))
                        ->setTargetClassName($mapping['targetEntity'] . 'Interface')
                        ->setTargetPropertyName($mapping['inversedBy'])
                        ->setMapInverseRelation(null !== $mapping['inversedBy']);

                    $manipulator->addManyToOneRelation($relation, $classMetadata);

                    break;
                case ClassMetadata::ONE_TO_ONE:

                    if (!$mapping['isOwningSide']) {
                        break;
                    }

                    $relation = new RelationOneToOne();
                    $relation
                        ->setPropertyName($mapping['fieldName'])
                        ->setTargetClassName($mapping['targetEntity'] . 'Interface')
                        ->setTargetPropertyName($mapping['isOwningSide'] === true ? $mapping['inversedBy'] : $mapping['mappedBy']);
                    $relation
                        ->setIsOwning($mapping['isOwningSide'])
                        ->setMapInverseRelation($mapping['isOwningSide'] === true ? (null !== $mapping['inversedBy']) : true);
                    $relation
                        ->setIsNullable($getIsNullable($mapping));

                    $manipulator->addOneToOneRelation($relation, $classMetadata);

                    break;
            }
        }

        $manipulator->updateSourceCode();
    }
}
