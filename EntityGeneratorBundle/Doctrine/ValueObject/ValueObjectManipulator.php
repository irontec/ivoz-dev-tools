<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\ValueObject;

use Doctrine\ORM\Mapping\ClassMetadata;
use IvozDevTools\EntityGeneratorBundle\Doctrine\CodeGeneratorUnitInterface;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Entity\EmbeddedProperty;
use IvozDevTools\EntityGeneratorBundle\Doctrine\EntityTypeTrait;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Getter;
use IvozDevTools\EntityGeneratorBundle\Doctrine\ManipulatorInterface;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Property;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Setter;
use IvozDevTools\EntityGeneratorBundle\Doctrine\StringNode;
use IvozDevTools\EntityGeneratorBundle\Doctrine\UseStatement;
use PhpParser\Lexer;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\Parser;
use Symfony\Bundle\MakerBundle\Doctrine\BaseRelation;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Symfony\Bundle\MakerBundle\Doctrine\RelationManyToMany;
use Symfony\Bundle\MakerBundle\Doctrine\RelationManyToOne;
use Symfony\Bundle\MakerBundle\Doctrine\RelationOneToMany;
use Symfony\Bundle\MakerBundle\Doctrine\RelationOneToOne;
use Symfony\Bundle\MakerBundle\Str;

/**
 * @internal
 */
final class ValueObjectManipulator implements ManipulatorInterface
{
    use EntityTypeTrait;

    const CLASS_USE_STATEMENT_PLACEHOLDER = '/*__class_use_statements*/';
    const CLASS_ATTRIBUTE_PLACEHOLDER = '/*__class_attributes*/';
    const CLASS_METHOD_PLACEHOLDER = '/*__class_methods*/';
    const CLASS_CONSTRUCTOR_ARGS_PLACEHOLDER = '/*__construct_args*/';
    const CLASS_CONSTRUCTOR_PLACEHOLDER = '/*__construct_body*/';
    const CLASS_EQUALS_BODY = '/*__equals_body*/';
    const CLASS_EQUALS_ATTR = '/*__equals_attribute*/';

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

    /** @var DoctrineHelper */
    private $doctrineHelper;

    public function __construct(
        string $sourceCode,
        DoctrineHelper $doctrineHelper
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
        $this->doctrineHelper = $doctrineHelper;
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

        $this->updateClassConstructor(
            $leftPad
        );

        $this->updateEquals(
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

    public function addEntityField(string $propertyName, array $columnOptions, array $comments = [], $classMetadata)
    {
        $columnName = $columnOptions['columnName'] ?? $propertyName;
        $typeHint = $this->getEntityTypeHint($columnOptions['type']);

        if ($typeHint == '\\DateTime') {
            $this->addUseStatementIfNecessary(
                'Ivoz\\Core\\Domain\\Model\\Helper\\DateTimeHelper'
            );
        }

        $nullable = $columnOptions['nullable'] ?? false;

        $comments += $this->buildPropertyCommentLines($columnOptions);
        $defaultValue = $columnOptions['options']['default'] ?? null;

        if ($defaultValue) {
            switch ($typeHint) {
                case 'int':
                    $defaultValue = intval($defaultValue);
                    break;
                case 'float':
                    $defaultValue = floatval($defaultValue);
                    break;
            }
        }

        if ('array' === $typeHint) {
            $defaultValue = [];
        }

        $this->addProperty(
            $propertyName,
            $columnName,
            $comments,
            $defaultValue,
            !$nullable,
            ''
        );


        $paramDoc = '@param ' . $typeHint . ' $' . $propertyName;
        if ($nullable) {
            $paramDoc .= ' | null';
        }

        $setterComments = [
            'Set ' . $propertyName,
            '',
            $paramDoc,
        ];

        $this->addSetter(
            $propertyName,
            $typeHint,
            $nullable,
            $setterComments,
            $columnOptions,
            $classMetadata
        );

        $returnHint = '@return ' . $typeHint;
        if ($nullable) {
            $returnHint .= ' | null';
        }
        $getterComments = [
            'Get ' . $propertyName,
            '',
            $returnHint
        ];

        $this->addGetter(
            $propertyName,
            $typeHint,
            // getter methods always have nullable return values
            // because even though these are required in the db, they may not be set yet
            $nullable,
            $getterComments
        );
    }

    public function addEmbeddedEntity(string $propertyName, string $className, ClassMetadata $classMetadata = null)
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

    public function addInterface(string $interfaceName, ClassMetadata $classMetadata = null)
    {
    }

    public function addAccessorMethod(string $propertyName, string $methodName, $returnType, bool $isReturnTypeNullable, array $commentLines = [], $typeCast = null)
    {
    }

    public function addGetter(string $propertyName, $returnType, bool $isReturnTypeNullable, array $commentLines = [])
    {
        $this->methods[] = new Getter(
            $propertyName,
            $returnType,
            $isReturnTypeNullable,
            $commentLines
        );
    }


    public function addEmbeddedGetter(string $propertyName, $returnType, bool $isReturnTypeNullable, array $commentLines = [])
    {
        $this->methods[] = new EmbeddedGetter(
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
        array $commentLines = [],
        array $columnOptions = [],
        $classMetadata,
        string $visibility = 'protected'
    ) {
        $this->methods[] = new Setter(
            $propertyName,
            $type,
            $isNullable,
            $commentLines,
            $columnOptions,
            $classMetadata,
            $visibility
        );
    }

    public function addEmbeddedSetter(
        string $propertyName,
        $type,
        bool $isNullable,
        array $commentLines = [],
        array $columnOptions = [],
        $classMetadata,
        string $visibility = 'protected'
    ) {
        $this->methods[] = new EmbeddedSetter(
            $propertyName,
            $type,
            $isNullable,
            $commentLines,
            $columnOptions,
            $classMetadata,
            $visibility
        );
    }

    public function addProperty(
        string $name,
        string $columnName,
        array $comments = [],
        $defaultValue = null,
        bool $required = false,
        string $fkFqdn
    ) {
        $this->properties[] = new Property(
            $name,
            $columnName,
            $comments,
            $defaultValue,
            $required,
            $fkFqdn
        );
    }

    public function addEmbeddedProperty(
        string $name,
        string $columnName,
        array $comments = [],
        $defaultValue = null,
        bool $required = false,
        string $fkFqdn
    ) {
        $this->properties[] = new EmbeddedProperty(
            $name,
            $columnName,
            $comments,
            $defaultValue,
            $required,
            $fkFqdn
        );
    }

    private function buildPropertyCommentLines(array $options): array
    {
        $comments = [];
        if ($options['fieldName'] !== $options['columnName']) {
            $comments[] = sprintf(
                'column: %s',
                $options['columnName']
            );
        }

        $columnComment = $options['options']['comment'] ?? null;
        if ($columnComment && strpos($columnComment, '[') === 0) {
            $comments[] = sprintf(
                'comment: %s',
                substr($columnComment, 1, -1)
            );
        }

        $typeHint = '@var ' . $this->getEntityTypeHint($options['type']);
        $nullable = $options['nullable'] ?? false;
        if ($nullable) {
            $typeHint .= ' | null';
        }
        $comments[] = $typeHint;

        return $comments;
    }

    private function addSingularRelation(BaseRelation $relation, $classMetadata)
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
            '@var ' . $typeHint
        ];

        $setterVisibility = 'protected';
        if ($relation->isOwning()) {
            // sometimes, we don't map the inverse relation
            if ($relation->getMapInverseRelation()) {
                $comments[] = 'inversedBy ' . $relation->getTargetPropertyName();
                $setterVisibility = 'public';
            }
        } else {
            $comments[] = 'mappedBy ' . $relation->getTargetPropertyName();
        }

        $this->addProperty(
            $relation->getPropertyName(),
            $columnName,
            $comments,
            null,
            !$relation->isNullable(),
            $relation->getTargetClassName()
        );

        // Setter

        $setterHint = $relation->isNullable()
            ? '@param ' . $typeHint .  ' | null'
            : '@param ' . $typeHint;

        $setterComments = [
            'Set ' . $relation->getPropertyName(),
            '',
            $setterHint,
        ];

        $this->addSetter(
            $relation->getPropertyName(),
            $typeHint,
            $relation->isNullable(),
            $setterComments,
            [],
            $classMetadata,
            $setterVisibility
        );

        // Getter
        $returnHint = $relation->isNullable()
            ? '@return ' . $typeHint .  ' | null'
            : '@return ' . $typeHint;

        $getterComments = [
            'Get ' . $relation->getPropertyName(),
            '',
            $returnHint,
        ];

        $this->addGetter(
            $relation->getPropertyName(),
            $relation->getCustomReturnType() ?: $typeHint,
            $relation->isNullable(),
            $getterComments
        );

        if ($relation->shouldAvoidSetter()) {
            return;
        }
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

    /**
     * @param CodeGeneratorUnitInterface[] $items
     */
    private function updateClassConstructor(string $leftPad): void
    {
        $src = [];
        foreach ($this->properties as $property) {
            $src[] = $property instanceof EmbeddedProperty
                ? $property->getForeignKeyFqdn() . ' $' . $property->getName()
                : '$' . $property->getName();
        }
        $srcStr = implode(",\n" . str_repeat($leftPad, 2), $src);

        $this->updateClass(
            self::CLASS_CONSTRUCTOR_ARGS_PLACEHOLDER,
            [new StringNode($srcStr)],
            '',
            ''
        );

        $src = [];
        foreach ($this->properties as $property) {
            $src[] = sprintf(
                '$this->set%s(%s);',
                Str::asCamelCase($property->getName()),
                '$' . $property->getName()
            );
        }
        $srcStr = implode(
            "\n" . str_repeat($leftPad, 2),
            $src
        );

        $this->updateClass(
            self::CLASS_CONSTRUCTOR_PLACEHOLDER,
            [new StringNode($srcStr)],
            '',
            ''
        );
    }

    /**
     * @param CodeGeneratorUnitInterface[] $items
     */
    private function updateEquals(string $leftPad): void
    {
        $attr = lcfirst($this->getClassNode()->name->toString());
        $this->updateClass(
            self::CLASS_EQUALS_ATTR,
            [new StringNode('self $' . $attr)],
            '',
            ''
        );

        $src = [];
        foreach ($this->properties as $property) {
            $getter = 'get' . Str::asCamelCase($property->getName());
            $src[] = '$this->' . $getter . '() === $' . $attr . '->' . $getter .'()';
        }
        $srcStr = implode(" &&\n" . str_repeat($leftPad, 3), $src);

        $this->updateClass(
            self::CLASS_EQUALS_BODY,
            [new StringNode($srcStr)],
            '',
            ''
        );

        $src = [];
        foreach ($this->properties as $property) {
            $src[] = sprintf(
                '$this->set%s(%s);',
                Str::asCamelCase($property->getName()),
                '$' . $property->getName()
            );
        }
        $srcStr = implode(
            "\n" . str_repeat($leftPad, 2),
            $src
        );

        $this->updateClass(
            self::CLASS_CONSTRUCTOR_PLACEHOLDER,
            [new StringNode($srcStr)],
            '',
            ''
        );
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
