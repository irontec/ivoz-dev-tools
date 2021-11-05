<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Dto;

use IvozDevTools\EntityGeneratorBundle\Doctrine\CodeGeneratorUnitInterface;
use Symfony\Bundle\MakerBundle\Str;

class DtoGetter implements CodeGeneratorUnitInterface
{
    protected $propertyName;
    protected $returnType;
    protected $isReturnTypeNullable;
    protected $comments = [];

    public function __construct(
        string $propertyName,
        $returnType,
        bool $isReturnTypeNullable,
        array $commentLines = []
    ) {
        $this->propertyName = $propertyName;
        $this->returnType = $returnType;
        $this->isReturnTypeNullable = $isReturnTypeNullable;
        $this->comments = $commentLines;
    }

    public function toString(string $nlLeftPad = ''): string
    {
        $methodName = 'get' . Str::asCamelCase($this->propertyName);

        $returnHint = '';
        if ($this->returnType && strpos($this->returnType, '|')) {
            $returnHint = ': ' . $this->returnType . '|null';
        } elseif ($this->returnType) {
            $returnHint = ': ?' . $this->returnType;
        }

        $response = [];
        $response[] = sprintf(
            'public function %s()%s',
            $methodName,
            $returnHint
        );
        $response[] = '{';
        $response[] = '    return $this->' . $this->propertyName . ';';
        $response[] = '}';

        return implode(
            "\n". $nlLeftPad,
            $response
        );
    }
}
