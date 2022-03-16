<?php

namespace IvozDevTools\EntityGeneratorBundle\Maker;

use Doctrine\ORM\EntityManagerInterface;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Regenerator;
use IvozDevTools\EntityGeneratorBundle\Generator as IvozGenerator;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Symfony\Bundle\MakerBundle\Doctrine\ORMDependencyBuilder;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\InputAwareMakerInterface;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Doctrine\Persistence\ManagerRegistry;

final class MakeRepositories extends AbstractMaker implements InputAwareMakerInterface
{
    private IvozGenerator $generator;

    /**
     * @param IvozGenerator $generator
     */
    public function __construct(
        Generator $generator,
        private FileManager $fileManager,
        private DoctrineHelper $doctrineHelper
    ) {
        if (!$generator instanceof IvozGenerator) {
            throw new \RuntimeException('generator must be instance of IvozDevTools\EntityGeneratorBundle\Generator');
        }

        $this->generator = $generator;
    }

    public static function getCommandName(): string
    {
        return 'ivoz:make:repositories';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConf)
    {
        $command
            ->setDescription('Creates or updates a Doctrine repository classes')
            ->addArgument(
                'namespaces',
                InputArgument::IS_ARRAY,
                'doctrine.orm.mappings namespace identifier'
            )
        ;

        $inputConf
            ->setArgumentAsNonInteractive('namespace');
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command)
    {
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator)
    {
        $namespaces = $input->getArgument('namespaces');

        foreach ($namespaces as $namespace) {
            $targetNamespace = $this
                ->getEntityNamespaces(
                    $namespace
                );

            if (empty($targetNamespace)) {
                throw new \Exception('Namespace identifier not found in doctrine.orm.mappings');
            }

            try {
                $allEntities = $this
                    ->doctrineHelper
                    ->getMetadata(null, true);
            } catch (\Exception $e) {
                continue;
            }

            $targetEntities = array_filter(
                $allEntities,
                function ($key) use ($targetNamespace) {
                    return str_starts_with($key, $targetNamespace);
                },
                ARRAY_FILTER_USE_KEY
            );

            krsort($targetEntities);
            foreach ($targetEntities as $name => $metadata) {
                $this->regenerateRepositoriesInterfaces($name, $this->generator);
            }
        }

        $io->text([
            'Next: When you\'re ready, create a migration with <info>php bin/console doctrine:migrations:diff</info>',
            '',
        ]);
        $this->writeSuccessMessage($io);
    }


    private function getEntityNamespaces(string $targetNamespace): ?string
    {
        /** @var ManagerRegistry $registry */
        $registry = $this->doctrineHelper->getRegistry();
        $managers = $registry->getManagers();

        /** @var EntityManagerInterface $em */
        foreach ($managers as $em) {
            $configuration = $em->getConfiguration();
            $entityNamespaces = $configuration->getEntityNamespaces();

            if (!array_key_exists($targetNamespace, $entityNamespaces)) {
                continue;
            }

            return $entityNamespaces[$targetNamespace];
        }

        return null;
    }

    public function configureDependencies(DependencyBuilder $dependencies, InputInterface $input = null)
    {
        ORMDependencyBuilder::buildDependencies($dependencies);
    }

    /**
     * @param string $classOrNamespace
     * @param \IvozDevTools\EntityGeneratorBundle\Generator $generator
     */
    private function regenerateRepositoriesInterfaces(
        string $classOrNamespace,
        IvozGenerator $generator
    ) {
        $regenerator = new Regenerator(
            $this->doctrineHelper,
            $this->fileManager,
            $generator
        );

        $regenerator->regenerateRepositories(
            $classOrNamespace
        );
    }

}