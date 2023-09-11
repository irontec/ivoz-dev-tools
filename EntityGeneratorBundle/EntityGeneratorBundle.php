<?php

namespace IvozDevTools\EntityGeneratorBundle;

use IvozDevTools\EntityGeneratorBundle\DependencyInjection\Compiler\MappingGeneratorCompiler;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class EntityGeneratorBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(
            new MappingGeneratorCompiler()
        );
    }
}
