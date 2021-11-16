<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\EntityInterface;

use Doctrine\ORM\Mapping\ClassMetadata;
use IvozDevTools\EntityGeneratorBundle\Doctrine\CodeGeneratorUnitInterface;
use IvozDevTools\EntityGeneratorBundle\Doctrine\ManipulatorInterface;
use IvozDevTools\EntityGeneratorBundle\Doctrine\UseStatement;
use PhpParser\Lexer;
use Symfony\Bundle\MakerBundle\Doctrine\RelationManyToMany;
use Symfony\Bundle\MakerBundle\Doctrine\RelationManyToOne;
use Symfony\Bundle\MakerBundle\Doctrine\RelationOneToMany;
use Symfony\Bundle\MakerBundle\Doctrine\RelationOneToOne;
use Symfony\Bundle\MakerBundle\Str;

/**
 * @internal
 */
final class InterfaceManipulator implements ManipulatorInterface
{
    const INTERFACE_USE_STATEMENT_PLACEHOLDER = '/*__interface_use_statements*/';
    const INTERFACE_BODY_PLACEHOLDER = '/*__interface_body*/';
    const INTERFACE_EXTENDS_PLACEHOLDER = '/*__interface_extends*/';

    private $sourceCode;

    /** @var CodeGeneratorUnitInterface[]  */
    private $properties = [];

    /** @var Method[]  */
    private $methods = [];
    /** @var UseStatement[]  */
    private $useStatements = [];

    /** @var string[]  */
    private $interfaces = [];

    public function __construct(string $sourceCode)
    {
        $this->sourceCode = $sourceCode;
    }

    public function addMethod(
        \ReflectionMethod $method,
        $classMetadata
    ) {
        $docComment = $method->getDocComment()
            ? $method->getDocComment()
            : '';

        $methodParameters = $method->getParameters();

        $methodParameterArray = [];
        foreach ($methodParameters as $methodParameter) {
            $str = '';
            try {
                $parameterClass = $methodParameter->getClass();
            } catch (\Exception $e) {
                // Interface does not exist yet
                continue;
            }

            $type = '';
            if ($parameterClass) {
                $type = $this->addUseStatementIfNecessary(
                    (string) $parameterClass->getName(),
                    $classMetadata
                );
                $str = $type . ' ';

                if ($methodParameter->isOptional() || str_contains((string) $methodParameter->getType(), '?')) {
                    if (!str_contains((string) $methodParameter->getType(), '|')) {
                        $str = '?' . $type . ' ';
                    }
                }
            } elseif ($methodParameter->isArray()) {
                $str = 'array ';
            } elseif ($methodParameter->hasType()) {
                $type = (string) $methodParameter->getType();
                $str = $type . ' ';
            }

            $str .= '$' . $methodParameter->getName();
            if ($methodParameter->isOptional()  && !is_null($methodParameter->getDefaultValue())) {

                $numericType = $methodParameter->hasType() && in_array($type, ['int', 'float'], true);
                $defaultValue = $numericType
                    ? $methodParameter->getDefaultValue()
                    : "'" . $methodParameter->getDefaultValue() . "'";
                $str .= " = " . $defaultValue;

            } elseif ($methodParameter->isOptional()) {
                $str .= " = null";
            }

            $methodParameterArray[] = $str;
        }

        $static = $method->isStatic()
            ? 'static '
            : '';

        $returnNamedTyped = $method->getReturnType();
        $strReturnNamedTyped = $returnNamedTyped?->__toString() ?? '';

        $nullableReturnType = $returnNamedTyped && $returnNamedTyped->allowsNull();
        $returnHints=[];
        if ($returnNamedTyped instanceof \ReflectionType) {
            $returnHints = explode('|', $strReturnNamedTyped);

            foreach ($returnHints as $k => $returnHint) {
                $cleanReturnHint = str_replace('?', '', $returnHint);

                if ($returnHint !== 'static') {
                    if (false !== strpos($returnHint, '\\')) {
                        $returnHint = $this->addUseStatementIfNecessary(
                            $returnNamedTyped->__toString(),
                            $classMetadata
                        );
                    } elseif (class_exists($returnHint) || interface_exists($returnHint)) {
                        $returnHint = '\\' . $returnHint;
                    } elseif (class_exists($cleanReturnHint) || interface_exists($cleanReturnHint)) {
                        $returnHint = str_replace('?', '?\\', $returnHint);
                    }
                }

                $returnHints[$k] = $returnHint;
            }
        }

        $hint = implode('|', $returnHints);
        $this->methods[] = new Method(
            $method->isStatic(),
            $method->getName(),
            $methodParameterArray,
            $hint,
            $nullableReturnType,
            explode("\n", $docComment)
        );
    }

    public function addInterface(string $interface, ClassMetadata $classMetadata = null)
    {
        $this->interfaces[] = $this->addUseStatementIfNecessary(
            $interface,
            $classMetadata
        );
    }

    public function updateSourceCode()
    {
        $interfaces = $this->interfaces;
        foreach ($this->methods as $method) {
            if ($method->getName() === 'getChangeSet') {
                $interfaces[] = $this->addUseStatementIfNecessary(
                    'Ivoz\\Core\\Domain\\Model\\LoggableEntityInterface'
                );
            }

            if ($method->getName() === 'getTempFiles') {
                $interfaces[] = $this->addUseStatementIfNecessary(
                    'Ivoz\\Core\\Domain\\Service\\FileContainerInterface'
                );
            }
        }

        if (empty($interfaces)) {
            $interfaces[] = $this->addUseStatementIfNecessary(
                'Ivoz\\Core\\Domain\\Model\\EntityInterface'
            );
        }

        $this->updateClass(
            self::INTERFACE_EXTENDS_PLACEHOLDER,
            [new InterfaceExtends(implode(', ', array_unique($interfaces)))],
            '',
            ''
        );

        $this->updateClass(
            self::INTERFACE_USE_STATEMENT_PLACEHOLDER,
            $this->useStatements,
            '',
            "\n"
        );

        $this->updateClass(
            self::INTERFACE_BODY_PLACEHOLDER,
            array_merge($this->properties, $this->methods),
            str_repeat(' ', 4)
        );

        // Remove black lines
        $this->sourceCode = preg_replace('/^[\t|\s]+\n+/m', "\n", $this->sourceCode);
        $this->sourceCode = preg_replace('/\n{2,}(\s*\})/m', "\n$1", $this->sourceCode);

        $this->useStatements = [];
        $this->methods = [];
        $this->interfaces = [];
    }

    public function getSourceCode(): string
    {
        return $this->sourceCode;
    }

    /**
     * @return string The alias to use when referencing this class
     */
    public function addUseStatementIfNecessary(string $class, ClassMetadata $classMetadata = null): string
    {
        $class = str_replace('?', '', $class);

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

    public function addConstant(string $name, string $value)
    {
        $this->properties[] = new Constant(
            $name,
            $value
        );
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
