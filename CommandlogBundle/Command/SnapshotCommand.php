<?php

namespace CommandlogBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Ivoz\Core\Application\Event\CommandWasExecuted;
use Ivoz\Core\Domain\Event\EntityWasUpdated;
use Ivoz\Core\Domain\Model\LoggableEntityInterface;
use Ivoz\Provider\Domain\Model\Changelog\Changelog;
use Ivoz\Provider\Domain\Model\Commandlog\Commandlog;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Snapshot database state into one sigle commandlog
 * @codeCoverageIgnore
 * @author Mikel Madariaga <mikel@irontec.com>
 */
class SnapshotCommand extends Command
{
    const INSERT_BATCH_SIZE = 50;

    protected static $defaultName = 'provider:commandlog:snapshot';
    private $rootDir;
    private $em;
    private $connection;

    public function __construct(
        EntityManagerInterface $em,
        string $rootDir
    ) {
        $this->em = $em;
        $this->connection = $this->em->getConnection();
        $this->rootDir = $rootDir;

        (function () {
            $this->connect();
            $driverName = $this->_conn->getAttribute(\PDO::ATTR_DRIVER_NAME);
            if ($driverName === 'mysql') {
                $this->_conn->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
            }
        })->call($this->connection);

        parent::__construct();
    }

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->setName('provider:commandlog:snapshot')
            ->setDescription('Set current database state into one sigle command and dump it into a file');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this
            ->connection
            ->beginTransaction();

        try {
            $this->runCommand(
                $input,
                $output
            );

            $this
                ->connection
                ->commit();
        } catch (\Exception $e) {
            $this
                ->connection
                ->rollBack();

            throw $e;
        }
    }

    protected function runCommand(InputInterface $input, OutputInterface $output)
    {
        $targetTables = $this->getLoggableTables();

        $output->writeln(
            sprintf(
                "[%d tables to record]\n",
                count($targetTables)
            )
        );

        $command = $this->registerCommand();
        $tableCount = 1;

        foreach ($targetTables as $entity => $tableName) {
            $recordCount = $this->getRecordCount($tableName);
            $start_time = microtime(true);

            $output->writeln(
                sprintf(
                    "%s - About to record %s, %d rows to go",
                    str_pad(
                        $tableCount++,
                        3,
                        0,
                        STR_PAD_LEFT
                    ),
                    $tableName,
                    $recordCount
                )
            );

            $progressBar = new ProgressBar($output, $recordCount);

            $format = $recordCount > 0
                ? '%current%/%max% [%bar%] %percent:3s%% [%remaining:6s% left]'
                : '';

            $progressBar->setFormat($format);
            $progressBar->start();

            $entityMetadata = $this->em->getClassMetadata($entity);
            $entityIdentifiers = $entityMetadata->getIdentifierColumnNames();
            $recordGenerators = $this->getRecordGenerator($tableName);

            $rows = [];
            foreach ($recordGenerators as $row) {
                if (empty($row)) {
                    continue;
                }

                $rows[] = $row;

                if (count($rows) >= self::INSERT_BATCH_SIZE) {
                    $this->persistChanges($command, $entity, $entityIdentifiers[0], $rows);
                    $progressBar->advance(count($rows));
                    $rows = [];
                }
            }

            if (!empty($rows)) {
                $this->persistChanges($command, $entity, $entityIdentifiers[0], $rows);
            }

            $executionTime = (microtime(true) - $start_time);
            $progressBar->finish();
            $output->writeln(
                sprintf(
                    "\x0D\x1B[2K      recorded in %d sec\n",
                    $executionTime
                )
            );
        }

        $this->closeFile();

        $output->writeln(
            sprintf(
                'Command %s [%s] prepared successfully, check out snapshot.sql',
                $command->getId(),
                $command->getCreatedOn()->format('Y-m-d H:i:s')
            )
        );
    }

    private function registerCommand()
    {
        $event = new CommandWasExecuted(
            0,
            get_class($this),
            self::$defaultName,
            [],
            []
        );

        $command = Commandlog::fromEvent($event);
        $commandData = $command->toDto()->toArray();
        $commandQuery = $this->createQuery(
            'Commandlog',
            $commandData
        );
        $this->write(
            $commandQuery,
            false
        );

        return $command;
    }

    private function persistChanges(Commandlog $command, string $entity, string $idColumn, array $rows)
    {
        $changelogQuery = '';
        foreach ($rows as $key => $data) {
            $event = new EntityWasUpdated(
                $entity,
                $data[$idColumn],
                $data
            );

            $changelog = Changelog::fromEvent($event);
            $changelogData = $changelog->toDto()->toArray();
            unset($changelogData['command']);
            $changelogData['commandId'] = $command->getId();
            $changelogQuery .= $this->createQuery(
                'Changelog',
                $changelogData,
                !empty($changelogQuery)
            );

            if ($key % self::INSERT_BATCH_SIZE === 0) {
                $this->write(
                    $changelogQuery
                );
                $changelogQuery = '';
            }
        }

        if (!empty($changelogQuery)) {
            $this->write(
                $changelogQuery
            );
        }
    }

    private function write($query, $fileAppend = true)
    {
        if (!$fileAppend) {
            $this->initFile();
        }

        file_put_contents(
            $this->getTargetFile(),
            $query . ";\n\n",
            FILE_APPEND
        );
    }

    private function getTargetFile()
    {
        return $this->rootDir . '/../snapshot.sql';
    }

    private function initFile()
    {
        $content = '
            /*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
            /*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
            /*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
            /*!40101 SET NAMES utf8 */;
            /*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
            /*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
            /*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE=\'NO_AUTO_VALUE_ON_ZERO\' */;
            /*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
        ';

        file_put_contents(
            $this->getTargetFile(),
            $content . "\n\n",
            false
        );
    }

    private function closeFile()
    {
        $content = '
            /*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
            /*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
            /*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
            /*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
            /*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
            /*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
            /*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
        ';

        file_put_contents(
            $this->getTargetFile(),
            "\n\n" . $content,
            FILE_APPEND
        );
    }

    private function getRecordCount(string $tableName): int
    {
        $query = 'SELECT count(*) as recordNum FROM ' . $tableName;

        $statement = $this->connection->query($query);
        $results = $statement->fetch();

        return $results['recordNum'];
    }

    private function getRecordGenerator(string $tableName)
    {
        $query = 'SELECT * FROM ' . $tableName;
        $statement = $this->connection->query($query);
        while ($row = $statement->fetch()) {
            yield $row;
        }
    }

    /**
     * @param string $entity
     * @param array $commandData
     * @return string
     */
    private function createQuery(string $entity, array $commandData, $partial = false): string
    {
        $fields = array_keys($commandData);
        $values = array_map(
            function ($value) {
                if ($value instanceof \DateTime) {
                    $value = $value->format('Y-m-d H:i:s');
                } elseif (is_array($value)) {
                    $value = json_encode($value);
                }
                return $this->connection->quote($value);
            },
            array_values($commandData)
        );

        $values = '('
            . implode(',', $values)
            . ')';

        if ($partial) {
            return ',' . $values;
        }

        return
            'INSERT INTO `'
            . $entity
            .'`(`'
            . implode('`,`', $fields)
            . '`) VALUES '
            . $values;
    }

    private function getLoggableTables()
    {
        $metadata = $this->em->getConfiguration()->getMetadataDriverImpl();
        $entities = $metadata->getAllClassNames();

        $tables = array_reduce(
            $entities,
            function ($accumulator, $entity) {
                $entityMetadata = $this->em->getClassMetadata($entity);
                if ($entityMetadata->isMappedSuperclass) {
                    return $accumulator;
                }
                if ($entityMetadata->isEmbeddedClass) {
                    return $accumulator;
                }
                $entityInterfaces = class_implements($entity);
                if (!in_array(LoggableEntityInterface::class, $entityInterfaces)) {
                    return $accumulator;
                }

                $accumulator[$entity] = $entityMetadata->getTableName();
                return $accumulator;
            },
            []
        );

        return $tables;
    }
}
