<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine;

use Doctrine\Common\Persistence\Mapping\MappingException as LegacyCommonMappingException;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\Persistence\Mapping\MappingException as PersistenceMappingException;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Dto\DtoRegenerator;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Entity\EntityRegenerator;
use IvozDevTools\EntityGeneratorBundle\Doctrine\EntityInterface\InterfaceRegenerator;
use IvozDevTools\EntityGeneratorBundle\Doctrine\ValueObject\ValueObjectRegenerator;
use IvozDevTools\EntityGeneratorBundle\Generator;
use IvozDevTools\EntityGeneratorBundle\Doctrine\EntityTrait\TraitRegenerator;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Symfony\Bundle\MakerBundle\Doctrine\EntityClassGenerator;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Symfony\Bundle\MakerBundle\FileManager;

/**
 * @internal
 */
final class Regenerator
{
    private $doctrineHelper;
    private $entityClassGenerator;
    private $overwrite;

    private $interfaceRegenerator;
    private $entityRegenerator;
    private $voRegenerator;
    private $dtoRegenerator;
    private $traitRegenerator;

    public function __construct(
        DoctrineHelper $doctrineHelper,
        FileManager $fileManager,
        Generator $generator,
        EntityClassGenerator $entityClassGenerator,
        bool $overwrite
    ) {
        $this->doctrineHelper = $doctrineHelper;
        $this->entityClassGenerator = $entityClassGenerator;
        $this->overwrite = $overwrite;

        $this->interfaceRegenerator = new InterfaceRegenerator(
            $fileManager,
            $generator
        );

        $this->entityRegenerator = new EntityRegenerator(
            $fileManager,
            $generator,
            $doctrineHelper
        );

        $this->voRegenerator = new ValueObjectRegenerator(
            $fileManager,
            $generator,
            $doctrineHelper
        );

        $this->dtoRegenerator = new DtoRegenerator(
            $fileManager,
            $generator,
            $doctrineHelper
        );

        $this->traitRegenerator = new TraitRegenerator(
            $fileManager,
            $generator,
            $doctrineHelper
        );
    }

    public function regenerateEntities(string $classOrNamespace)
    {
        $classMetadata = $this->getMetadata($classOrNamespace);
        $isMappedSuperclass = $classMetadata->isMappedSuperclass;
        $isEmbeddedClass = $classMetadata->isEmbeddedClass;

        if ($isEmbeddedClass) {
            $this->voRegenerator->makeValueObject(
                $classMetadata
            );
        } elseif ($isMappedSuperclass) {
            $this->entityRegenerator->makeAbstractEntity($classMetadata);
            $this->dtoRegenerator->makeAbstractDto($classMetadata);
        } else {
            $this->interfaceRegenerator->makeEmptyInterface($classMetadata);
            $this->traitRegenerator->makeTrait($classMetadata);
            $this->entityRegenerator->makeEntity($classMetadata);
            $this->dtoRegenerator->makeDto($classMetadata);
            $this->interfaceRegenerator->makeInterface($classMetadata);
        }
    }

    /**
     * @param string $classOrNamespace
     * @return \Doctrine\Common\Persistence\Mapping\ClassMetadata|ClassMetadata|\Doctrine\Persistence\Mapping\ClassMetadata
     */
    private function getMetadata(string $classOrNamespace)
    {
        try {
            $metadata = $this->doctrineHelper->getMetadata($classOrNamespace);
        } catch (MappingException | LegacyCommonMappingException | PersistenceMappingException $mappingException) {
            $metadata = $this->doctrineHelper->getMetadata($classOrNamespace, true);
        }

        if ($metadata instanceof ClassMetadata) {
            return $metadata;
        }

        if (class_exists($classOrNamespace)) {
            throw new RuntimeCommandException(sprintf('Could not find Doctrine metadata for "%s". Is it mapped as an entity?', $classOrNamespace));
        } elseif (empty($metadata)) {
            throw new RuntimeCommandException(sprintf('No entities were found in the "%s" namespace.', $classOrNamespace));
        }
    }
}
