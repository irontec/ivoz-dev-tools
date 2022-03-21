<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Entity;

use Doctrine\ORM\Mapping\ClassMetadata;
use IvozDevTools\EntityGeneratorBundle\Doctrine\CodeGeneratorUnitInterface;
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
final class EntityManipulator implements ManipulatorInterface
{
    use EntityTypeTrait;

    const CLASS_USE_STATEMENT_PLACEHOLDER = '/*__class_use_statements*/';
    const CLASS_ATTRIBUTE_PLACEHOLDER = '/*__class_attributes*/';
    const CLASS_METHOD_PLACEHOLDER = '/*__class_methods*/';
    const CLASS_CONSTRUCTOR_ARGS_PLACEHOLDER = '/*__construct_args*/';
    const CLASS_CONSTRUCTOR_PLACEHOLDER = '/*__construct_body*/';
    const FROM_DTO_INSTANCE_CONSTRUCTOR_PLACEHOLDER = '/*__fromDto_instance_constructor*/';
    const FROM_DTO_EMBEDDED_CONSTRUCTOR_PLACEHOLDER = '/*__fromDto_embedded_constructor*/';
    const FROM_DTO_SETTERS_PLACEHOLDER = '/*__fromDto_setters*/';
    const UPDATE_FROM_DTO_ASSERTION_PLACEHOLDER = '/*__updateFromDto_assertions*/';
    const UPDATE_FROM_DTO_SETTERS_PLACEHOLDER = '/*__updateFromDto_body*/';
    const TO_DTO_SETTERS_PLACEHOLDER = '/*__toDto_body*/';
    const TO_ARRAY_PLACEHOLDER = '/*__toArray_body*/';

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
            self::CLASS_ATTRIBUTE_PLACEHOLDER,
            $this->properties,
            $leftPad
        );

        $this->updateClassConstructor(
            $leftPad
        );

        $this->setFromDto($leftPad);

        $this->setUpdateFromDto($leftPad);

        $this->setToDto($leftPad);

        $this->setToArray($leftPad);

        $this->updateClass(
            self::CLASS_METHOD_PLACEHOLDER,
            $this->methods,
            $leftPad
        );

        $this->updateClass(
            self::CLASS_USE_STATEMENT_PLACEHOLDER,
            $this->useStatements,
            '',
            "\n"
        );

        // Remove black lines
        $this->sourceCode = preg_replace('/^[\t|\s]+\n+/m', "\n", $this->sourceCode);
        $this->sourceCode = preg_replace('/\n{2,}(\s*\})/m', "\n$1", $this->sourceCode);
        $this->sourceCode = preg_replace('/\(\n{2,}\s*\)\;/m', "();", $this->sourceCode);
        $this->sourceCode = preg_replace('/\(\n+(\s+)\) \{/m', "()\n$1{", $this->sourceCode);

        $this->useStatements = [];
        $this->properties = [];
        $this->methods = [];
    }

    public function getSourceCode(): string
    {
        return $this->sourceCode;
    }

    public function addEntityField(string $propertyName, array $columnOptions, $classMetadata, array $comments = [])
    {
        $columnName = $columnOptions['columnName'] ?? $propertyName;
        $typeHint = $this->getEntityTypeHint($columnOptions['type']);
        if ($typeHint == '\\DateTime') {
            $this->addUseStatementIfNecessary(
                'Ivoz\\Core\\Domain\\Model\\Helper\\DateTimeHelper'
            );
        }

        $nullable = $columnOptions['nullable'] ?? false;
        $isId = (bool) ($columnOptions['id'] ?? false);

        if ($typeHint === 'resource') {
            if ($nullable) {
                $comments[] = '@var null | ' . $typeHint;
            } else {
                $comments[] = '@var ' . $typeHint;
            }
        } else {
            if ($nullable) {
                $comments[] = '@var ?' . $typeHint;
            } else {
                $comments[] = '@var ' . $typeHint;
            }
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
                case '\\DateTime':
                    $defaultValue = null;
                    break;
            }
        }

        if ('array' === $typeHint) {
            $defaultValue = [];
        }

        if ($defaultValue === 'CURRENT_TIMESTAMP') {
            $defaultValue = null;
        }

        $this->addProperty(
            $propertyName,
            $typeHint,
            $columnName,
            '',
            $comments,
            $defaultValue,
            !$nullable,
        );

        // don't generate setters for id fields
        if (!$isId) {

            $paramDoc = '@param ' . $typeHint . ' $' . $propertyName;
            if ($nullable) {
                $paramDoc .= ' | null';
            }

            $this->addSetter(
                $propertyName,
                $typeHint,
                $nullable,
                $classMetadata,
                [],
                $columnOptions,
            );
        }

        $returnHint = '@return ' . ($typeHint !== 'resource' ? $typeHint : 'string');
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
        $typeHint = $this->addUseStatementIfNecessary($className);
        $comments = ['@var ' . $typeHint];
        $this->addEmbeddedProperty($propertyName, $typeHint, $propertyName, $typeHint, $comments, null, true);
        $this->addEmbeddedGetter($propertyName, $typeHint, false);
        $this->addEmbeddedSetter($propertyName, $typeHint, false, $classMetadata);
    }

    public function addManyToOneRelation(RelationManyToOne $manyToOne, ClassMetadata $classMetadata)
    {
        $this->addSingularRelation($manyToOne, $classMetadata);
    }

    public function addOneToOneRelation(RelationOneToOne $oneToOne, ClassMetadata $classMetadata)
    {
        $this->addSingularRelation($oneToOne, $classMetadata);
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
        $classMetadata,
        array $commentLines = [],
        array $columnOptions = [],
        string $visibility = 'protected'
    ) {
        $this->methods[] = new Setter(
            $propertyName,
            $type,
            $isNullable,
            $classMetadata,
            $commentLines,
            $columnOptions,
            $visibility
        );
    }

    public function addEmbeddedSetter(
        string $propertyName,
        $type,
        bool $isNullable,
        $classMetadata,
        array $commentLines = [],
        array $columnOptions = [],
        string $visibility = 'protected'
    ) {
        $this->methods[] = new EmbeddedSetter(
            $propertyName,
            $type,
            $isNullable,
            $classMetadata,
            $commentLines,
            $columnOptions,
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
        $isCollection = true
    ) {
        $this->properties[] = new Property(
            $name,
            $typeHint,
            $columnName,
            $comments,
            $defaultValue,
            $required,
            $fkFqdn
        );
    }

    public function addEmbeddedProperty(
        string $name,
        string $typeHint,
        string $columnName,
        string $fkFqdn,
        array $comments = [],
        $defaultValue = null,
        bool $required = false,
    ) {
        $this->properties[] = new EmbeddedProperty(
            $name,
            $typeHint,
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

        return $comments;
    }

    private function addSingularRelation(BaseSingleRelation $relation, $classMetadata)
    {
        $columnName = $classMetadata->getColumnName(
            $relation->getPropertyName()
        );

        $typeHint = $this->addUseStatementIfNecessary(
            $relation->getTargetClassName(),
            $classMetadata
        );

        if ($relation->getTargetClassName() == $this->getThisFullClassName()) {
            $typeHint = 'self';
        }

        $comments = [];
        $comments[] = $relation->isNullable()
            ? '@var ?' . $typeHint
            : '@var ' . $typeHint;

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
            $typeHint,
            $columnName,
            $relation->getTargetClassName(),
            $comments,
            null,
            !$relation->isNullable(),
            false
        );

        // Setter

        $nullableSetter = $relation->isNullable();
        $setterHint = $nullableSetter
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
            $nullableSetter,
            $classMetadata,
            $setterComments,
            [],
            $setterVisibility
        );

        // Getter
        $returnHint = $nullableSetter
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
            $nullableSetter,
            $getterComments
        );
    }

    /**
     * @return string The alias to use when referencing this class
     */
    public function addUseStatementIfNecessary(string $class, ClassMetadata $classMetadata = null): string
    {
        $needle = [
            'Interface',
            'Abstract'
        ];

        $namespace = Str::getNamespace($class);
        $namespace2 = Str::getNamespace($classMetadata->name ?? '');

        $shortClassName = Str::getShortClassName($class);
        $shortClassName2 = Str::getShortClassName($classMetadata->name ?? '');

        $fixedShortClassName = str_replace($needle, '', $shortClassName);
        $fixedShortClassName2 = str_replace($needle, '', $shortClassName2);

        if ($namespace === $namespace2) {
            return $shortClassName;
        }

        if ($fixedShortClassName === $fixedShortClassName2) {
            return '\\' . $class;
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

    private function getThisFullClassName(): string
    {
        $class = $this->getClassNode();
        $namespace = $this->getNamespaceNode();

        return
            $namespace->name->toString()
            . '\\'
            . $class->name->toString();
    }

    private function updateClassConstructor(string $leftPad): void
    {
        $requiredProperties = array_filter(
            $this->properties,
            function ($property) {

                if ($property instanceof EmbeddedProperty) {
                    return true;
                }

                return $property->isRequired() && !$property->isForeignKey();
            }
        );

        $src = [];
        foreach ($requiredProperties as $property) {

            $hint = $property->getHint();
            if ($property->getHint() === '\\' . \DateTime::class) {
                $hint = '\DateTimeInterface|string';
            }

            $src[] = $property instanceof EmbeddedProperty
                ? $property->getForeignKeyFqdn() . ' $' . $property->getName()
                : $hint . ' $' . $property->getName();
        }
        $srcStr = implode(",\n" . str_repeat($leftPad, 2), $src);

        $this->updateClass(
            self::CLASS_CONSTRUCTOR_ARGS_PLACEHOLDER,
            [new StringNode($srcStr)],
            '',
            ''
        );

        $src = [];
        foreach ($requiredProperties as $property) {

            if ($property instanceof EmbeddedProperty) {
                $src[] = sprintf(
                    '$this->%s = %s;',
                    $property->getName(),
                    '$' . $property->getName()
                );
            } else {
                $src[] = sprintf(
                    '$this->set%s(%s);',
                    Str::asCamelCase($property->getName()),
                    '$' . $property->getName()
                );
            }
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

    /**
     * @param string $leftPad
     * @throws \Exception
     */
    private function setToArray(string $leftPad): void
    {
        $src = [];
        foreach ($this->properties as $property) {

            $getter = 'self::get' . Str::asCamelCase($property->getName());

            if ($property instanceof EmbeddedProperty) {

                $embeddedFqdn =
                    $this->getNamespaceNode()->name->toCodeString()
                    . '\\'
                    . $property->getForeignKeyFqdn();

                /** @var ClassMetadata $embeddedMetadata */
                $embeddedMetadata = $this->doctrineHelper->getMetadata(
                    $embeddedFqdn
                );

                foreach ($embeddedMetadata->fieldMappings as $name => $details) {

                    $instance = Str::asCamelCase($property->getName());
                    $subProperty = Str::asCamelCase($name);

                    $src[] =
                        '\''
                        . $property->getName() . $subProperty
                        . '\' => '
                        . 'self::get' . $instance . '()->get' . $subProperty . '()';
                }

            } elseif ($property->isForeignKey()) {

                $stmt =
                    '\''
                    . $property->getColumnName() . 'Id'
                    . '\''
                    . ' => ';

                if ($property->isRequired()) {
                    $stmt .= $getter . '()->getId()';
                } else {
                    $stmt .= $getter . '()?->getId()';
                }

                $src[] = $stmt;

            } else {
                $src[] =
                    '\''
                    . $property->getColumnName()
                    . '\''
                    . ' => '
                    . $getter . '()';
            }
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

    /**
     * @param string $leftPad
     * @throws \Exception
     */
    private function setToDto(string $leftPad): void
    {
        $src = [];
        foreach ($this->properties as $property) {

            $setter = 'set' . Str::asCamelCase($property->getName());
            $getter = 'get' . Str::asCamelCase($property->getName());

            if ($property instanceof EmbeddedProperty) {

                $embeddedFqdn =
                    $this->getNamespaceNode()->name->toCodeString()
                    . '\\'
                    . $property->getForeignKeyFqdn();

                /** @var ClassMetadata $embeddedMetadata */
                $embeddedMetadata = $this->doctrineHelper->getMetadata(
                    $embeddedFqdn
                );

                foreach ($embeddedMetadata->fieldMappings as $name => $details) {

                    $instance = Str::asCamelCase($property->getName());
                    $subProperty = Str::asCamelCase($name);

                    $setter =
                        'set'
                        . $instance
                        . $subProperty
                        . '('
                        . 'self::get' . $instance . '()->get' . $subProperty . '()'
                        . ')';

                    $src[] = '->' . $setter;
                }

            } elseif ($property->isForeignKey()) {
                $targetClass = str_replace(
                    'Interface',
                    '',
                    $property->getForeignKeyFqdn(false)
                );

                $shortTargetClass = $this->addUseStatementIfNecessary(
                    $targetClass
                );

                $src[] = '->' . $setter . '(' . $shortTargetClass . '::entityToDto(self::' . $getter . '(), $depth))';

            } else {
                $src[] = '->' . $setter . '(self::' . $getter . '())';
            }
        }

        $srcStr = join(
            "\n" . str_repeat($leftPad, 3),
            $src
        );

        $this->updateClass(
            self::TO_DTO_SETTERS_PLACEHOLDER,
            [new StringNode($srcStr)],
            '',
            ''
        );
    }

    /**
     * @param string $leftPad
     */
    private function setUpdateFromDto(string $leftPad): void
    {
        $src = [];
        $assertions = [];
        foreach ($this->properties as $property) {

            if ($property instanceof EmbeddedProperty) {

                $setter = 'set' . Str::asCamelCase($property->getName());
                $property = '$' . $property->getName();
                $stmt = '->' . $setter . '(' . $property . ')';

                $src[] = $stmt;

                continue;
            }

            $setter = 'set' . Str::asCamelCase($property->getName());
            $getter = 'get' . Str::asCamelCase($property->getName());

            if ($property->isRequired()) {
                $assertions[] = sprintf(
                    '$%s = $dto->%s();',
                    $property->getName(),
                    $getter
                );

                $assertions[] = sprintf(
                    'Assertion::notNull($%s, \'%s value is null, but non null value was expected.\');',
                    $property->getName(),
                    $getter,
                );

                $stmt = $property->isForeignKey()
                    ? '->' . $setter . '($fkTransformer->transform($' . $property->getName() . '))'
                    : '->' . $setter . '($' . $property->getName() . ')';

            } else {

                $stmt = $property->isForeignKey()
                    ? '->' . $setter . '($fkTransformer->transform($dto->' . $getter . '()))'
                    : '->' . $setter . '($dto->' . $getter . '())';
            }

            $src[] = $stmt;
        }

        $assertionsStr = join(
            "\n" . str_repeat($leftPad, 2),
            $assertions
        );

        $this->updateClass(
            self::UPDATE_FROM_DTO_ASSERTION_PLACEHOLDER,
            [new StringNode($assertionsStr)],
            '',
            ''
        );

        $srcStr = join(
            "\n" . str_repeat($leftPad, 3),
            $src
        );

        $this->updateClass(
            self::UPDATE_FROM_DTO_SETTERS_PLACEHOLDER,
            [new StringNode($srcStr)],
            '',
            ''
        );
    }

    /**
     * @param string $leftPad
     */
    private function setFromDto(string $leftPad): void
    {
        $src = [];
        foreach ($this->properties as $property) {

            if (!$property instanceof EmbeddedProperty) {
                continue;
            }

            if (empty($src)) {
                $src[] = '';
            }

            $embeddedFqdn =
                $this->getNamespaceNode()->name->toCodeString()
                . '\\'
                . $property->getForeignKeyFqdn();

            /** @var ClassMetadata $embeddedMetadata */
            $embeddedMetadata = $this->doctrineHelper->getMetadata(
                $embeddedFqdn
            );

            $src[] = '$' . $property->getName() . ' = new '. ucfirst($property->getName()) . '(';
            $position = 1;
            foreach ($embeddedMetadata->fieldMappings as $name => $details) {
                $getter =
                    'get'
                    . Str::asCamelCase($property->getName())
                    . Str::asCamelCase($name)
                    . '()';

                if ($position < count($embeddedMetadata->fieldMappings)) {
                    $getter .= ',';
                    $position++;
                }

                $src[] = '    $dto->'. $getter;
            }
            $src[] = ');';
            $src[] = '';
        }

        $srcStr = join(
            "\n" . str_repeat($leftPad, 2),
            $src
        );

        $replaceValue = ! empty($srcStr)
            ? [new StringNode($srcStr)]
            : [];

        $this->updateClass(
            self::FROM_DTO_EMBEDDED_CONSTRUCTOR_PLACEHOLDER,
            $replaceValue,
            '',
            ''
        );

        ///////////////////////////////////

        $src = [];
        foreach ($this->properties as $property) {

            if ($property instanceof EmbeddedProperty) {
                $src[] = '$' . $property->getName();
                continue;
            }

            if (!$property->isRequired() || $property->isForeignKey()) {
                continue;
            }

            $src[] = '$' . $property->getName();
        }

        $srcStr = join(
            ",\n" . str_repeat($leftPad, 3),
            $src
        );

        $this->updateClass(
            self::FROM_DTO_INSTANCE_CONSTRUCTOR_PLACEHOLDER,
            [new StringNode($srcStr)],
            '',
            ''
        );

        ///////////////////////////////////////////

        $src = [];
        foreach ($this->properties as $property) {
            if ($property->isRequired() && !$property->isForeignKey()) {
                continue;
            }

            $setter = 'set' . Str::asCamelCase($property->getName());
            $getter = 'get' . Str::asCamelCase($property->getName());

            if ($property->isRequired()) {
                $stmt = $property->isForeignKey()
                    ? '->' . $setter . '($fkTransformer->transform($' . $property->getName() . '))'
                    : '->' . $setter . '($' . $property->getName() . ')';
            } else {
                $stmt = $property->isForeignKey()
                    ? '->' . $setter . '($fkTransformer->transform($dto->' . $getter . '()))'
                    : '->' . $setter . '($dto->' . $getter . '())';
            }

            if (empty($src)) {
                $stmt =
                    '$self'
                    . "\n"
                    . str_repeat($leftPad, 3)
                    . $stmt;
            }

            $src[] = $stmt;
        }

        $srcStr = join(
            "\n" . str_repeat($leftPad, 3),
            $src
        );
        $srcStr .= ';';

        $this->updateClass(
            self::FROM_DTO_SETTERS_PLACEHOLDER,
            [new StringNode($srcStr)],
            '',
            ''
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
