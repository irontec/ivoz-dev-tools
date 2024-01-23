<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\Updater;

use IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\RequestedProperty;

class UpdateIndexes
{

    public function __construct()
    {
    }

    /**
     * @param \SimpleXMLElement[] $elements
     * @return \SimpleXMLElement[]
     */
    public function execute(array $elements, RequestedProperty $field): array
    {
        $elements[0]->attributes()["columns"] = $field->getUniqueConstraintColumns();
        return  $elements;
    }
}