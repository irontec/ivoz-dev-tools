<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping;

use SimpleXMLElement;
use Symfony\Bundle\MakerBundle\Doctrine\EntityRelation;

class MappingManipulator
{
    public function __construct(
        private MappingGenerator $generator,
        private MappedPaths $paths,
        private string $mappingName
    ) {
    }

    /**
     * @param RequestedProperty[] $data
     */
    public function dumpXmlAttributes(array $data): void
    {
        $mappedSuperClassPath = $this
            ->paths
            ->getMappedSuperClassPath();

        $xml = $this->generator
            ->readXmlFile($mappedSuperClassPath);

        $constraints = [];
        $fields = [];
        $relations = [];
        foreach ($data as $value) {
            if ($value->getType() === 'index') {
                $constraints[] = $value;
                continue;
            }

            if ($value->getType() === 'relation') {
                $relations[] = $value;
                continue;
            }

            $fields[] = $value;
        }

        $xml = $this->generateFields($xml, $fields);
        $xml = $this->generateRelations($xml, $relations);
        $xml = $this->generateConstraints($xml, $constraints);

        $this->generator->generateXml(
            $xml,
            $this->paths->getMappedSuperClassPath()
        );
    }

    /**
     * @param RequestedProperty[] $data
     */
    private function generateConstraints(SimpleXMLElement $xml, array $data): SimpleXMLElement
    {
        /** @var SimpleXMLElement  $mappedSuperClass*/
        $mappedSuperClass = $xml->{'mapped-superclass'};
        /** @var SimpleXMLElement  $uniqueConstraints*/
        $uniqueConstraints = $mappedSuperClass->{'unique-constraints'};
        /** @var SimpleXMLElement  $uniqueConstraint*/
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

    /**
     * @param RequestedProperty[] $data
     */
    private function generateFields(SimpleXMLElement $xml, array $data): SimpleXMLElement
    {
        $mappedSuperClass = $xml->{'mapped-superclass'};
        $options = null;

        foreach ($data as $field) {

            /** @var SimpleXMLElement $item */
            $item = $mappedSuperClass->addChild('field');
            $item->addAttribute('name', $field->getFieldName());
            $item->addAttribute('type', $field->getType());
            $item->addAttribute('column', $field->getFieldName());

            if ($field->getType() === 'string') {
                $item->addAttribute('length', (string) $field->getLength());
                $options = $item->addChild('options');
                $option = $options->addchild('option');
                $option->addAttribute('name', 'fixed');

                if ($field->getComment()) {
                    $option = $options->addchild(
                        'option',
                        sprintf(
                            '[enum:%s]',
                            $field->getComment()
                        )
                    );
                    $option->addAttribute('name', 'comment');
                }
            }

            if ($field->getType() === 'integer') {
                if ($field->isUnsigned()) {
                    $options = $item->addChild('options');
                    $option = $options->addchild('option', (string) $field->isUnsigned());
                    $option->addAttribute('name', 'unsigned');
                    $option = $options->addchild('option');
                    $option->addAttribute('name', 'fixed');
                }
            }

            if ($field->getType() === 'decimal' || $field->getType() === 'float') {
                $item->addAttribute('precision', (string) $field->getPrecision());
                $item->addAttribute('scale', (string) $field->getScale());
            }

            $item->addAttribute('nullable', $field->getNullable());

            if ($field->getDefault()) {
                if (!$options) {
                    $options = $item->addChild('options');
                }
                $option = $options->addchild('option', $field->getDefault());
                $option->addAttribute('name', 'default');
            }
        }

        return $xml;
    }

    /** @param RequestedProperty[] $data */
    private function generateRelations(SimpleXMLElement $xml, array $data): SimpleXMLElement
    {
        $mappedSuperClass = $xml->{'mapped-superclass'};

        foreach ($data as $field) {

            $relation = $field->getRelation();

            switch ($relation->getType()) {
                case MappedEntityRelation::MANY_TO_ONE:
                    /** @var SimpleXMLElement $manyToOne */
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
                        $joinColumn->addAttribute('nullable', (string) $relation->isNullable());
                    }

                    if ($relation->getMapInverseRelation()) {
                        $this->generateInversedRelation(
                            $relation,
                            $field->getFetch()
                        );
                    }
                    break;
                case MappedEntityRelation::ONE_TO_MANY:
                    /** @var SimpleXMLElement $oneToMany */
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
                        $joinColumn->addAttribute('nullable', (string) $relation->isNullable());
                    }

                    if ($relation->getMapInverseRelation()) {
                        $this->generateInversedRelation(
                            $relation,
                            $field->getFetch()
                        );
                    }
                    break;
                case MappedEntityRelation::ONE_TO_ONE:
                    /** @var SimpleXMLElement $oneToOne */
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
                        $joinColumn->addAttribute('nullable', (string) $relation->isNullable());
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
        string $fetch
    ) {
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

    /**
     * @return RequestedProperty[]
     */
    public function getFields(): array
    {
        $mappedSuperClassPath = $this
            ->paths
            ->getMappedSuperClassPath();

        $xml = $this->generator
            ->readXmlFile($mappedSuperClassPath);

        /** @var SimpleXMLElement $mappedSuperClass*/
        $mappedSuperClass = $xml->{'mapped-superclass'};

        /** @var RequestedProperty[] $currentFields */
        $currentFields = [];

        foreach ($mappedSuperClass->field as $field) {
            $currentFields[] = new RequestedProperty(
                $field->attributes()['name'],
                $field->attributes()['type']
            );
        }

        foreach ($mappedSuperClass->{'unique-constraints'}->{'unique-constraint'} as $constraint) {
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
