<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\EntityInterface;

use Doctrine\ORM\Mapping\ClassMetadata;
use IvozDevTools\EntityGeneratorBundle\Doctrine\ManipulatorInterface;
use IvozDevTools\EntityGeneratorBundle\Generator;
use Symfony\Bundle\MakerBundle\Doctrine\RelationManyToMany;
use Symfony\Bundle\MakerBundle\Doctrine\RelationManyToOne;
use Symfony\Bundle\MakerBundle\Doctrine\RelationOneToMany;
use Symfony\Bundle\MakerBundle\Doctrine\RelationOneToOne;
use Symfony\Bundle\MakerBundle\FileManager;

/**
 * @internal
 */
final class InterfaceRegenerator
{
    private $fileManager;
    private $generator;

    public function __construct(
        FileManager $fileManager,
        Generator $generator
    ) {
        $this->fileManager = $fileManager;
        $this->generator = $generator;
    }

    public function makeEmptyInterface($classMetadata)
    {
        $classMetadata = clone $classMetadata;

        $fqdn =
            $classMetadata->name
            . 'Interface';

        $classMetadata->name = $fqdn;
        $classMetadata->rootEntityName = $fqdn;

        [$classPath, $content] = $this->getClassTemplate(
            $classMetadata,
            'doctrine/EntityInterface.tpl.php'
        );

        $manipulator = $this->createClassManipulator(
            $classPath,
            $content
        );

        $this->dumpFile(
            $classPath,
            $manipulator
        );
    }

    public function makeInterface($classMetadata)
    {
        $classMetadata = clone $classMetadata;

        $fqdn =
            $classMetadata->name
            . 'Interface';

        $classMetadata->name = $fqdn;
        $classMetadata->rootEntityName = $fqdn;

        [$classPath, $content] = $this->getClassTemplate(
            $classMetadata,
            'doctrine/EntityInterface.tpl.php'
        );

        $manipulator = $this->createClassManipulator(
            $classPath,
            $content
        );

        $this->addMethods(
            $manipulator,
            $classMetadata
        );

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
        string $classPath,
        ?string $content
    ): ManipulatorInterface
    {
        $classContent = $content ?? $this->fileManager->getFileContents($classPath);
        return new InterfaceManipulator(
            $classContent
        );
    }

    /**
     * @param $metadata
     * @param array $operations
     * @return array
     * @throws \Exception
     */
    private function addMethods($manipulator, $classMetadata): void
    {
        foreach ($classMetadata->fieldMappings as $fieldName => $mapping) {
            $comment = $mapping['options']['comment'] ?? '';
            if (0 !== strpos($comment, '[enum:')) {
                continue;
            }

            $comment = str_replace(
                '[enum:',
                '',
                substr(trim($comment), 0, -1)
            );
            $choices = explode('|', $comment);

            foreach ($choices as $choice) {
                $normalizedChoice = preg_replace('/[^A-Za-z0-9]+/', '', $choice);
                $constantName = strtoupper($fieldName) . '_' . strtoupper($normalizedChoice);
                $manipulator->addConstant(
                    $constantName,
                    $choice
                );
            }
        }

        $entityInterfaceMethods = get_class_methods(
            'Ivoz\\Core\\Domain\\Model\\EntityInterface'
        );

        $className = str_replace('Interface', '', $classMetadata->getName());
        $reflectionClass = new \ReflectionClass($className);
        $publicMethods = $reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC);

        foreach ($publicMethods as $publicMethod) {
            if ($publicMethod->isConstructor()) {
                continue;
            }

            $methodName = $publicMethod->getName();
            if (in_array($methodName, $entityInterfaceMethods)) {
                continue;
            }

            $manipulator->addMethod(
                $publicMethod,
                $classMetadata
            );
        }

        $manipulator->updateSourceCode();
    }
}
