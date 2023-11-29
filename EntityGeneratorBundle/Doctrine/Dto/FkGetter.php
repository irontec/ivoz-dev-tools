<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Dto;

use IvozDevTools\EntityGeneratorBundle\Doctrine\Getter;
use Symfony\Bundle\MakerBundle\Str;

class FkGetter extends Getter
{
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
            'public function %sId()%s',
            $methodName,
            $returnHint
        );
        $response[] = '{';
        $response[] = '    if ($dto = $this->' . $methodName . '()) {';
        $response[] = '        return $dto->getId();';
        $response[] = '    }';
        $response[] = '';
        $response[] = '    return null;';
        $response[] = '}';

        return implode(
            "\n". $nlLeftPad,
            $response
        );
    }
}