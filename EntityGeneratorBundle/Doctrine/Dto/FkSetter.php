<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Dto;

use IvozDevTools\EntityGeneratorBundle\Doctrine\Setter;
use Symfony\Bundle\MakerBundle\Str;

class FkSetter extends Setter
{
    public function toString(string $nlLeftPad = ''): string
    {
        $methodName = 'set' . Str::asCamelCase($this->propertyName);

        $response = [];
        $response[] = sprintf(
            '%s function %sId($id): %s',
            $this->visibility,
            $methodName,
            'static'
        );

        $response[] = '{';
        $response[] = '    $value = !is_null($id)';
        $response[] = "        ? new " . $this->type . '($id)';
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