<?php

namespace AndyDefer\InvertedIndexSearch\Tests;

use AndyDefer\StorageKit\Contracts\Storage\StorageInterface;
use AndyDefer\StorageKit\Storage\SessionStorage;
use PHPUnit\Framework\TestCase;

abstract class SessionStorageTestCase extends TestCase
{
    protected StorageInterface $storage;

    protected string $namespace;

    protected function setUp(): void
    {
        parent::setUp();

        // Démarrer la session si elle n'est pas déjà active
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->namespace = 'test_'.uniqid();
        $this->storage = new SessionStorage($this->namespace);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Nettoyer le namespace
        if ($this->storage instanceof SessionStorage) {
            $this->storage->clear();
        }

        // Détruire la session si elle est active
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    protected function getStorage(): StorageInterface
    {
        return $this->storage;
    }

    protected function getNamespace(): string
    {
        return $this->namespace;
    }

    protected function setNamespace(string $namespace): void
    {
        $this->namespace = $namespace;
        if ($this->storage instanceof SessionStorage) {
            $this->storage->setNamespace($namespace);
        }
    }
}
