<?php

namespace AndyDefer\InvertedIndexSearch\Tests;

use AndyDefer\StorageKit\Contracts\Storage\StorageInterface;
use AndyDefer\StorageKit\Storage\CookieStorage;
use PHPUnit\Framework\TestCase;

abstract class CookieStorageTestCase extends TestCase
{
    protected StorageInterface $storage;

    protected string $prefix;

    protected int $expires;

    protected string $path;

    protected bool $secure;

    protected bool $httpOnly;

    protected string $sameSite;

    protected function setUp(): void
    {
        parent::setUp();

        // Réinitialiser $_COOKIE pour les tests
        $_COOKIE = [];

        $this->prefix = 'test_'.uniqid().'_';
        $this->expires = time() + 3600;
        $this->path = '/';
        $this->secure = false;
        $this->httpOnly = true;
        $this->sameSite = 'Lax';

        $this->storage = new CookieStorage(
            prefix: $this->prefix,
            expires: $this->expires,
            domain: null,
            path: $this->path,
            secure: $this->secure,
            httpOnly: $this->httpOnly,
            sameSite: $this->sameSite
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Nettoyer les cookies
        if ($this->storage instanceof CookieStorage) {
            $this->storage->clear();
        }

        $_COOKIE = [];
    }

    protected function getStorage(): StorageInterface
    {
        return $this->storage;
    }

    protected function getPrefix(): string
    {
        return $this->prefix;
    }

    protected function setCookieConfig(
        ?int $expires = null,
        ?string $domain = null,
        string $path = '/',
        bool $secure = false,
        bool $httpOnly = true,
        string $sameSite = 'Lax'
    ): void {
        $this->expires = $expires ?? $this->expires;
        $this->path = $path;
        $this->secure = $secure;
        $this->httpOnly = $httpOnly;
        $this->sameSite = $sameSite;

        $this->storage = new CookieStorage(
            prefix: $this->prefix,
            expires: $this->expires,
            domain: $domain,
            path: $this->path,
            secure: $this->secure,
            httpOnly: $this->httpOnly,
            sameSite: $this->sameSite
        );
    }
}
