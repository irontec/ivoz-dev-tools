<?= "<?php\n" ?>
declare(strict_types = 1);

namespace <?= $namespace ?>;

use Assert\Assertion;
use Ivoz\Core\Application\DataTransferObjectInterface;
use Ivoz\Core\Domain\Model\ChangelogTrait;
use Ivoz\Core\Domain\Model\EntityInterface;
use \Ivoz\Core\Application\ForeignKeyTransformerInterface;
/*__class_use_statements*/
/**
* <?= $class_name ."\n" ?>
* @codeCoverageIgnore
*/
abstract class <?= $class_name."\n" ?>
{
    use ChangelogTrait;

    /*__class_attributes*/

    /**
     * Constructor
     */
    protected function __construct(
        /*__construct_args*/
    ) {
        /*__construct_body*/
    }

    abstract public function getId();

    public function __toString()
    {
        return sprintf(
            "%s#%s",
            "<?= $parent_class_name ?>",
            $this->getId()
        );
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function sanitizeValues()
    {
    }

    /**
     * @param null $id
     * @return <?= $parent_class_name ?>Dto
     */
    public static function createDto($id = null)
    {
        return new <?= $parent_class_name ?>Dto($id);
    }

    /**
     * @internal use EntityTools instead
     * @param <?= $parent_class_name ?>Interface|null $entity
     * @param int $depth
     * @return <?= $parent_class_name ?>Dto|null
     */
    public static function entityToDto(EntityInterface $entity = null, $depth = 0)
    {
        if (!$entity) {
            return null;
        }

        Assertion::isInstanceOf($entity, <?= $parent_class_name ?>Interface::class);

        if ($depth < 1) {
            return static::createDto($entity->getId());
        }

        if ($entity instanceof \Doctrine\ORM\Proxy\Proxy && !$entity->__isInitialized()) {
            return static::createDto($entity->getId());
        }

        /** @var <?= $parent_class_name ?>Dto $dto */
        $dto = $entity->toDto($depth-1);

        return $dto;
    }

    /**
     * Factory method
     * @internal use EntityTools instead
     * @param <?= $parent_class_name ?>Dto $dto
     * @return self
     */
    public static function fromDto(
        DataTransferObjectInterface $dto,
        ForeignKeyTransformerInterface $fkTransformer
    ) {
        Assertion::isInstanceOf($dto, <?= $parent_class_name ?>Dto::class);
        /*__fromDto_embedded_constructor*/
        $self = new static(
            /*__fromDto_instance_constructor*/
        );

        /*__fromDto_setters*/

        $self->initChangelog();

        return $self;
    }

    /**
     * @internal use EntityTools instead
     * @param <?= $parent_class_name ?>Dto $dto
     * @return self
     */
    public function updateFromDto(
        DataTransferObjectInterface $dto,
        ForeignKeyTransformerInterface $fkTransformer
    ) {
        Assertion::isInstanceOf($dto, <?= $parent_class_name ?>Dto::class);
        /*__fromDto_embedded_constructor*/
        $this
            /*__updateFromDto_body*/;

        return $this;
    }

    /**
     * @internal use EntityTools instead
     * @param int $depth
     * @return <?= $parent_class_name ?>Dto
     */
    public function toDto($depth = 0)
    {
        return self::createDto()
            /*__toDto_body*/;
    }

    /**
     * @return array
     */
    protected function __toArray()
    {
        return [
            /*__toArray_body*/
        ];
    }

    /*__class_methods*/
}
