<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Repository;

use Doctrine\ORM\Mapping\ClassMetadata;
use IvozDevTools\EntityGeneratorBundle\Doctrine\CodeGeneratorUnitInterface;
use IvozDevTools\EntityGeneratorBundle\Doctrine\ManipulatorInterface;
use Symfony\Bundle\MakerBundle\Doctrine\RelationManyToMany;
use Symfony\Bundle\MakerBundle\Doctrine\RelationManyToOne;
use Symfony\Bundle\MakerBundle\Doctrine\RelationOneToMany;
use Symfony\Bundle\MakerBundle\Doctrine\RelationOneToOne;

class RepositoryManipulator implements ManipulatorInterface
{
    private $sourceCode;

    public function __construct(string $sourceCode)
    {
        $this->sourceCode = $sourceCode;
    }

    public function addMethod(\ReflectionMethod $method, $classMetadata)
    {
        // TODO: Implement addMethod() method.
    }

    public function addInterface(string $interfaceName, ClassMetadata $classMetadata = null)
    {
        // TODO: Implement addInterface() method.
    }

    public function updateSourceCode()
    {
        // TODO: Implement updateSourceCode() method.
    }

    public function getSourceCode(): string
    {
        return $this->sourceCode;
    }

    public function addUseStatementIfNecessary(string $class): string
    {
        return '';
    }


    public function addConstant(string $name, string $value)
    {
        // TODO: Implement addConstant() method.
    }

    public function addEntityField(string $propertyName, array $columnOptions, $classMetadata, array $comments = [])
    {
    }

    public function addEmbeddedEntity(string $propertyName, string $className)
    {
    }

    public function addManyToOneRelation(RelationManyToOne $manyToOne, ClassMetadata $classMetadata)
    {
    }

    public function addOneToOneRelation(RelationOneToOne $oneToOne, ClassMetadata $classMetadata)
    {
    }

    public function addOneToManyRelation(RelationOneToMany $oneToMany, ClassMetadata $classMetadata)
    {
    }

    public function addManyToManyRelation(RelationManyToMany $manyToMany, ClassMetadata $classMetadata)
    {
    }

    public function addAccessorMethod(string $propertyName, string $methodName, $returnType, bool $isReturnTypeNullable, array $commentLines = [], $typeCast = null)
    {
    }

    public function addGetter(string $propertyName, $returnType, bool $isReturnTypeNullable, array $commentLines = [])
    {
    }

    public function addSetter(string $propertyName, $type, bool $isNullable, $classMetadata, array $commentLines = [], array $columnOptions = [], string $visibility = 'protected')
    {
    }

    public function addProperty(string $name, string $typeHint, string $columnName, string $fkFqdn, array $comments = [], $defaultValue = null, bool $required = false)
    {
    }

}