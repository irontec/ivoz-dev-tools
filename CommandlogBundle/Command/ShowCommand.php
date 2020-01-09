<?php

namespace IvozDevTools\CommandlogBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Ivoz\Provider\Domain\Model\Commandlog\Commandlog;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Ivoz\Core\Domain\Model\LoggableEntityInterface;

/**
 * Show entity history
 * @codeCoverageIgnore
 * @author Mikel Madariaga <mikel@irontec.com>
 */
class ShowCommand extends Command
{
    protected static $defaultName = 'provider:commandlog:show';
    private $em;
    private $connection;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->connection = $this->em->getConnection();

        parent::__construct();
    }

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->setName('provider:commandlog:show')
            ->setDescription(
                'Show entity change history'
            )
            ->addArgument(
                'tableName',
                InputArgument::REQUIRED,
                'tableName'
            )
            ->addArgument(
                'id',
                InputArgument::REQUIRED,
                'id'
            )
            ->addOption(
                'detailed',
                null,
                InputOption::VALUE_NONE,
                'Show detailed command payload?'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $entity = $this->findEntityByTable(
            $input->getArgument('tableName')
        );
        $id = $input->getArgument('id');

        $output->writeln(
            '<comment>Rememeber that some changes like cascade deletes'
            . ' or manully executed queries will not appear</comment>'
        );
        $rows = $this->getEntityChangelog(
            $entity,
            $id,
            $output,
            $input->getOption('detailed')
        );

        $table = new Table($output);
        $table->setStyle('borderless');
        $table->setColumnWidths([60, 60]);
        $table
            ->setHeaders(['Command', 'Changeset'])
            ->setRows($rows);

        $table->render();
    }

    private function findEntityByTable(string $table)
    {
        $metadata = $this->em->getConfiguration()->getMetadataDriverImpl();
        $entities = $metadata->getAllClassNames();

        foreach ($entities as $entity) {
            $entityMetadata = $this->em->getClassMetadata($entity);
            if ($entityMetadata->isMappedSuperclass) {
                continue;
            }
            if ($entityMetadata->isEmbeddedClass) {
                continue;
            }
            $entityInterfaces = class_implements($entity);
            if (!in_array(LoggableEntityInterface::class, $entityInterfaces)) {
                continue;
            }

            if ($entityMetadata->getTableName() !== $table) {
                continue;
            }

            return $entity;
        }

        throw new \Exception('Table does not exist or its not loggable');
    }

    private function getEntityChangelog(string $entity, string $id, OutputInterface $output, bool $detailed)
    {
        $queryStr =
            'select id, commandId, data, createdOn from Changelog where entity = :entity'
            . ' and (entityId = \':entityId\' OR (entityId = 0 and data REGEXP \':data\'))'
            . ' order by createdOn asc, microtime asc';

        $replacements = [
            ':entityId' => $id,
            ':entity' => $this->connection->quote($entity),
            ':data' => '.*\,"arguments":\\[.*[\[,",\,]'. $id .'[\],",\,].*'
        ];

        $query = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $queryStr
        );

        $output->writeln(
            ' > <info>' . $query . "</info>\n"
        );

        $rows = $this
            ->connection
            ->query($query)
            ->fetchAll();

        $rows = array_map(
            function ($row) {
                if (!empty($row['data'])) {
                    $row['data'] = json_encode(
                        json_decode($row['data']),
                        JSON_PRETTY_PRINT
                    );
                } else {
                    $row['data'] = '[REMOVED]';
                }

                return $row;
            },
            $rows
        );

        $rows = array_reduce(
            $rows,
            function ($acc, $row) {

                $commandId = $row['commandId'];
                if (!isset($acc[$commandId])) {
                    $acc[$commandId] = [];
                }

                unset($row['commandId']);
                $acc[$commandId][] =
                    $row['createdOn']
                    . "\n"
                    . $row['id']
                    . "\n"
                    . $row['data'];

                return $acc;
            },
            []
        );

        $command = null;
        $commandStr = '';
        $response = [];
        foreach ($rows as $commandId => $changes) {
            if (is_null($command) || $command['id'] !== $commandId) {
                if (!is_null($command)) {
                    $response[] = new TableSeparator();
                }

                $command = $this->getCommand($commandId, $detailed);

                $commandStr =
                    $command['createdOn']
                    . "\n"
                    . $command['id']
                    . "\n"
                    . $command['class']
                    . "\n"
                    . $command['method']
                    . "\n"
                    . $command['arguments']
                    . "\n"
                    . $command['agent'];
            }

            foreach ($changes as $key => $change) {
                $response[] = [
                    $key === 0 ? $commandStr : '',
                    $change
                ];
            }
        }

        return $response;
    }

    private function getCommand($id, bool $detailed)
    {
        $query = sprintf(
            'select id, class, method, arguments, createdOn, agent from Commandlog where id = \'%s\'',
            $id
        );

        $command = $this
            ->connection
            ->query($query)
            ->fetch();

        $command['arguments'] = json_encode(
            json_decode($command['arguments']),
            JSON_PRETTY_PRINT
        );

        if (!$detailed) {
            $command['arguments'] = '';
        }

        $command['agent'] = json_encode(
            json_decode($command['agent']),
            JSON_PRETTY_PRINT
        );

        return $command;
    }
}
