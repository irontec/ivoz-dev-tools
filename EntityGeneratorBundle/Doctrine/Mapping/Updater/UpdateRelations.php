<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\Updater;

use IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\RequestedProperty;

class UpdateRelations
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
        $relation = $field->getRelation();
        $inversedEntityName = ucfirst($relation->getOwningProperty());
        $inverseRelation = sprintf(
            '%s\\%sInterface',
            $relation->getInverseClass(),
            $inversedEntityName
        );
        $elements[0]->attributes()["target-entity"] = $inverseRelation;
        $elements[0]->attributes()["fetch"] = $field->getFetch();

        $elements[0]
            ->{"join-columns"}
            ->{"join-column"}
            ->attributes()["on-delete"] = $field->getOnDelete();

        if ($relation->isNullable()) {
            $elements[0]
                ->{"join-columns"}
                ->{"join-column"}
                ->attributes()["nullable"] = (string)$relation->isNullable();
        }

        return $elements;
    }

}