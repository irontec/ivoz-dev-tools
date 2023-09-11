<?php

namespace IvozDevTools\EntityGeneratorBundle\Command;

use IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\MappingGenerator;
use Symfony\Bundle\MakerBundle\ApplicationAwareMakerInterface;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\MakerInterface;
use Symfony\Bundle\MakerBundle\Validator;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Used as the Command class for the makers.
 *
 * @internal
 */
final class MakeOrmMappingCommand extends MakeCommand
{
    protected static $defaultName = 'ivoz:make:orm:mapping';

    public function __construct(MakerInterface $maker, FileManager $fileManager, MappingGenerator $mappingGenerator)
    {
        parent::__construct(
            $maker,
            $fileManager,
            $mappingGenerator
        );
    }
}
