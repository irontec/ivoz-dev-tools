<?php

namespace IvozDevTools\MigrationsBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class MigrationsBundle extends Bundle
{
    public function getParent()
    {
        return 'DoctrineMigrationsBundle';
    }
}
