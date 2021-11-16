<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Entity;

use IvozDevTools\EntityGeneratorBundle\Doctrine\Getter;
use Symfony\Bundle\MakerBundle\Str;

class EmbeddedGetter extends Getter
{
    public function toString(string $nlLeftPad = ''): string
    {
        $returnType = $this->isReturnTypeNullable
            ? '?' . $this->returnType
            : $this->returnType;

        $methodName = 'get' . Str::asCamelCase($this->propertyName);

        $response = [];
        $response[] = sprintf(
            'public function %s(): %s',
            $methodName,
            $returnType
        );
        $response[] = '{';

        if ($this->returnType === '\\DateTime') {
            $response[] =
                '    return !is_null($this->'
                . $this->propertyName
                . ') ? clone $this->'
                . $this->propertyName
                . ' : null;';
        } else {
            $response[] = '    return $this->' . $this->propertyName . ';';
        }
        $response[] = '}';

        return implode(
            "\n". $nlLeftPad,
            $response
        );
    }
}
