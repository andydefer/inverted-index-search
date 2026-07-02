<?php

declare(strict_types=1);

namespace AndyDefer\InvertedIndexSearch\Tests;

use AndyDefer\StorageKit\Contracts\Storage\StorageInterface;
use AndyDefer\StorageKit\Storage\SqliteStorage;
use PHPUnit\Framework\TestCase;

abstract class SqliteStorageTestCase extends TestCase
{
    protected StorageInterface $storage;

    protected SqliteStorage $sqliteStorage;

    protected string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        // Utiliser un fichier temporaire pour la persistance
        $tempDir = sys_get_temp_dir().'/sqlite_test_'.uniqid();
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        $this->databasePath = $tempDir.'/test.db';

        $this->sqliteStorage = new SqliteStorage(
            database: $this->databasePath,
            table: 'test_storage'
        );

        $this->storage = $this->sqliteStorage;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->sqliteStorage)) {
            try {
                if ($this->sqliteStorage->inTransaction()) {
                    $this->sqliteStorage->rollback();
                }
                $this->sqliteStorage->close();
            } catch (\Exception $e) {
                // Ignorer les erreurs de fermeture
            }
        }

        // Supprimer le fichier et le répertoire temporaires
        if (file_exists($this->databasePath)) {
            @unlink($this->databasePath);
        }
        $tempDir = dirname($this->databasePath);
        if (is_dir($tempDir)) {
            @rmdir($tempDir);
        }
    }

    protected function getStorage(): StorageInterface
    {
        return $this->storage;
    }

    protected function getSqliteStorage(): SqliteStorage
    {
        return $this->sqliteStorage;
    }

    protected function getDatabasePath(): string
    {
        return $this->databasePath;
    }
}
