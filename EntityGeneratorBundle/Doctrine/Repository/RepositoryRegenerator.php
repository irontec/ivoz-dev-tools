<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Repository;

use Doctrine\ORM\Mapping\ClassMetadata;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Dto\DtoManipulator;
use IvozDevTools\EntityGeneratorBundle\Doctrine\ManipulatorInterface;
use IvozDevTools\EntityGeneratorBundle\Generator;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\Str;

/**
 * @internal
 */
final class RepositoryRegenerator
{

    public function __construct(
        private FileManager $fileManager,
        private Generator   $generator
    )
    {
    }

    public function makeDoctrineRepository($classMetadata)
    {

        $classMetadata = clone $classMetadata;
        if (class_exists($classMetadata->name)) {
            return;
        }

        $entity_namespace = $classMetadata->name;
        $interface_namespace = $classMetadata->name.'RepositoryInterface';
        $interface_name = Str::getShortClassName($interface_namespace);
        $classMetadata->name = $classMetadata->customRepositoryClassName;
        $classMetadata->rootEntityName = $classMetadata->customRepositoryClassName;
        $variables = [
            'interface_namespace' => $interface_namespace,
            'interface_name' => $interface_name,
            'entity_namespace' => $entity_namespace,
            'entity_classname' => Str::getShortClassName($entity_namespace)
        ];

        [$classPath, $content] = $this->getDoctrineClassTemplate(
            $classMetadata,
            'doctrine/DoctrineRepository.tpl.php',
            $variables
        );

        $manipulator = $this->createClassManipulator(
            $classPath,
            $content
        );

        $manipulator->updateSourceCode();

        $this->dumpFile(
            $classPath,
            $manipulator
        );
    }


    public function makeEmptyInterface($classMetadata)
    {

        $classMetadata = clone $classMetadata;

        $fqdn =
            $classMetadata->name
            . 'RepositoryInterface';

        if (class_exists($fqdn)) {
            return;
        }
        $classMetadata->name = $fqdn;
        $classMetadata->rootEntityName = $fqdn;

        [$classPath, $content] = $this->getDoctrineClassTemplate(
            $classMetadata,
            'doctrine/RepositoryInterface.tpl.php',
            []
        );

        $manipulator = $this->createClassManipulator(
            $classPath,
            $content
        );

        $manipulator->updateSourceCode();

        $this->dumpFile(
            $classPath,
            $manipulator
        );
    }

    private function dumpFile(string $classPath, ManipulatorInterface $manipulator): void
    {
        $this->fileManager->dumpFile(
            $classPath,
            $manipulator->getSourceCode()
        );
    }

    private function getDoctrineClassTemplate(
        ClassMetadata $metadata,
                      $templateName,
                      $variables
    ): array
    {
        [$path, $variables] = $this->generator->generateClassContentVariables(
            $metadata->name,
            $templateName,
            $variables
        );

        if (file_exists($variables['relative_path'])) {
            $variables['relative_path'] = realpath($variables['relative_path']);
        } else {
            $variables['relative_path'] = str_replace(
                'vendor/composer/../../',
                '',
                $variables['relative_path']
            );
        }


        return [
            $variables['relative_path'],
            $this->fileManager->parseTemplate(
                $path,
                $variables
            )
        ];
    }

    private function getClassTemplate(
        ClassMetadata $metadata,
                      $templateName
    ): array
    {
        [$path, $variables] = $this->generator->generateClassContentVariables(
            $metadata->name,
            $templateName,
            []
        );

        if (file_exists($variables['relative_path'])) {
            $variables['relative_path'] = realpath($variables['relative_path']);
        } else {
            $variables['relative_path'] = str_replace(
                'vendor/composer/../../',
                '',
                $variables['relative_path']
            );
        }


        return [
            $variables['relative_path'],
            $this->fileManager->parseTemplate(
                $path,
                $variables
            )
        ];
    }


    private function createClassManipulator(
        string  $classPath,
        ?string $content
    ): ManipulatorInterface
    {
        $classContent = $content ?? $this->fileManager->getFileContents($classPath);
        return new RepositoryManipulator(
            $classContent
        );
    }
}