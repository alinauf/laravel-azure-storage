<?php

namespace AliNauf\AzureStorage;

use AliNauf\AzureStorage\Exceptions\BlobNotFoundException;
use AliNauf\AzureStorage\Support\MimeTypeGuesser;
use Generator;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;

class AzureBlobStorageAdapter implements FilesystemAdapter
{
    public function __construct(
        protected AzureBlobStorageClient $client,
        protected string $publicUrl = '',
    ) {}

    /**
     * Check if a file exists.
     */
    public function fileExists(string $path): bool
    {
        return $this->client->blobExists($path);
    }

    /**
     * Check if a directory exists.
     *
     * Azure Blob Storage doesn't have real directories, so we check if any blobs
     * exist with the given prefix.
     */
    public function directoryExists(string $path): bool
    {
        $path = rtrim($path, '/').'/';
        $result = $this->client->listBlobs($path, 1);

        return ! empty($result['blobs']);
    }

    /**
     * Write a file.
     */
    public function write(string $path, string $contents, Config $config): void
    {
        $contentType = $config->get('content_type', MimeTypeGuesser::guess($path));

        $response = $this->client->putBlob($path, $contents, $contentType);

        if (! $response->successful()) {
            throw UnableToWriteFile::atLocation($path, $response->body());
        }
    }

    /**
     * Write a file from a stream.
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->write($path, stream_get_contents($contents), $config);
    }

    /**
     * Read a file.
     */
    public function read(string $path): string
    {
        $response = $this->client->getBlob($path);

        if ($response->status() === 404) {
            throw UnableToReadFile::fromLocation($path, 'Blob not found');
        }

        if (! $response->successful()) {
            throw UnableToReadFile::fromLocation($path, $response->body());
        }

        return $response->body();
    }

    /**
     * Read a file as a stream.
     *
     * @return resource
     */
    public function readStream(string $path)
    {
        $contents = $this->read($path);
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw UnableToReadFile::fromLocation($path, 'Failed to create stream');
        }

        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }

    /**
     * Delete a file.
     */
    public function delete(string $path): void
    {
        $response = $this->client->deleteBlob($path);

        // 404 is acceptable - file doesn't exist
        if (! $response->successful() && $response->status() !== 404) {
            throw UnableToDeleteFile::atLocation($path, $response->body());
        }
    }

    /**
     * Delete a directory.
     *
     * Deletes all blobs with the given prefix.
     */
    public function deleteDirectory(string $path): void
    {
        $prefix = rtrim($path, '/').'/';
        $marker = null;

        do {
            $result = $this->client->listBlobs($prefix, 1000, $marker);

            foreach ($result['blobs'] as $blob) {
                $this->delete($blob['name']);
            }

            $marker = $result['next_marker'];
        } while ($marker !== null);
    }

    /**
     * Create a directory.
     *
     * Azure doesn't have real directories, so this is a no-op.
     */
    public function createDirectory(string $path, Config $config): void
    {
        // Azure doesn't have real directories
    }

    /**
     * Set the visibility of a file.
     *
     * Visibility is controlled at the container level in Azure.
     */
    public function setVisibility(string $path, string $visibility): void
    {
        // Visibility is controlled at the container level in Azure
    }

    /**
     * Get the visibility of a file.
     */
    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, null, 'public');
    }

    /**
     * Get the MIME type of a file.
     */
    public function mimeType(string $path): FileAttributes
    {
        try {
            $properties = $this->client->getBlobProperties($path);

            return new FileAttributes($path, null, null, null, $properties['content_type']);
        } catch (BlobNotFoundException $e) {
            return new FileAttributes($path, null, null, null, MimeTypeGuesser::guess($path));
        }
    }

    /**
     * Get the last modified timestamp of a file.
     */
    public function lastModified(string $path): FileAttributes
    {
        try {
            $properties = $this->client->getBlobProperties($path);

            return new FileAttributes($path, null, null, $properties['last_modified']);
        } catch (BlobNotFoundException $e) {
            return new FileAttributes($path);
        }
    }

    /**
     * Get the file size.
     */
    public function fileSize(string $path): FileAttributes
    {
        try {
            $properties = $this->client->getBlobProperties($path);

            return new FileAttributes($path, $properties['content_length']);
        } catch (BlobNotFoundException $e) {
            return new FileAttributes($path);
        }
    }

    /**
     * List the contents of a directory.
     *
     * @return Generator<FileAttributes|DirectoryAttributes>
     */
    public function listContents(string $path, bool $deep): iterable
    {
        $prefix = $path === '' ? '' : rtrim($path, '/').'/';
        $marker = null;
        $directories = [];

        do {
            $result = $this->client->listBlobs($prefix, 5000, $marker);

            foreach ($result['blobs'] as $blob) {
                $blobPath = $blob['name'];

                // If not deep listing, we need to handle "virtual directories"
                if (! $deep && $prefix !== '') {
                    $relativePath = substr($blobPath, strlen($prefix));
                    $slashPos = strpos($relativePath, '/');

                    if ($slashPos !== false) {
                        // This is a file in a subdirectory
                        $dirName = $prefix.substr($relativePath, 0, $slashPos);
                        if (! isset($directories[$dirName])) {
                            $directories[$dirName] = true;
                            yield new DirectoryAttributes($dirName);
                        }

                        continue;
                    }
                }

                yield new FileAttributes(
                    $blobPath,
                    $blob['size'],
                    null,
                    $blob['last_modified'],
                    $blob['content_type']
                );
            }

            $marker = $result['next_marker'];
        } while ($marker !== null);
    }

    /**
     * Move a file.
     */
    public function move(string $source, string $destination, Config $config): void
    {
        $this->copy($source, $destination, $config);
        $this->delete($source);
    }

    /**
     * Copy a file.
     */
    public function copy(string $source, string $destination, Config $config): void
    {
        $response = $this->client->copyBlob($source, $destination);

        if (! $response->successful()) {
            throw UnableToWriteFile::atLocation($destination, $response->body());
        }
    }

    /**
     * Get the public URL for a file.
     */
    public function getUrl(string $path): string
    {
        $path = ltrim($path, '/');

        if ($this->publicUrl !== '') {
            return rtrim($this->publicUrl, '/').'/'.$path;
        }

        return $this->client->getPublicUrl($path);
    }

    /**
     * Get the underlying client.
     */
    public function getClient(): AzureBlobStorageClient
    {
        return $this->client;
    }
}
