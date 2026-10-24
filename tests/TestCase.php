<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $database = (string) $app['config']->get('database.connections.'.$app['config']->get('database.default').'.database');
        if (! $app->environment('testing')) {
            throw new \RuntimeException('Tests refuse cached or non-testing application configuration.');
        }
        if ($database !== ':memory:' && ! str_ends_with($database, '_test')) {
            throw new \RuntimeException('Tests require :memory: or a dedicated database ending in _test.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        config(['app.lock_path' => sys_get_temp_dir().'/tripcontrol-tests-'.getmypid()]);
        Http::preventStrayRequests();
        Storage::fake('local');
        Storage::fake('public');
        config(['filesystems.private_fallback_roots' => [], 'filesystems.public_fallback_roots' => [], 'filesystems.legacy_default_roots' => false]);
    }
}
