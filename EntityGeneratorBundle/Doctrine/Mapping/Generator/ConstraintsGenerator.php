<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\Generator;

use IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\RequestedProperty;

class ConstraintsGenerator
{
    public function __construct()
    {
    }

    /**
     * @param RequestedProperty[] $data
     */
    public function execute(\SimpleXMLElement $xml, array $data): \SimpleXMLElement
    {
        /** @var \SimpleXMLElement $mappedSuperClass */
        $mappedSuperClass = $xml->{'mapped-superclass'};
        /** @var \SimpleXMLElement $uniqueConstraints */
        $uniqueConstraints = $mappedSuperClass->{'unique-constraints'};
        /** @var \SimpleXMLElement $uniqueConstraint */
        $uniqueConstraint = $uniqueConstraints->{'unique-constraint'};

        if (!$uniqueConstraint) {
            $uniqueConstraints = $mappedSuperClass->addChild('unique-constraints');
        }

        foreach ($data as $constraint) {
            $uniqueConstraint = $uniqueConstraints->addChild('unique-constraint');
            $uniqueConstraint->addAttribute('name', $constraint->getFieldName());
            $uniqueConstraint->addAttribute('columns', $constraint->getUniqueConstraintColumns());
        }

        return $xml;
    }

}