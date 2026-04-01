<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Siempre limpiar config cache en tests para evitar que config congelado
        // de .env de producción interfiera con phpunit.xml env overrides
        Artisan::call('config:clear');

        // Clear application cache at start of each test
        Cache::flush();
    }

    protected function tearDown(): void
    {
        // Clear all application cache after each test to prevent contamination
        // Reset the cache manager to get a fresh instance for the next test
        try {
            // Force reinitialize the cache manager to reset the array cache store
            app()->forgetInstance('cache');
            app()->forgetInstance('cache.store');
        } catch (\Throwable $e) {
            // Ignore cache errors
        }

        parent::tearDown();
    }

    /**
     * Disable foreign key constraints for SQLite tests
     * This allows testing with orphaned foreign keys (common testing pattern)
     */
    protected function disableForeignKeyConstraints(): void
    {
        if (config('database.default') === 'sqlite') {
            DB::connection()->statement('PRAGMA foreign_keys=OFF');
        }
    }

    /**
     * Enable foreign key constraints for SQLite tests
     */
    protected function enableForeignKeyConstraints(): void
    {
        if (config('database.default') === 'sqlite') {
            DB::connection()->statement('PRAGMA foreign_keys=ON');
        }
    }
}
