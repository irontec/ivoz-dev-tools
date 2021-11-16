<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\EntityInterface;

use IvozDevTools\EntityGeneratorBundle\Doctrine\CodeGeneratorUnitInterface;

class Method implements CodeGeneratorUnitInterface
{
    protected $static;
    protected $method;
    protected $parameters = [];
    protected $returnType;
    protected $isReturnTypeNullable;
    protected $comments = [];

    public function __construct(
        bool $static,
        string $method,
        array $parameters,
        string $returnType,
        bool $isReturnTypeNullable,
        array $comments = []
    ) {
        $this->static = $static;
        $this->method = $method;
        $this->parameters = $parameters;
        $this->returnType = $returnType;
        $this->isReturnTypeNullable = $isReturnTypeNullable;
        $this->comments = $comments;
    }

    public function getName()
    {
        return $this->method;
    }

    public function toString(string $nlLeftPad = ''): string
    {
        foreach ($this->comments as $comment) {
            $response[] = preg_replace('/^\s+/', ' ', $comment);
        }

        $returnType = '';
        if (! empty($this->returnType)) {

            $makeItNullable =
                $this->returnType !== 'mixed'
                && $this->isReturnTypeNullable
                && !str_contains($this->returnType, '?')
                && !str_contains($this->returnType, '|null');

            if ($makeItNullable) {
                $returnType = str_contains($this->returnType, '|')
                    ? ': null|' . $this->returnType
                    : ': ?' . $this->returnType;
            } else {
                $returnType = ': ' . $this->returnType;
            }
        }

        $methodName = $this->method;

        $static = $this->static
            ? ' static'
            : '';

        $response[] = sprintf(
            'public%s function %s(%s)%s;',
            $static,
            $methodName,
            implode(', ', $this->parameters),
            $returnType
        );

        return implode(
            "\n" . $nlLeftPad,
            $response
        );
    }
}
