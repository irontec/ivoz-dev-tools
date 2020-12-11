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

    private $lexer;

    private $sourceCode;

    /** @var CodeGeneratorUnitInterface[]  */
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

            if ($parameterClass) {
                $type = $this->addUseStatementIfNecessary(
                    (string) $parameterClass->getName(),
                    $classMetadata
                );
                $str = $type . ' ';

                if ($methodParameter->isOptional()) {
                    $str = '?' . $type . ' ';
                }

            } elseif ($methodParameter->isArray()) {
                $str = 'array ';
            } elseif ($methodParameter->hasType()) {
                $type = (string) $methodParameter->getType();
                $str = $type . ' ';
            }

            $str .= '$' . $methodParameter->getName();
            if ($methodParameter->isOptional()  && !is_null($methodParameter->getDefaultValue())) {
                $str .= " = '" . $methodParameter->getDefaultValue() . "'";
            } elseif ($methodParameter->isOptional()) {
                $str .= " = null";
            }

            $methodParameterArray[] = $str;
        }

        $static = $method->isStatic()
            ? 'static '
            : '';

        $returnNamedTyped = $method->getReturnType();
        $returnHint = '';
        $nullableReturnType = $returnNamedTyped && $returnNamedTyped->allowsNull();
        if ($returnNamedTyped instanceof \ReflectionType) {
            $returnHint = (string) $returnNamedTyped;
            if ($returnHint === 'self') {
                $returnHint = substr(
                    $classMetadata->name,
                    strrpos($classMetadata->name, '\\') + 1
                );
            } else {
                if (false !== strpos($returnHint, '\\')) {
                    $returnHint = $this->addUseStatementIfNecessary(
                        $returnNamedTyped->__toString(),
                        $classMetadata
                    );
                } elseif (class_exists($returnHint) || interface_exists($returnHint)) {
                    $returnHint = '\\' . $returnHint;
                }
            }
        }

        $this->methods[] = new Method(
            $static,
            $method->getName(),
            $methodParameterArray,
            $returnHint,
            $nullableReturnType,
            explode("\n", $docComment)
        );
    }

    public function updateSourceCode()
    {
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
        $this->sourceCode = preg_replace('/\n{2,}/', "\n\n", $this->sourceCode);

        $this->useStatements = [];
        $this->methods = [];
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
        $shortClassName = Str::getShortClassName($class);
        if ($classMetadata && $classMetadata->name == $class) {
            return $shortClassName;
        }

        if ($shortClassName === Str::getShortClassName($classMetadata->name)) {
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

    public function addEntityField(string $propertyName, array $columnOptions, array $comments = [], $classMetadata)
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

    public function addInterface(string $interfaceName)
    {
    }

    public function addAccessorMethod(string $propertyName, string $methodName, $returnType, bool $isReturnTypeNullable, array $commentLines = [], $typeCast = null)
    {
    }

    public function addGetter(string $propertyName, $returnType, bool $isReturnTypeNullable, array $commentLines = [])
    {
    }

    public function addSetter(string $propertyName, $type, bool $isNullable, array $commentLines = [], array $columnOptions = [], $classMetadata, string $visibility = 'protected')
    {
    }

    public function addProperty(string $name, string $columnName, array $comments = [], $defaultValue = null, bool $required = false, string $fkFqdn)
    {
    }
}
