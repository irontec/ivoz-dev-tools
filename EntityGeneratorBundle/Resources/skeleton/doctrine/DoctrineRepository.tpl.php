<?= "<?php\n" ?>

namespace <?= $namespace ?>;

use Doctrine\Persistence\ManagerRegistry;
use Ivoz\Core\Domain\Service\EntityPersisterInterface;
use Ivoz\Core\Infrastructure\Persistence\Doctrine\Repository\DoctrineRepository;
use <?= $entity_namespace ?>;
use <?= $interface_namespace ?>;
use <?= $entity_namespace ?>Interface;
use <?= $entity_namespace ?>Dto;

/**
 * <?= $class_name . "\n" ?>
 *
 * This class was generated by ivoz:make:repositories command.
 * Add your own custom repository methods below.
 *
 * @extends DoctrineRepository<<?= $entity_classname ?>Interface, <?= $entity_classname ?>Dto>
 */
class <?= $class_name ?> extends DoctrineRepository implements <?= $interface_name ?>

{
    public function __construct(
        ManagerRegistry $registry,
        EntityPersisterInterface $entityPersisterInterface,
    ) {
        parent::__construct(
            $registry,
            <?= $entity_classname ?>::class,
            $entityPersisterInterface
        );
    }
}