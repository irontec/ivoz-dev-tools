<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\EntityTrait;

use Doctrine\Common\Collections\ArrayCollection;
use IvozDevTools\EntityGeneratorBundle\Doctrine\CodeGeneratorUnitInterface;
use Symfony\Bundle\MakerBundle\Str;

class Replacer implements CodeGeneratorUnitInterface
{
    protected $propertyName;
    protected $type;
    protected $isNullable;
    protected $comments = [];
    protected $columnOptions = [];
    protected $classMetadata;
    protected $visibility;

    public function __construct(
        string $propertyName,
        string $type,
        bool $isNullable,
        array $commentLines = [],
        array $columnOptions = [],
        $classMetadata,
        string $visibility = 'protected'
    ) {
        $this->propertyName = $propertyName;
        $this->type = $type;
        $this->isNullable = $isNullable;
        $this->comments = $commentLines;
        $this->columnOptions = $columnOptions;
        $this->classMetadata = $classMetadata;
        $this->visibility = $visibility;
    }

    public function toString(string $nlLeftPad = ''): string
    {
        $camelCaseProperty = Str::asCamelCase($this->propertyName);
        $singularProperty = Str::pluralCamelCaseToSingular($camelCaseProperty);

        $hint = substr(
            $this->type,
            strrpos($this->type, '\\')
        );

        $response[] = '/**';
        $response[] = ' * Replace ' . $this->propertyName;
        $response[] = ' *';
        $response[] = ' * @param ArrayCollection $' . $this->propertyName . ' of ' . $hint;
        $response[] = ' *';
        $response[] = ' * @return static';
        $response[] = ' */';

        $methodName = 'replace' . $camelCaseProperty;
        $fqdnSegments = explode('\\', $this->classMetadata->name);
        $returnHint = $fqdnSegments[count($fqdnSegments) -2] . 'Interface';

        $response[] = sprintf(
            '%s function %s(%s %s): %s',
            $this->visibility,
            $methodName,
            'ArrayCollection',
            '$' . $this->propertyName,
            $returnHint
        );

        $association = $this->classMetadata->associationMappings[$this->propertyName];
        $body = $this->getMethodBody(
            'set' . Str::asCamelCase($association['mappedBy']),
            '$' . $this->propertyName,
            $this->propertyName,
            'add' . ucfirst($singularProperty)
        );

        $response[] = '{';
        $response[] = $body;
        $response[] = '}';

        return implode(
            "\n". $nlLeftPad,
            $response
        );
    }

    private function getMethodBody(
        string $mappedBy,
        string $paramName,
        string $propertyName,
        string $adder
    ): string
    {
        $template = <<<'TPL'
    $updatedEntities = [];
        $fallBackId = -1;
        foreach ([PARAM_NAME] as $entity) {
            $index = $entity->getId() ? $entity->getId() : $fallBackId--;
            $updatedEntities[$index] = $entity;
            $entity->[MAPPED_BY]($this);
        }
        $updatedEntityKeys = array_keys($updatedEntities);

        foreach ($this->[PROPERTY_NAME] as $key => $entity) {
            $identity = $entity->getId();
            if (in_array($identity, $updatedEntityKeys)) {
                $this->[PROPERTY_NAME]->set($key, $updatedEntities[$identity]);
            } else {
                $this->[PROPERTY_NAME]->remove($key);
            }
            unset($updatedEntities[$identity]);
        }

        foreach ($updatedEntities as $entity) {
            $this->[ADDER]($entity);
        }

        return $this;
TPL;

        return str_replace(
            ['[MAPPED_BY]', '[PARAM_NAME]', '[PROPERTY_NAME]', '[ADDER]'],
            [$mappedBy, $paramName, $propertyName, $adder],
            $template
        );
    }
}