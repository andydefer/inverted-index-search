<?php

declare(strict_types=1);

namespace AndyDefer\InvertedIndexSearch\Contracts\Services;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

/**
 * Interface for evaluating boolean expressions in Inverted Index searches.
 *
 * Supports AND, OR, NOT, XOR operators with parentheses for grouping.
 */
interface InvertedIndexExpressionEvaluatorInterface
{
    /**
     * Evaluates a boolean expression and returns matching document IDs.
     *
     * @param  string  $expression  Boolean expression (e.g., 'php AND (laravel OR python)')
     * @return StringTypedCollection Collection of document IDs
     *
     * @throws \InvalidArgumentException If expression is invalid
     */
    public function evaluate(string $expression): StringTypedCollection;
}
