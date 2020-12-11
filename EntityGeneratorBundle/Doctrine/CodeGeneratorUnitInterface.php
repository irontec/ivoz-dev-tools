<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine;

interface CodeGeneratorUnitInterface
{
    public function toString(string $nlLeftPad = ''): string;
}