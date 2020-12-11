<?= "<?php\n" ?>

namespace <?= $namespace ?>;

use Ivoz\Core\Application\DataTransferObjectInterface;
use Ivoz\Core\Application\Model\DtoNormalizer;
/*__dto_use_statements*/
/**
* <?= $class_name ."\n" ?>
* @codeCoverageIgnore
*/
abstract class <?= $class_name." implements DataTransferObjectInterface\n" ?>
{
    use DtoNormalizer;

    /*__dto_attributes*/

    public function __construct($id = null)
    {
        $this->setId($id);
    }

    /**
    * @inheritdoc
    */
    public static function getPropertyMap(string $context = '', string $role = null)
    {
        if ($context === self::CONTEXT_COLLECTION) {
            return ['id' => 'id'];
        }

        return [
            /*__getPropertyMap*/
        ];
    }

    /**
    * @return array
    */
    public function toArray($hideSensitiveData = false)
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
