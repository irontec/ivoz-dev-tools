<?php


namespace IvozDevTools\EntityGeneratorBundle\Doctrine;

class UseStatement implements CodeGeneratorUnitInterface
{
    private $fqdn;

    public function __construct(string $fqdn)
    {
        $this->fqdn = $fqdn;
    }

    public function toString(string $nlLeftPad = ''): string
    {
        return
            'use '
            . $this->fqdn
            . ';';
    }
}