<?php

namespace IvozDevTools\EntityGeneratorBundle\Maker;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Regenerator;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Symfony\Bundle\MakerBundle\Doctrine\EntityRelation;
use Symfony\Bundle\MakerBundle\Doctrine\ORMDependencyBuilder;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputAwareMakerInterface;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Util\ClassDetails;
use Symfony\Bundle\MakerBundle\Validator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Question\Question;

final class MakeEntities extends AbstractMaker implements InputAwareMakerInterface
{
    private $fileManager;
    private $doctrineHelper;
    private $generator;

    public function __construct(
        FileManager $fileManager,
        DoctrineHelper $doctrineHelper,
        Generator $generator = null
    ) {
        $this->fileManager = $fileManager;
        $this->doctrineHelper = $doctrineHelper;
        // $projectDirectory is unused, argument kept for BC

        if (null === $generator) {
            @trigger_error(sprintf('Passing a "%s" instance as 4th argument is mandatory since version 1.5.', Generator::class), E_USER_DEPRECATED);
            $this->generator = new Generator($fileManager, 'App\\');
        } else {
            $this->generator = $generator;
        }
    }

    public static function getCommandName(): string
    {
        return 'ivoz:make:entities';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConf)
    {
        $command
            ->setDescription('Creates or updates a Doctrine entity classes')
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
                    return strpos($key, $targetNamespace) === 0;
                },
                ARRAY_FILTER_USE_KEY
            );

            krsort($targetEntities);
            foreach ($targetEntities as $name => $metadata) {
                $this->regenerateEntities($name, $this->generator);
            }
        }

        $io->text([
            'Next: When you\'re ready, create a migration with <info>php bin/console doctrine:migrations:diff</info>',
            '',
        ]);
        $this->writeSuccessMessage($io);
    }

    public function configureDependencies(DependencyBuilder $dependencies, InputInterface $input = null)
    {
        ORMDependencyBuilder::buildDependencies($dependencies);
    }

    private function getEntityNamespaces(string $targetNamespace): ?string
    {
        $managers = $this->doctrineHelper->getRegistry()->getManagers();

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

    /**
     * @param string $classOrNamespace
     * @param \IvozDevTools\EntityGeneratorBundle\Generator $generator
     */
    private function regenerateEntities(
        string $classOrNamespace,
        Generator $generator
    ) {
        $regenerator = new Regenerator(
            $this->doctrineHelper,
            $this->fileManager,
            $generator
        );

        $regenerator->regenerateEntities(
            $classOrNamespace
        );
    }
}
