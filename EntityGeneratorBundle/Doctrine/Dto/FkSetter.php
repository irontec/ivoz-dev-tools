<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Dto;

use IvozDevTools\EntityGeneratorBundle\Doctrine\Setter;
use Symfony\Bundle\MakerBundle\Str;

class FkSetter
{
    public function __construct(
        private string $propertyName,
        private ?string $targetClass,
        private string $typeHint,
        private string $visibility = 'protected'
    ) {
    }

    public function toString(string $nlLeftPad = ''): string
    {
        $methodName = 'set' . Str::asCamelCase($this->propertyName);

        $response = [];
        $response[] = sprintf(
            '%s function %sId(?%s $id): %s',
            $this->visibility,
            $methodName,
            $this->typeHint,
            'static'
        );

        $response[] = '{';
        $response[] = '    $value = !is_null($id)';
        $response[] = "        ? new " . $this->targetClass . '($id)';
        $response[] = '        : null;';
        $response[] = '';
        $response[] = '    return $this->' . $methodName . '($value);';
        $response[] = '}';

        return implode(
            "\n". $nlLeftPad,
            $response
        );
    }
}