<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Dto;

use IvozDevTools\EntityGeneratorBundle\Doctrine\Setter;
use Symfony\Bundle\MakerBundle\Str;

class FkSetter extends Setter
{
    public function toString(string $nlLeftPad = ''): string
    {
        $response = [];
        $response[] = '/**';
        $response[] = ' * @return static';
        $response[] = ' */';

        $methodName = 'set' . Str::asCamelCase($this->propertyName);

        $response[] = sprintf(
            '%s function %sId($id): self',
            $this->visibility,
            $methodName
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