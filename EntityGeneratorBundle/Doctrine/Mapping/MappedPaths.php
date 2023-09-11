<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping;

final class MappedPaths
{
    public function __construct(
        private string $superClassPath,
        private string $mappedSuperClassPath
    ) {
    }

    public function getMappedSuperClassPath(): string
    {
        return $this->mappedSuperClassPath;
    }


    public function getSuperClassPath(): string
    {
        return $this->superClassPath;
    }
}
