<?= "<?php\n" ?>

namespace <?= $namespace ?>;
use Doctrine\Common\Collections\Selectable;
use Doctrine\Persistence\ObjectRepository;

interface <?= $class_name  ?> extends ObjectRepository, Selectable
{

}