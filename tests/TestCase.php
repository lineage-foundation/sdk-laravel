<?php

namespace Lineage\Tests;

use Lineage\Providers\LineageServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LineageServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('lineage.mempool_host', 'http://mempool.test');
        $app['config']->set('lineage.storage_host', 'http://storage.test');
        $app['config']->set('lineage.api_key', 'test-api-key');
    }
}
