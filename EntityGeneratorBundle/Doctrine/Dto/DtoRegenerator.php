<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Dto;

use Doctrine\ORM\Mapping\ClassMetadata;
use IvozDevTools\EntityGeneratorBundle\Doctrine\ManipulatorInterface;
use IvozDevTools\EntityGeneratorBundle\Generator;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Symfony\Bundle\MakerBundle\Doctrine\RelationManyToMany;
use Symfony\Bundle\MakerBundle\Doctrine\RelationManyToOne;
use Symfony\Bundle\MakerBundle\Doctrine\RelationOneToMany;
use Symfony\Bundle\MakerBundle\Doctrine\RelationOneToOne;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\Str;

/**
 * @internal
 */
final class DtoRegenerator
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

    public function makeAbstractDto($classMetadata)
    {
        $classMetadata = clone $classMetadata;
        if (empty($classMetadata->identifier)) {

            $parentClass = substr(
                $classMetadata->name,
                0,
                strrpos($classMetadata->name, 'Abstract')
            );

            $parentMetadata = $this->doctrineHelper->getMetadata(
                $parentClass,
                true
            );

            $fieldsToMerge = [
                'identifier', 'fieldMappings', 'fieldNames', 'columnNames', 'reflFields', 'associationMappings'
            ];

            foreach ($fieldsToMerge as $fieldToMerge) {
                $classMetadata->$fieldToMerge += $parentMetadata->$fieldToMerge;
            }
        }

        $classMetadata->name = str_replace(
            'Abstract',
            'DtoAbstract',
            $classMetadata->name
        );
        $classMetadata->rootEntityName = str_replace(
            'Abstract',
            'DtoAbstract',
            $classMetadata->rootEntityName
        );

        [$classPath, $content] = $this->getClassTemplate(
            $classMetadata,
            'doctrine/AbstractDto.tpl.php'
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

    public function makeDto($classMetadata)
    {
        if (class_exists($classMetadata->name)) {
            return;
        }

        $classMetadata = clone $classMetadata;
        $classMetadata->name .= 'Dto';
        $classMetadata->rootEntityName .= 'Dto';

        [$classPath, $content] = $this->getClassTemplate(
            $classMetadata,
            'doctrine/Dto.tpl.php'
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
        return new DtoManipulator(
            $classContent
        );
    }

    private function getMappedFieldsInEntity(ClassMetadata $classMetadata)
    {
        $targetFields = array_merge(
            array_keys($classMetadata->fieldMappings),
            array_keys($classMetadata->associationMappings)
        );

        return $targetFields;
    }

    private function addMethods($manipulator, $classMetadata/*, array $operations*/): void
    {
        $mappedFields = $this->getMappedFieldsInEntity($classMetadata);

        foreach ($classMetadata->fieldMappings as $fieldName => $mapping) {
            // skip embedded fields
            if (false !== strpos($fieldName, '.')) {
                continue;
            }

            if (!\in_array($fieldName, $mappedFields)) {
                continue;
            }

            $manipulator->addEntityField($fieldName, $mapping, $classMetadata);
        }

        foreach ($classMetadata->embeddedClasses as $fieldName => $mapping) {
            $embeddedMetadata = $this->doctrineHelper->getMetadata(
                $mapping['class']
            );

            foreach ($embeddedMetadata->fieldMappings as $subPropertyName => $subMapping) {
                $subProperty = $fieldName . Str::asCamelCase($subPropertyName);
                $manipulator->addEntityField($subProperty, $subMapping, $classMetadata, [], true);
            }
        }

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
                        ->setIsNullable(true)
                        ->setTargetClassName($mapping['targetEntity'] . 'Dto')
                        ->setTargetPropertyName($mapping['inversedBy'])
                        ->setMapInverseRelation(null !== $mapping['inversedBy']);

                    $manipulator->addManyToOneRelation($relation, $classMetadata);

                    break;
                case ClassMetadata::ONE_TO_MANY:
                    $relation = new RelationOneToMany();
                    $relation
                        ->setPropertyName($mapping['fieldName'])
                        ->setTargetClassName($mapping['targetEntity'] . 'Dto')
                        ->setTargetPropertyName($mapping['mappedBy']);
                    $relation
                        ->setOrphanRemoval($mapping['orphanRemoval']);

                    $manipulator->addOneToManyRelation($relation, $classMetadata);

                    break;
                case ClassMetadata::MANY_TO_MANY:
                    $relation = new RelationManyToMany();
                    $relation
                        ->setPropertyName($mapping['fieldName'])
                        ->setTargetClassName($mapping['targetEntity'] . 'Dto')
                        ->setTargetPropertyName($mapping['mappedBy']);
                    $relation
                        ->setIsOwning($mapping['isOwningSide'])
                        ->setMapInverseRelation($mapping['isOwningSide'] ? (null !== $mapping['inversedBy']) : true);

                    $manipulator->addManyToManyRelation($relation, $classMetadata);

                    break;
                case ClassMetadata::ONE_TO_ONE:
                    $relation = new RelationOneToOne();
                    $relation
                        ->setPropertyName($mapping['fieldName'])
                        ->setTargetClassName($mapping['targetEntity'] . 'Dto')
                        ->setTargetPropertyName($mapping['isOwningSide'] ? $mapping['inversedBy'] : $mapping['mappedBy']);
                    $relation
                        ->setIsOwning($mapping['isOwningSide'])
                        ->setMapInverseRelation($mapping['isOwningSide'] ? (null !== $mapping['inversedBy']) : true);
                    $relation
                        ->setIsNullable(true);

                    $manipulator->addOneToOneRelation($relation, $classMetadata);

                    break;
                default:
                    throw new \Exception('Unknown association type.');
            }
        }

        $manipulator->updateSourceCode();
    }
}
