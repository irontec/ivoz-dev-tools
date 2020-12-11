<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Entity;

use IvozDevTools\EntityGeneratorBundle\Doctrine\Property;

class EmbeddedProperty extends Property
{
    public function isForeignKey()
    {
        return false;
    }
}