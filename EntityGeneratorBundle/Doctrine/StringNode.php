<?php


namespace IvozDevTools\EntityGeneratorBundle\Doctrine;

class StringNode implements CodeGeneratorUnitInterface
{
    private $code;

    public function __construct(string $code)
    {
        $this->code = $code;
    }

    public function toString(string $nlLeftPad = ''): string
    {
        return $this->code;
    }
}