<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Dto;

use IvozDevTools\EntityGeneratorBundle\Doctrine\Getter;
use Symfony\Bundle\MakerBundle\Str;

class FkGetter extends Getter
{
    public function toString(string $nlLeftPad = ''): string
    {
        $response = [];
        $response[] = '/**';
        $response[] = ' * @return mixed | null';
        $response[] = ' */';

        $methodName = 'get' . Str::asCamelCase($this->propertyName);

        $response[] = sprintf(
            'public function %sId()',
            $methodName
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