<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine;

class Property implements CodeGeneratorUnitInterface
{
    private $defaultValue;

    public function __construct(
        private string $name,
        private string $typeHint,
        private string $columnName,
        private array $comments,
        $defaultValue,
        private bool $required,
        private string $fkFqdn,
        private string $visibility = 'protected',
        private bool $isCollection = false
    ) {
        if ($defaultValue === null) {
            return;
        }

        if (!is_string($defaultValue)) {
            $this->defaultValue = $defaultValue;
            return;
        }

        if ($defaultValue === 'NULL') {
            return;
        }

        $scapeCuotes =
            !empty($defaultValue)
            && $defaultValue[0] === "'"
            && $defaultValue[strlen($defaultValue) -1] === "'";

        $this->defaultValue = $scapeCuotes
            ? substr($defaultValue, 1, -1)
            : $defaultValue;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getHint(): string
    {
        $typeHint = $this->typeHint !== 'resource'
            ? $this->typeHint
            : 'string';

        if ($this->required) {
            return $typeHint;
        }

        if (str_contains($typeHint, '|')) {
            return 'null|' . $typeHint;
        }

        return '?' . $typeHint;
    }

    public function getColumnName(): string
    {
        return $this->columnName;
    }

    public function isRequired()
    {
        return $this->required;
    }

    public function isForeignKey()
    {
        return ! empty($this->fkFqdn);
    }

    public function getForeignKeyFqdn($short = false)
    {
        if ($short) {
            $segments = explode('\\', $this->fkFqdn);
            return end($segments);
        }

        return $this->fkFqdn;
    }

    public function isCollection()
    {
        return $this->isCollection;
    }

    public function getComments()
    {
        return $this->comments;
    }

    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    public function toString(string $nlLeftPad = ''): string
    {
        $response = [];
        if (count($this->comments) > 0) {
            $response[] = '/**';
            foreach ($this->comments as $comment) {
                $response[] = ' * ' . $comment;
            }
            $response[] = ' */';
        }

        $attr = $this->visibility . ' $' . $this->name;
        if (!is_null($this->defaultValue) || !$this->isRequired()) {

            if ($this->defaultValue === 'null') {
                $defaultValue = 'null';
            } else if (is_array($this->defaultValue)) {
                $defaultValue = '[]';
            } else {
                $defaultValue = $this->defaultValue;

                switch(gettype($this->defaultValue)) {
                    case 'boolean':
                        $defaultValue = $this->defaultValue
                            ? 'true'
                            : 'false';
                        break;
                    case 'string':
                        if (!is_null($this->defaultValue)) {
                            $defaultValue = '\'' . $this->defaultValue . '\'';
                        }
                        break;
                    default:
                        $defaultValue = !is_null($this->defaultValue)
                            ? $this->defaultValue
                            : 'null';
                }
            }

            $attr .= sprintf(
                " = %s",
                $defaultValue
            );
        }
        $attr .= ';';
        $response[] = $attr;

        return implode(
            "\n". $nlLeftPad,
            $response
        );
    }
}
