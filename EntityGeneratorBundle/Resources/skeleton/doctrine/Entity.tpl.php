<?= "<?php\n" ?>

namespace <?= $namespace ?>;

/**
 * <?= $class_name . "\n" ?>
 */
class <?= $class_name ?> extends <?= $class_name ?>Abstract implements <?= $class_name ?>Interface
{
    use <?= $class_name ?>Trait;

    /**
     * @codeCoverageIgnore
     * @return array
     */
    public function getChangeSet()
    {
        return parent::getChangeSet();
    }

    /**
     * Get id
     * @codeCoverageIgnore
     * @return integer
     */
    public function getId()/*: ?int*/
    {
        return $this->id;
    }
}
