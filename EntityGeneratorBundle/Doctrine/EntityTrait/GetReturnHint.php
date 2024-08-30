<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\EntityTrait;

class GetReturnHint
{
    private array $useStatements;

    public function __construct(
        array $useStatements
    )
    {
        $this->useStatements = $useStatements;
    }

    public function execute(string $returnHint): string
    {
        $similarInterfaces = false;

        foreach (array_keys($this->useStatements) as $fqdn) {
            $parts = explode('\\', $fqdn);
            $lastValue = end($parts);

            if ($lastValue === $returnHint) {
                $similarInterfaces = true;
                break;
            }
        }

        if ($similarInterfaces) {
            $returnHint = 'static';
        }

        return $returnHint;
    }
}