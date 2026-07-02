<?php

declare(strict_types=1);

namespace AndyDefer\InvertedIndexSearch\Services;

use AndyDefer\AlgoKIT\Contracts\Algorithms\InvertedIndexInterface;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\InvertedIndexSearch\Contracts\Services\InvertedIndexExpressionEvaluatorInterface;
use AndyDefer\InvertedIndexSearch\Enums\InvertedIndexOperator;

/**
 * Evaluates boolean expressions for Inverted Index searches.
 *
 * Supports AND, OR, NOT, XOR operators with parentheses for grouping.
 *
 * @example
 * $evaluator = new InvertedIndexExpressionEvaluator($index);
 * $results = $evaluator->evaluate('(php OR python) AND laravel');
 */
final class InvertedIndexExpressionEvaluator implements InvertedIndexExpressionEvaluatorInterface
{
    private const PRECEDENCE = [
        'NOT' => 4,
        'XOR' => 3,
        'AND' => 2,
        'OR' => 1,
    ];

    public function __construct(
        private InvertedIndexInterface $index
    ) {}

    public function evaluate(string $expression): StringTypedCollection
    {
        $expression = trim($expression);
        if (empty($expression)) {
            return new StringTypedCollection;
        }

        $tokens = $this->tokenize($expression);
        $rpn = $this->shuntingYard($tokens);

        return $this->evaluateRPN($rpn);
    }

    private function tokenize(string $expression): array
    {
        $tokens = [];
        $current = '';
        $length = strlen($expression);

        for ($i = 0; $i < $length; $i++) {
            $char = $expression[$i];

            if ($char === ' ') {
                if (! empty($current)) {
                    $tokens[] = $this->normalizeToken($current);
                    $current = '';
                }

                continue;
            }

            if ($char === '(' || $char === ')') {
                if (! empty($current)) {
                    $tokens[] = $this->normalizeToken($current);
                    $current = '';
                }
                $tokens[] = $char;

                continue;
            }

            $current .= $char;

            $operatorValues = array_map(fn ($op) => $op->value, InvertedIndexOperator::cases());
            if (in_array($current, $operatorValues, true)) {
                $tokens[] = $this->normalizeToken($current);
                $current = '';
            }
        }

        if (! empty($current)) {
            $tokens[] = $this->normalizeToken($current);
        }

        $operatorValues = array_map(fn ($op) => $op->value, InvertedIndexOperator::cases());
        foreach ($tokens as $token) {
            if (! in_array($token, ['(', ')', ...$operatorValues], true) && ! preg_match('/^[a-zA-Z0-9_-]+$/', $token)) {
                throw new \InvalidArgumentException(sprintf('Invalid token: "%s"', $token));
            }
        }

        return $this->handleNotOperator($tokens);
    }

    private function normalizeToken(string $token): string
    {
        $operatorValues = array_map(fn ($op) => $op->value, InvertedIndexOperator::cases());
        if (in_array($token, $operatorValues, true)) {
            return $token;
        }

        return $token;
    }

    /**
     * Transforme NOT en opérateur unaire avec un token factice.
     * Exemple: "php AND NOT python" → "php", "AND", "NOT", "python"
     * Devient: "php", "AND", "NOT", "python" (NOT est unaire)
     */
    private function handleNotOperator(array $tokens): array
    {
        $result = [];
        $i = 0;
        $count = count($tokens);

        while ($i < $count) {
            $token = $tokens[$i];

            // Si c'est NOT, on le garde comme opérateur unaire
            if ($token === 'NOT') {
                $result[] = 'NOT';
                $i++;

                continue;
            }

            // Si le token précédent est NOT, on garde le token
            if ($i > 0 && $tokens[$i - 1] === 'NOT') {
                $result[] = $token;
                $i++;

                continue;
            }

            // Vérifier si le token courant est suivi de NOT
            // Exemple: "php AND NOT python" → on voit "php", puis "AND", puis "NOT"
            if ($i + 1 < $count && $tokens[$i + 1] === 'NOT') {
                $result[] = $token;
                $i++;

                continue;
            }

            // Si c'est le token avant NOT
            if ($i + 1 < $count && $tokens[$i + 1] === 'NOT') {
                $result[] = $token;
                $i++;

                continue;
            }

            $result[] = $token;
            $i++;
        }

        return $result;
    }

    private function shuntingYard(array $tokens): array
    {
        $output = [];
        $operators = [];

        foreach ($tokens as $token) {
            if ($token === '(') {
                $operators[] = $token;
            } elseif ($token === ')') {
                while (! empty($operators) && end($operators) !== '(') {
                    $output[] = array_pop($operators);
                }
                if (empty($operators)) {
                    throw new \InvalidArgumentException('Mismatched parentheses');
                }
                array_pop($operators);
            } elseif (isset(self::PRECEDENCE[$token])) {
                while (
                    ! empty($operators) &&
                    end($operators) !== '(' &&
                    self::PRECEDENCE[end($operators)] >= self::PRECEDENCE[$token]
                ) {
                    $output[] = array_pop($operators);
                }
                $operators[] = $token;
            } else {
                $output[] = $token;
            }
        }

        while (! empty($operators)) {
            $op = array_pop($operators);
            if ($op === '(') {
                throw new \InvalidArgumentException('Mismatched parentheses');
            }
            $output[] = $op;
        }

        return $output;
    }

    private function evaluateRPN(array $rpn): StringTypedCollection
    {
        $stack = [];

        foreach ($rpn as $token) {
            $operatorValues = array_map(fn ($op) => $op->value, InvertedIndexOperator::cases());
            if (in_array($token, $operatorValues, true)) {
                if ($token === InvertedIndexOperator::NOT->value) {
                    // NOT est unaire - prend 1 opérande
                    if (count($stack) < 1) {
                        throw new \InvalidArgumentException('NOT operator requires at least 1 operand');
                    }
                    $right = array_pop($stack);

                    // Appliquer NOT : tous les documents sauf ceux qui contiennent le token
                    $allDocuments = $this->getAllDocumentIds();
                    $rightArray = $right->toArray();
                    $result = array_values(array_diff($allDocuments, $rightArray));

                    $stack[] = StringTypedCollection::from($result);
                } else {
                    // Opérateurs binaires (AND, OR, XOR)
                    if (count($stack) < 2) {
                        throw new \InvalidArgumentException(sprintf('%s operator requires 2 operands', $token));
                    }
                    $right = array_pop($stack);
                    $left = array_pop($stack);
                    $result = $this->applyOperator($token, $left, $right);
                    $stack[] = $result;
                }
            } else {
                // Token est un terme → chercher dans l'index
                $stack[] = $this->index->search($token);
            }
        }

        if (count($stack) !== 1) {
            throw new \RuntimeException('Invalid expression: '.count($stack).' results on stack');
        }

        return $stack[0];
    }

    /**
     * Récupère tous les IDs de documents de l'index.
     *
     * @return array<int, string> Liste de tous les IDs de documents
     */
    private function getAllDocumentIds(): array
    {
        $allTokens = $this->index->getAllTokens()->toArray();
        $allDocuments = [];

        foreach ($allTokens as $token) {
            $docs = $this->index->search($token)->toArray();
            $allDocuments = array_merge($allDocuments, $docs);
        }

        return array_unique($allDocuments);
    }

    private function applyOperator(
        string $operator,
        StringTypedCollection $left,
        StringTypedCollection $right
    ): StringTypedCollection {
        $leftArray = $left->toArray();
        $rightArray = $right->toArray();

        return match ($operator) {
            InvertedIndexOperator::AND->value => StringTypedCollection::from(array_values(array_intersect($leftArray, $rightArray))),
            InvertedIndexOperator::OR->value => StringTypedCollection::from(array_values(array_unique(array_merge($leftArray, $rightArray)))),
            InvertedIndexOperator::XOR->value => StringTypedCollection::from(array_values(array_merge(
                array_diff($leftArray, $rightArray),
                array_diff($rightArray, $leftArray)
            ))),
            default => $right,
        };
    }
}
