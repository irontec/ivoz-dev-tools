<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\EntityInterface;

use IvozDevTools\EntityGeneratorBundle\Doctrine\CodeGeneratorUnitInterface;

class InterfaceExtends implements CodeGeneratorUnitInterface
{
    protected $interfaces;
    protected $value;

    public function __construct(
        string $interfaces
    ) {
        $this->interfaces = $interfaces;
    }

    public function toString(string $nlLeftPad = ''): string
    {
        if (empty($this->interfaces)) {
            return '';
        }

        return 'extends ' . $this->interfaces;
    }
}