<?php

declare(strict_types=1);

namespace AndyDefer\InvertedIndexSearch\Services;

use AndyDefer\AlgoKIT\Contracts\Algorithms\InvertedIndexInterface;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\InvertedIndexSearch\Contracts\Services\InvertedIndexExpressionEvaluatorInterface;
use AndyDefer\InvertedIndexSearch\Contracts\Services\InvertedIndexQueryBuilderInterface;
use AndyDefer\InvertedIndexSearch\Contracts\Services\InvertedIndexSearchServiceInterface;
use AndyDefer\InvertedIndexSearch\Enums\InvertedIndexOperator;

/**
 * Search service for Inverted Index with boolean operations.
 *
 * Provides convenient methods for common boolean searches and expression evaluation.
 *
 * @example
 * $search = new InvertedIndexSearchService($index, $evaluator);
 * $results = $search->and(['php', 'laravel']);
 * $results = $search->expression('(php OR python) AND laravel');
 * $results = $search->query()->where('php')->whereNot('python')->get();
 */
final class InvertedIndexSearchService implements InvertedIndexSearchServiceInterface
{
    private ?InvertedIndexQueryBuilderInterface $queryBuilder = null;

    public function __construct(
        private InvertedIndexInterface $index,
        private InvertedIndexExpressionEvaluatorInterface $evaluator
    ) {}

    public function and(array $tokens): StringTypedCollection
    {
        if (empty($tokens)) {
            return new StringTypedCollection;
        }

        return $this->applyOperator($tokens, InvertedIndexOperator::AND);
    }

    public function or(array $tokens): StringTypedCollection
    {
        if (empty($tokens)) {
            return new StringTypedCollection;
        }

        return $this->applyOperator($tokens, InvertedIndexOperator::OR);
    }

    public function not(string $include, string $exclude): StringTypedCollection
    {
        return $this->applyOperator([$include, $exclude], InvertedIndexOperator::NOT);
    }

    public function xor(string $term1, string $term2): StringTypedCollection
    {
        return $this->applyOperator([$term1, $term2], InvertedIndexOperator::XOR);
    }

    public function expression(string $expression): StringTypedCollection
    {
        return $this->evaluator->evaluate($expression);
    }

    public function andWithLimit(array $tokens, int $limit = 10): StringTypedCollection
    {
        if (empty($tokens)) {
            return new StringTypedCollection;
        }

        $results = $this->applyOperator($tokens, InvertedIndexOperator::AND);

        return StringTypedCollection::from(array_slice($results->toArray(), 0, $limit));
    }

    public function orWithLimit(array $tokens, int $limit = 10): StringTypedCollection
    {
        if (empty($tokens)) {
            return new StringTypedCollection;
        }

        $results = $this->applyOperator($tokens, InvertedIndexOperator::OR);

        return StringTypedCollection::from(array_slice($results->toArray(), 0, $limit));
    }

    public function query(): InvertedIndexQueryBuilderInterface
    {
        if ($this->queryBuilder === null) {
            $this->queryBuilder = new InvertedIndexQueryBuilder($this->index, $this->evaluator);
        }

        return $this->queryBuilder;
    }

    /**
     * Applies a boolean operator to a list of tokens.
     *
     * @param  array<string>  $tokens  Tokens to search
     * @param  InvertedIndexOperator  $operator  Boolean operator
     * @return StringTypedCollection Result of the operation
     *
     * @throws \InvalidArgumentException If operator requires exactly 2 tokens and count doesn't match
     */
    private function applyOperator(array $tokens, InvertedIndexOperator $operator): StringTypedCollection
    {
        if (empty($tokens)) {
            return new StringTypedCollection;
        }

        if ($operator->requiresTwoTokens() && count($tokens) !== 2) {
            throw new \InvalidArgumentException(
                sprintf('%s operator requires exactly 2 tokens, %d given', $operator->value, count($tokens))
            );
        }

        if ($operator === InvertedIndexOperator::NOT || $operator === InvertedIndexOperator::XOR) {
            $list1 = $this->index->search($tokens[0])->toArray();
            $list2 = $this->index->search($tokens[1])->toArray();

            if ($operator === InvertedIndexOperator::NOT) {
                return StringTypedCollection::from(array_values(array_diff($list1, $list2)));
            }

            return StringTypedCollection::from(array_values(array_merge(
                array_diff($list1, $list2),
                array_diff($list2, $list1)
            )));
        }

        $lists = [];
        foreach ($tokens as $token) {
            $lists[] = $this->index->search($token)->toArray();
        }

        if ($operator === InvertedIndexOperator::OR) {
            return StringTypedCollection::from(array_values(array_unique(array_merge(...$lists))));
        }

        return StringTypedCollection::from(array_values(array_intersect(...$lists)));
    }
}
