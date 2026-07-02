<?php

declare(strict_types=1);

namespace AndyDefer\InvertedIndexSearch\Enums;

/**
 * Boolean operators for Inverted Index search operations.
 */
enum InvertedIndexOperator: string
{
    /**
     * Intersection: Documents must contain ALL tokens.
     * Example: 'php' AND 'laravel' → documents containing both
     */
    case AND = 'AND';

    /**
     * Union: Documents must contain AT LEAST ONE token.
     * Example: 'php' OR 'python' → documents containing either
     */
    case OR = 'OR';

    /**
     * Exclusion: Documents containing the first token but NOT the second.
     * Requires exactly 2 tokens.
     * Example: 'php' NOT 'python' → documents with php but without python
     */
    case NOT = 'NOT';

    /**
     * Exclusive OR: Documents containing one token OR the other, but NOT both.
     * Requires exactly 2 tokens.
     * Example: 'php' XOR 'python' → documents with php or python, but not both
     */
    case XOR = 'XOR';

    /**
     * Checks if the operator requires exactly 2 tokens.
     */
    public function requiresTwoTokens(): bool
    {
        return in_array($this, [self::NOT, self::XOR], true);
    }

    /**
     * Returns the operator symbol for display purposes.
     */
    public function getSymbol(): string
    {
        return match ($this) {
            self::AND => '&',
            self::OR => '|',
            self::NOT => '!',
            self::XOR => '^',
        };
    }

    /**
     * Returns the operator description.
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::AND => 'All tokens must be present (intersection)',
            self::OR => 'At least one token must be present (union)',
            self::NOT => 'First token present, second token absent (exclusion)',
            self::XOR => 'Exactly one of the two tokens must be present (exclusive or)',
        };
    }
}
