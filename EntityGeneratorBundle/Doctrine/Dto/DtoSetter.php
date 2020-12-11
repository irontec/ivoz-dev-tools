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
        $response[] = '/**';
        foreach ($this->comments as $comment) {
            $response[] = !empty(trim($comment))
                ?' * ' . $comment
                : ' *';
        }
        $response[] = ' */';

        $typeHint = $this->isNullable
            ? '?' . $this->type
            : $this->type;

        $nullableStr = $this->isNullable
            ? ' = null'
            : '';

        $methodName = 'set' . Str::asCamelCase($this->propertyName);

        $response[] = sprintf(
            '%s function %s(%s %s%s): self',
            $this->visibility,
            $methodName,
            $typeHint,
            '$' . $this->propertyName,
            $nullableStr
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