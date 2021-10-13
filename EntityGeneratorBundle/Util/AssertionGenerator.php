<?php

namespace IvozDevTools\EntityGeneratorBundle\Util;

/**
 * Description of EntityGenerator
 *
 * @author Mikel Madariaga <mikel@irontec.com>
 */
class AssertionGenerator
{
    public static function get(array $columnOptions, $classMetadata, string $nlLeftPad = ''): string
    {
        if (empty($columnOptions)) {
            return '';
        }

        $currentField = (object) $columnOptions;
        $isNullable = isset($currentField->nullable) && $currentField->nullable;

        $assertions = [];

        if (in_array($currentField->type, ['boolean'])) {
            $assertions = array_merge(
                $assertions,
                [AssertionGenerator::boolean($currentField->fieldName)]
            );
        }

        if (in_array($currentField->type, ['datetime'])) {

            $call = '';
            if (isset($columnOptions['nullable']) && $columnOptions['nullable']) {
                $call =
                    'Assertion::notNull('
                    . "\n$nlLeftPad"
                    . '$var,'
                    . "\n$nlLeftPad"
                    . '\'$fldName value "%s" is null, but non null value was expected.\''
                    . "\n"
                    . ');';
            }

            $call .= "\n"
                . '$var = DateTimeHelper::createOrFix('
                . "\n$nlLeftPad"
                .'$var,'
                . "\n$nlLeftPad"
                . '$default'
                . "\n"
                . ');'
                . "\n"
                . "\n"
                . 'if ($this->$fldName == $var) {'
                . "\n"
                . '    return $this;'
                . "\n"
                . "}"
            ;

            $defaultValue = (isset($currentField->options) && \array_key_exists('default', $currentField->options))
                ? var_export($currentField->options['default'], true)
                : "null";

            if ($defaultValue === "NULL") {
                $defaultValue = "null";
            }

            $assertions[] = str_replace(
                ['$var', '$fldName', '$default'],
                ['$' . $currentField->fieldName, $currentField->fieldName, $defaultValue],
                $call
            );
        }

        if (in_array($currentField->type, ['smallint', 'integer', 'bigint'])) {
            $integerAssertions = self::getIntegerAssertions($currentField);

            $assertions = array_merge(
                $assertions,
                $integerAssertions
            );
        }

        if (in_array($currentField->type, ['decimal', 'float'])) {
            $floatAssertions = self::getFloatAssertions($currentField);

            $assertions = array_merge(
                $assertions,
                $floatAssertions
            );
        }

        if (in_array($currentField->type, ['string', 'text'])) {
            $stringAssertions = self::getStringAssertions($currentField);
            $assertions = array_merge(
                $assertions,
                $stringAssertions
            );
        }

        $comment = $currentField->options['comment'] ?? '';
        if (preg_match('/\[enum:(?P<fieldValues>.+)\]/', $comment, $matches)) {
            $acceptedValues = explode('|', $matches['fieldValues']);
            $entityFqdn = substr(
                $classMetadata->name,
                0,
                strlen('Abstract') * -1
            );

            $entityFqdnSegments = explode('\\', $entityFqdn);
            $interface = end($entityFqdnSegments) . 'Interface';

            $choices = self::getEnumConstants($currentField->fieldName, $acceptedValues, $interface . '::');

            $choicesStr =
                "[\n" .  str_repeat($nlLeftPad, 2)
                . implode(",\n" . str_repeat($nlLeftPad, 2), $choices)
                . ",\n". $nlLeftPad
                . "]";

            $assertions[] = AssertionGenerator::choice(
                $currentField->fieldName,
                $choicesStr,
                $nlLeftPad
            );
        }

        $assertionStr = join("\n", $assertions);

        if ($isNullable && !empty(trim($assertionStr))) {
            $assertionStr = self::nullable(
                $currentField->fieldName,
                $assertionStr,
                $nlLeftPad
            );
        }

        if ($assertionStr) {
            $assertionStr .= "\n";
        }

        return self::indentLines($assertionStr, $nlLeftPad);
    }

    public static function notNull($fieldName): string
    {
        $message = $fieldName . ' value "%s" is null, but non null value was expected.';
        return "Assertion::notNull($". $fieldName .", '". $message ."');";
    }

    public static function nullable(string $fieldName, string $body, string $nlLeftPad)
    {
        return
            'if (!is_null($'. $fieldName .')) {'
            . "\n"
            . self::indentLines($body, $nlLeftPad)
            . "\n"
            . '}'
            . "\n";
    }

    private static function indentLines(string $text, string $nlLeftPad)
    {
        return
            $nlLeftPad
            . str_replace(
                "\n",
                "\n" . $nlLeftPad,
                $text
            );
    }

    public static function boolean($fieldName): string
    {
        $message = $fieldName . ' provided "%s" is not a valid boolean value.';
        return
            "Assertion::between((int) $". $fieldName .", 0, 1, '". $message ."');\n"
            // @todo not necesssary anymore
            ."$" . $fieldName . " = (bool) $". $fieldName .";";
    }

    public static function integer($fieldName): string
    {
        $message = $fieldName . ' value "%s" is not an integer or a number castable to integer.';
        return "Assertion::integerish($". $fieldName . ", '". $message ."');";
    }

    public static function float($fieldName): string
    {
        $message = $fieldName . ' value "%s" is not numeric.';
        return "Assertion::numeric($". $fieldName .");";
    }

    public static function greaterOrEqualThan($fieldName, $limit): string
    {
        $message = $fieldName . ' provided "%s" is not greater or equal than "%s".';
        return "Assertion::greaterOrEqualThan($". $fieldName .", " . $limit . ", '". $message ."');";
    }

    public static function maxLength($fieldName, $maxLength): string
    {
        $message = $fieldName . ' value "%s" is too long, it should have no more than %d characters, but has %d characters.';
        return "Assertion::maxLength($" . $fieldName . ", " . $maxLength . ", '". $message ."');";
    }

    public static function choice($fieldName, $choices, $nlLeftPad): string
    {
        $message = $fieldName . 'value "%s" is not an element of the valid values: %s';
        $response =
            "Assertion::choice("
            . "\n" . $nlLeftPad
            . "$"
            . $fieldName
            . ","
            . "\n" . $nlLeftPad
            . $choices
            . ","
            . "\n" . $nlLeftPad
            . "'"
            . $message
            . "'"
            . "\n"
            . ");";

        return $response;
    }

    private static function getFloatAssertions($currentField)
    {
        $assertions = [];
        if (!isset($currentField->options)) {
            $currentField->options = [];
        }
        $options = (object) $currentField->options;

        if (isset($options->unsigned) && $options->unsigned) {
            $assertions[] = AssertionGenerator::greaterOrEqualThan($currentField->fieldName, 0);
        }

        $isNullable = isset($currentField->nullable) && $currentField->nullable;
        if ($isNullable) {
            $assertions[] = '$' . $currentField->fieldName . ' = (float) $' . $currentField->fieldName . ';';
        }

        return $assertions;
    }

    private static function getIntegerAssertions($currentField)
    {
        $assertions = [];
        if (!isset($currentField->options)) {
            $currentField->options = [];
        }
        $options = (object) $currentField->options;

        if (isset($options->unsigned) && $options->unsigned) {
            $assertions[] = AssertionGenerator::greaterOrEqualThan($currentField->fieldName, 0);
        }

        return $assertions;
    }

    private static function getStringAssertions($currentField)
    {
        $assertions = [];
        if (isset($currentField->length)) {
            $assertions[] = AssertionGenerator::maxLength(
                $currentField->fieldName,
                $currentField->length
            );
        }

        return $assertions;
    }

    private static function getEnumConstants($fieldName, $acceptedValues, $prefix = '')
    {
        $choices = [];
        foreach ($acceptedValues as $acceptedValue) {
            $choice =
                $prefix
                . strtoupper($fieldName)
                . '_'
                . strtoupper(
                    preg_replace('/[^A-Z0-9]/i', '', $acceptedValue)
                );

            $choices[] = $choice;
        }

        return $choices;
    }
}
