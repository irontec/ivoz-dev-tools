<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\Generator;

use IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\RequestedProperty;

class FieldsGenerator
{

    public function __construct()
    {
    }

    /**
     * @param RequestedProperty[] $data
     */
    public function execute(\SimpleXMLElement $xml, array $data): \SimpleXMLElement
    {
        $mappedSuperClass = $xml->{'mapped-superclass'};
        $options = null;
        foreach ($data as $field) {

            /** @var \SimpleXMLElement $item */
            $item = $mappedSuperClass->addChild('field');
            $item->addAttribute('name', $field->getFieldName());
            $item->addAttribute('type', $field->getType());
            $item->addAttribute('column', $field->getFieldName());

            if ($field->getType() === 'string') {
                $item->addAttribute('length', (string)$field->getLength());
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
                    $option = $options->addchild('option', (string)$field->isUnsigned());
                    $option->addAttribute('name', 'unsigned');
                    $option = $options->addchild('option');
                    $option->addAttribute('name', 'fixed');
                }
            }

            if ($field->getType() === 'decimal' || $field->getType() === 'float') {
                $item->addAttribute('precision', (string)$field->getPrecision());
                $item->addAttribute('scale', (string)$field->getScale());
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
}