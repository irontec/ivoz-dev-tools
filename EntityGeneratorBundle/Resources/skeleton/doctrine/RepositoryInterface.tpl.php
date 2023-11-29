<?= "<?php\n" ?>

namespace <?= $namespace ?>;

use Ivoz\Core\Domain\Service\Repository\RepositoryInterface;

/**
 * @extends RepositoryInterface<<?= $entity_classname  ?>Interface, <?= $entity_classname  ?>Dto>
 */
interface <?= $entity_classname  ?> extends RepositoryInterface
{
}
