<?php

declare(strict_types=1);

namespace AndyDefer\InvertedIndexSearch\Contracts\Services;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

/**
 * Interface for Inverted Index search service with boolean operations.
 *
 * Provides convenient methods for common boolean searches, expression evaluation,
 * and query building for complex conditions.
 *
 * @example
 * $search = new InvertedIndexSearchService($index, $evaluator);
 * $results = $search->and(['php', 'laravel']);
 * $results = $search->expression('(php OR python) AND laravel');
 * $results = $search->query()->where('php')->whereNot('python')->get();
 */
interface InvertedIndexSearchServiceInterface
{
    /**
     * Searches documents containing ALL terms (AND).
     *
     * @param  array<string>  $tokens  Terms to search
     * @return StringTypedCollection Document IDs matching all terms
     */
    public function and(array $tokens): StringTypedCollection;

    /**
     * Searches documents containing ANY term (OR).
     *
     * @param  array<string>  $tokens  Terms to search
     * @return StringTypedCollection Document IDs matching any term
     */
    public function or(array $tokens): StringTypedCollection;

    /**
     * Searches documents containing the first term but NOT the second (NOT).
     *
     * @param  string  $include  Term to include
     * @param  string  $exclude  Term to exclude
     * @return StringTypedCollection Document IDs containing include but not exclude
     */
    public function not(string $include, string $exclude): StringTypedCollection;

    /**
     * Searches documents containing exactly ONE of the two terms (XOR).
     *
     * @param  string  $term1  First term
     * @param  string  $term2  Second term
     * @return StringTypedCollection Document IDs containing exactly one of the terms
     */
    public function xor(string $term1, string $term2): StringTypedCollection;

    /**
     * Evaluates a complex boolean expression.
     *
     * Supports: AND, OR, NOT, XOR and parentheses.
     *
     * @param  string  $expression  Boolean expression
     * @return StringTypedCollection Document IDs matching the expression
     */
    public function expression(string $expression): StringTypedCollection;

    /**
     * Searches documents containing ALL terms with limit (AND).
     *
     * @param  array<string>  $tokens  Terms to search
     * @param  int  $limit  Maximum number of results
     * @return StringTypedCollection Limited document IDs
     */
    public function andWithLimit(array $tokens, int $limit = 10): StringTypedCollection;

    /**
     * Searches documents containing ANY term with limit (OR).
     *
     * @param  array<string>  $tokens  Terms to search
     * @param  int  $limit  Maximum number of results
     * @return StringTypedCollection Limited document IDs
     */
    public function orWithLimit(array $tokens, int $limit = 10): StringTypedCollection;

    /**
     * Returns a query builder for complex conditions.
     */
    public function query(): InvertedIndexQueryBuilderInterface;
}
