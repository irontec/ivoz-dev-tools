<?= "<?php\n" ?>

namespace <?= $namespace ?>;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use <?= $entity_namespace ?>;
use <?= $interface_namespace ?>;

use Doctrine\Persistence\ManagerRegistry;

/**
* <?= $class_name ?>
*
* This class was generated by the Doctrine ORM. Add your own custom
* repository methods below.
*
* @template-extends ServiceEntityRepository<<?= $class_name ?>>
*/
class <?= $class_name ?> extends ServiceEntityRepository implements <?= $interface_name ?>

{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, <?= $entity_classname ?>::class);
    }
}