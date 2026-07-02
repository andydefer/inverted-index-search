<?php

declare(strict_types=1);

namespace AndyDefer\InvertedIndexSearch\Services;

use AndyDefer\AlgoKIT\Contracts\Algorithms\InvertedIndexInterface;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\InvertedIndexSearch\Contracts\Services\InvertedIndexExpressionEvaluatorInterface;
use AndyDefer\InvertedIndexSearch\Contracts\Services\InvertedIndexQueryBuilderInterface;

/**
 * Query builder for Inverted Index with complex boolean conditions.
 *
 * Provides a fluent interface for building boolean expressions
 * without manual string parsing.
 *
 * @example
 * $results = $queryBuilder
 *     ->where('php')
 *     ->whereGroup(function($q) {
 *         $q->where('laravel')
 *           ->orWhere('vuejs');
 *     })
 *     ->whereNot('python')
 *     ->get();
 */
final class InvertedIndexQueryBuilder implements InvertedIndexQueryBuilderInterface
{
    /** @var array<int, array{type: string, operator: string, token?: string, callback?: callable}> */
    private array $conditions = [];

    public function __construct(
        private InvertedIndexInterface $index,
        private InvertedIndexExpressionEvaluatorInterface $evaluator
    ) {}

    public function where(string $token): self
    {
        $this->conditions[] = ['type' => 'token', 'operator' => 'AND', 'token' => $token];

        return $this;
    }

    public function orWhere(string $token): self
    {
        $this->conditions[] = ['type' => 'token', 'operator' => 'OR', 'token' => $token];

        return $this;
    }

    public function whereNot(string $token): self
    {
        $this->conditions[] = ['type' => 'not', 'operator' => 'AND', 'token' => $token];

        return $this;
    }

    public function whereGroup(callable $callback): self
    {
        $this->conditions[] = ['type' => 'group', 'operator' => 'AND', 'callback' => $callback];

        return $this;
    }

    public function orWhereGroup(callable $callback): self
    {
        $this->conditions[] = ['type' => 'group', 'operator' => 'OR', 'callback' => $callback];

        return $this;
    }

    public function get(): StringTypedCollection
    {
        if (empty($this->conditions)) {
            return new StringTypedCollection;
        }

        $expression = $this->toExpression();

        $result = $this->evaluator->evaluate($expression);

        return $result;
    }

    public function reset(): self
    {
        $this->conditions = [];

        return $this;
    }

    public function toExpression(): string
    {
        if (empty($this->conditions)) {
            return '';
        }

        return $this->buildExpression($this->conditions, false);
    }

    /**
     * Builds a string expression from conditions array.
     *
     * @param  array<int, array>  $conditions  Conditions to build
     * @param  bool  $wrap  Whether to wrap in parentheses
     * @return string The expression string
     */
    private function buildExpression(array $conditions, bool $wrap = false): string
    {
        $parts = [];

        foreach ($conditions as $index => $condition) {
            if ($index > 0) {
                $parts[] = $condition['operator'] ?? 'AND';
            }

            if ($condition['type'] === 'group') {
                $subBuilder = new self($this->index, $this->evaluator);
                $callback = $condition['callback'];
                $callback($subBuilder);
                $subExpression = $subBuilder->toExpression();
                $parts[] = $subExpression !== '' ? '('.$subExpression.')' : '';
            } elseif ($condition['type'] === 'not') {
                $parts[] = sprintf('(NOT %s)', $condition['token']);
            } else {
                $parts[] = $condition['token'];
            }
        }

        $expression = implode(' ', $parts);

        // Nettoyer les espaces doubles et les parenthèses vides
        $expression = preg_replace('/\s+/', ' ', $expression);
        $expression = trim($expression);

        $result = $wrap ? '('.$expression.')' : $expression;

        return $result;
    }
}
