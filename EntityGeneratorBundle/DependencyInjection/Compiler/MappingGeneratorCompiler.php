<?php

namespace IvozDevTools\EntityGeneratorBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\MappingGenerator;

class MappingGeneratorCompiler implements CompilerPassInterface
{
    /**
     * @var ContainerBuilder
     */
    protected $container;

    /**
     * @return void
     */
    public function process(ContainerBuilder $container)
    {
        $this->container = $container;

        $mappingGeneratorDefinition = $this->container->findDefinition(MappingGenerator::class);
        $mappingConfig = $this->getDoctrineMappingConfig();
        $mappingGeneratorDefinition->setArgument('$mappings', $mappingConfig);
    }

    /**
     * @return array<array-key, array<string,string>>
     */
    private function getDoctrineMappingConfig(): array
    {
        /** @var array<array-key, array<array-key, mixed>> $doctrine*/
        $doctrine = (array) $this->container->getExtension('doctrine');
        $aliasMapKeys = [];
        $aliasMap = [];
        $paths = [];

        foreach (array_keys($doctrine) as $key) {
            if (strpos($key, 'aliasMap') !== false) {
                $aliasMapKeys = array_keys($doctrine[$key]);
                $aliasMap = $doctrine[$key];
                continue;
            }

            if (strpos($key, 'drivers') !== false) {
                $paths = array_values($doctrine[$key]['xml']);
                continue;
            }
        }

        return ['paths' => array_combine($aliasMapKeys, $paths), 'aliasMap' => $aliasMap];
    }
}
