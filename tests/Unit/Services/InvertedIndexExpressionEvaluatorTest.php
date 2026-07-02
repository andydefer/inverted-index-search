<?php

declare(strict_types=1);

namespace AndyDefer\InvertedIndexSearch\Tests\Unit\Services;

use AndyDefer\AlgoKIT\Algorithms\InvertedIndex;
use AndyDefer\AlgoKIT\Records\InvertedIndexRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\InvertedIndexSearch\Services\InvertedIndexExpressionEvaluator;
use AndyDefer\InvertedIndexSearch\Tests\SqliteStorageTestCase;

final class InvertedIndexExpressionEvaluatorTest extends SqliteStorageTestCase
{
    private InvertedIndex $index;

    private InvertedIndexExpressionEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();

        // Arrange - Initialisation des données de test
        $this->index = new InvertedIndex($this->getStorage(), 'test_index');
        $this->evaluator = new InvertedIndexExpressionEvaluator($this->index);

        $this->index->add(InvertedIndexRecord::from([
            'document_id' => 'doc_1',
            'tokens' => ['php', 'laravel'],
        ]));
        $this->index->add(InvertedIndexRecord::from([
            'document_id' => 'doc_2',
            'tokens' => ['php', 'python'],
        ]));
        $this->index->add(InvertedIndexRecord::from([
            'document_id' => 'doc_3',
            'tokens' => ['laravel', 'vuejs'],
        ]));
        $this->index->add(InvertedIndexRecord::from([
            'document_id' => 'doc_4',
            'tokens' => ['python', 'django'],
        ]));
        $this->index->add(InvertedIndexRecord::from([
            'document_id' => 'doc_5',
            'tokens' => ['php', 'laravel', 'api'],
        ]));
    }

    protected function tearDown(): void
    {
        $this->index->clear();
        parent::tearDown();
    }

    public function test_evaluate_simple_and(): void
    {
        // Arrange
        $expression = 'php AND laravel';
        $expectedDocuments = ['doc_1', 'doc_5'];

        // Act
        $results = $this->evaluator->evaluate($expression);

        // Assert
        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing($expectedDocuments, $results->toArray());
    }

    public function test_evaluate_simple_or(): void
    {
        // Arrange
        $expression = 'php OR python';
        $expectedDocuments = ['doc_1', 'doc_2', 'doc_4', 'doc_5'];

        // Act
        $results = $this->evaluator->evaluate($expression);

        // Assert
        $this->assertCount(4, $results);
        $this->assertEqualsCanonicalizing($expectedDocuments, $results->toArray());
    }

    public function test_evaluate_not(): void
    {
        // Arrange
        $expression = 'php AND (NOT python)';
        $expectedDocuments = ['doc_1', 'doc_5'];

        // Act
        $results = $this->evaluator->evaluate($expression);

        // Assert
        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing($expectedDocuments, $results->toArray());
    }

    public function test_evaluate_xor(): void
    {
        // Arrange
        $expression = 'php XOR python';
        $expectedDocuments = ['doc_1', 'doc_4', 'doc_5'];

        // Act
        $results = $this->evaluator->evaluate($expression);

        // Assert
        $this->assertCount(3, $results);
        $this->assertEqualsCanonicalizing($expectedDocuments, $results->toArray());
    }

    public function test_evaluate_with_parentheses(): void
    {
        // Arrange
        $expression = '(php OR python) AND laravel';
        $expectedDocuments = ['doc_1', 'doc_5'];

        // Act
        $results = $this->evaluator->evaluate($expression);

        // Assert
        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing($expectedDocuments, $results->toArray());
    }

    public function test_evaluate_complex_expression(): void
    {
        // Arrange
        $expression = '(php AND laravel) OR (python AND django)';
        $expectedDocuments = ['doc_1', 'doc_4', 'doc_5'];

        // Act
        $results = $this->evaluator->evaluate($expression);

        // Assert
        $this->assertCount(3, $results);
        $this->assertEqualsCanonicalizing($expectedDocuments, $results->toArray());
    }

    public function test_evaluate_nested_parentheses(): void
    {
        // Arrange
        $expression = 'php AND (laravel OR vuejs) AND (NOT python)';
        $expectedDocuments = ['doc_1', 'doc_5'];

        // Act
        $results = $this->evaluator->evaluate($expression);

        // Assert
        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing($expectedDocuments, $results->toArray());
    }

    public function test_evaluate_not_simple(): void
    {
        // Arrange
        $expression = '(NOT python)';
        $expectedDocuments = ['doc_1', 'doc_3', 'doc_5'];

        // Act
        $results = $this->evaluator->evaluate($expression);

        // Assert
        $this->assertCount(3, $results);
        $this->assertEqualsCanonicalizing($expectedDocuments, $results->toArray());
    }

    public function test_evaluate_empty_expression(): void
    {
        // Arrange
        $expression = '';

        // Act
        $results = $this->evaluator->evaluate($expression);

        // Assert
        $this->assertInstanceOf(StringTypedCollection::class, $results);
        $this->assertCount(0, $results);
    }

    public function test_evaluate_whitespace_expression(): void
    {
        // Arrange
        $expression = '   ';

        // Act
        $results = $this->evaluator->evaluate($expression);

        // Assert
        $this->assertInstanceOf(StringTypedCollection::class, $results);
        $this->assertCount(0, $results);
    }

    public function test_evaluate_invalid_expression_throws_exception(): void
    {
        // Arrange
        $expression = 'php AND';

        // Expect
        $this->expectException(\InvalidArgumentException::class);

        // Act
        $this->evaluator->evaluate($expression);
    }

    public function test_evaluate_mismatched_parentheses_throws_exception(): void
    {
        // Arrange
        $expression = '(php AND laravel';

        // Expect
        $this->expectException(\InvalidArgumentException::class);

        // Act
        $this->evaluator->evaluate($expression);
    }
}
