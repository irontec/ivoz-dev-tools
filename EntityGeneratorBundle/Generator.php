<?php

namespace IvozDevTools\EntityGeneratorBundle;

use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\Generator as MakerBundleGenerator;
use Symfony\Bundle\MakerBundle\Str;

class Generator extends MakerBundleGenerator
{
    private $fileManager;

    public function __construct(FileManager $fileManager, string $namespacePrefix)
    {
        $this->fileManager = $fileManager;

        return parent::__construct(
            $fileManager,
            $namespacePrefix
        );
    }

    public function generateClassContentVariables(
        string $className,
        string $templateName,
        array $variables = []
    ): array
    {
        $targetPath = $this->fileManager->getRelativePathForFutureClass($className);

        if (null === $targetPath) {
            throw new \LogicException(sprintf('Could not determine where to locate the new class "%s", maybe try with a full namespace like "\\My\\Full\\Namespace\\%s"', $className, Str::getShortClassName($className)));
        }

        $shortClassName = Str::getShortClassName($className);
        $parentClassName = str_replace(
            ['Abstract', 'Trait'],
            '',
            $shortClassName
        );

        $variables = array_merge($variables, [
            'class_name' => $shortClassName,
            'parent_class_name' => $parentClassName,
            'namespace' => Str::getNamespace($className),
        ]);

        return $this->getClassContentVariables(
            $targetPath,
            $templateName,
            $variables
        );
    }

    private function getClassContentVariables(string $targetPath, string $templateName, array $variables): array
    {
        $variables['relative_path'] = $this->fileManager->relativizePath($targetPath);

        $templatePath = $templateName;
        if (!file_exists($templatePath)) {
            $templatePath = __DIR__ . '/Resources/skeleton/' . $templateName;

            if (!file_exists($templatePath)) {
                throw new \Exception(sprintf('Cannot find template "%s"', $templateName));
            }
        }

        return [
            $templatePath,
            $variables,
        ];
    }
}
