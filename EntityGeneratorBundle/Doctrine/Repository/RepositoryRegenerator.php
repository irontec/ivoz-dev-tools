<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Repository;

use Doctrine\ORM\Mapping\ClassMetadata;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Dto\DtoManipulator;
use IvozDevTools\EntityGeneratorBundle\Doctrine\ManipulatorInterface;
use IvozDevTools\EntityGeneratorBundle\Generator;
use Symfony\Bundle\MakerBundle\FileManager;

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


    public function makeEmptyRepository($classMetadata)
    {

        $classMetadata = clone $classMetadata;

        $fqdn =
            $classMetadata->name
            . 'Repository';

        if (class_exists($fqdn)) {
            return;
        }
        $classMetadata->name = $fqdn;
        $classMetadata->rootEntityName = $fqdn;

        [$classPath, $content] = $this->getClassTemplate(
            $classMetadata,
            'doctrine/RepositoryInterface.tpl.php'
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
        return new DtoManipulator(
            $classContent
        );
    }
}