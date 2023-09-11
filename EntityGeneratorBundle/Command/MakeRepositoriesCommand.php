<?php

namespace IvozDevTools\EntityGeneratorBundle\Command;

use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\MakerInterface;

/**
 * Used as the Command class for the makers.
 *
 * @internal
 */
final class MakeRepositoriesCommand extends MakeCommand
{
    protected static $defaultName = 'ivoz:make:entities';

    
    public function __construct(
        MakerInterface $maker,
        FileManager $fileManager,
        Generator $generator
    ) {
        parent::__construct(
            $maker,
            $fileManager,
            $generator
        );
    }

}