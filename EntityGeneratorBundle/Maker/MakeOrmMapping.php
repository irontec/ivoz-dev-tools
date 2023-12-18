<?php

namespace IvozDevTools\EntityGeneratorBundle\Maker;

use IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\Manipulator\DumpXmlAttributes;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\Manipulator\GetFields;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\MappingGenerator;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Symfony\Bundle\MakerBundle\Doctrine\ORMDependencyBuilder;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputAwareMakerInterface;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Validator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Doctrine\DBAL\Types\Type;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\MappedEntityRelation;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\MappedPaths;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\MappingManipulator;
use IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping\RequestedProperty;

use Symfony\Component\Console\Question\Question;

final class MakeOrmMapping extends AbstractMaker implements InputAwareMakerInterface
{
    private $doctrineHelper;
    private $generator;

    public function __construct(
        FileManager      $fileManager,
        DoctrineHelper   $doctrineHelper,
        MappingGenerator $mappingGenerator = null,
    )
    {
        $this->doctrineHelper = $doctrineHelper;
        // $projectDirectory is unused, argument kept for BC

        if (null === $mappingGenerator) {
            @trigger_error(sprintf('Passing a "%s" instance as 4th argument is mandatory since version 1.5.', Generator::class), E_USER_DEPRECATED);
            $this->generator = new Generator($fileManager, 'App\\');
        } else {
            $this->generator = $mappingGenerator;
        }
    }

    public static function getCommandDescription(): string
    {
        return 'Command for generation of orm mapping files';
    }

    public static function getCommandName(): string
    {
        return 'ivoz:make:orm:mapping';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConf)
    {
        $command
            ->setDescription('Creates or updates Orm mapping files')
            ->addArgument('name', InputArgument::OPTIONAL, sprintf('Class name of the entity to create or update (e.g. <fg=yellow>%s</>)', Str::asClassName(Str::getRandomTerm())))
            ->addArgument('mappingName', InputArgument::OPTIONAL, 'Insert a mapping name defined in doctrine.orm.mappings config');
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command)
    {

        if (strlen($input->getArgument('mappingName')) <= 2) {

            $io->error('Mapping Name should contains more than two characters');

            while (true) {
                $mappingNameValidated = $io->ask('Mapping name to save on:', 'APS');

                if (strlen($mappingNameValidated) > 2) {
                    break;
                }

                $io->error('Mapping name should contains more than two characters');
            }
            $input->setArgument('mappingName', $mappingNameValidated);
        }
    }

    public function generate(
        InputInterface $input,
        ConsoleStyle   $io,
        Generator      $generator
    ): void
    {
        $mappingName = $input->getArgument('mappingName');
        $entityName = $input->getArgument('name');
        $paths = $this->generator->getMappingsPath();

        $mappingFile = sprintf(
            '%s/%s.%s.orm.xml',
            $paths[$mappingName],
            $entityName,
            $entityName
        );

        $mappingAbstractFile = sprintf(
            '%s/%s.%sAbstract.orm.xml',
            $paths[$mappingName],
            $entityName,
            $entityName
        );

        $mappedPaths = new MappedPaths($mappingFile, $mappingAbstractFile);
        $mappingExist = $this->generator
            ->readXmlFile(
                $mappingAbstractFile
            );

        if (!$mappingExist) {
            $this->generator->generateMappingClass(
                $mappingName,
                $entityName
            );
        }

        if ($mappingExist) {
            $io->text([
                'Your entity already exists! So let\'s add some new fields!',
            ]);
        } else {
            $io->text([
                '',
                'Entity generated! Now let\'s add some fields!',
                'You can always add more fields later manually or by re-running this command.',
            ]);
        }

        $this->updateMappedClass($io, $mappingName, $entityName, $mappedPaths);
    }

    private function updateMappedClass(
        ConsoleStyle $io,
        string       $mappingName,
        string       $entityName,
        MappedPaths  $paths
    ): void
    {
        $fieldsManipulator = new GetFields(
            $paths,
            $this->generator
        );
        $currentFields = $fieldsManipulator->execute();
        $newFields = [];
        while (true) {
            $newField = $this->askForNextInput($io, $currentFields, $entityName);

            if (null === $newField) {
                break;
            }

            /** @var RequestedProperty[] $newFields */
            $newFields[] = $newField;
        }
        $xmlManipulator = new DumpXmlAttributes(
            generator: $this->generator,
            paths: $paths,
            mappingName: $mappingName
        );

        $xmlManipulator->execute($newFields);
    }

    public function configureDependencies(DependencyBuilder $dependencies, InputInterface $input = null)
    {
        ORMDependencyBuilder::buildDependencies($dependencies);
    }

    /**
     * @param RequestedProperty[] $fields
     */
    private function askForNextInput(
        ConsoleStyle $io,
        array        $fields,
        string       $entityName
    ): ?RequestedProperty
    {
        $io->writeln('');

        $question = new Question('⚒️   Do you want to set a field or an index? [index, field] (or write "apply" to finish)', 'field');
        $type = $io->askQuestion($question);

        switch ($type) {
            case 'index':
                return $this->askForNextIndex(
                    $io,
                    $fields
                );
            case 'field':
                return $this->askForNextField(
                    $io,
                    $fields,
                    $entityName
                );
            case 'apply':
                return null;
            default:
                $io->error('Unknown input value');
                return $this->askForNextInput(
                    $io,
                    $fields,
                    $entityName
                );
        }
    }

    /**
     * @param RequestedProperty[] $fields
     */
    private function askForNextIndex(
        ConsoleStyle $io,
        array        $fields
    ): ?RequestedProperty
    {
        $io->writeln('');

        $questionText = 'Index name (press <return> to stop adding fields)';
        /** @var RequestedProperty|null $data */
        $data = null;
        $indexName = $io->ask($questionText, null, function ($name) use ($fields) {
            // allow it to be empty
            if (!$name) {
                return $name;
            }

            $fieldNames = array_map(function (RequestedProperty $field) {
                return $field->getFieldName();
            }, $fields);

            if (in_array($name, $fieldNames)) {
                $data = array_filter($fields, function ($field) use ($name) {
                    return $field->getFieldName() === $name;
                });
            }

            return Validator::validateDoctrineFieldName($name, $this->doctrineHelper->getRegistry());
        });

        if (!$indexName) {
            return null;
        }

        $data = new RequestedProperty($indexName, 'index');
        $columns = $io->ask('Columns referenced for unique constraints, it should be separated by a comma ","');
        $data->setUniqueConstraintColumns($columns);

        return $data;
    }

    /**
     * @param RequestedProperty[] $fields
     */
    private function askForNextField(
        ConsoleStyle $io,
        array        $fields,
        string       $entityName
    ): ?RequestedProperty
    {
        $io->writeln('');
        $questionText = 'Property name (press <return> to stop adding fields)';

        /** @var RequestedProperty|null $data */
        $data = null;
        $fieldName = $io->ask($questionText, null, function ($name) use ($fields) {
            // allow it to be empty
            if (!$name) {
                return $name;
            }

            $fieldNames = array_map(function (RequestedProperty $field) {
                return $field->getFieldName();
            }, $fields);

            if (in_array($name, $fieldNames)) {
                $data = array_filter($fields, function ($field) use ($name) {
                    return $field->getFieldName() === $name;
                });
            }

            return Validator::validateDoctrineFieldName($name, $this->doctrineHelper->getRegistry());
        });

        if (!$fieldName) {
            return null;
        }

        $types = $this->getTypesMap();

        $allValidTypes = array_merge(
            array_keys($types),
            MappedEntityRelation::getValidRelationTypes(),
            ['relation']
        );

        $type = null;
        while (null === $type) {
            $question = new Question('Field type (enter <comment>?</comment> to see all types)');
            $question->setAutocompleterValues($allValidTypes);
            $type = $io->askQuestion($question);

            if ('?' === $type) {
                $this->printAvailableTypes($io);
                $io->writeln('');

                $type = null;
            } elseif (!\in_array($type, $allValidTypes)) {
                $this->printAvailableTypes($io);
                $io->error(sprintf('Invalid type "%s".', $type));
                $io->writeln('');

                $type = null;
            }
        }

        $data = $data ?? new RequestedProperty($fieldName, $type);
        if ('relation' === $type || \in_array($type, MappedEntityRelation::getValidRelationTypes())) {
            $entityRelation = $this->askRelationDetails($io, $entityName, $type, $fieldName);

            $onDeleteMethods = [
                'CASCADE',
                'NO ACTION'
            ];
            if ($entityRelation->isNullable()) {
                $onDeleteMethods[] = 'SET NULL';
            }

            $methods = implode(' | ', $onDeleteMethods);
            $question = sprintf('On delete method [%s]', $methods);
            $onDeleteResponse = $io->ask($question, 'NO ACTION');
            if (!in_array($onDeleteResponse, $onDeleteMethods)) {
                throw new \InvalidArgumentException('Method not valid');
            }
            $data->setOnDelete($onDeleteResponse);

            $fetchMethods = [
                'LAZY',
                'EAGER'
            ];
            $methods = implode(' | ', $fetchMethods);
            $question = sprintf('Fetch method [%s]', $methods);
            $fetchResponse = $io->ask($question, 'LAZY');
            if (!in_array($fetchResponse, $fetchMethods)) {
                throw new \InvalidArgumentException('Method not valid');
            }
            $data->setFetch($fetchResponse);

            $data->setRelation($entityRelation);
        } else {
            if ('string' === $type) {
                // default to 255, avoid the question
                $length = $io->ask('Field length', '255', [Validator::class, 'validateLength']);
                $data->setLength($length);

                $itHasComment = $io->confirm('Is this field be of type enum?', false);

                if ($itHasComment) {
                    $comment = $io->ask('Set enum strings separated by "|" ');
                    $data->setComment($comment);
                }
            } elseif ('decimal' === $type || 'float' === $type) {
                // 10 is the default value given in \Doctrine\DBAL\Schema\Column::$_precision
                $precision = $io->ask('Precision (total number of digits stored: 100.00 would be 5)', '10', [Validator::class, 'validatePrecision']);
                $data->setPrecision($precision);

                // 0 is the default value given in \Doctrine\DBAL\Schema\Column::$_scale
                $scale = $io->ask('Scale (number of decimals to store: 100.00 would be 2)', '0', [Validator::class, 'validateScale']);
                $data->setScale($scale);
            } elseif ('integer' === $type) {
                $unsigned = $io->confirm('Can this field be unsigned in the database?', true);
                $data->setUnsigned($unsigned);
            }

            if ($io->confirm('Can this field be null in the database (nullable)', false)) {
                $data->setNullable('true');
            }

            if ($io->confirm('Can this field has a default value?', false)) {
                $default = $io->ask('Default value');
                $data->setDefault($default);
            }
        }

        return $data;
    }

    private function getEntityNamespace(string $mappingName): string
    {
        $aliasMapPaths = $this->generator->getAliasMappingPaths();
        return $aliasMapPaths[$mappingName];
    }

    private function askRelationType(ConsoleStyle $io, string $entityClass, string $targetEntityClass)
    {
        $io->writeln('What type of relationship is this?');

        $originalEntityShort = Str::getShortClassName($entityClass);
        $targetEntityShort = Str::getShortClassName($targetEntityClass);
        $rows = [];
        $rows[] = [
            MappedEntityRelation::MANY_TO_ONE,
            sprintf("Each <comment>%s</comment> relates to (has) <info>one</info> <comment>%s</comment>.\nEach <comment>%s</comment> can relate to (can have) <info>many</info> <comment>%s</comment> objects.", $originalEntityShort, $targetEntityShort, $targetEntityShort, $originalEntityShort),
        ];
        $rows[] = ['', ''];
        $rows[] = [
            MappedEntityRelation::ONE_TO_MANY,
            sprintf("Each <comment>%s</comment> can relate to (can have) <info>many</info> <comment>%s</comment> objects.\nEach <comment>%s</comment> relates to (has) <info>one</info> <comment>%s</comment>.", $originalEntityShort, $targetEntityShort, $targetEntityShort, $originalEntityShort),
        ];
        $rows[] = ['', ''];
        $rows[] = [
            MappedEntityRelation::ONE_TO_ONE,
            sprintf("Each <comment>%s</comment> relates to (has) exactly <info>one</info> <comment>%s</comment>.\nEach <comment>%s</comment> also relates to (has) exactly <info>one</info> <comment>%s</comment>.", $originalEntityShort, $targetEntityShort, $targetEntityShort, $originalEntityShort),
        ];

        $io->table([
            'Type',
            'Description',
        ], $rows);

        $question = new Question(
            sprintf(
                'Relation type? [%s]',
                implode(', ', MappedEntityRelation::getValidRelationTypes())
            )
        );
        $question->setAutocompleterValues(MappedEntityRelation::getValidRelationTypes());
        $question->setValidator(function ($type) {
            if (!\in_array($type, MappedEntityRelation::getValidRelationTypes())) {
                throw new \InvalidArgumentException(sprintf('Invalid type: use one of: %s', implode(', ', MappedEntityRelation::getValidRelationTypes())));
            }

            return $type;
        });

        return $io->askQuestion($question);
    }

    private function askRelationDetails(
        ConsoleStyle $io,
        string       $entityName,
        string       $type,
        string       $newFieldName
    ): MappedEntityRelation
    {

        $targetEntityClass = null;
        while (null === $targetEntityClass) {
            $questionEntityName = $this->createEntityClassQuestion('What entity should this entity be related to?');
            $questionProjectName = $this->createEntityClassQuestion('Insert a mapping name defined in doctrine.orm.mappings config');

            $answeredEntityClass = $io->askQuestion($questionEntityName);
            $answeredProjectName = $io->askQuestion($questionProjectName);

            $mappedPath = $this->generator->getMappingsPath();
            $path = $mappedPath[$answeredProjectName];
            $file = sprintf('%s/%s.%s.orm.xml', $path, $answeredEntityClass, $answeredEntityClass);
            $fileExists = $this->generator->readXmlFile($file);

            if ($fileExists) {
                $targetEntityClass = $this->getEntityNamespace($answeredProjectName) . '\\' . $answeredEntityClass;
            } else {
                $io->error(sprintf('Unknown entity "%s"', $answeredEntityClass));
                continue;
            }
        }

        // help the user select the type
        if ('relation' === $type) {
            $type = $this->askRelationType($io, $entityName, $targetEntityClass);
        }

        $askFieldName = fn(string $targetClass, string $defaultValue) => $io->ask(
            sprintf('New field name inside %s', Str::getShortClassName($targetClass)),
            $defaultValue,
            function ($name) use ($targetClass) {
                // it's still *possible* to create duplicate properties - by
                // trying to generate the same property 2 times during the
                // same make:entity run. property_exists() only knows about
                // properties that *originally* existed on this class.
                if (property_exists($targetClass, $name)) {
                    throw new \InvalidArgumentException(sprintf('The "%s" class already has a "%s" property.', $targetClass, $name));
                }

                return Validator::validateDoctrineFieldName($name, $this->doctrineHelper->getRegistry());
            }
        );

        $askIsNullable = static fn(string $propertyName, string $targetClass) => $io->confirm(
            sprintf(
                'Is the <comment>%s</comment>.<comment>%s</comment> property allowed to be null (nullable)?',
                Str::getShortClassName($targetClass),
                $propertyName
            )
        );

        $askOrphanRemoval = static function (string $owningClass, string $inverseClass) use ($io) {
            $io->text([
                'Do you want to activate <comment>orphanRemoval</comment> on your relationship?',
                sprintf(
                    'A <comment>%s</comment> is "orphaned" when it is removed from its related <comment>%s</comment>.',
                    Str::getShortClassName($owningClass),
                    Str::getShortClassName($inverseClass)
                ),
                sprintf(
                    'e.g. <comment>$%s->remove%s($%s)</comment>',
                    Str::asLowerCamelCase(Str::getShortClassName($inverseClass)),
                    Str::asCamelCase(Str::getShortClassName($owningClass)),
                    Str::asLowerCamelCase(Str::getShortClassName($owningClass))
                ),
                '',
                sprintf(
                    'NOTE: If a <comment>%s</comment> may *change* from one <comment>%s</comment> to another, answer "no".',
                    Str::getShortClassName($owningClass),
                    Str::getShortClassName($inverseClass)
                ),
            ]);

            return $io->confirm(sprintf('Do you want to automatically delete orphaned <comment>%s</comment> objects (orphanRemoval)?', $owningClass), false);
        };

        $askInverseSide = function (MappedEntityRelation $relation) use ($io) {
            // recommend an inverse side, except for OneToOne, where it's inefficient
            $recommendMappingInverse = MappedEntityRelation::ONE_TO_ONE !== $relation->getType();

            $getterMethodName = 'get' . Str::asCamelCase(Str::getShortClassName($relation->getOwningClass()));
            if (MappedEntityRelation::ONE_TO_ONE !== $relation->getType()) {
                // pluralize!
                $getterMethodName = Str::singularCamelCaseToPluralCamelCase($getterMethodName);
            }
            $mapInverse = $io->confirm(
                sprintf(
                    'Do you want to add a new property to <comment>%s</comment> so that you can access/update <comment>%s</comment> objects from it - e.g. <comment>$%s->%s()</comment>?',
                    Str::getShortClassName($relation->getInverseClass()),
                    Str::getShortClassName($relation->getOwningClass()),
                    Str::asLowerCamelCase(Str::getShortClassName($relation->getInverseClass())),
                    $getterMethodName
                ),
                $recommendMappingInverse
            );
            $relation->setMapInverseRelation($mapInverse);
        };

        switch ($type) {
            case MappedEntityRelation::MANY_TO_ONE:
                $relation = new MappedEntityRelation(
                    MappedEntityRelation::MANY_TO_ONE,
                    $entityName,
                    $targetEntityClass
                );
                $relation->setOwningProperty($newFieldName);
                $relation->setInversedProjectName($answeredProjectName);

                $relation->setIsNullable(
                    $askIsNullable(
                        $relation->getOwningProperty(),
                        $relation->getOwningClass()
                    )
                );

                $askInverseSide($relation);
                if ($relation->getMapInverseRelation()) {
                    $io->comment(
                        sprintf(
                            'A new property will also be added to the <comment>%s</comment> class so that you can access the related <comment>%s</comment> objects from it.',
                            Str::getShortClassName($relation->getInverseClass()),
                            Str::getShortClassName($relation->getOwningClass())
                        )
                    );
                    $relation->setInverseProperty(
                        $askFieldName(
                            $relation->getInverseClass(),
                            Str::singularCamelCaseToPluralCamelCase(Str::getShortClassName($relation->getOwningClass()))
                        )
                    );

                    if (!$relation->isNullable()) {
                        $relation->setOrphanRemoval(
                            $askOrphanRemoval(
                                $relation->getOwningClass(),
                                $relation->getInverseClass()
                            )
                        );
                    }
                }
                break;
            case MappedEntityRelation::ONE_TO_MANY:
                // we *actually* create a ManyToOne, but populate it differently
                $relation = new MappedEntityRelation(
                    MappedEntityRelation::ONE_TO_MANY,
                    $targetEntityClass,
                    $entityName
                );
                $relation->setInverseProperty($newFieldName);
                $relation->setInversedProjectName($answeredProjectName);

                $io->comment(
                    sprintf(
                        'A new property will also be added to the <comment>%s</comment> class so that you can access and set the related <comment>%s</comment> object from it.',
                        Str::getShortClassName($relation->getOwningClass()),
                        Str::getShortClassName($relation->getInverseClass())
                    )
                );
                $relation->setOwningProperty(
                    $askFieldName(
                        $relation->getOwningClass(),
                        Str::asLowerCamelCase(Str::getShortClassName($relation->getInverseClass()))
                    )
                );

                $relation->setIsNullable(
                    $askIsNullable(
                        $relation->getOwningProperty(),
                        $relation->getOwningClass()
                    )
                );

                if (!$relation->isNullable()) {
                    $relation->setOrphanRemoval(
                        $askOrphanRemoval(
                            $relation->getOwningClass(),
                            $relation->getInverseClass()
                        )
                    );
                }

                break;
            case MappedEntityRelation::ONE_TO_ONE:
                $relation = new MappedEntityRelation(
                    MappedEntityRelation::ONE_TO_ONE,
                    $entityName,
                    $targetEntityClass
                );
                $relation->setOwningProperty($newFieldName);
                $relation->setIsNullable(
                    $askIsNullable(
                        $relation->getOwningProperty(),
                        $relation->getOwningClass()
                    )
                );

                $askInverseSide($relation);
                if ($relation->getMapInverseRelation()) {
                    $io->comment(
                        sprintf(
                            'A new property will also be added to the <comment>%s</comment> class so that you can access the related <comment>%s</comment> object from it.',
                            Str::getShortClassName($relation->getInverseClass()),
                            Str::getShortClassName($relation->getOwningClass())
                        )
                    );
                    $relation->setInversedProjectName($answeredProjectName);
                    $relation->setInverseProperty(
                        $askFieldName(
                            $relation->getInverseClass(),
                            Str::asLowerCamelCase(Str::getShortClassName($relation->getOwningClass()))
                        )
                    );
                }

                break;
            default:
                throw new \InvalidArgumentException('Invalid type: ' . $type);
        }

        return $relation;
    }

    private function createEntityClassQuestion(string $questionText): Question
    {
        $question = new Question($questionText);
        $question->setValidator([Validator::class, 'notBlank']);
        $question->setAutocompleterValues($this->doctrineHelper->getEntitiesForAutocomplete());

        return $question;
    }

    private function printAvailableTypes(ConsoleStyle $io): void
    {
        $allTypes = $this->getTypesMap();

        if ('Hyper' === getenv('TERM_PROGRAM')) {
            $wizard = 'wizard 🧙';
        } else {
            $wizard = '\\' === \DIRECTORY_SEPARATOR ? 'wizard' : 'wizard 🧙';
        }

        $typesTable = [
            'main' => [
                'string' => [],
                'text' => [],
                'boolean' => [],
                'integer' => ['smallint', 'bigint'],
                'float' => [],
            ],
            'relation' => [
                'relation' => 'a ' . $wizard . ' will help you build the relation',
                MappedEntityRelation::MANY_TO_ONE => [],
                MappedEntityRelation::ONE_TO_MANY => [],
                MappedEntityRelation::ONE_TO_ONE => [],
            ],
            'constraint' => [
                'unique_constraint' => [],
            ],
            'array_object' => [
                'array' => ['simple_array'],
                'json' => [],
                'object' => [],
                'binary' => [],
                'blob' => [],
            ],
            'date_time' => [
                'datetime' => ['datetime_immutable'],
                'datetimetz' => ['datetimetz_immutable'],
                'date' => ['date_immutable'],
                'time' => ['time_immutable'],
                'dateinterval' => [],
            ],
        ];

        $printSection = static function (array $sectionTypes) use ($io, &$allTypes) {
            foreach ($sectionTypes as $mainType => $subTypes) {
                unset($allTypes[$mainType]);
                $line = sprintf('  * <comment>%s</comment>', $mainType);

                if (\is_string($subTypes) && $subTypes) {
                    $line .= sprintf(' or %s', $subTypes);
                } elseif (\is_array($subTypes) && !empty($subTypes)) {
                    $line .= sprintf(
                        ' or %s',
                        implode(
                            ' or ',
                            array_map(
                                static fn($subType) => sprintf('<comment>%s</comment>', $subType),
                                $subTypes
                            )
                        )
                    );

                    foreach ($subTypes as $subType) {
                        unset($allTypes[$subType]);
                    }
                }

                $io->writeln($line);
            }

            $io->writeln('');
        };

        $io->writeln('<info>Main Types</info>');
        $printSection($typesTable['main']);

        $io->writeln('<info>Relationships/Associations</info>');
        $printSection($typesTable['relation']);

        $io->writeln('<info>Unique Constraints</info>');
        $printSection($typesTable['constraint']);

        $io->writeln('<info>Array/Object Types</info>');
        $printSection($typesTable['array_object']);

        $io->writeln('<info>Date/Time Types</info>');
        $printSection($typesTable['date_time']);

        $io->writeln('<info>Other Types</info>');
        // empty the values
        $allTypes = array_map(static fn() => [], $allTypes);
        $printSection($allTypes);
    }

    private function getTypesMap(): array
    {
        $types = Type::getTypesMap();

        return $types;
    }

    private function createMappedClassManipulator(
        MappedPaths $paths,
        string      $mappingName
    ): MappingManipulator
    {
        $manipulator = new MappingManipulator(
            generator: $this->generator,
            paths: $paths,
            mappingName: $mappingName
        );

        return $manipulator;
    }
}
