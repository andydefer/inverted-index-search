<?php

namespace AndyDefer\InvertedIndexSearch\Tests;

use AndyDefer\StorageKit\Contracts\Storage\StorageInterface;
use AndyDefer\StorageKit\Enums\CacheDriver;
use AndyDefer\StorageKit\Storage\CacheStorage;
use PHPUnit\Framework\TestCase;

abstract class CacheStorageTestCase extends TestCase
{
    protected StorageInterface $storage;

    protected CacheDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = CacheDriver::FILES;
        $this->storage = new CacheStorage($this->driver);
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

    protected function setCacheDriver(CacheDriver $driver): void
    {
        $this->driver = $driver;
        $this->storage = new CacheStorage($this->driver);
    }

    protected function getCacheDriver(): CacheDriver
    {
        return $this->driver;
    }
}
