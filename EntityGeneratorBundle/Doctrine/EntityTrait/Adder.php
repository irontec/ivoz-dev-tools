<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\EntityTrait;

use IvozDevTools\EntityGeneratorBundle\Doctrine\CodeGeneratorUnitInterface;
use Symfony\Bundle\MakerBundle\Str;

class Adder implements CodeGeneratorUnitInterface
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

        $methodName = 'add' . ucfirst($singularProperty);

        $fqdnSegments = explode('\\', $this->classMetadata->name);
        $returnHint = $fqdnSegments[count($fqdnSegments) -2] . 'Interface';

        $response = [];
        $response[] = sprintf(
            '%s function %s(%s %s): %s',
            $this->visibility,
            $methodName,
            $this->type,
            '$' . $singularProperty,
            $returnHint
        );

        $response[] = '{';
        $response[] = '    $this->' . $this->propertyName . '->add($' . $singularProperty . ');';
        $response[] = '';
        $response[] = '    return $this;';
        $response[] = '}';

        return implode(
            "\n". $nlLeftPad,
            $response
        );
    }
}
