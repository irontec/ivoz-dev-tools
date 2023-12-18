<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\Updater;

use IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\RequestedProperty;

class UpdateFields
{
    public function __construct()
    {
    }

    /**
     * @param \SimpleXMLElement[] $element
     * @return \SimpleXMLElement[]
     */
    public function execute(array $elements, RequestedProperty $field): array
    {
        $elements[0]->attributes()["type"] = $field->getType();
        $elements[0]->attributes()["nullable"] = (string)$field->getNullable();

        if ($field->getType() === 'string') {
            $elements[0]->attributes()["length"] = (string)$field->getLength();
        }

        if ($field->getType() === 'decimal' || $field->getType() === 'float') {
            $elements[0]->attributes()["precision"] = (string)$field->getPrecision();
            $elements[0]->attributes()["scale"] = (string)$field->getScale();
        }

        /** @var \SimpleXMLElement $options */
        $options = $elements[0]->options;
        $usedOptions = [
            "fixed" => false,
            "comment" => false,
            "default" => false,
            "unsigned" => false
        ];

        foreach ($options->option as $option) {
            switch ($option[0]->attributes()["name"]) {
                case "fixed":
                    $usedOptions["fixed"] = true;
                    break;
                case "comment":
                    $option[1] = $field->getComment();
                    $usedOptions["comment"] = true;
                    break;
                case "default":
                    $option[1] = $field->getDefault();
                    $usedOptions["default"] = true;
                    break;
                case "unsigned":
                    $usedOptions["unsigned"] = true;
                    $option[1] = (string)$field->isUnsigned();
                    break;
            }
        }

        if (!$usedOptions["fixed"]) {
            $option = $options->addchild('option');
            $option->addAttribute('name', 'fixed');
        }

        if ($field->getComment()) {
            if (!$usedOptions["comment"]) {
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


        if ($field->isUnsigned() && !$usedOptions["unsigned"]) {
            $options = $elements[0]->addChild('options');
            $option = $options->addchild('option', (string)$field->isUnsigned());
            $option->addAttribute('name', 'unsigned');
        }

        if ($field->getDefault()) {
            if (!$usedOptions["default"]) {
                $option = $options->addchild('option', $field->getDefault());
                $option->addAttribute('name', 'default');
            }
        }
        return $elements;
    }
}