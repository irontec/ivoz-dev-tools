<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\EntityInterface;

use Doctrine\ORM\Mapping\ClassMetadata;
use IvozDevTools\EntityGeneratorBundle\Doctrine\ManipulatorInterface;
use IvozDevTools\EntityGeneratorBundle\Generator;
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
        $interfaces = $this->getParentInterfaces($fqdn);

        [$classPath, $content] = $this->getClassTemplate(
            $classMetadata,
            'doctrine/EntityInterface.tpl.php'
        );

        $manipulator = $this->createClassManipulator(
            $classPath,
            $content
        );

        foreach ($interfaces as $interface) {
            $manipulator->addInterface(
                $interface,
                $classMetadata
            );
        }

        $manipulator->updateSourceCode();

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
        $interfaces = $this->getParentInterfaces($fqdn);

        [$classPath, $content] = $this->getClassTemplate(
            $classMetadata,
            'doctrine/EntityInterface.tpl.php'
        );

        $manipulator = $this->createClassManipulator(
            $classPath,
            $content
        );

        foreach ($interfaces as $interface) {
            $manipulator->addInterface(
                $interface,
                $classMetadata
            );
        }

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

        $entityInterface = 'Ivoz\\Core\\Domain\\Model\\EntityInterface';
        $entityInterfaceMethods = get_class_methods(
            $entityInterface
        );

        $className = str_replace('Interface', '', $classMetadata->getName());
        $reflectionClass = new \ReflectionClass($className);
        $reflectionClassInterface = new \ReflectionClass($entityInterface);
        $publicMethods = $reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC);

        foreach ($publicMethods as $publicMethod) {
            if ($publicMethod->isConstructor()) {
                continue;
            }

            $methodName = $publicMethod->getName();
            if (in_array($methodName, $entityInterfaceMethods)) {

                $interfaceMethod = $reflectionClassInterface->getMethod($methodName);
                $interfaceReturnType = (string) $interfaceMethod->getReturnType();
                $methodReturnType = (string) $publicMethod->getReturnType();

                if ($interfaceReturnType === $methodReturnType) {
                    continue;
                }
            }

            $manipulator->addMethod(
                $publicMethod,
                $classMetadata
            );
        }

        $manipulator->updateSourceCode();
    }

    private function getParentInterfaces(string $defaultImplementationClassName, bool $fqdn = true)
    {
        $parentInterfaces = [];
        try {
            $defaultImplementationReflection = new \ReflectionClass($defaultImplementationClassName);
            $getChangeSetMethod = $defaultImplementationReflection->getMethod('getChangeSet');

            $parentInterfaces[] = $getChangeSetMethod->isPublic()
                ? 'Ivoz\\Core\\Domain\\Model\\LoggableEntityInterface'
                : 'Ivoz\\Core\\Domain\\Model\\EntityInterface';

            $implementedInterfaces = $defaultImplementationReflection->getInterfaceNames();

            if (!empty($implementedInterfaces)) {
                $parentInterfaces = array_merge(
                    $implementedInterfaces,
                    $parentInterfaces
                );
            }
        } catch (\Exception $e) {
            $parentInterfaces = ['Ivoz\\Core\\Domain\\Model\\EntityInterface'];
        }

        $parentInterfaces = array_filter(
            $parentInterfaces,
            function ($className) {
                return stripos($className, 'Ivoz\\') === 0;
            }
        );

        $potentiallyDuplicated = [];
        foreach ($parentInterfaces as $interfaceFqdn) {
            $interfaceReflection = new \ReflectionClass($interfaceFqdn);
            $interfaces = $interfaceReflection->getInterfaceNames();

            foreach ($interfaces as $item) {
                $potentiallyDuplicated[] = $item;
            }
        }

        $parentInterfaces = array_diff($parentInterfaces, $potentiallyDuplicated);

        if (!$fqdn) {
            $parentInterfaces = array_map(
                function ($item) {
                    $segments = explode('\\', $item);
                    return end($segments);
                },
                $parentInterfaces
            );
        }

        return array_unique($parentInterfaces);
    }
}
