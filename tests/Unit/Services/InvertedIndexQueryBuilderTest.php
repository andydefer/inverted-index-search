<?php

declare(strict_types=1);

namespace AndyDefer\InvertedIndexSearch\Tests\Unit\Services;

use AndyDefer\AlgoKIT\Algorithms\InvertedIndex;
use AndyDefer\AlgoKIT\Records\InvertedIndexRecord;
use AndyDefer\InvertedIndexSearch\Services\InvertedIndexExpressionEvaluator;
use AndyDefer\InvertedIndexSearch\Services\InvertedIndexQueryBuilder;
use AndyDefer\InvertedIndexSearch\Tests\SqliteStorageTestCase;

final class InvertedIndexQueryBuilderTest extends SqliteStorageTestCase
{
    private InvertedIndex $index;

    private InvertedIndexQueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        // Arrange - Initialisation des données de test
        $this->index = new InvertedIndex($this->getStorage(), 'test_index');
        $evaluator = new InvertedIndexExpressionEvaluator($this->index);
        $this->queryBuilder = new InvertedIndexQueryBuilder($this->index, $evaluator);

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

    public function test_simple_and(): void
    {
        // Arrange
        $expectedDocuments = ['doc_1', 'doc_5'];

        // Act
        $results = $this->queryBuilder
            ->where('php')
            ->where('laravel')
            ->get();

        // Assert
        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing($expectedDocuments, $results->toArray());
    }

    public function test_simple_or(): void
    {
        // Arrange
        $expectedDocuments = ['doc_1', 'doc_2', 'doc_4', 'doc_5'];

        // Act
        $results = $this->queryBuilder
            ->where('php')
            ->orWhere('python')
            ->get();

        // Assert
        $this->assertCount(4, $results);
        $this->assertEqualsCanonicalizing($expectedDocuments, $results->toArray());
    }

    public function test_with_not(): void
    {
        // Arrange
        $expectedDocuments = ['doc_1', 'doc_5'];

        // Act
        $results = $this->queryBuilder
            ->where('php')
            ->whereNot('python')
            ->get();

        // Assert
        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing($expectedDocuments, $results->toArray());
    }

    public function test_with_group(): void
    {
        // Arrange
        $expectedDocuments = ['doc_1', 'doc_5'];

        // Act
        $results = $this->queryBuilder
            ->where('php')
            ->whereGroup(function ($q) {
                $q->where('laravel')
                    ->orWhere('vuejs');
            })
            ->get();

        // Assert
        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing($expectedDocuments, $results->toArray());
    }

    public function test_with_or_group(): void
    {
        // Arrange
        $expectedDocuments = ['doc_1', 'doc_4', 'doc_5'];

        // Act
        $results = $this->queryBuilder
            ->whereGroup(function ($q) {
                $q->where('php')
                    ->where('laravel');
            })
            ->orWhereGroup(function ($q) {
                $q->where('python')
                    ->where('django');
            })
            ->get();

        // Assert
        $this->assertCount(3, $results);
        $this->assertEqualsCanonicalizing($expectedDocuments, $results->toArray());
    }

    public function test_complex_with_not(): void
    {
        // Arrange
        $expectedDocuments = ['doc_1', 'doc_5'];

        // Act
        $results = $this->queryBuilder
            ->where('php')
            ->whereGroup(function ($q) {
                $q->where('laravel')
                    ->orWhere('vuejs');
            })
            ->whereNot('python')
            ->get();

        // Assert
        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing($expectedDocuments, $results->toArray());
    }

    public function test_empty_query(): void
    {
        // Act
        $results = $this->queryBuilder->get();

        // Assert
        $this->assertCount(0, $results);
    }

    public function test_reset(): void
    {
        // Arrange
        $this->queryBuilder
            ->where('php')
            ->where('laravel');

        // Act
        $this->queryBuilder->reset();
        $results = $this->queryBuilder->get();

        // Assert
        $this->assertCount(0, $results);
    }

    public function test_to_expression(): void
    {
        // Arrange
        $expectedExpression = 'php AND (laravel OR vuejs) AND (NOT python)';

        // Act
        $expression = $this->queryBuilder
            ->where('php')
            ->whereGroup(function ($q) {
                $q->where('laravel')
                    ->orWhere('vuejs');
            })
            ->whereNot('python')
            ->toExpression();

        // Assert
        $this->assertEquals($expectedExpression, $expression);
    }

    public function test_fluent_interface(): void
    {
        // Arrange
        $expectedDocuments = ['doc_1', 'doc_5'];

        // Act
        $results = $this->queryBuilder
            ->where('php')
            ->where('laravel')
            ->whereNot('python')
            ->get();

        // Assert
        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing($expectedDocuments, $results->toArray());
    }

    public function test_multiple_resets(): void
    {
        // Arrange
        $this->queryBuilder
            ->where('php')
            ->where('laravel');

        // Act
        $this->queryBuilder->reset();
        $this->queryBuilder
            ->where('python')
            ->where('django');

        $results = $this->queryBuilder->get();

        // Assert
        $expectedDocuments = ['doc_4'];
        $this->assertCount(1, $results);
        $this->assertEqualsCanonicalizing($expectedDocuments, $results->toArray());
    }

    public function test_chained_groups(): void
    {
        // Arrange
        $expectedDocuments = ['doc_1', 'doc_5'];

        // Act
        $results = $this->queryBuilder
            ->whereGroup(function ($q) {
                $q->where('php')
                    ->orWhere('python');
            })
            ->whereGroup(function ($q) {
                $q->where('laravel')
                    ->orWhere('vuejs');
            })
            ->get();

        // Assert
        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing($expectedDocuments, $results->toArray());
    }

    public function test_single_where(): void
    {
        // Arrange
        $expectedDocuments = ['doc_1', 'doc_2', 'doc_5'];

        // Act
        $results = $this->queryBuilder
            ->where('php')
            ->get();

        // Assert
        $this->assertCount(3, $results);
        $this->assertEqualsCanonicalizing($expectedDocuments, $results->toArray());
    }

    public function test_single_or_where(): void
    {
        // Arrange
        $expectedDocuments = ['doc_2', 'doc_4'];

        // Act
        $results = $this->queryBuilder
            ->orWhere('python')
            ->get();

        // Assert
        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing($expectedDocuments, $results->toArray());
    }

    public function test_where_not_with_or(): void
    {
        // Arrange
        $expectedDocuments = ['doc_2'];

        // Act
        $results = $this->queryBuilder
            ->orWhere('python')
            ->whereNot('django')
            ->get();

        // Assert
        $this->assertCount(1, $results);
        $this->assertEqualsCanonicalizing($expectedDocuments, $results->toArray());
    }

    public function test_complex_nested_groups(): void
    {
        // Arrange
        $expectedDocuments = ['doc_1', 'doc_5'];

        // Act
        $results = $this->queryBuilder
            ->where('php')
            ->whereGroup(function ($q) {
                $q->whereGroup(function ($q2) {
                    $q2->where('laravel')
                        ->orWhere('vuejs');
                });
            })
            ->get();

        // Assert
        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing($expectedDocuments, $results->toArray());
    }
}
