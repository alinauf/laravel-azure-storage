<?php

namespace AliNauf\AzureStorage\Tests\Feature;

use AliNauf\AzureStorage\AzureBlobStorageAdapter;
use AliNauf\AzureStorage\AzureBlobStorageClient;
use AliNauf\AzureStorage\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use League\Flysystem\Config;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\Visibility;
use PHPUnit\Framework\Attributes\Test;

class AdapterTest extends TestCase
{
    private AzureBlobStorageAdapter $adapter;

    private string $azureUrl;

    protected function setUp(): void
    {
        parent::setUp();

        $client = new AzureBlobStorageClient(
            accountName: 'testaccount',
            accountKey: base64_encode('test-key-1234567890'),
            container: 'testcontainer',
        );

        $this->adapter = new AzureBlobStorageAdapter($client);
        $this->azureUrl = 'https://testaccount.blob.core.windows.net/testcontainer';
    }

    #[Test]
    public function it_can_write_a_file(): void
    {
        Http::fake([
            "{$this->azureUrl}/test.txt" => Http::response('', 201),
        ]);

        $this->adapter->write('test.txt', 'Hello World', new Config);

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && str_contains($request->url(), 'test.txt')
                && $request->body() === 'Hello World';
        });
    }

    #[Test]
    public function it_throws_exception_on_write_failure(): void
    {
        Http::fake([
            "{$this->azureUrl}/test.txt" => Http::response('Error', 500),
        ]);

        $this->expectException(UnableToWriteFile::class);

        $this->adapter->write('test.txt', 'Hello World', new Config);
    }

    #[Test]
    public function it_can_read_a_file(): void
    {
        Http::fake([
            "{$this->azureUrl}/test.txt" => Http::response('Hello World', 200),
        ]);

        $contents = $this->adapter->read('test.txt');

        $this->assertEquals('Hello World', $contents);
    }

    #[Test]
    public function it_throws_exception_when_reading_non_existent_file(): void
    {
        Http::fake([
            "{$this->azureUrl}/notfound.txt" => Http::response('', 404),
        ]);

        $this->expectException(UnableToReadFile::class);

        $this->adapter->read('notfound.txt');
    }

    #[Test]
    public function it_can_check_if_file_exists(): void
    {
        Http::fake([
            "{$this->azureUrl}/exists.txt" => Http::response('', 200, [
                'Content-Length' => '100',
                'Content-Type' => 'text/plain',
                'Last-Modified' => 'Mon, 01 Jan 2024 00:00:00 GMT',
            ]),
        ]);

        $this->assertTrue($this->adapter->fileExists('exists.txt'));
    }

    #[Test]
    public function it_returns_false_for_non_existent_file(): void
    {
        Http::fake([
            "{$this->azureUrl}/notexists.txt" => Http::response('', 404),
        ]);

        $this->assertFalse($this->adapter->fileExists('notexists.txt'));
    }

    #[Test]
    public function it_can_delete_a_file(): void
    {
        Http::fake([
            "{$this->azureUrl}/delete.txt" => Http::response('', 202),
        ]);

        $this->adapter->delete('delete.txt');

        Http::assertSent(function ($request) {
            return $request->method() === 'DELETE'
                && str_contains($request->url(), 'delete.txt');
        });
    }

    #[Test]
    public function it_does_not_throw_when_deleting_non_existent_file(): void
    {
        Http::fake([
            "{$this->azureUrl}/notfound.txt" => Http::response('', 404),
        ]);

        // Should not throw
        $this->adapter->delete('notfound.txt');

        $this->assertTrue(true);
    }

    #[Test]
    public function it_can_copy_a_file(): void
    {
        Http::fake([
            "{$this->azureUrl}/destination.txt" => Http::response('', 202),
        ]);

        $this->adapter->copy('source.txt', 'destination.txt', new Config);

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && str_contains($request->url(), 'destination.txt')
                && $request->hasHeader('x-ms-copy-source');
        });
    }

    #[Test]
    public function it_can_get_file_metadata(): void
    {
        Http::fake([
            "{$this->azureUrl}/test.txt" => Http::response('', 200, [
                'Content-Length' => '1024',
                'Content-Type' => 'text/plain',
                'Last-Modified' => 'Mon, 01 Jan 2024 12:00:00 GMT',
                'ETag' => '"abc123"',
            ]),
        ]);

        $fileSize = $this->adapter->fileSize('test.txt');
        $this->assertEquals(1024, $fileSize->fileSize());
    }

    #[Test]
    public function it_can_list_contents(): void
    {
        $xmlResponse = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<EnumerationResults>
    <Blobs>
        <Blob>
            <Name>file1.txt</Name>
            <Properties>
                <Content-Length>100</Content-Length>
                <Content-Type>text/plain</Content-Type>
                <Last-Modified>Mon, 01 Jan 2024 00:00:00 GMT</Last-Modified>
            </Properties>
        </Blob>
        <Blob>
            <Name>file2.jpg</Name>
            <Properties>
                <Content-Length>2048</Content-Length>
                <Content-Type>image/jpeg</Content-Type>
                <Last-Modified>Tue, 02 Jan 2024 00:00:00 GMT</Last-Modified>
            </Properties>
        </Blob>
    </Blobs>
</EnumerationResults>
XML;

        Http::fake([
            "{$this->azureUrl}*" => Http::response($xmlResponse, 200),
        ]);

        $contents = iterator_to_array($this->adapter->listContents('', true));

        $this->assertCount(2, $contents);
        $this->assertEquals('file1.txt', $contents[0]->path());
        $this->assertEquals('file2.jpg', $contents[1]->path());
    }

    #[Test]
    public function it_generates_correct_public_url(): void
    {
        $url = $this->adapter->getUrl('path/to/file.jpg');

        $this->assertEquals(
            'https://testaccount.blob.core.windows.net/testcontainer/path/to/file.jpg',
            $url
        );
    }

    #[Test]
    public function it_uses_custom_public_url_when_configured(): void
    {
        $client = new AzureBlobStorageClient(
            accountName: 'testaccount',
            accountKey: base64_encode('test-key-1234567890'),
            container: 'testcontainer',
        );

        $adapter = new AzureBlobStorageAdapter(
            $client,
            'https://cdn.example.com'
        );

        $url = $adapter->getUrl('path/to/file.jpg');

        $this->assertEquals('https://cdn.example.com/path/to/file.jpg', $url);
    }

    #[Test]
    public function it_guesses_mime_type_for_write(): void
    {
        Http::fake([
            "{$this->azureUrl}/image.jpg" => Http::response('', 201),
        ]);

        $this->adapter->write('image.jpg', 'fake-image-data', new Config);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Content-Type', 'image/jpeg');
        });
    }

    #[Test]
    public function it_uses_provided_content_type_for_write(): void
    {
        Http::fake([
            "{$this->azureUrl}/data.bin" => Http::response('', 201),
        ]);

        $this->adapter->write('data.bin', 'binary-data', new Config([
            'content_type' => 'application/x-custom',
        ]));

        Http::assertSent(function ($request) {
            return $request->hasHeader('Content-Type', 'application/x-custom');
        });
    }

    #[Test]
    public function it_returns_private_visibility_when_container_has_no_public_access(): void
    {
        Http::fake([
            "{$this->azureUrl}?comp=acl&restype=container" => Http::response('', 200, [
                'x-ms-blob-public-access' => '',
            ]),
        ]);

        $visibility = $this->adapter->visibility('test.txt');

        $this->assertEquals(Visibility::PRIVATE, $visibility->visibility());
    }

    #[Test]
    public function it_returns_public_visibility_when_container_has_blob_access(): void
    {
        Http::fake([
            "{$this->azureUrl}?comp=acl&restype=container" => Http::response('', 200, [
                'x-ms-blob-public-access' => 'blob',
            ]),
        ]);

        $visibility = $this->adapter->visibility('test.txt');

        $this->assertEquals(Visibility::PUBLIC, $visibility->visibility());
    }

    #[Test]
    public function it_returns_public_visibility_when_container_has_container_access(): void
    {
        Http::fake([
            "{$this->azureUrl}?comp=acl&restype=container" => Http::response('', 200, [
                'x-ms-blob-public-access' => 'container',
            ]),
        ]);

        $visibility = $this->adapter->visibility('test.txt');

        $this->assertEquals(Visibility::PUBLIC, $visibility->visibility());
    }

    #[Test]
    public function it_caches_visibility_result(): void
    {
        Http::fake([
            "{$this->azureUrl}?comp=acl&restype=container" => Http::response('', 200, [
                'x-ms-blob-public-access' => 'blob',
            ]),
        ]);

        $this->adapter->visibility('test.txt');
        $this->adapter->visibility('other.txt');

        Http::assertSentCount(1);
    }

    #[Test]
    public function it_throws_when_setting_visibility_by_default(): void
    {
        $this->expectException(UnableToSetVisibility::class);

        $this->adapter->setVisibility('test.txt', Visibility::PUBLIC);
    }

    #[Test]
    public function it_can_set_visibility_when_allowed(): void
    {
        Http::fake([
            "{$this->azureUrl}?comp=acl&restype=container" => Http::response('', 200),
        ]);

        $client = new AzureBlobStorageClient(
            accountName: 'testaccount',
            accountKey: base64_encode('test-key-1234567890'),
            container: 'testcontainer',
        );

        $adapter = new AzureBlobStorageAdapter(
            client: $client,
            publicUrl: '',
            defaultVisibility: Visibility::PRIVATE,
            allowSetVisibility: true,
        );

        $adapter->setVisibility('test.txt', Visibility::PUBLIC);

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && str_contains($request->url(), 'comp=acl')
                && str_contains($request->url(), 'restype=container')
                && $request->hasHeader('x-ms-blob-public-access', 'blob');
        });
    }

    #[Test]
    public function it_sets_private_visibility_by_omitting_public_access_header(): void
    {
        Http::fake([
            "{$this->azureUrl}?comp=acl&restype=container" => Http::response('', 200),
        ]);

        $client = new AzureBlobStorageClient(
            accountName: 'testaccount',
            accountKey: base64_encode('test-key-1234567890'),
            container: 'testcontainer',
        );

        $adapter = new AzureBlobStorageAdapter(
            client: $client,
            publicUrl: '',
            defaultVisibility: Visibility::PRIVATE,
            allowSetVisibility: true,
        );

        $adapter->setVisibility('test.txt', Visibility::PRIVATE);

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && str_contains($request->url(), 'comp=acl')
                && ! $request->hasHeader('x-ms-blob-public-access');
        });
    }

    #[Test]
    public function it_updates_cached_visibility_after_set(): void
    {
        Http::fake([
            "{$this->azureUrl}?comp=acl&restype=container" => Http::response('', 200),
        ]);

        $client = new AzureBlobStorageClient(
            accountName: 'testaccount',
            accountKey: base64_encode('test-key-1234567890'),
            container: 'testcontainer',
        );

        $adapter = new AzureBlobStorageAdapter(
            client: $client,
            publicUrl: '',
            defaultVisibility: Visibility::PRIVATE,
            allowSetVisibility: true,
        );

        $adapter->setVisibility('test.txt', Visibility::PUBLIC);
        $visibility = $adapter->visibility('test.txt');

        $this->assertEquals(Visibility::PUBLIC, $visibility->visibility());

        // Only 1 HTTP call (the PUT), no GET needed because cache was updated
        Http::assertSentCount(1);
    }
}
