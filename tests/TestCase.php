<?php

namespace AliNauf\AzureStorage\Tests;

use AliNauf\AzureStorage\AzureBlobStorageServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            AzureBlobStorageServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('azure-storage.account_name', 'testaccount');
        $app['config']->set('azure-storage.account_key', base64_encode('test-key-1234567890'));
        $app['config']->set('azure-storage.container', 'testcontainer');
    }
}
