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
        array $commentLines = [],
        array $columnOptions = [],
        $classMetadata,
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
        $response[] = '/**';
        foreach ($this->comments as $comment) {
            $response[] = !empty(trim($comment))
                ?' * ' . $comment
                : ' *';
        }
        $response[] = ' */';

        $typeHint = $this->isNullable
            ? '?' . $this->type
            : $this->type;

        if ($this->type === '\\DateTimeInterface') {
            $typeHint = '';
        } else {
            $typeHint .= ' ';
        }

        $nullableStr = $this->isNullable
            ? ' = null'
            : '';

        $methodName = 'set' . Str::asCamelCase($this->propertyName);

        $fqdnSegments = explode('\\', $this->classMetadata->name);
        $returnHint = $this->classMetadata->isEmbeddedClass
            ? $fqdnSegments[count($fqdnSegments) -1]
            : $fqdnSegments[count($fqdnSegments) -2] . 'Interface';

        $response[] = sprintf(
            '%s function %s(%s%s%s): %s',
            $this->visibility,
            $methodName,
            $typeHint,
            '$' . $this->propertyName,
            $nullableStr,
            $returnHint
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