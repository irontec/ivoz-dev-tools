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
        $type = $this->type !== 'resource'
            ? $this->type
            : 'string';

        $typeHint = $this->isNullable
            ? '?' . $type
            : $type;

        if ($this->type === '\\DateTime') {
            $typeHint = 'string|\DateTimeInterface';
            if ($this->isNullable) {
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

        if ($this->type === 'resource') {
            if ($this->isNullable) {
                $response[] = '    if (is_null($this->' . $this->propertyName . ')) {';
                $response[] = '        $this->' . $this->propertyName . ' = $' . $this->propertyName . ';';
                $response[] = '';
                $response[] = '        return $this;';
                $response[] = '    }';
                $response[] = '';
            }

            $response[] = '    $stream = fopen(\'php://memory\', \'r+\');';
            $response[] = '    fwrite($stream, $' . $this->propertyName . ');';
            $response[] = '    rewind($stream);';
            $response[] = '    $this->' . $this->propertyName . ' = $stream;';
        } else {
            $response[] = '    $this->' . $this->propertyName . ' = $' . $this->propertyName . ';';
        }
        $response[] = '';
        $response[] = '    return $this;';
        $response[] = '}';

        return implode(
            "\n". $nlLeftPad,
            $response
        );
    }
}
