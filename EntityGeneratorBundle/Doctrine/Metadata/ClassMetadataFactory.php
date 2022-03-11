<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Metadata;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory as DoctrineClassMetadataFactory;
use Doctrine\Persistence\Mapping\ReflectionService;
use Doctrine\Persistence\Mapping\ClassMetadata as ClassMetadataInterface;

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

    protected function getParentClasses($name)
    {
        // Collect parent classes, ignoring transient (not-mapped) classes.
        $parentClasses = [];

        try {
            foreach (array_reverse($this->getReflectionService()->getParentClasses($name)) as $parentClass) {
                if ($this->getDriver()->isTransient($parentClass)) {
                    continue;
                }

                $parentClasses[] = $parentClass;
            }
        } catch (\Exception $e) {}

        return $parentClasses;
    }

    protected function initializeReflection(ClassMetadataInterface $class, ReflectionService $reflService)
    {
        try {
            parent::initializeReflection($class, $reflService);
        } catch (\Exception $e) {}
    }

    protected function wakeupReflection(ClassMetadataInterface $class, ReflectionService $reflService)
    {
        try {
            parent::wakeupReflection($class, $reflService);
        } catch (\Exception $e) {}
    }
}
