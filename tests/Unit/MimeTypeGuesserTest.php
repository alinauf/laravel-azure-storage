<?php

namespace AliNauf\AzureStorage\Tests\Unit;

use AliNauf\AzureStorage\Support\MimeTypeGuesser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MimeTypeGuesserTest extends TestCase
{
    #[Test]
    #[DataProvider('mimeTypeProvider')]
    public function it_guesses_mime_types_correctly(string $path, string $expectedMimeType): void
    {
        $this->assertEquals($expectedMimeType, MimeTypeGuesser::guess($path));
    }

    public static function mimeTypeProvider(): array
    {
        return [
            // Images
            ['photo.jpg', 'image/jpeg'],
            ['photo.jpeg', 'image/jpeg'],
            ['image.png', 'image/png'],
            ['animation.gif', 'image/gif'],
            ['modern.webp', 'image/webp'],
            ['vector.svg', 'image/svg+xml'],

            // Videos
            ['video.mp4', 'video/mp4'],
            ['video.webm', 'video/webm'],
            ['video.mov', 'video/quicktime'],
            ['video.avi', 'video/x-msvideo'],

            // Audio
            ['audio.mp3', 'audio/mpeg'],
            ['audio.wav', 'audio/wav'],

            // Documents
            ['document.pdf', 'application/pdf'],
            ['document.json', 'application/json'],
            ['document.txt', 'text/plain'],

            // Case insensitive
            ['photo.JPG', 'image/jpeg'],
            ['photo.PNG', 'image/png'],
            ['VIDEO.MP4', 'video/mp4'],

            // Unknown extension
            ['file.unknown', 'application/octet-stream'],
            ['file', 'application/octet-stream'],

            // Paths with directories
            ['path/to/photo.jpg', 'image/jpeg'],
            ['/absolute/path/to/video.mp4', 'video/mp4'],
        ];
    }

    #[Test]
    public function it_returns_extension_for_mime_type(): void
    {
        // Note: 'jpeg' is returned instead of 'jpg' because both map to 'image/jpeg'
        // and array_flip keeps the last value
        $this->assertEquals('jpeg', MimeTypeGuesser::getExtension('image/jpeg'));
        $this->assertEquals('png', MimeTypeGuesser::getExtension('image/png'));
        $this->assertEquals('pdf', MimeTypeGuesser::getExtension('application/pdf'));
        $this->assertNull(MimeTypeGuesser::getExtension('application/unknown'));
    }
}
