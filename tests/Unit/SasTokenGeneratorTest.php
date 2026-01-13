<?php

namespace AliNauf\AzureStorage\Tests\Unit;

use AliNauf\AzureStorage\Support\SasTokenGenerator;
use DateTime;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SasTokenGeneratorTest extends TestCase
{
    private SasTokenGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new SasTokenGenerator(
            accountName: 'testaccount',
            accountKey: base64_encode('test-key-1234567890'),
            apiVersion: '2023-08-03',
        );
    }

    #[Test]
    public function it_generates_blob_sas_token(): void
    {
        $expiry = new DateTime('2025-01-01 12:00:00');

        $sas = $this->generator->generateBlobSas(
            container: 'mycontainer',
            blob: 'myblob.jpg',
            expiry: $expiry,
            permissions: 'r',
        );

        // Parse the query string
        parse_str($sas, $params);

        $this->assertEquals('r', $params['sp']);
        $this->assertEquals('2025-01-01T12:00:00Z', $params['se']);
        $this->assertEquals('2023-08-03', $params['sv']);
        $this->assertEquals('b', $params['sr']);
        $this->assertEquals('https', $params['spr']);
        $this->assertArrayHasKey('sig', $params);
        $this->assertNotEmpty($params['sig']);
    }

    #[Test]
    public function it_generates_blob_sas_with_start_time(): void
    {
        $start = new DateTime('2024-12-01 00:00:00');
        $expiry = new DateTime('2025-01-01 12:00:00');

        $sas = $this->generator->generateBlobSas(
            container: 'mycontainer',
            blob: 'myblob.jpg',
            expiry: $expiry,
            permissions: 'r',
            start: $start,
        );

        parse_str($sas, $params);

        $this->assertEquals('2024-12-01T00:00:00Z', $params['st']);
    }

    #[Test]
    public function it_generates_blob_sas_with_ip_restriction(): void
    {
        $expiry = new DateTime('2025-01-01 12:00:00');

        $sas = $this->generator->generateBlobSas(
            container: 'mycontainer',
            blob: 'myblob.jpg',
            expiry: $expiry,
            permissions: 'r',
            ipRange: '168.1.5.60-168.1.5.70',
        );

        parse_str($sas, $params);

        $this->assertEquals('168.1.5.60-168.1.5.70', $params['sip']);
    }

    #[Test]
    public function it_generates_container_sas_token(): void
    {
        $expiry = new DateTime('2025-01-01 12:00:00');

        $sas = $this->generator->generateContainerSas(
            container: 'mycontainer',
            expiry: $expiry,
            permissions: 'rl',
        );

        parse_str($sas, $params);

        $this->assertEquals('rl', $params['sp']);
        $this->assertEquals('c', $params['sr']);
        $this->assertArrayHasKey('sig', $params);
    }

    #[Test]
    public function it_generates_full_signed_url(): void
    {
        $expiry = new DateTime('2025-01-01 12:00:00');

        $url = $this->generator->generateSignedUrl(
            container: 'mycontainer',
            blob: 'path/to/myblob.jpg',
            expiry: $expiry,
        );

        $this->assertStringStartsWith(
            'https://testaccount.blob.core.windows.net/mycontainer/path/to/myblob.jpg?',
            $url
        );

        // Parse query string from URL
        $parts = parse_url($url);
        parse_str($parts['query'], $params);

        $this->assertEquals('r', $params['sp']);
        $this->assertArrayHasKey('sig', $params);
    }

    #[Test]
    public function it_generates_different_signatures_for_different_blobs(): void
    {
        $expiry = new DateTime('2025-01-01 12:00:00');

        $sas1 = $this->generator->generateBlobSas('container', 'blob1.jpg', $expiry);
        $sas2 = $this->generator->generateBlobSas('container', 'blob2.jpg', $expiry);

        parse_str($sas1, $params1);
        parse_str($sas2, $params2);

        $this->assertNotEquals($params1['sig'], $params2['sig']);
    }

    #[Test]
    public function it_generates_different_signatures_for_different_permissions(): void
    {
        $expiry = new DateTime('2025-01-01 12:00:00');

        $sasRead = $this->generator->generateBlobSas('container', 'blob.jpg', $expiry, 'r');
        $sasWrite = $this->generator->generateBlobSas('container', 'blob.jpg', $expiry, 'rw');

        parse_str($sasRead, $paramsRead);
        parse_str($sasWrite, $paramsWrite);

        $this->assertNotEquals($paramsRead['sig'], $paramsWrite['sig']);
    }

    #[Test]
    public function it_strips_leading_slash_from_blob_path(): void
    {
        $expiry = new DateTime('2025-01-01 12:00:00');

        $url1 = $this->generator->generateSignedUrl('container', '/blob.jpg', $expiry);
        $url2 = $this->generator->generateSignedUrl('container', 'blob.jpg', $expiry);

        $this->assertEquals($url1, $url2);
    }
}
