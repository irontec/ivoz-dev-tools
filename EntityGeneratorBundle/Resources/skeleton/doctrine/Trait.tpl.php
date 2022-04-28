<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

use Ivoz\Core\Application\DataTransferObjectInterface;
use Ivoz\Core\Application\ForeignKeyTransformerInterface;
/*__class_use_statements*/

/**
* @codeCoverageIgnore
*/
trait <?= $class_name."\n" ?>
{
    /*__class_attributes*/

    /**
     * Constructor
     */
    protected function __construct()
    {
        parent::__construct(...func_get_args());
        /*__construct_body*/
    }

    abstract protected function sanitizeValues(): void;

    /**
     * Factory method
     * @internal use EntityTools instead
     * @param <?= $parent_class_name . "Dto" ?> $dto
     */
    public static function fromDto(
        DataTransferObjectInterface $dto,
        ForeignKeyTransformerInterface $fkTransformer
    ): static {
        /** @var static $self */
        $self = parent::fromDto($dto, $fkTransformer);
        /*__fromDto_setters*/

        $self->sanitizeValues();
        if ($dto->getId()) {
            $self->id = $dto->getId();
            $self->initChangelog();
        }

        return $self;
    }

    /**
     * @internal use EntityTools instead
     * @param <?= $parent_class_name . "Dto" ?> $dto
     */
    public function updateFromDto(
        DataTransferObjectInterface $dto,
        ForeignKeyTransformerInterface $fkTransformer
    ): static {
        parent::updateFromDto($dto, $fkTransformer);
        /*__updateFromDto_body*/
        $this->sanitizeValues();

        return $this;
    }

    /**
     * @internal use EntityTools instead
     */
    public function toDto(int $depth = 0): <?= $parent_class_name . "Dto\n" ?>
    {
        $dto = parent::toDto($depth);
        return $dto
            ->setId($this->getId());
    }

    /**
     * @return array<string, mixed>
     */
    protected function __toArray(): array
    {
        return parent::__toArray() + [
            'id' => self::getId()
        ];
    }

    /*__class_methods*/
}
