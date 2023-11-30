<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Dto;

use IvozDevTools\EntityGeneratorBundle\Doctrine\CodeGeneratorUnitInterface;
use Symfony\Bundle\MakerBundle\Str;

class DtoSetter implements CodeGeneratorUnitInterface
{
    public function __construct(
        private string $propertyName,
        private ?string $type,
        private bool $isNullable,
        private array $comments = [],
        private string $visibility = 'protected'
    ) {
    }

    public function toString(string $nlLeftPad = ''): string
    {
        $response = [];
        $showComments = is_null($this->type) || $this->type === 'array';
        if (count($this->comments) && $showComments) {
            $response = ['/**'];
            foreach ($this->comments as $comment) {
                $response[] = ' * ' . $comment;
            }
            $response[] = ' */';
        }

        if ($this->isNullable && $this->type) {
            $typeHint = strpos($this->type, '|') !== false
                ? 'null|' . $this->type
                : '?' . $this->type;
        } else {
            $typeHint = $this->type;
        }

        if($typeHint) {
            $typeHint .= ' ';
        }

        $methodName = 'set' . Str::asCamelCase($this->propertyName);
        $response[] = sprintf(
            '%s function %s(%s%s): %s',
            $this->visibility,
            $methodName,
            $typeHint,
            '$' . $this->propertyName,
            'static'
        );

        $response[] = '{';
        $response[] = '    $this->' . $this->propertyName . ' = $' . $this->propertyName . ';';
        $response[] = '';
        $response[] = '    return $this;';
        $response[] = '}';

        return implode(
            "\n". $nlLeftPad,
            $response
        );
    }
}
