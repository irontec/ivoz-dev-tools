<?= "<?php\n" ?>

namespace <?= $namespace ?>;

use Ivoz\Core\Domain\DataTransferObjectInterface;
use Ivoz\Core\Domain\Model\DtoNormalizer;
/*__dto_use_statements*/
/**
* <?= $class_name ."\n" ?>
* @codeCoverageIgnore
*/
abstract class <?= $class_name." implements DataTransferObjectInterface\n" ?>
{
    use DtoNormalizer;

    /*__dto_attributes*/

    public function __construct(?<?= $pk_type_hint ?> $id = null)
    {
        $this->setId($id);
    }

    /**
    * @inheritdoc
    */
    public static function getPropertyMap(string $context = '', string $role = null): array
    {
        if ($context === self::CONTEXT_COLLECTION) {
            return ['id' => 'id'];
        }

        return [
            /*__getPropertyMap*/
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $hideSensitiveData = false): array
    {
        $response = [
            /*__toArray_body*/
        ];

        if (!$hideSensitiveData) {
            return $response;
        }

        foreach ($this->sensitiveFields as $sensitiveField) {
            if (!array_key_exists($sensitiveField, $response)) {
                throw new \Exception($sensitiveField . ' field was not found');
            }
            $response[$sensitiveField] = '*****';
        }

        return $response;
    }

    /*__dto_methods*/
}
