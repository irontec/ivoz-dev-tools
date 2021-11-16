<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine;

class Property implements CodeGeneratorUnitInterface
{
    public function __construct(
        private string $name,
        private string $typeHint,
        private string $columnName,
        private array $comments,
        private $defaultValue,
        private bool $required,
        private string $fkFqdn,
        private string $visibility = 'protected',
        private bool $isCollection = false
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getHint(): string
    {
        if ($this->required) {
            return $this->typeHint;
        }

        if (str_contains($this->typeHint, '|')) {
            return 'null|' . $this->typeHint;
        }

        return '?' . $this->typeHint;
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

        $attr = $this->visibility /*. ' ' . $this->getHint()*/ . ' $' . $this->name;
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
