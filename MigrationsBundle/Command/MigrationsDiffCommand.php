<?php

namespace IvozDevTools\MigrationsBundle\Command;

use Doctrine\Bundle\MigrationsBundle\Command\MigrationsDiffDoctrineCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrationsDiffCommand extends MigrationsDiffDoctrineCommand
{
    private static $_template =
        '<?php

namespace <namespace>;

use Ivoz\Core\Infrastructure\Persistence\Doctrine\LoggableMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version<version> extends LoggableMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
<up>
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
<down>
    }
}
';

    public function execute(InputInterface $input, OutputInterface $output)
    {
        return parent::execute($input, $output);
    }

    protected function getTemplate()
    {
        return self::$_template;
    }
}
