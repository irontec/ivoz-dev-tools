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
                
                $xmlPaths = [];
                if (array_key_exists('xml', $doctrine[$key])) {
                    $xmlPaths = array_values($doctrine[$key]['xml']);
                }

                $annotationPaths = [];
                if (array_key_exists('annotation', $doctrine[$key])) {
                    $annotationPaths = array_values($doctrine[$key]['annotation']);
                }

                $phpPaths = [];
                if (array_key_exists('php', $doctrine[$key])) {
                    $phpPaths = array_values($doctrine[$key]['php']);
                }

                $paths = array_merge($xmlPaths, $annotationPaths, $phpPaths);
                continue;
            }
        }

        return ['paths' => array_combine($aliasMapKeys, $paths), 'aliasMap' => $aliasMap];
    }
}
