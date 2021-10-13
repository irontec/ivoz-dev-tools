<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine;

trait EntityTypeTrait
{
    private function getEntityTypeHint($doctrineType)
    {
        switch ($doctrineType) {
            case 'string':
            case 'text':
            case 'guid':
            case 'binary':
            case 'blob':
                return 'string';

            case 'array':
            case 'simple_array':
            case 'json':
            case 'json_array':
                return 'array';

            case 'boolean':
                return 'bool';

            case 'bigint':
            case 'integer':
            case 'smallint':
                return 'int';

            case 'decimal':
            case 'float':
                return 'float';

            case 'datetime':
            case 'datetimetz':
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
