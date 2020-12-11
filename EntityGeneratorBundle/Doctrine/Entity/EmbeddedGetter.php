<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Entity;

use IvozDevTools\EntityGeneratorBundle\Doctrine\Getter;
use Symfony\Bundle\MakerBundle\Str;

class EmbeddedGetter extends Getter
{
    public function toString(string $nlLeftPad = ''): string
    {
        $response[] = '/**';
        $response[] = ' * Get ' . $this->propertyName;
        $response[] = ' *';
        $response[] = ' * @return ' . $this->returnType;
        $response[] = ' */';

        $returnType = $this->isReturnTypeNullable
            ? '?' . $this->returnType
            : $this->returnType;

        $methodName = 'get' . Str::asCamelCase($this->propertyName);

        $response[] = sprintf(
            'public function %s(): %s',
            $methodName,
            $returnType
        );
        $response[] = '{';

        if ($this->returnType === '\\DateTimeInterface') {
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