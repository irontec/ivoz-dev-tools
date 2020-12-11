<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\EntityTrait;

use IvozDevTools\EntityGeneratorBundle\Doctrine\CodeGeneratorUnitInterface;
use Symfony\Bundle\MakerBundle\Str;

class Getter implements CodeGeneratorUnitInterface
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
        $response[] = '/**';
        foreach ($this->comments as $comment) {
            $response[] = !empty(trim($comment))
                ? ' * ' . $comment
                : ' *';
        }
        $response[] = ' */';

        $methodName = 'get' . Str::asCamelCase($this->propertyName);

        $response[] = sprintf(
            'public function %s(Criteria $criteria = null): array',
            $methodName
        );
        $response[] = '{';
        $response[] = '    if (!is_null($criteria)) {';
        $response[] = '        return $this->' . $this->propertyName . '->matching($criteria)->toArray();';
        $response[] = '    }';
        $response[] = '';
        $response[] = '    return $this->' . $this->propertyName . '->toArray();';
        $response[] = '}';

        return implode(
            "\n". $nlLeftPad,
            $response
        );
    }
}