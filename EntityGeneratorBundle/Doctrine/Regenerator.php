<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\Mapping\MappingException as LegacyCommonMappingException;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\Persistence\Mapping\MappingException as PersistenceMappingException;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Dto\DtoRegenerator;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Entity\EntityRegenerator;
use IvozDevTools\EntityGeneratorBundle\Doctrine\EntityInterface\InterfaceRegenerator;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Repository\RepositoryRegenerator;
use IvozDevTools\EntityGeneratorBundle\Doctrine\ValueObject\ValueObjectRegenerator;
use IvozDevTools\EntityGeneratorBundle\Generator;
use IvozDevTools\EntityGeneratorBundle\Doctrine\EntityTrait\TraitRegenerator;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Symfony\Bundle\MakerBundle\FileManager;

/**
 * @internal
 */
final class Regenerator
{
    private DoctrineHelper $doctrineHelper;

    private InterfaceRegenerator $interfaceRegenerator;
    private RepositoryRegenerator $repositoryRegenerator;
    private EntityRegenerator $entityRegenerator;
    private ValueObjectRegenerator $voRegenerator;
    private DtoRegenerator $dtoRegenerator;
    private TraitRegenerator $traitRegenerator;

    public function __construct(
        DoctrineHelper $doctrineHelper,
        FileManager $fileManager,
        Generator $generator
    ) {
        $this->doctrineHelper = $doctrineHelper;

        $this->interfaceRegenerator = new InterfaceRegenerator(
            $fileManager,
            $generator
        );

        $this->repositoryRegenerator = new RepositoryRegenerator(
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
            $generator
        );

        $this->dtoRegenerator = new DtoRegenerator(
            $fileManager,
            $generator,
            $doctrineHelper
        );

        $this->traitRegenerator = new TraitRegenerator(
            $fileManager,
            $generator
        );
    }

    public function regenerateEntities(string $classOrNamespace)
    {
        /** @var ClassMetadataInfo $classMetadata */
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
        }
    }

    public function regenerateInterfaces(string $classOrNamespace)
    {
        /** @var ClassMetadataInfo $classMetadata */
        $classMetadata = $this->getMetadata($classOrNamespace);
        $isMappedSuperclass = $classMetadata->isMappedSuperclass;
        $isEmbeddedClass = $classMetadata->isEmbeddedClass;

        if (!$isEmbeddedClass && !$isMappedSuperclass) {
            $this->interfaceRegenerator->makeInterface($classMetadata);
        }
    }

    public function regenerateRepositories(string $classOrNamespace){
        /** @var ClassMetadataInfo $classMetadata */
        $classMetadata = $this->getMetadata($classOrNamespace);
        $isMappedSuperclass = $classMetadata->isMappedSuperclass;
        $isEmbeddedClass = $classMetadata->isEmbeddedClass;

        if (!$isEmbeddedClass && !$isMappedSuperclass) {
            $this->repositoryRegenerator->makeEmptyRepository($classMetadata);
        }
    }


    private function getMetadata(string $classOrNamespace): ClassMetadata
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

        throw new RuntimeCommandException('Unexpected execution point');
    }
}
