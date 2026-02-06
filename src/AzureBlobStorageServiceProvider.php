<?php

namespace AliNauf\AzureStorage;

use AliNauf\AzureStorage\Support\SasTokenGenerator;
use DateTimeInterface;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;

class AzureBlobStorageServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/azure-storage.php',
            'azure-storage'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishConfig();
        $this->registerAzureDriver();
    }

    /**
     * Publish the configuration file.
     */
    protected function publishConfig(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/azure-storage.php' => config_path('azure-storage.php'),
            ], 'azure-storage-config');
        }
    }

    /**
     * Register the Azure filesystem driver.
     */
    protected function registerAzureDriver(): void
    {
        Storage::extend('azure', function ($app, array $config) {
            $client = new AzureBlobStorageClient(
                accountName: $config['account_name'] ?? config('azure-storage.account_name'),
                accountKey: $config['account_key'] ?? config('azure-storage.account_key'),
                container: $config['container'] ?? config('azure-storage.container'),
                apiVersion: $config['api_version'] ?? config('azure-storage.api_version'),
            );

            $publicUrl = $config['url'] ?? config('azure-storage.url') ?? '';
            $defaultVisibility = $config['visibility']['default'] ?? config('azure-storage.visibility.default', 'private');
            $allowSetVisibility = $config['visibility']['allow_set'] ?? config('azure-storage.visibility.allow_set', false);

            $adapter = new AzureBlobStorageAdapter($client, $publicUrl, $defaultVisibility, $allowSetVisibility);

            $flysystem = new Filesystem($adapter, $config);

            $filesystemAdapter = new FilesystemAdapter($flysystem, $adapter, $config);

            // Add support for temporaryUrl
            $this->registerTemporaryUrlMacro($filesystemAdapter, $client, $config);

            return $filesystemAdapter;
        });
    }

    /**
     * Register the temporaryUrl macro on the filesystem adapter.
     */
    protected function registerTemporaryUrlMacro(
        FilesystemAdapter $filesystemAdapter,
        AzureBlobStorageClient $client,
        array $config,
    ): void {
        // Laravel's FilesystemAdapter doesn't support macros directly,
        // but we can use the buildTemporaryUrlsUsing method if available
        if (method_exists($filesystemAdapter, 'buildTemporaryUrlsUsing')) {
            $filesystemAdapter->buildTemporaryUrlsUsing(
                function (string $path, DateTimeInterface $expiration, array $options) use ($client, $config) {
                    $sasGenerator = new SasTokenGenerator(
                        accountName: $config['account_name'] ?? config('azure-storage.account_name'),
                        accountKey: $config['account_key'] ?? config('azure-storage.account_key'),
                        apiVersion: $config['api_version'] ?? config('azure-storage.api_version', '2023-08-03'),
                    );

                    $permissions = $options['permissions']
                        ?? config('azure-storage.sas.default_permissions', SasTokenGenerator::PERMISSION_READ);

                    return $sasGenerator->generateSignedUrl(
                        container: $client->getContainer(),
                        blob: $path,
                        expiry: $expiration,
                        permissions: $permissions,
                    );
                }
            );
        }
    }
}
