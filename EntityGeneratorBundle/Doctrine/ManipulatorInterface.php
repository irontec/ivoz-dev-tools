<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bundle\MakerBundle\Doctrine\RelationManyToMany;
use Symfony\Bundle\MakerBundle\Doctrine\RelationManyToOne;
use Symfony\Bundle\MakerBundle\Doctrine\RelationOneToMany;
use Symfony\Bundle\MakerBundle\Doctrine\RelationOneToOne;

/**
 * @internal
 */
interface ManipulatorInterface
{
    public function updateSourceCode();

    public function getSourceCode(): string;

    public function addEntityField(string $propertyName, array $columnOptions, array $comments = [], $classMetadata);

    public function addEmbeddedEntity(string $propertyName, string $className);

    public function addManyToOneRelation(RelationManyToOne $manyToOne, ClassMetadata $classMetadata);

    public function addOneToOneRelation(RelationOneToOne $oneToOne, ClassMetadata $classMetadata);

    public function addOneToManyRelation(RelationOneToMany $oneToMany, ClassMetadata $classMetadata);

    public function addManyToManyRelation(RelationManyToMany $manyToMany, ClassMetadata $classMetadata);

    public function addInterface(string $interfaceName, ClassMetadata $classMetadata = null);

    public function addAccessorMethod(string $propertyName, string $methodName, $returnType, bool $isReturnTypeNullable, array $commentLines = [], $typeCast = null);

    public function addGetter(string $propertyName, $returnType, bool $isReturnTypeNullable, array $commentLines = []);

    public function addSetter(
        string $propertyName,
        $type,
        bool $isNullable,
        array $commentLines = [],
        array $columnOptions = [],
        $classMetadata,
        string $visibility = 'protected'
    );

    public function addMethod(
        \ReflectionMethod $method,
        $classMetadata
    );

    public function addProperty(
        string $name,
        string $typeHint,
        string $columnName,
        array $comments = [],
        $defaultValue = null,
        bool $required = false,
        string $fkFqdn
    );

    public function addConstant(
        string $name,
        string $value
    );

    public function addUseStatementIfNecessary(string $class): string;
}
