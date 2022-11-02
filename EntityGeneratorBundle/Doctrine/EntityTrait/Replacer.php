<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\EntityTrait;

use IvozDevTools\EntityGeneratorBundle\Doctrine\CodeGeneratorUnitInterface;
use Symfony\Bundle\MakerBundle\Str;

class Replacer implements CodeGeneratorUnitInterface
{
    protected $propertyName;
    protected $type;
    protected $isNullable;
    protected $comments = [];
    protected $columnOptions = [];
    protected $classMetadata;
    protected $visibility;

    public function __construct(
        string $propertyName,
        string $type,
        bool $isNullable,
        $classMetadata,
        array $commentLines = [],
        array $columnOptions = [],
        string $visibility = 'protected'
    ) {
        $this->propertyName = $propertyName;
        $this->type = $type;
        $this->isNullable = $isNullable;
        $this->comments = $commentLines;
        $this->columnOptions = $columnOptions;
        $this->classMetadata = $classMetadata;
        $this->visibility = $visibility;
    }

    public function toString(string $nlLeftPad = ''): string
    {
        $camelCaseProperty = Str::asCamelCase($this->propertyName);
        $singularProperty = Str::pluralCamelCaseToSingular($camelCaseProperty);

        $hint = substr(
            $this->type,
            strrpos($this->type, '\\')
        );

        $methodName = 'replace' . $camelCaseProperty;
        $fqdnSegments = explode('\\', $this->classMetadata->name);
        $returnHint = $fqdnSegments[count($fqdnSegments) -2] . 'Interface';

        $response = [];
        $response[] = '/**';
        $response[] = ' * @param Collection<array-key, '. $hint . '> $' . $this->propertyName;
        $response[] = ' */';
        $response[] = sprintf(
            '%s function %s(%s %s): %s',
            $this->visibility,
            $methodName,
            'Collection',
            '$' . $this->propertyName,
            $returnHint
        );

        $association = $this->classMetadata->associationMappings[$this->propertyName];
        $body = $this->getMethodBody(
            'set' . Str::asCamelCase($association['mappedBy']),
            '$' . $this->propertyName,
            $this->propertyName,
            'add' . ucfirst($singularProperty),
            $hint
        );

        $response[] = '{';
        $response[] = $body;
        $response[] = '}';

        return implode(
            "\n". $nlLeftPad,
            $response
        );
    }

    private function getMethodBody(
        string $mappedBy,
        string $paramName,
        string $propertyName,
        string $adder,
        string $hint
    ): string
    {
        $template = <<<'TPL'
    foreach ([PARAM_NAME] as $entity) {
            $entity->[MAPPED_BY]($this);
        }

        $toStringCallable = fn(mixed $val): \Stringable|string => $val instanceof \Stringable ? $val : serialize($val);
        foreach ($this->[PROPERTY_NAME] as $key => $entity) {
            /**
             * @psalm-suppress MixedArgument
             */
            $currentValue = array_map(
                $toStringCallable,
                (function (): array {
                    return $this->__toArray(); /** @phpstan-ignore-line */
                })->call($entity)
            );

            $match = false;
            foreach ($[PROPERTY_NAME] as $newKey => $newEntity) {
                /**
                 * @psalm-suppress MixedArgument
                 */
                $newValue = array_map(
                    $toStringCallable,
                    (function (): array {
                        return $this->__toArray(); /** @phpstan-ignore-line */
                    })->call($newEntity)
                );

                $diff = array_diff_assoc(
                    $currentValue,
                    $newValue
                );
                unset($diff['id']);

                if (empty($diff)) {
                    unset($[PROPERTY_NAME][$newKey]);
                    $match = true;
                    break;
                }
            }

            if (!$match) {
                $this->[PROPERTY_NAME]->remove($key);
            }
        }

        foreach ($[PROPERTY_NAME] as $entity) {
            $this->[ADDER]($entity);
        }

        return $this;
TPL;

        return str_replace(
            ['[MAPPED_BY]', '[PARAM_NAME]', '[PROPERTY_NAME]', '[ADDER]', '[HINT]'],
            [$mappedBy, $paramName, $propertyName, $adder, $hint],
            $template
        );
    }
}
