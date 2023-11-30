<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;

trait EntityTypeTrait
{
    private function getEntityTypeHintByMetadata(ClassMetadata $metadata)
    {
        $identifier = $metadata->getIdentifier();
        if (empty($identifier)) {
            return null;
        }

        $pkField = $metadata->getFieldMapping(
            current($identifier)
        );

        return $this->getEntityTypeHint(
            $pkField["type"]
        );
    }

    private function getEntityTypeHint($doctrineType)
    {
        switch ($doctrineType) {
            case 'bigint':
            case 'string':
            case 'text':
            case 'guid':
                return 'string';
            case 'binary':
            case 'blob':
                return 'resource';
            case 'array':
            case 'simple_array':
            case 'json':
            case 'json_array':
                return 'array';

            case 'boolean':
                return 'bool';

            case 'integer':
            case 'smallint':
                return 'int';

            case 'decimal':
            case 'float':
                return 'float';

            case 'datetime':
            case 'datetimetz':
                return '\\'.\DateTime::class;
            case 'date':
            case 'time':
                return '\\'.\DateTimeInterface::class;
            case 'datetime_immutable':
            case 'datetimetz_immutable':
            case 'date_immutable':
            case 'time_immutable':
                return '\\'.\DateTimeImmutable::class;

            case 'dateinterval':
                return '\\'.\DateInterval::class;

            case 'object':
                return 'object';

            default:
                return null;
        }
    }
}
