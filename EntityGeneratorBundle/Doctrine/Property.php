<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine;

class Property implements CodeGeneratorUnitInterface
{
    private $name;
    private $columnName;
    private $comments;
    private $defaultValue;
    private $required;
    private $fkFqdn;
    private $visibility;
    private $isCollection;

    public function __construct(
        string $name,
        string $columnName,
        array $comments,
        $defaultValue,
        bool $required,
        string $fkFqdn,
        string $visibility = 'protected',
        bool $isCollection = false
    ) {
        $this->name = $name;
        $this->columnName = $columnName;
        $this->comments = $comments;
        $this->defaultValue = $defaultValue;
        $this->required = $required;
        $this->fkFqdn = $fkFqdn;
        $this->visibility = $visibility;
        $this->isCollection = $isCollection;
    }

    public function getName(): string
    {
        return $this->name;
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
        $response[] = '/**';
        foreach ($this->comments as $comment) {
            $response[] = ' * ' . $comment;
        }
        $response[] = ' */';

        $attr = $this->visibility . ' $' . $this->name;
        if ($this->defaultValue) {

            if (is_array($this->defaultValue)) {
                $defaultValue = '[]';
            } else {
                $defaultValue = is_numeric($this->defaultValue)
                    ? $this->defaultValue
                    : '\'' . $this->defaultValue . '\'';
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