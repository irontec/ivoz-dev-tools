<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine;

use IvozDevTools\EntityGeneratorBundle\Util\AssertionGenerator;
use Symfony\Bundle\MakerBundle\Str;

class Setter implements CodeGeneratorUnitInterface
{
    protected $propertyName;
    protected $type;
    protected $isNullable;
    protected $comments = [];
    protected $columnOptions = [];
    protected $classMetadata;
    protected $visibility;

    public function __construct(
        string $propertyName,
        ?string $type,
        bool $isNullable,
        $classMetadata,
        array $commentLines = [],
        array $columnOptions = [],
        string $visibility = 'protected'
    ) {
        $this->propertyName = $propertyName;
        $this->type = $type;
        $this->isNullable = $isNullable;
        $this->comments = $commentLines;
        $this->columnOptions = $columnOptions;
        $this->classMetadata = $classMetadata;
        $this->visibility = $visibility;
    }

    public function toString(string $nlLeftPad = ''): string
    {
        $typeHint = $this->isNullable
            ? '?' . $this->type
            : $this->type;

        if ($this->type === '\\DateTime') {
            $typeHint = 'string|\DateTimeInterface';
            if ( $this->isNullable) {
                $typeHint .= '|null';
            }
            $typeHint .= ' ';
        } else {
            $typeHint .= ' ';
        }

        $nullableStr = $this->isNullable
            ? ' = null'
            : '';

        $methodName = 'set' . Str::asCamelCase($this->propertyName);

        $response = [];
        $response[] = sprintf(
            '%s function %s(%s%s%s): %s',
            $this->visibility,
            $methodName,
            $typeHint,
            '$' . $this->propertyName,
            $nullableStr,
            'static'
        );

        $assertions = AssertionGenerator::get($this->columnOptions, $this->classMetadata, $nlLeftPad);
        $assertions = str_replace(
            "\n",
            "\n" . $nlLeftPad,
            $assertions
        );

        $response[] = '{';
        if (!empty(trim($assertions))) {
            $response[] = $assertions;
        }
        $response[] = '    $this->' . $this->propertyName . ' = $' . $this->propertyName . ';';
        $response[] = '';
        $response[] = '    return $this;';
        $response[] = '}';

        return implode(
            "\n". $nlLeftPad,
            $response
        );
    }
}
