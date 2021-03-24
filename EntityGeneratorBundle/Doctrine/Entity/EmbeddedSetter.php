<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Entity;

use IvozDevTools\EntityGeneratorBundle\Doctrine\Setter;
use Symfony\Bundle\MakerBundle\Str;

class EmbeddedSetter extends Setter
{
    public function toString(string $nlLeftPad = ''): string
    {
        $typeHint = $this->isNullable
            ? '?' . $this->type
            : $this->type;

        $nullableStr = $this->isNullable
            ? ' = null'
            : '';

        $methodName = 'set' . Str::asCamelCase($this->propertyName);

        $response = [];
        $response[] = sprintf(
            '%s function %s(%s %s%s): %s',
            $this->visibility,
            $methodName,
            $typeHint,
            '$' . $this->propertyName,
            $nullableStr,
            'static'
        );

        $isEqual = sprintf(
            '$isEqual = $this->%s && $this->%s->equals($%s);',
            $this->propertyName,
            $this->propertyName,
            $this->propertyName
        );

        $response[] = '{';
        $response[] = '    ' . $isEqual;
        $response[] = '    if ($isEqual) {';
        $response[] = '        return $this;';
        $response[] = '    }';
        $response[] = '';
        $response[] = '    $this->' . $this->propertyName . ' = $' . $this->propertyName . ';';
        $response[] = '    return $this;';
        $response[] = '}';

        return implode(
            "\n". $nlLeftPad,
            $response
        );
    }
}