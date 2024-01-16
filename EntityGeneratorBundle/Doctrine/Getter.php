<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine;

use Symfony\Bundle\MakerBundle\Str;

class Getter implements CodeGeneratorUnitInterface
{
    protected $propertyName;
    protected $returnType;
    protected $isReturnTypeNullable;
    protected $comments = [];

    public function __construct(
        string $propertyName,
        $returnType,
        bool $isReturnTypeNullable,
        array $commentLines = []
    ) {
        $this->propertyName = $propertyName;
        $this->returnType = $returnType;
        $this->isReturnTypeNullable = $isReturnTypeNullable;
        $this->comments = $commentLines;
    }

    public function toString(string $nlLeftPad = ''): string
    {
        $returnType = $this->returnType !== 'resource'
            ? $this->returnType
            : 'string';

        $returnType = $this->isReturnTypeNullable
            ? ': ?' . $returnType
            : ': ' . $returnType;

        if (is_null($this->returnType)) {
            $returnType = '';
        }

        $methodName = 'get' . Str::asCamelCase($this->propertyName);

        $response = [];
        $response[] = sprintf(
            'public function %s()%s',
            $methodName,
            $returnType
        );
        $response[] = '{';

        if ($this->returnType === '\\DateTime') {

            $clone =
                'clone $this->'
                . $this->propertyName;

            $stmt = $this->isReturnTypeNullable
                ? '!is_null($this->' . $this->propertyName . ') ? ' . $clone . ' : null;'
                : $clone . ';';

            $response[] = '    return ' . $stmt;

        } else if ($this->returnType === 'resource') {
            if ($this->isReturnTypeNullable) {
                $response[] = '    if (is_null($this->' . $this->propertyName . ')) {';
                $response[] = '        return null;';
                $response[] = '    }';
                $response[] = '';
            }

            $response[] = '    rewind($this->' . $this->propertyName . ');';
            $response[] = '';
            $response[] = '    /** @var string $response */';
            $response[] = '    $response = stream_get_contents($this->' . $this->propertyName . ');';
            $response[] = '';
            $response[] = '    return $response;';
        } else {
            $response[] = '    return $this->' . $this->propertyName . ';';
        }
        $response[] = '}';

        return implode(
            "\n". $nlLeftPad,
            $response
        );
    }
}
