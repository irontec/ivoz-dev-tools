<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\Manipulator;

use IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\MappedPaths;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\MappingGenerator;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\RequestedProperty;

class GetFields
{

    public function __construct(
        private MappedPaths      $paths,
        private MappingGenerator $generator,
    )
    {
    }

    /**
     * @return RequestedProperty[]
     */
    public function execute(): array
    {

        $mappedSuperClassPath = $this
            ->paths
            ->getMappedSuperClassPath();

        $xml = $this->generator
            ->readXmlFile($mappedSuperClassPath);

        /** @var \SimpleXMLElement $mappedSuperClass */
        $mappedSuperClass = $xml->{'mapped-superclass'};

        /** @var RequestedProperty[] $currentFields */
        $currentFields = [];

        foreach ($mappedSuperClass->field as $field) {
            $currentFields[] = new RequestedProperty(
                $field->attributes()['name'],
                $field->attributes()['type']
            );
        }

        $uniqueConstraints = $mappedSuperClass
            ->{'unique-constraints'}
            ?->{'unique-constraint'};

        foreach (($uniqueConstraints ?? []) as $constraint) {
            $currentFields[] = new RequestedProperty(
                $constraint->attributes()['name'],
                'unique_constraint'
            );
        }

        foreach ($mappedSuperClass->{'many-to-one'} as $relation) {
            $currentFields[] = new RequestedProperty(
                $relation->attributes()['field'],
                'relation'
            );
        }

        foreach ($mappedSuperClass->{'one-to-many'} as $relation) {
            $currentFields[] = new RequestedProperty(
                $relation->attributes()['field'],
                'relation'
            );
        }

        foreach ($mappedSuperClass->{'one-to-one'} as $relation) {
            $currentFields[] = new RequestedProperty(
                $relation->attributes()['field'],
                'relation'
            );
        }

        return $currentFields;
    }
}