<?php

declare(strict_types=1);

namespace AndyDefer\InvertedIndexSearch\Contracts\Services;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

/**
 * Interface for building complex Inverted Index queries.
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
interface InvertedIndexQueryBuilderInterface
{
    /**
     * Adds a WHERE condition (AND).
     *
     * @param  string  $token  Token to include
     */
    public function where(string $token): self;

    /**
     * Adds an OR WHERE condition.
     *
     * @param  string  $token  Token to include
     */
    public function orWhere(string $token): self;

    /**
     * Adds a WHERE NOT condition.
     *
     * @param  string  $token  Token to exclude
     */
    public function whereNot(string $token): self;

    /**
     * Adds a nested group of conditions with AND operator.
     *
     * @param  callable(InvertedIndexQueryBuilderInterface): void  $callback  Function that receives a builder instance
     */
    public function whereGroup(callable $callback): self;

    /**
     * Adds a nested group of conditions with OR operator.
     *
     * @param  callable(InvertedIndexQueryBuilderInterface): void  $callback  Function that receives a builder instance
     */
    public function orWhereGroup(callable $callback): self;

    /**
     * Executes the query and returns results.
     *
     * @return StringTypedCollection Document IDs matching the query
     */
    public function get(): StringTypedCollection;

    /**
     * Resets the query builder to empty state.
     */
    public function reset(): self;

    /**
     * Returns the current conditions as an expression string.
     *
     * @return string The expression string
     */
    public function toExpression(): string;
}
