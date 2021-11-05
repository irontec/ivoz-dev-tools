<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Metadata;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory as DoctrineClassMetadataFactory;

/**
 * The ClassMetadataFactory is used to create ClassMetadata objects that contain all the
 * metadata mapping information of a class which describes how a class should be mapped
 * to a relational database.
 */
class ClassMetadataFactory extends DoctrineClassMetadataFactory
{
    private $em;

    public function setEntityManager(EntityManagerInterface $em)
    {
        $this->em = $em;
        parent::setEntityManager($em);
    }

    /**
     * {@inheritDoc}
     */
    protected function newClassMetadataInstance($className)
    {
        return new ClassMetadata(
            $className,
            $this->em->getConfiguration()->getNamingStrategy()
        );
    }
}
