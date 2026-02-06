<?php

namespace AliNauf\AzureStorage\Tests\Feature;

use AliNauf\AzureStorage\Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;

class ServiceProviderTest extends TestCase
{
    #[Test]
    public function it_registers_the_azure_driver(): void
    {
        // Configure a disk using the azure driver
        config(['filesystems.disks.azure' => [
            'driver' => 'azure',
            'account_name' => 'testaccount',
            'account_key' => base64_encode('test-key-1234567890'),
            'container' => 'testcontainer',
        ]]);

        // The disk should be available
        $disk = Storage::disk('azure');

        $this->assertNotNull($disk);
    }

    #[Test]
    public function it_merges_config_from_package(): void
    {
        $this->assertNotNull(config('azure-storage'));
        $this->assertEquals('testaccount', config('azure-storage.account_name'));
        $this->assertEquals('testcontainer', config('azure-storage.container'));
    }

    #[Test]
    public function it_has_default_sas_configuration(): void
    {
        $this->assertEquals(3600, config('azure-storage.sas.default_expiry'));
        $this->assertEquals('r', config('azure-storage.sas.default_permissions'));
    }

    #[Test]
    public function it_has_default_visibility_configuration(): void
    {
        $this->assertEquals('private', config('azure-storage.visibility.default'));
        $this->assertFalse(config('azure-storage.visibility.allow_set'));
    }
}
