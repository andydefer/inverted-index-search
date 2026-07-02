<?php

namespace AndyDefer\InvertedIndexSearch\Tests;

use AndyDefer\StorageKit\Contracts\Storage\StorageInterface;
use AndyDefer\StorageKit\Storage\MemoryStorage;
use PHPUnit\Framework\TestCase;

abstract class MemoryStorageTestCase extends TestCase
{
    protected StorageInterface $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = new MemoryStorage;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->storage->clear();
    }

    protected function getStorage(): StorageInterface
    {
        return $this->storage;
    }
}
