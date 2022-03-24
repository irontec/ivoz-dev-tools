<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

use Assert\Assertion;
use Ivoz\Core\Application\DataTransferObjectInterface;
use Ivoz\Core\Domain\Model\ChangelogTrait;
use Ivoz\Core\Domain\Model\EntityInterface;
use Ivoz\Core\Application\ForeignKeyTransformerInterface;
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

    abstract public function getId(): null|string|int;

    public function __toString(): string
    {
        return sprintf(
            "%s#%s",
            "<?= $parent_class_name ?>",
            (string) $this->getId()
        );
    }

    /**
     * @throws \Exception
     */
    protected function sanitizeValues(): void
    {
    }

    public static function createDto(string|int|null $id = null): <?= $parent_class_name ?>Dto
    {
        return new <?= $parent_class_name ?>Dto($id);
    }

    /**
     * @internal use EntityTools instead
     * @param null|<?= $parent_class_name ?>Interface $entity
     */
    public static function entityToDto(?EntityInterface $entity, int $depth = 0): ?<?= $parent_class_name ?>Dto
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

        $dto = $entity->toDto($depth - 1);

        return $dto;
    }

    /**
     * Factory method
     * @internal use EntityTools instead
     * @param <?= $parent_class_name ?>Dto $dto
     */
    public static function fromDto(
        DataTransferObjectInterface $dto,
        ForeignKeyTransformerInterface $fkTransformer
    ): static {
        Assertion::isInstanceOf($dto, <?= $parent_class_name ?>Dto::class);
        /*__updateFromDto_assertions*/

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
     */
    public function updateFromDto(
        DataTransferObjectInterface $dto,
        ForeignKeyTransformerInterface $fkTransformer
    ): static {
        Assertion::isInstanceOf($dto, <?= $parent_class_name ?>Dto::class);

        /*__updateFromDto_assertions*/
        /*__fromDto_embedded_constructor*/
        $this
            /*__updateFromDto_body*/;

        return $this;
    }

    /**
     * @internal use EntityTools instead
     */
    public function toDto(int $depth = 0): <?= $parent_class_name ?>Dto
    {
        return self::createDto()
            /*__toDto_body*/;
    }

    protected function __toArray(): array
    {
        return [
            /*__toArray_body*/
        ];
    }

    /*__class_methods*/
}
