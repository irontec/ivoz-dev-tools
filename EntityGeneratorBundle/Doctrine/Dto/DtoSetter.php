<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Dto;

use IvozDevTools\EntityGeneratorBundle\Doctrine\CodeGeneratorUnitInterface;
use Symfony\Bundle\MakerBundle\Str;

class DtoSetter implements CodeGeneratorUnitInterface
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
        ?string $type,
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
        if ($this->isNullable && $this->type) {
            $typeHint = strpos($this->type, '|') !== false
                ? 'null|' . $this->type
                : '?' . $this->type;
        } else {
            $typeHint = $this->type;
        }

        if($typeHint) {
            $typeHint .= ' ';
        }

        $methodName = 'set' . Str::asCamelCase($this->propertyName);

        $response = [];
        $response[] = sprintf(
            '%s function %s(%s%s%s): %s',
            $this->visibility,
            $methodName,
            $typeHint,
            '$' . $this->propertyName,
            $nullableStr,
            'static'
        );

        $response[] = '{';
        $response[] = '    $this->' . $this->propertyName . ' = $' . $this->propertyName . ';';
        $response[] = '';
        $response[] = '    return $this;';
        $response[] = '}';

        return implode(
            "\n". $nlLeftPad,
            $response
        );
    }
}