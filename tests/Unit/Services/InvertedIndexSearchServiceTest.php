<?php

declare(strict_types=1);

namespace AndyDefer\InvertedIndexSearch\Tests\Unit\Services;

use AndyDefer\AlgoKIT\Algorithms\InvertedIndex;
use AndyDefer\AlgoKIT\Records\InvertedIndexRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\InvertedIndexSearch\Services\InvertedIndexExpressionEvaluator;
use AndyDefer\InvertedIndexSearch\Services\InvertedIndexSearchService;
use AndyDefer\InvertedIndexSearch\Tests\SqliteStorageTestCase;

final class InvertedIndexSearchServiceTest extends SqliteStorageTestCase
{
    private InvertedIndex $index;

    private InvertedIndexSearchService $search;

    protected function setUp(): void
    {
        parent::setUp();

        // Arrange - Initialisation des données de test
        $this->index = new InvertedIndex($this->getStorage(), 'test_index');
        $evaluator = new InvertedIndexExpressionEvaluator($this->index);
        $this->search = new InvertedIndexSearchService($this->index, $evaluator);

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

    // ============================================================
    // TESTS AND
    // ============================================================

    public function test_and(): void
    {
        // Arrange
        $tokens = ['php', 'laravel'];
        $expectedDocuments = ['doc_1', 'doc_5'];

        // Act
        $results = $this->search->and($tokens);

        // Assert
        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing($expectedDocuments, $results->toArray());
    }

    public function test_and_empty_tokens(): void
    {
        // Arrange
        $tokens = [];

        // Act
        $results = $this->search->and($tokens);

        // Assert
        $this->assertInstanceOf(StringTypedCollection::class, $results);
        $this->assertCount(0, $results);
    }

    public function test_and_no_match(): void
    {
        // Arrange
        $tokens = ['php', 'ruby'];

        // Act
        $results = $this->search->and($tokens);

        // Assert
        $this->assertCount(0, $results);
    }

    // ============================================================
    // TESTS OR
    // ============================================================

    public function test_or(): void
    {
        // Arrange
        $tokens = ['php', 'python'];
        $expectedDocuments = ['doc_1', 'doc_2', 'doc_4', 'doc_5'];

        // Act
        $results = $this->search->or($tokens);

        // Assert
        $this->assertCount(4, $results);
        $this->assertEqualsCanonicalizing($expectedDocuments, $results->toArray());
    }

    public function test_or_empty_tokens(): void
    {
        // Arrange
        $tokens = [];

        // Act
        $results = $this->search->or($tokens);

        // Assert
        $this->assertInstanceOf(StringTypedCollection::class, $results);
        $this->assertCount(0, $results);
    }

    public function test_or_no_match(): void
    {
        // Arrange
        $tokens = ['ruby'];

        // Act
        $results = $this->search->or($tokens);

        // Assert
        $this->assertCount(0, $results);
    }

    // ============================================================
    // TESTS NOT
    // ============================================================

    public function test_not(): void
    {
        // Arrange
        $include = 'php';
        $exclude = 'python';
        $expectedDocuments = ['doc_1', 'doc_5'];

        // Act
        $results = $this->search->not($include, $exclude);

        // Assert
        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing($expectedDocuments, $results->toArray());
    }

    public function test_not_no_include(): void
    {
        // Arrange
        $include = 'ruby';
        $exclude = 'php';

        // Act
        $results = $this->search->not($include, $exclude);

        // Assert
        $this->assertCount(0, $results);
    }

    // ============================================================
    // TESTS XOR
    // ============================================================

    public function test_xor(): void
    {
        // Arrange
        $tokenA = 'php';
        $tokenB = 'python';
        $expectedDocuments = ['doc_1', 'doc_4', 'doc_5'];

        // Act
        $results = $this->search->xor($tokenA, $tokenB);

        // Assert
        $this->assertCount(3, $results);
        $this->assertEqualsCanonicalizing($expectedDocuments, $results->toArray());
    }

    // ============================================================
    // TESTS EXPRESSION
    // ============================================================

    public function test_expression(): void
    {
        // Arrange
        $expression = '(php OR python) AND laravel';
        $expectedDocuments = ['doc_1', 'doc_5'];

        // Act
        $results = $this->search->expression($expression);

        // Assert
        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing($expectedDocuments, $results->toArray());
    }

    public function test_expression_complex(): void
    {
        // Arrange
        $expression = '(php AND laravel) OR (python AND django)';
        $expectedDocuments = ['doc_1', 'doc_4', 'doc_5'];

        // Act
        $results = $this->search->expression($expression);

        // Assert
        $this->assertCount(3, $results);
        $this->assertEqualsCanonicalizing($expectedDocuments, $results->toArray());
    }

    public function test_expression_with_not(): void
    {
        // Arrange
        $expression = 'php AND laravel AND (NOT python)';
        $expectedDocuments = ['doc_1', 'doc_5'];

        // Act
        $results = $this->search->expression($expression);

        // Assert
        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing($expectedDocuments, $results->toArray());
    }

    public function test_expression_empty(): void
    {
        // Arrange
        $expression = '';

        // Act
        $results = $this->search->expression($expression);

        // Assert
        $this->assertInstanceOf(StringTypedCollection::class, $results);
        $this->assertCount(0, $results);
    }

    public function test_expression_invalid_throws_exception(): void
    {
        // Arrange
        $expression = 'php AND';

        // Expect
        $this->expectException(\InvalidArgumentException::class);

        // Act
        $this->search->expression($expression);
    }

    // ============================================================
    // TESTS AND WITH LIMIT
    // ============================================================

    public function test_and_with_limit(): void
    {
        // Arrange
        $tokens = ['php'];
        $limit = 2;

        // Act
        $results = $this->search->andWithLimit($tokens, $limit);

        // Assert
        $this->assertCount(2, $results);
    }

    public function test_and_with_limit_empty_tokens(): void
    {
        // Arrange
        $tokens = [];
        $limit = 2;

        // Act
        $results = $this->search->andWithLimit($tokens, $limit);

        // Assert
        $this->assertInstanceOf(StringTypedCollection::class, $results);
        $this->assertCount(0, $results);
    }

    public function test_and_with_limit_returns_correct_limit(): void
    {
        // Arrange
        $tokens = ['php'];
        $limit = 5;
        $expectedCount = 5;

        // Ajout de documents supplémentaires
        for ($i = 6; $i <= 15; $i++) {
            $this->index->add(InvertedIndexRecord::from([
                'document_id' => 'doc_'.$i,
                'tokens' => ['php', 'extra_'.$i],
            ]));
        }

        // Act
        $results = $this->search->andWithLimit($tokens, $limit);

        // Assert
        $this->assertCount($expectedCount, $results);
    }

    public function test_and_with_limit_greater_than_results(): void
    {
        // Arrange
        $tokens = ['php'];
        $limit = 100;

        // Act
        $results = $this->search->andWithLimit($tokens, $limit);

        // Assert
        $this->assertGreaterThan(0, $results->count());
        $this->assertLessThanOrEqual(5, $results->count());
    }

    // ============================================================
    // TESTS OR WITH LIMIT
    // ============================================================

    public function test_or_with_limit(): void
    {
        // Arrange
        $tokens = ['php'];
        $limit = 2;

        // Act
        $results = $this->search->orWithLimit($tokens, $limit);

        // Assert
        $this->assertCount(2, $results);
    }

    public function test_or_with_limit_empty_tokens(): void
    {
        // Arrange
        $tokens = [];
        $limit = 2;

        // Act
        $results = $this->search->orWithLimit($tokens, $limit);

        // Assert
        $this->assertInstanceOf(StringTypedCollection::class, $results);
        $this->assertCount(0, $results);
    }

    public function test_or_with_limit_returns_correct_limit(): void
    {
        // Arrange
        $tokens = ['php'];
        $limit = 5;
        $expectedCount = 5;

        // Ajout de documents supplémentaires
        for ($i = 6; $i <= 15; $i++) {
            $this->index->add(InvertedIndexRecord::from([
                'document_id' => 'doc_'.$i,
                'tokens' => ['php', 'extra_'.$i],
            ]));
        }

        // Act
        $results = $this->search->orWithLimit($tokens, $limit);

        // Assert
        $this->assertCount($expectedCount, $results);
    }

    public function test_or_with_limit_greater_than_results(): void
    {
        // Arrange
        $tokens = ['php'];
        $limit = 100;

        // Act
        $results = $this->search->orWithLimit($tokens, $limit);

        // Assert
        $this->assertGreaterThan(0, $results->count());
        $this->assertLessThanOrEqual(5, $results->count());
    }

    // ============================================================
    // TESTS DE PERFORMANCE
    // ============================================================

    public function test_performance_complex_expression(): void
    {
        // Arrange
        for ($i = 6; $i <= 20; $i++) {
            $this->index->add(InvertedIndexRecord::from([
                'document_id' => 'doc_'.$i,
                'tokens' => ['php', 'laravel', 'api', 'test_'.$i],
            ]));
        }

        // Act
        $startTime = microtime(true);
        $results = $this->search->expression('(php AND laravel) OR (php AND api)');
        $endTime = microtime(true);

        // Assert
        $this->assertGreaterThan(0, $results->count());
        $this->assertLessThan(1.0, $endTime - $startTime, 'Expression evaluation should take less than 1 second');
    }

    // ============================================================
    // TESTS DE TYPAGE DES RETOURS
    // ============================================================

    public function test_all_methods_return_string_typed_collection(): void
    {
        // Arrange - Définition des paramètres
        $tokens = ['php'];
        $include = 'php';
        $exclude = 'python';
        $tokenA = 'php';
        $tokenB = 'python';
        $expression = 'php AND laravel';
        $limit = 2;

        // Act & Assert
        $this->assertInstanceOf(StringTypedCollection::class, $this->search->and($tokens));
        $this->assertInstanceOf(StringTypedCollection::class, $this->search->or($tokens));
        $this->assertInstanceOf(StringTypedCollection::class, $this->search->not($include, $exclude));
        $this->assertInstanceOf(StringTypedCollection::class, $this->search->xor($tokenA, $tokenB));
        $this->assertInstanceOf(StringTypedCollection::class, $this->search->expression($expression));
        $this->assertInstanceOf(StringTypedCollection::class, $this->search->andWithLimit($tokens, $limit));
        $this->assertInstanceOf(StringTypedCollection::class, $this->search->orWithLimit($tokens, $limit));
    }
}
