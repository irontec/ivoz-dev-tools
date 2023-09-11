<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping;

use Symfony\Bundle\MakerBundle\Doctrine\BaseRelation;

final class MappedEntityRelation extends BaseRelation
{
    public const MANY_TO_ONE = 'ManyToOne';
    public const ONE_TO_MANY = 'OneToMany';
    public const ONE_TO_ONE = 'OneToOne';

    private ?string $inversedProjectName;

    private string $owningProperty;

    private ?string $inverseProperty = null;

    private bool $isNullable = false;

    private bool $orphanRemoval = false;

    private bool $isOwning = false;
    public function __construct(
        private string $type,
        private string $owningClass,
        private string $inverseClass
    ) {
    }

    public static function getValidRelationTypes(): array
    {
        return [
            self::MANY_TO_ONE,
            self::ONE_TO_MANY,
            self::ONE_TO_ONE,
        ];
    }

    public function getInversedProjectName(): ?string
    {
        return $this->inversedProjectName;
    }

    public function setInversedProjectName(?string $inversedProjectName): self
    {
        $this->inversedProjectName = $inversedProjectName;

        return $this;
    }

    public function isOwning(): bool
    {
        return $this->isOwning;
    }

    public function setIsOwning(bool $isOwning): self
    {
        $this->isOwning = $isOwning;

        return $this;
    }


    public function getType(): string
    {
        return $this->type;
    }


    public function getOwningClass(): string
    {
        return $this->owningClass;
    }


    public function getInverseClass(): string
    {
        return $this->inverseClass;
    }

    public function isNullable(): bool
    {
        return $this->isNullable;
    }

    public function setIsNullable(bool $isNullable): self
    {
        $this->isNullable = $isNullable;

        return $this;
    }

    public function getInverseProperty(): ?string
    {
        return $this->inverseProperty;
    }

    public function setInverseProperty(?string $inverseProperty): self
    {
        if (!$this->getMapInverseRelation()) {
            throw new \Exception('Cannot call setInverseProperty() when the inverse relation will not be mapped.');
        }

        $this->inverseProperty = $inverseProperty;

        return $this;
    }

    public function getOwningProperty(): string
    {
        return $this->owningProperty;
    }

    public function setOwningProperty(string $owningProperty): self
    {
        $this->owningProperty = $owningProperty;

        return $this;
    }

    public function isOrphanRemoval(): bool
    {
        return $this->orphanRemoval;
    }

    public function setOrphanRemoval(bool $orphanRemoval): self
    {
        $this->orphanRemoval = $orphanRemoval;

        return $this;
    }

    public function setMapInverseRelation(bool $mapInverseRelation): self
    {
        if ($mapInverseRelation && $this->inverseProperty) {
            throw new \Exception('Cannot set setMapInverseRelation() to true when the inverse relation property is set.');
        }

        parent::setMapInverseRelation($mapInverseRelation);

        return $this;
    }
}
