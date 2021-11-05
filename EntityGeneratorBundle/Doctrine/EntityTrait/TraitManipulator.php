<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\EntityTrait;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping\ClassMetadata;
use IvozDevTools\EntityGeneratorBundle\Doctrine\CodeGeneratorUnitInterface;
use IvozDevTools\EntityGeneratorBundle\Doctrine\EntityTypeTrait;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Getter as SingularGetter;
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
use Symfony\Bundle\MakerBundle\Doctrine\BaseCollectionRelation;
use Symfony\Bundle\MakerBundle\Doctrine\BaseRelation;
use Symfony\Bundle\MakerBundle\Doctrine\BaseSingleRelation;
use Symfony\Bundle\MakerBundle\Doctrine\RelationManyToMany;
use Symfony\Bundle\MakerBundle\Doctrine\RelationManyToOne;
use Symfony\Bundle\MakerBundle\Doctrine\RelationOneToMany;
use Symfony\Bundle\MakerBundle\Doctrine\RelationOneToOne;
use Symfony\Bundle\MakerBundle\Str;

/**
 * @internal
 */
final class TraitManipulator implements ManipulatorInterface
{
    use EntityTypeTrait;

    const CLASS_USE_STATEMENT_PLACEHOLDER = '/*__class_use_statements*/';
    const CLASS_ATTRIBUTE_PLACEHOLDER = '/*__class_attributes*/';
    const CLASS_METHOD_PLACEHOLDER = '/*__class_methods*/';
    const CLASS_CONSTRUCTOR_PLACEHOLDER = '/*__construct_body*/';
    const FROM_DTO_SETTERS_PLACEHOLDER = '/*__fromDto_setters*/';
    const UPDATE_FROM_DTO_SETTERS_PLACEHOLDER = '/*__updateFromDto_body*/';

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

    public function __construct(string $sourceCode)
    {
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

    public function updateSourceCode(ClassMetadata $classMetadata = null)
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
            $leftPad,
            $classMetadata
        );

        $this->updateClassFromDto(
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

    public function addEntityField(string $propertyName, array $columnOptions, $classMetadata, array $comments = [])
    {
        $columnName = $columnOptions['columnName'] ?? $propertyName;
        $typeHint = $this->getEntityTypeHint($columnOptions['type']) . 'Interface';

        if ($typeHint == '\\DateTimeInterface') {
            $this->addUseStatementIfNecessary(
                'Ivoz\\Core\\Domain\\Model\\Helper\\DateTimeHelper'
            );
        }

        $nullable = $columnOptions['nullable'] ?? false;
        $isId = (bool) ($columnOptions['id'] ?? false);

        $comments += $this->buildPropertyCommentLines($columnOptions);
        $isCollection = in_array(
            $columnOptions['type'] ?? null,
            [
                ClassMetadata::ONE_TO_MANY,
                ClassMetadata::MANY_TO_MANY
            ],
            true
        );

        $this->addProperty(
            $propertyName,
            $typeHint,
            $columnName,
            '',
            $comments,
            null,
            !$nullable,
            $isCollection
        );

        if ($isId) {
            return;
        }

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
            $classMetadata,
            $setterComments,
            $columnOptions,
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

    public function addEmbeddedEntity(string $propertyName, string $className)
    {
    }

    public function addManyToOneRelation(RelationManyToOne $manyToOne, $classMetadata)
    {
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
        throw new \Exception('@todo ManyToMany');
//        $this->addCollectionRelation($manyToMany, $classMetadata);
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

    public function addSetter(
        string $propertyName,
        $type,
        bool $isNullable,
        $classMetadata,
        array $commentLines = [],
        array $columnOptions = [],
        string $visibility = 'protected'
    ) {
        $this->methods[] = new Adder(
            $propertyName,
            $type,
            $isNullable,
            $classMetadata,
            $commentLines,
            $columnOptions,
            $visibility
        );

        $this->methods[] = new Remover(
            $propertyName,
            $type,
            $isNullable,
            $classMetadata,
            $commentLines,
            $columnOptions,
            $visibility
        );

        $this->methods[] = new Replacer(
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
        bool $isCollection = true
    ) {
        $this->properties[] = new Property(
            $name,
            $typeHint,
            $columnName,
            $comments,
            $defaultValue,
            $required,
            $fkFqdn,
            'protected',
            $isCollection
        );
    }

    private function buildPropertyCommentLines(array $options): array
    {
        $comments = [];

        $typeHint = '@var ' . $this->getEntityTypeHint($options['type']);
        $comments[] = $typeHint;

        return $comments;
    }

    private function addSingularRelation(BaseSingleRelation $relation, $classMetadata)
    {
        $columnName = $classMetadata->getColumnName(
            $relation->getPropertyName()
        );

        $targetClass = $relation->getTargetClassName() . 'Interface';
        $typeHint = $relation->isSelfReferencing()
            ? 'self'
            : $this->addUseStatementIfNecessary($targetClass);

        $this->addUseStatementIfNecessary($targetClass);

        $comments = ['@var ' . $typeHint];
        if (!$relation->isOwning()) {
            $comments[] = 'mappedBy ' . $relation->getTargetPropertyName();
        }

        $this->addProperty(
            $relation->getPropertyName(),
            $typeHint,
            $columnName,
            $typeHint,
            $comments,
            null,
            true,
            false
        );

        // Setter
        $this->methods[] = new Setter(
            $relation->getPropertyName(),
            $typeHint,
            $relation->isNullable(),
            $classMetadata,
            $comments,
            [],
            'public'
        );

        // Getter
        $returnHint = '@return ' . $typeHint;

        $getterComments = [
            'Get ' . $relation->getPropertyName(),
            $returnHint,
        ];

        $this->methods[] = new SingularGetter(
            $relation->getPropertyName(),
            $relation->getCustomReturnType() ?: $typeHint,
            true,
            $getterComments
        );
    }

    private function addCollectionRelation(BaseCollectionRelation $relation, ClassMetadata $classMetadata)
    {
        $columnName = $classMetadata->getColumnName(
            $relation->getPropertyName()
        );

        $targetClass = $relation->getTargetClassName() . 'Interface';
        $typeHint = $relation->isSelfReferencing()
            ? 'self'
            : $this->addUseStatementIfNecessary($targetClass);

        $this->addUseStatementIfNecessary(ArrayCollection::class);
        $this->addUseStatementIfNecessary(Criteria::class);
        $this->addUseStatementIfNecessary($targetClass);

        $comments = ['@var ArrayCollection'];
        if ($relation->isOwning()) {
            // sometimes, we don't map the inverse relation
            if ($relation->getMapInverseRelation()) {
                $comments[] = 'inversedBy ' . $relation->getTargetPropertyName();
            }
        } else {
            $comments[] = $typeHint . ' mappedBy ' . $relation->getTargetPropertyName();
        }

        if ($relation->getOrphanRemoval()) {
            $comments[] = 'orphanRemoval';
        }

        $this->addProperty(
            $relation->getPropertyName(),
            $typeHint,
            $columnName,
            $relation->getTargetClassName(),
            $comments,
            null,
            true,
            true
        );

        // Setter
        $this->addSetter(
            $relation->getPropertyName(),
            $typeHint,
            false,
            $classMetadata,
            [],
            [],
            'public'
        );

        // Getter
        $returnHint = '@return ' . $typeHint . '[]';

        $getterComments = [
            'Get ' . $relation->getPropertyName(),
            '@param Criteria | null $criteria',
            $returnHint,
        ];

        $this->addGetter(
            $relation->getPropertyName(),
            $relation->getCustomReturnType() ?: $typeHint,
            false,
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

    /**
     * @param string $leftPad
     * @param ClassMetadata $classMetadata
     */
    private function updateClassConstructor(string $leftPad, ClassMetadata $classMetadata): void
    {
        $associationProperties = $this->getAssociationMappings(
            $classMetadata
        );

        $src = [];
        foreach ($associationProperties as $property) {
            $src[] = sprintf(
                '$this->%s = new ArrayCollection();',
                $property['fieldName']
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

    private function updateClassFromDto(string $leftPad): void
    {
        $src = [];
        $template = <<<'TPL'
if (!is_null($dto->[GETTER]())) {
            $[INSTANCE]->[REPLACER](
                $fkTransformer->[TRANSFORMER](
                    $dto->[GETTER]()
                )
            );
        }
TPL;

        foreach ($this->properties as $property) {
            if (!$property->isForeignKey()) {
                continue;
            }

            $getter = 'get' . Str::asCamelCase($property->getName());

            $replacer = $property->isCollection()
                ? 'replace' . Str::asCamelCase($property->getName())
                : 'set' . Str::asCamelCase($property->getName());

            $transformer = $property->isCollection()
                ? 'transformCollection'
                : 'transform';

            $src[] = str_replace(
                ['[INSTANCE]', '[GETTER]', '[REPLACER]', '[TRANSFORMER]'],
                ['self', $getter, $replacer, $transformer],
                $template
            );
        }

        $srcStr = join(
            "\n\n" . str_repeat($leftPad, 2),
            $src
        );

        $this->updateClass(
            self::FROM_DTO_SETTERS_PLACEHOLDER,
            [new StringNode($srcStr)],
            '',
            ''
        );

        ///////////////////////////////////////////////////////////

        $src = [];
        foreach ($this->properties as $property) {
            if (!$property->isForeignKey()) {
                continue;
            }

            $getter = 'get' . Str::asCamelCase($property->getName());

            $replacer = $property->isCollection()
                ? 'replace' . Str::asCamelCase($property->getName())
                : 'set' . Str::asCamelCase($property->getName());

            $transformer = $property->isCollection()
                ? 'transformCollection'
                : 'transform';

            $src[] = str_replace(
                ['[INSTANCE]', '[GETTER]', '[REPLACER]', '[TRANSFORMER]'],
                ['this', $getter, $replacer, $transformer],
                $template
            );
        }

        $srcStr = join(
            "\n\n" . str_repeat($leftPad, 2),
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
     * @param ClassMetadata $classMetadata
     * @return array
     */
    private function getAssociationMappings(ClassMetadata $classMetadata): array
    {
        $associationProperties = array_filter(
            $classMetadata->associationMappings,
            function ($property) {
                return in_array(
                    $property['type'] ?? null,
                    [
                        ClassMetadata::ONE_TO_MANY,
                        ClassMetadata::MANY_TO_MANY
                    ],
                    true
                );
            }
        );
        return $associationProperties;
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
