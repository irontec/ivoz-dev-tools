<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\EntityTrait;

use IvozDevTools\EntityGeneratorBundle\Doctrine\CodeGeneratorUnitInterface;
use Symfony\Bundle\MakerBundle\Str;

class Getter implements CodeGeneratorUnitInterface
{
    protected $propertyName;
    protected $returnType;
    protected $isReturnTypeNullable;
    protected $classMetadata;
    protected $comments = [];

    public function __construct(
        string $propertyName,
        $returnType,
        bool $isReturnTypeNullable,
        $classMetadata,
        array $commentLines = [],
    ) {
        $this->propertyName = $propertyName;
        $this->returnType = $returnType;
        $this->isReturnTypeNullable = $isReturnTypeNullable;
        $this->comments = $commentLines;
        $this->classMetadata = $classMetadata;
    }

    public function toString(string $nlLeftPad = ''): string
    {
        $mapping = $this->classMetadata->getAssociationMapping($this->propertyName);
        $onDeleteAction =
            $this
                ->classMetadata
                ->getInversedRelationCascadeAction($this->propertyName);
        $setNull = $onDeleteAction == 'SET NULL';

        $methodName = 'get' . Str::asCamelCase($this->propertyName);

        $response = ['/**'];
        foreach ($this->comments as $comment) {
            $response[] = ' * @' . $comment;
        }
        $response[] = ' */';
        $response[] = sprintf(
            'public function %s(Criteria $criteria = null): array',
            $methodName
        );
        $response[] = '{';
        $response = array_merge(
            $response,
            $setNull
                ? $this->getFilterForNullValue($mapping['mappedBy'] ?? '', $this->propertyName, $this->returnType)
                : $this->getBasicDataWithOutFilter($this->propertyName),
        );
        $response[] = '}';

        return implode(
            "\n" . $nlLeftPad,
            $response
        );
    }

    public function getBasicDataWithOutFilter(string $propertyName)
    {
        $response[] = '    if (!is_null($criteria)) {';
        $response[] = '        return $this->' . $propertyName . '->matching($criteria)->toArray();';
        $response[] = '    }';
        $response[] = '';
        $response[] = '    return $this->' . $propertyName . '->toArray();';

        return $response;
    }

    public function getFilterForNullValue(string $inversedBy, string $propertyName, string $returnType)
    {
        $template = <<<'TPL'
            /** @var ArrayCollection<int, [RETURN_TYPE]> $[PROPERTY_NAME] */
            $[PROPERTY_NAME] = $this->[PROPERTY_NAME]->matching(
                    Criteria::create()
                        ->where(
                            Criteria::expr()
                                ->neq('[INVERSED_BY]', null)
                        ),
                );
            
                if (!is_null($criteria)) {
                    return $[PROPERTY_NAME]->matching($criteria)->toArray();
                }
                
                return $[PROPERTY_NAME]->toArray();
        TPL;

        return [
            str_replace(
                ['[INVERSED_BY]', '[PROPERTY_NAME]', '[RETURN_TYPE]'],
                [$inversedBy, $propertyName, $returnType],
                $template,
            )
        ];
    }
}
