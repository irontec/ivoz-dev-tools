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
     */
    public function getChangeSet(): array
    {
        return parent::getChangeSet();
    }

    /**
     * Get id
     * @codeCoverageIgnore
     */
    public function getId(): int|string
    {
        return $this->id;
    }
}
