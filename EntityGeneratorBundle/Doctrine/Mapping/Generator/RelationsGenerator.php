<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\Generator;

use IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\MappedEntityRelation;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\RequestedProperty;

class RelationsGenerator
{
    public function __construct()
    {
    }

    /**
     * ss@param RequestedProperty[] $data
     */
    public function execute(\SimpleXMLElement $xml, array $data): \SimpleXMLElement
    {
        $mappedSuperClass = $xml->{'mapped-superclass'};
        foreach ($data as $field) {
            $relation = $field->getRelation();

            switch ($relation->getType()) {
                case MappedEntityRelation::MANY_TO_ONE:
                    /** @var \SimpleXMLElement $manyToOne */
                    $manyToOne = $mappedSuperClass->addChild('many-to-one');

                    $inversedEntityName = ucfirst($relation->getOwningProperty());
                    $inverseRelation = sprintf(
                        '%s\\%sInterface',
                        $relation->getInverseClass(),
                        $inversedEntityName
                    );
                    $manyToOne->addAttribute('field', $field->getFieldName());
                    $manyToOne->addAttribute('target-entity', $inverseRelation);
                    $manyToOne->addAttribute('fetch', $field->getFetch());

                    $joinColumns = $manyToOne->addChild('join-columns');
                    $joinColumn = $joinColumns->addChild('join-column');
                    $entityId = sprintf('%sId', $relation->getOwningProperty());
                    $joinColumn->addAttribute('name', $entityId);
                    $joinColumn->addAttribute('referenced-column-name', 'id');
                    $joinColumn->addAttribute('on-delete', $field->getOnDelete());

                    if ($relation->isNullable()) {
                        $joinColumn->addAttribute('nullable', (string)$relation->isNullable());
                    }

                    if ($relation->getMapInverseRelation()) {
                        $this->generateInversedRelation(
                            $relation,
                            $field->getFetch()
                        );
                    }
                    break;
                case MappedEntityRelation::ONE_TO_MANY:
                    /** @var \SimpleXMLElement $oneToMany */
                    $oneToMany = $mappedSuperClass->addChild('one-to-many');

                    $inversedEntityName = ucfirst($relation->getOwningProperty());
                    $inverseRelation = sprintf(
                        '%s\\%sInterface',
                        $relation->getInverseClass(),
                        $inversedEntityName
                    );
                    $oneToMany->addAttribute('field', $field->getFieldName());
                    $oneToMany->addAttribute('target-entity', $inverseRelation);
                    $oneToMany->addAttribute('fetch', $field->getFetch());

                    $joinColumns = $oneToMany->addChild('join-columns');
                    $joinColumn = $joinColumns->addChild('join-column');
                    $entityId = sprintf('%sId', $relation->getOwningProperty());
                    $joinColumn->addAttribute('name', $entityId);
                    $joinColumn->addAttribute('referenced-column-name', 'id');
                    $joinColumn->addAttribute('on-delete', $field->getOnDelete());

                    if ($relation->isNullable()) {
                        $joinColumn->addAttribute('nullable', (string)$relation->isNullable());
                    }

                    if ($relation->getMapInverseRelation()) {
                        $this->generateInversedRelation(
                            $relation,
                            $field->getFetch()
                        );
                    }
                    break;
                case MappedEntityRelation::ONE_TO_ONE:
                    /** @var \SimpleXMLElement $oneToOne */
                    $oneToOne = $mappedSuperClass->addChild('one-to-one');

                    $inversedEntityName = ucfirst($relation->getOwningProperty());
                    $inverseRelation = sprintf(
                        '%s\\%sInterface',
                        $relation->getInverseClass(),
                        $inversedEntityName
                    );

                    $oneToOne->addAttribute('field', $field->getFieldName());
                    $oneToOne->addAttribute('target-entity', $inverseRelation);
                    $oneToOne->addAttribute('inversed-by', strtolower($relation->getOwningClass()));
                    $oneToOne->addAttribute('fetch', $field->getFetch());

                    $joinColumns = $oneToOne->addChild('join-columns');
                    $joinColumn = $joinColumns->addChild('join-column');
                    $entityId = sprintf('%sId', $relation->getOwningProperty());
                    $joinColumn->addAttribute('name', $entityId);
                    $joinColumn->addAttribute('referenced-column-name', 'id');
                    $joinColumn->addAttribute('on-delete', $field->getOnDelete());

                    if ($relation->isNullable()) {
                        $joinColumn->addAttribute('nullable', (string)$relation->isNullable());
                    }

                    if ($relation->getMapInverseRelation()) {
                        $this->generateInversedRelation(
                            $relation,
                            $field->getFetch(),
                        );
                    }
                    break;
            }
        }

        return $xml;
    }

    private function generateInversedRelation(
        MappedEntityRelation $relation,
        string               $fetch
    )
    {
        $mappingPaths = $this->generator->getMappingsPath();
        $owningProperty = $relation->getOwningProperty();
        $owningClass = $relation->getOwningClass();
        $inverseProperty = $relation->getInverseProperty();
        $owningProperty = $relation->getOwningProperty();

        $output = sprintf(
            '%s/%s.%s.orm.xml',
            $mappingPaths[$relation->getInversedProjectName()],
            ucfirst($owningProperty),
            ucfirst($owningProperty)
        );

        $xml = $this->generator
            ->readXmlFile($output);
        $aliasMappingPaths = $this->generator->getAliasMappingPaths();
        $targetEntity = sprintf(
            '%s\\%s\\%sInterface',
            $aliasMappingPaths[$this->mappingName],
            $owningClass,
            $owningClass
        );

        $entity = $xml->{'entity'};
        $oneToMany = $entity->addChild('one-to-many');
        $oneToMany->addAttribute('field', $inverseProperty);
        $oneToMany->addAttribute('target-entity', $targetEntity);
        $oneToMany->addAttribute('mapped-by', $owningProperty);
        $oneToMany->addAttribute('fetch', $fetch);

        $this->generator->generateXml(
            $xml,
            $output
        );
    }

}