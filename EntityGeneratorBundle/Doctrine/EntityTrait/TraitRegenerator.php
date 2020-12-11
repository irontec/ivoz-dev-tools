<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\EntityTrait;

use Doctrine\ORM\Mapping\ClassMetadata;
use IvozDevTools\EntityGeneratorBundle\Doctrine\ManipulatorInterface;
use IvozDevTools\EntityGeneratorBundle\Generator;
use Symfony\Bundle\MakerBundle\Doctrine\RelationManyToMany;
use Symfony\Bundle\MakerBundle\Doctrine\RelationOneToMany;
use Symfony\Bundle\MakerBundle\Doctrine\RelationOneToOne;
use Symfony\Bundle\MakerBundle\FileManager;

/**
 * @internal
 */
final class TraitRegenerator
{
    private $fileManager;
    private $generator;

    public function __construct(
        FileManager $fileManager,
        Generator $generator
    ) {
        $this->fileManager = $fileManager;
        $this->generator = $generator;
    }

    public function makeTrait($classMetadata)
    {
        $classMetadata = clone $classMetadata;

        $fqdn =
            $classMetadata->name
            . 'Trait';

        $classMetadata->name = $fqdn;
        $classMetadata->rootEntityName = $fqdn;

        [$classPath, $content] = $this->getClassTemplate(
            $classMetadata,
            'doctrine/Trait.tpl.php'
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
        return new TraitManipulator(
            $classContent
        );
    }

    private function getMappedFieldsInEntity(ClassMetadata $classMetadata)
    {
        $idFld = current($classMetadata->identifier);

        $associations = array_filter(
            $classMetadata->associationMappings,
            function ($mapping) {
                return in_array(
                    $mapping['type'] ?? null,
                    [
                        ClassMetadata::ONE_TO_ONE,
                        ClassMetadata::ONE_TO_MANY,
                        ClassMetadata::MANY_TO_MANY
                    ],
                    true
                );
            }
        );

        $targetFields = array_merge(
            [$idFld],
            array_keys($associations)
        );

        return $targetFields;
    }

    /**
     * @param $metadata
     * @param array $operations
     * @return array
     * @throws \Exception
     */
    private function addMethods($manipulator, ClassMetadata $classMetadata): void
    {
        $mappedFields = $this->getMappedFieldsInEntity($classMetadata);

        foreach ($classMetadata->fieldMappings as $fieldName => $mapping) {

            if (!\in_array($fieldName, $mappedFields)) {
                continue;
            }

            $manipulator->addEntityField($fieldName, $mapping, [], $classMetadata);
        }

        foreach ($classMetadata->associationMappings as $fieldName => $mapping) {
            if (!\in_array($fieldName, $mappedFields)) {
                continue;
            }

            switch ($mapping['type']) {
                case ClassMetadata::ONE_TO_ONE:

                    if ($mapping['isOwningSide']) {
                        break;
                    }

                    $relation = (new RelationOneToOne())
                        ->setPropertyName($mapping['fieldName'])
                        ->setTargetClassName($mapping['targetEntity'])
                        ->setTargetPropertyName(
                            $mapping['mappedBy']
                        )
                        ->setIsOwning($mapping['isOwningSide'])
                        ->setMapInverseRelation(
                            true
                        )
                        ->setIsNullable(false);

                    $manipulator->addOneToOneRelation($relation, $classMetadata);

                    break;
                case ClassMetadata::ONE_TO_MANY:
                    $relation = (new RelationOneToMany())
                        ->setPropertyName($mapping['fieldName'])
                        ->setTargetClassName($mapping['targetEntity'])
                        ->setTargetPropertyName($mapping['mappedBy'])
                        ->setOrphanRemoval($mapping['orphanRemoval']);

                    $manipulator->addOneToManyRelation($relation, $classMetadata);

                    break;
                case ClassMetadata::MANY_TO_MANY:
                    $relation = (new RelationManyToMany())
                        ->setPropertyName($mapping['fieldName'])
                        ->setTargetClassName($mapping['targetEntity'])
                        ->setTargetPropertyName($mapping['mappedBy'])
                        ->setIsOwning($mapping['isOwningSide'])
                        ->setMapInverseRelation($mapping['isOwningSide'] ? (null !== $mapping['inversedBy']) : true);

                    $manipulator->addManyToManyRelation($relation, $classMetadata);

                    break;
            }
        }

        $manipulator->updateSourceCode($classMetadata);
    }
}
