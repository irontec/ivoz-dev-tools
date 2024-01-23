<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\Manipulator;

use IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\Generator\ConstraintsGenerator;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\Generator\FieldsGenerator;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\Generator\RelationsGenerator;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\MappedEntityRelation;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\MappedPaths;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\MappingGenerator;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\RequestedProperty;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\Updater\UpdateFields;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\Updater\UpdateIndexes;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\Updater\UpdateRelations;

class DumpXmlAttributes
{
    private UpdateFields $updateFields;
    private UpdateIndexes $updateIndexes;
    private UpdateRelations $updateRelations;
    private ConstraintsGenerator$constraintsGenerator;
    private RelationsGenerator $relationsGenerator;
    private FieldsGenerator $fieldsGenerator;

    public function __construct(
        private MappingGenerator $generator,
        private MappedPaths $paths,
        string $mappingName
    ) {
        $this->updateFields = new UpdateFields();
        $this->updateIndexes = new UpdateIndexes();
        $this->updateRelations = new UpdateRelations();
        $this->constraintsGenerator = new ConstraintsGenerator();
        $this->relationsGenerator = new RelationsGenerator($generator, $mappingName);
        $this->fieldsGenerator = new FieldsGenerator();
    }

    /**
     * @param RequestedProperty[] $currentFields
     * @return void
     */
    public function execute(array $currentFields)
    {
        $mappedSuperClassPath = $this
            ->paths
            ->getMappedSuperClassPath();

        $xml = $this->generator
            ->readXmlFile($mappedSuperClassPath);

        // Register the XML namespace it's needed to perform search by attributes xml operations
        $xml->registerXPathNamespace('ns', 'http://doctrine-project.org/schemas/orm/doctrine-mapping');

        $constraints = [];
        $fields = [];
        $relations = [];
        foreach ($currentFields as $value) {
            if ($value->getType() === 'relation') {
                $relations[] = $value;
                $fieldName = $value->getFieldName();

                switch ($value->getRelation()->getType()) {
                    case MappedEntityRelation::MANY_TO_ONE:
                        $xpathExpression = "//ns:many-to-one[@field='$fieldName']";
                        $elements = $xml->xpath($xpathExpression);
                        break;
                    case MappedEntityRelation::ONE_TO_MANY:
                        $xpathExpression = "//ns:one-to-many[@field='$fieldName']";
                        $elements = $xml->xpath($xpathExpression);
                        break;
                    case MappedEntityRelation::ONE_TO_ONE:
                        $xpathExpression = "//ns:one-to-one[@field='$fieldName']";
                        $elements = $xml->xpath($xpathExpression);
                        break;
                }

                if (!empty($elements)) {
                    $elements = $this
                        ->updateRelations
                        ->execute($elements, $value);
                }
                continue;
            }

            if ($value->getType() === 'index') {
                $constraints[] = $value;

                $fieldName = $value->getFieldName();
                $xpathExpression = "//ns:unique-constraint[@name='$fieldName']";
                $elements = $xml->xpath($xpathExpression);

                if (!empty($elements)) {
                    $this
                        ->updateIndexes
                        ->execute($elements, $value);
                }
                continue;
            }


            $fieldName = $value->getFieldName();
            $xpathExpression = "//ns:field[@name='$fieldName']";
            $elements = $xml->xpath($xpathExpression);

            // TODO: Pass through to methods $elements[0]
            if (!empty($elements)) {
                $elements = $this
                    ->updateFields
                    ->execute($elements, $value);
                continue;
            }

            $fields[] = $value;
        }

        $xml = $this->fieldsGenerator->execute($xml, $fields);
        $xml = $this->relationsGenerator->execute($xml, $relations);
        $xml = $this->constraintsGenerator->execute($xml, $relations);

        $this->generator->generateXml(
            $xml,
            $this->paths->getMappedSuperClassPath()
        );
    }
}