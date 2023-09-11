<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping;


final class RequestedProperty
{
    private string $fieldName;
    private string $type;
    private ?int $length = null;
    private ?string $nullable = "false";
    private ?int $precision = null;
    private ?int $scale = null;
    private string | int | null $default = null;
    private ?bool $unsigned = false;
    private ?string $comment = null;
    private ?MappedEntityRelation $relation;
    private ?string $onDelete = 'SET NULL';
    private string $fetch = 'LAZY';

    private string $uniqueConstraintColumns = '';
    

    public function __construct(string $fieldName, string $type)
    {
        $this->fieldName = $fieldName;
        $this->type = $type;
    }

    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setLength(int $length): void
    {
        $this->length = $length;
    }

    public function getLength(): ?int
    {
        return $this->length;
    }

    public function setNullable(string $nullable): void
    {
        $this->nullable = $nullable;
    }

    public function getNullable(): ?string
    {
        return $this->nullable;
    }

    public function setPrecision(int $precision): void
    {
        $this->precision = $precision;
    }

    public function getPrecision(): ?int
    {
        return $this->precision;
    }

    public function setScale(int $scale): void
    {
        $this->scale = $scale;
    }

    public function getScale(): ?int
    {
        return $this->scale;
    }

    public function getDefault(): string | int | null
    {
        return $this->default;
    }

    public function setDefault(string |int | null $default): self
    {
        $this->default = $default;

        return $this;
    }

    public function isUnsigned(): ?bool
    {
        return $this->unsigned;
    }

    public function setUnsigned(?bool $unsigned): self
    {
        $this->unsigned = $unsigned;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getRelation(): ?MappedEntityRelation
    {
        return $this->relation;
    }

    public function setRelation(?MappedEntityRelation $relation): self
    {
        $this->relation = $relation;

        return $this;
    }

    public function getOnDelete(): ?string
    {
        return $this->onDelete;
    }

    public function setOnDelete(?string $onDelete): self
    {
        $this->onDelete = $onDelete;

        return $this;
    }

    public function getFetch(): string
    {
        return $this->fetch;
    }

    public function setFetch(string $fetch): self
    {
        $this->fetch = $fetch;

        return $this;
    }

    public function getUniqueConstraintColumns(): string
    {
        return $this->uniqueConstraintColumns;
    }

    public function setUniqueConstraintColumns(string $uniqueConstraintColumns): self
    {
        $this->uniqueConstraintColumns = $uniqueConstraintColumns;

        return $this;
    }
}
