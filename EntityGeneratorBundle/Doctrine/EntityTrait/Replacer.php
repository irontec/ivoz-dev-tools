<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\EntityTrait;

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
        $classMetadata,
        array $commentLines = [],
        array $columnOptions = [],
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

        $methodName = 'replace' . $camelCaseProperty;
        $fqdnSegments = explode('\\', $this->classMetadata->name);
        $returnHint = $fqdnSegments[count($fqdnSegments) -2] . 'Interface';

        $response = [];
        $response[] = '/**';
        $response[] = ' * @param Collection<array-key, '. $hint . '> $' . $this->propertyName;
        $response[] = ' */';
        $response[] = sprintf(
            '%s function %s(%s %s): %s',
            $this->visibility,
            $methodName,
            'Collection',
            '$' . $this->propertyName,
            $returnHint
        );

        $association = $this->classMetadata->associationMappings[$this->propertyName];
        $body = $this->getMethodBody(
            'set' . Str::asCamelCase($association['mappedBy']),
            '$' . $this->propertyName,
            $this->propertyName,
            'add' . ucfirst($singularProperty),
            $hint
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
        string $adder,
        string $hint
    ): string
    {
        $template = <<<'TPL'
    $updatedEntities = [];
        $fallBackId = -1;
        foreach ([PARAM_NAME] as $entity) {
            /** @var string|int $index */
            $index = $entity->getId() ? $entity->getId() : $fallBackId--;
            $updatedEntities[$index] = $entity;
            $entity->[MAPPED_BY]($this);
        }

        foreach ($this->[PROPERTY_NAME] as $key => $entity) {
            $identity = $entity->getId();
            if (!$identity) {
                $this->[PROPERTY_NAME]->remove($key);
                continue;
            }

            if (array_key_exists($identity, $updatedEntities)) {
                $this->[PROPERTY_NAME]->set($key, $updatedEntities[$identity]);
                unset($updatedEntities[$identity]);
            } else {
                $this->[PROPERTY_NAME]->remove($key);
            }
        }

        foreach ($updatedEntities as $entity) {
            $this->[ADDER]($entity);
        }

        return $this;
TPL;

        return str_replace(
            ['[MAPPED_BY]', '[PARAM_NAME]', '[PROPERTY_NAME]', '[ADDER]', '[HINT]'],
            [$mappedBy, $paramName, $propertyName, $adder, $hint],
            $template
        );
    }
}
