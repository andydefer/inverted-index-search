<?php

namespace AndyDefer\InvertedIndexSearch\Tests;

use AndyDefer\StorageKit\Contracts\Storage\StorageInterface;
use AndyDefer\StorageKit\Storage\JsonlStorage;
use PHPUnit\Framework\TestCase;

abstract class JsonlStorageTestCase extends TestCase
{
    protected StorageInterface $storage;

    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir().'/storage_test_'.uniqid();
        $this->storage = new JsonlStorage($this->tempDir, 3600, 2);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->storage->clear();
        $this->removeDirectory($this->tempDir);
    }

    protected function getStorage(): StorageInterface
    {
        return $this->storage;
    }

    protected function getTempDir(): string
    {
        return $this->tempDir;
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = array_diff(scandir($directory), ['.', '..']);
        foreach ($items as $item) {
            $path = $directory.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($directory);
    }
}
