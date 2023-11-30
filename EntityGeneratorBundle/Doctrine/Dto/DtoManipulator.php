<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Dto;

use Doctrine\ORM\Mapping\ClassMetadata;
use IvozDevTools\EntityGeneratorBundle\Doctrine\CodeGeneratorUnitInterface;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Entity\EmbeddedProperty;
use IvozDevTools\EntityGeneratorBundle\Doctrine\EntityTypeTrait;
use IvozDevTools\EntityGeneratorBundle\Doctrine\ManipulatorInterface;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Property;
use IvozDevTools\EntityGeneratorBundle\Doctrine\StringNode;
use IvozDevTools\EntityGeneratorBundle\Doctrine\UseStatement;
use PhpParser\Lexer;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\Parser;
use Symfony\Bundle\MakerBundle\Doctrine\BaseCollectionRelation;
use Symfony\Bundle\MakerBundle\Doctrine\BaseSingleRelation;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Symfony\Bundle\MakerBundle\Doctrine\RelationManyToMany;
use Symfony\Bundle\MakerBundle\Doctrine\RelationManyToOne;
use Symfony\Bundle\MakerBundle\Doctrine\RelationOneToMany;
use Symfony\Bundle\MakerBundle\Doctrine\RelationOneToOne;
use Symfony\Bundle\MakerBundle\Str;

/**
 * @internal
 */
final class DtoManipulator implements ManipulatorInterface
{
    use EntityTypeTrait;

    const CLASS_USE_STATEMENT_PLACEHOLDER = '/*__dto_use_statements*/';
    const CLASS_ATTRIBUTE_PLACEHOLDER = '/*__dto_attributes*/';
    const CLASS_METHOD_PLACEHOLDER = '/*__dto_methods*/';
    const TO_ARRAY_PLACEHOLDER = '/*__toArray_body*/';
    const PROPERTY_MAP_PLACEHOLDER = '/*__getPropertyMap*/';

    private $parser;
    private $lexer;

    private $sourceCode;
    private $ast;

    /** @var Property[]  */
    private $properties = [];
    /** @var CodeGeneratorUnitInterface[]  */
    private $methods = [];
    /** @var UseStatement[]  */
    private $useStatements = [];

    public function __construct(
        string $sourceCode,
        private DoctrineHelper $doctrineHelper,
    ) {
        $this->lexer = new Lexer\Emulative([
            'usedAttributes' => [
                'comments',
                'startLine', 'endLine',
                'startTokenPos', 'endTokenPos',
            ],
        ]);
        $this->parser = new Parser\Php7($this->lexer);
        $this->sourceCode = $sourceCode;
        $this->ast = $this->parser->parse($sourceCode);
    }

    public function updateSourceCode()
    {
        $leftPad = str_repeat(' ', 4);

        $this->updateClass(
            self::CLASS_USE_STATEMENT_PLACEHOLDER,
            $this->useStatements,
            '',
            "\n"
        );

        $this->updateClass(
            self::CLASS_ATTRIBUTE_PLACEHOLDER,
            $this->properties,
            $leftPad
        );

        $this->updateToArray(
            $leftPad
        );

        $this->updatePropertyMap(
            $leftPad
        );

        $this->updateClass(
            self::CLASS_METHOD_PLACEHOLDER,
            $this->methods,
            $leftPad
        );

        // Remove black lines
        $this->sourceCode = preg_replace('/^[\t|\s]+\n+/m', "\n", $this->sourceCode);
        $this->sourceCode = preg_replace('/\n{2,}(\s*\})/m', "\n$1", $this->sourceCode);

        $this->useStatements = [];
        $this->properties = [];
        $this->methods = [];
    }

    public function getSourceCode(): string
    {
        return $this->sourceCode;
    }

    public function addEntityField(
        string $propertyName,
        array $columnOptions,
        $classMetadata,
        array $comments = [],
        $embedded = false
    ) {
        $fieldName = $columnOptions['fieldName'];
        $isPk = in_array($fieldName, $classMetadata->identifier);
        $typeHint = $this->getEntityTypeHint($columnOptions['type']);
        if ($typeHint === 'resource') {
            $typeHint = 'string';
        }

        $nullable = $columnOptions['nullable'] ?? false;

        if (in_array($typeHint, ['\\DateTime', '\\DateTimeInterface'], true)) {
            $typeHint = '\DateTimeInterface|string';
        }

        $comments = array_merge(
            $comments,
            $this->buildPropertyCommentLines($columnOptions)
        );
        $defaultValue = $columnOptions['options']['default'] ?? null;

        if (!is_null($defaultValue)) {
            switch ($typeHint) {
                case 'int':
                    $defaultValue = intval($defaultValue);
                    break;
                case 'float':
                    $defaultValue = floatval($defaultValue);
                    break;
                case 'bool':
                    $defaultValue = $defaultValue !== '0';
                    break;
            }
        }

        $this->addProperty(
            $propertyName,
            $typeHint ?? '',
            $fieldName,
            '',
            $comments,
            $defaultValue,
            false,
            $embedded
        );

        if ($nullable || $isPk) {
            $paramDoc = '@param ' . $typeHint . '|null $' . $propertyName;
        } else {
            $paramDoc = '@param ' . $typeHint . ' $' . $propertyName;
        }

        $setterComments = [
            $paramDoc,
        ];

        $this->addSetter(
            $propertyName,
            $isPk ? null : $typeHint,
            $nullable,
            $classMetadata,
            $setterComments,
            $columnOptions,
        );

        $returnHint = '@return ' . $typeHint;
        if ($nullable) {
            $returnHint .= ' | null';
        }
        $getterComments = [
            $returnHint
        ];

        $this->addGetter(
            $propertyName,
            $typeHint,
            // getter methods always have nullable return values
            // because even though these are required in the db, they may not be set yet
            true,
            $getterComments
        );
    }

    public function addEmbeddedEntity(string $propertyName, string $className)
    {
    }

    public function addManyToOneRelation(RelationManyToOne $manyToOne, $classMetadata)
    {
        $this->addSingularRelation($manyToOne, $classMetadata);
    }

    public function addOneToOneRelation(RelationOneToOne $oneToOne, ClassMetadata $classMetadata)
    {
        $this->addSingularRelation($oneToOne, $classMetadata);
    }

    public function addOneToManyRelation(RelationOneToMany $oneToMany, ClassMetadata $classMetadata)
    {
        $this->addCollectionRelation($oneToMany, $classMetadata);
    }

    public function addManyToManyRelation(RelationManyToMany $manyToMany, ClassMetadata $classMetadata)
    {
        $this->addCollectionRelation($manyToMany, $classMetadata);
    }

    public function addInterface(string $interfaceName, ClassMetadata $classMetadata = null)
    {
        $this->addUseStatementIfNecessary($interfaceName);

        $this->getClassNode()->implements[] = new Node\Name(Str::getShortClassName($interfaceName));
    }

    public function addAccessorMethod(string $propertyName, string $methodName, $returnType, bool $isReturnTypeNullable, array $commentLines = [], $typeCast = null)
    {
    }

    public function addGetter(string $propertyName, $returnType, bool $isReturnTypeNullable, array $commentLines = [])
    {
        $this->methods[] = new DtoGetter(
            $propertyName,
            $returnType,
            $isReturnTypeNullable,
            $commentLines
        );
    }


    public function addIdGetter(string $propertyName, $returnType, bool $isReturnTypeNullable, array $commentLines = [])
    {
        $this->methods[] = new FkGetter(
            $propertyName,
            $returnType,
            $isReturnTypeNullable,
            $commentLines
        );
    }


    public function addSetter(
        string $propertyName,
        $type,
        bool $isNullable,
        $classMetadata,
        array $commentLines = [],
        array $columnOptions = [],
        string $visibility = 'public'
    ) {
        $this->methods[] = new DtoSetter(
            $propertyName,
            $type,
            $isNullable,
            $commentLines,
            $visibility
        );
    }

    public function addIdSetter(
        string $propertyName,
        $targetClass,
        $hint,
        bool $isNullable,
        $classMetadata,
        array $commentLines = [],
        array $columnOptions = [],
        string $visibility = 'public'
    ) {
        $this->methods[] = new FkSetter(
            $propertyName,
            $targetClass,
            $hint,
            $visibility
        );
    }

    public function addProperty(
        string $name,
        string $typeHint,
        string $columnName,
        string $fkFqdn,
        array $comments = [],
        $defaultValue = null,
        bool $required = false,
        bool $embedded = false
    ) {

        if ($embedded) {
            $this->properties[] = new EmbeddedProperty(
                $name,
                $typeHint,
                $columnName,
                $comments,
                $defaultValue,
                $required,
                $fkFqdn,
                'private'
            );

            return;
        }

        $this->properties[] = new Property(
            $name,
            $typeHint,
            $columnName,
            $comments,
            $defaultValue,
            $required,
            $fkFqdn,
            'private'
        );
    }

    private function buildPropertyCommentLines(array $options): array
    {
        $comments = [];

        $typeHint = $this->getEntityTypeHint($options['type']);
        if (in_array($typeHint, ['\\DateTime', '\\DateTimeInterface'], true)) {
            $typeHint = '\DateTimeInterface|string';
        } else if ($typeHint === 'resource') {
            $typeHint = 'string';
        }

        $typeHint = '@var ' . $typeHint . '|null';
        $comments[] = $typeHint;

        return $comments;
    }

    private function addSingularRelation(BaseSingleRelation $relation, $classMetadata)
    {
        $propertyName = $relation->getPropertyName();
        $columnName = $classMetadata->associationMappings[$propertyName]['joinColumns'][0]['name'] ?? $propertyName;

        $typeHint = $this->addUseStatementIfNecessary(
            $relation->getTargetClassName()
        );

        if ($relation->getTargetClassName() == $this->getThisFullClassName()) {
            $typeHint = 'self';
        }

        $comments = [
            '@var ' . $typeHint . ' | null'
        ];

        $this->addProperty(
            $relation->getPropertyName(),
            $typeHint,
            $columnName,
            $relation->getTargetClassName(),
            $comments,
            null,
            false,
        );

        // Setter
        $setterHint = $relation->isNullable()
            ? '@param ' . $typeHint .  ' | null'
            : '@param ' . $typeHint;

        $setterComments = [
            $setterHint,
        ];

        $this->addSetter(
            $relation->getPropertyName(),
            $typeHint,
            $relation->isNullable(),
            $classMetadata,
            $setterComments,
            [],
            'public'
        );

        // Getter
        $returnHint = $relation->isNullable()
            ? '@return ' . $typeHint .  ' | null'
            : '@return ' . $typeHint;

        $getterComments = [
            $returnHint,
        ];

        $this->addGetter(
            $relation->getPropertyName(),
            $relation->getCustomReturnType() ?: $typeHint,
            true,
            $getterComments
        );

        ////

        $targetClassName = substr(
            $relation->getTargetClassName(),
            0,
            -3
        );

        /** @var \IvozDevTools\EntityGeneratorBundle\Doctrine\Metadata\ClassMetadata $targetClassMetadata */
        $targetClassMetadata = $this->doctrineHelper->getMetadata($targetClassName);
        $identifier = $targetClassMetadata->getIdentifier();
        $targetPkField = $targetClassMetadata->getFieldMapping(
            current($identifier)
        );
        $targetPkHint = $this->getEntityTypeHint(
            $targetPkField["type"]
        );

        $this->addIdSetter(
            $relation->getPropertyName(),
            $typeHint,
            $targetPkHint,
            true,
            $classMetadata,
        );

        $this->addIdGetter(
            $relation->getPropertyName(),
            $relation->getCustomReturnType() ?: $targetPkHint,
            true,
            []
        );
    }

    private function addCollectionRelation(BaseCollectionRelation $relation, $classMetadata)
    {
        $columnName = $classMetadata->getColumnName(
            $relation->getPropertyName()
        );

        $typeHint = $this->addUseStatementIfNecessary(
            $relation->getTargetClassName()
        );

        if ($relation->getTargetClassName() == $this->getThisFullClassName()) {
            $typeHint = 'self';
        }

        $comments = [
            '@var ' . $typeHint . '[] | null'
        ];

        $this->addProperty(
            $relation->getPropertyName(),
            'array',
            $columnName,
            $relation->getTargetClassName(),
            $comments,
            null,
            false,
        );

        // Setter
        $setterHint = '@param ' . $typeHint .  '[] | null $' . $relation->getPropertyName();

        $setterComments = [
            $setterHint,
        ];

        $this->addSetter(
            $relation->getPropertyName(),
            'array',
            true,
            $classMetadata,
            $setterComments,
            [],
            'public'
        );

        // Getter
        $returnHint = '@return ' . $typeHint .  '[] | null';

        $getterComments = [
            $returnHint,
        ];

        $this->addGetter(
            $relation->getPropertyName(),
            'array',
            true,
            $getterComments
        );
    }

    /**
     * @return string The alias to use when referencing this class
     */
    public function addUseStatementIfNecessary(string $class): string
    {
        $shortClassName = Str::getShortClassName($class);
        if ($this->isInSameNamespace($class)) {
            return $shortClassName;
        }

        if (!array_key_exists($class, $this->useStatements)) {
            $this->useStatements[$class] = new UseStatement($class);
        }

        return $shortClassName;
    }

    private function getClassNode(): Node\Stmt\Class_
    {
        /** @var null|Node\Stmt\Class_ $node */
        $node = $this->findFirstNode(function ($node) {
            return $node instanceof Node\Stmt\Class_;
        });

        if (!$node) {
            throw new \Exception('Could not find class node');
        }

        return $node;
    }

    private function getNamespaceNode(): Node\Stmt\Namespace_
    {
        /** @var null|Node\Stmt\Namespace_ $node */
        $node = $this->findFirstNode(function ($node) {
            return $node instanceof Node\Stmt\Namespace_;
        });

        if (!$node) {
            throw new \Exception('Could not find namespace node');
        }

        return $node;
    }

    /**
     * @return Node|null
     */
    private function findFirstNode(callable $filterCallback)
    {
        $traverser = new NodeTraverser();
        $visitor = new NodeVisitor\FirstFindingVisitor($filterCallback);
        $traverser->addVisitor($visitor);
        $traverser->traverse($this->ast);

        return $visitor->getFoundNode();
    }

    private function isInSameNamespace($class)
    {
        $namespace = substr($class, 0, strrpos($class, '\\'));

        return $this->getNamespaceNode()->name->toCodeString() === $namespace;
    }

    private function getThisFullClassName(): string
    {
        $class = $this->getClassNode();
        $namespace = $this->getNamespaceNode();

        return
            $namespace->name->toString()
            . '\\'
            . $class->name->toString();
    }

    private function updateToArray(string $leftPad): void
    {
        $src = [];
        $parsedEmbeddedProperties = [];
        foreach ($this->properties as $property) {

            if ($property instanceof EmbeddedProperty) {

                $prefix = substr(
                    $property->getName(),
                    0,
                    -1 * strlen($property->getColumnName())
                );

                if (in_array($prefix, $parsedEmbeddedProperties)) {
                    continue;
                }

                $parsedEmbeddedProperties[] = $prefix;
                $src[] = $this->getEmbeddedToArray($prefix, $leftPad);
                continue;
            }

            $getter = '$this->get' . Str::asCamelCase($property->getName());

            $src[] =
                '\''
                . $property->getName()
                . '\''
                .' => '
                . $getter . '()';;
        }

        $srcStr = join(
            ",\n" . str_repeat($leftPad, 3),
            $src
        );

        $this->updateClass(
            self::TO_ARRAY_PLACEHOLDER,
            [new StringNode($srcStr)],
            '',
            ''
        );
    }

    private function getEmbeddedToArray(string $prefix, $leftPad): string
    {
        /** @var Property[] $targetProperties */
        $targetProperties = array_filter(
            $this->properties,
            function (Property $item) use($prefix) {
                return
                    $item->getName() === $prefix . ucfirst($item->getColumnName());
            }
        );

        $src = [
            '\'' . $prefix . '\' => ['
        ];

        foreach ($targetProperties as $property) {
            $src[] =
                '    \''
                . $property->getColumnName()
                . '\' => $this->get'
                . ucfirst($property->getName())
                .  '(),';
        }
        $src[] = ']';

        return join("\n" . str_repeat($leftPad, 3), $src);
    }

    private function updatePropertyMap(string $leftPad): void
    {
        $src = [];
        $parsedEmbeddedProperties= [];
        foreach ($this->properties as $property) {

            $comments = $property->getComments();
            $isCollection = strpos(
                $comments[0] ?? '',
                '[]'
            );

            if ($isCollection) {
                continue;
            }

            if ($property instanceof EmbeddedProperty) {

                $prefix = substr(
                    $property->getName(),
                    0,
                    -1 * strlen($property->getColumnName())
                );

                if (in_array($prefix, $parsedEmbeddedProperties)) {
                    continue;
                }

                $parsedEmbeddedProperties[] = $prefix;
                $src[] = $this->getEmbeddedPropertyMap($prefix, $leftPad);
                continue;
            }

            $key = $property->isForeignKey()
                ? $property->getName() . 'Id'
                : $property->getName();

            $src[] =
                '\''
                . $key
                . '\''
                .' => \''
                . $property->getName() . '\'';
        }

        $srcStr = join(
            ",\n" . str_repeat($leftPad, 3),
            $src
        );

        $this->updateClass(
            self::PROPERTY_MAP_PLACEHOLDER,
            [new StringNode($srcStr)],
            '',
            ''
        );
    }

    private function getEmbeddedPropertyMap(string $prefix, $leftPad): string
    {
        /** @var Property[] $targetProperties */
        $targetProperties = array_filter(
            $this->properties,
            function (Property $item) use($prefix) {
                return
                    $item->getName() === $prefix . ucfirst($item->getColumnName());
            }
        );

        $src = '\'' . $prefix . '\' => [';

        foreach ($targetProperties as $property) {
            $src .=
                "\n" . str_repeat($leftPad, 4)
                . '\''
                . $property->getColumnName()
                . '\',';
        }
        $src .= "\n" . str_repeat($leftPad, 3) . "]";

        return $src;
    }

    /**
     * @param CodeGeneratorUnitInterface[] $items
     * @param string $leftPad
     */
    private function updateClass(string $placeholder, array $items, string $leftPad, string $join = "\n\n"): void
    {
        $value = '';
        foreach ($items as $item) {
            $value .=
                $item->toString($leftPad)
                . $join
                . $leftPad;
        }

        $this->sourceCode = str_replace(
            $placeholder,
            $value,
            $this->sourceCode
        );
    }

    public function addMethod(\ReflectionMethod $method, $classMetadata)
    {
        // TODO: Implement addMethod() method.
    }

    public function addConstant(string $name, string $value)
    {
        // TODO: Implement addConstant() method.
    }
}
