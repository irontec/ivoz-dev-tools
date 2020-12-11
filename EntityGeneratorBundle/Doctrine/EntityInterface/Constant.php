<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\EntityInterface;

use IvozDevTools\EntityGeneratorBundle\Doctrine\CodeGeneratorUnitInterface;

class Constant implements CodeGeneratorUnitInterface
{
    protected $name;
    protected $value;

    public function __construct(
        string $name,
        string $value
    ) {
        $this->name = $name;
        $this->value = $value;
    }

    public function toString(string $nlLeftPad = ''): string
    {
        return 'const ' . $this->name . ' = \''  . $this->value . '\';';
    }
}