<?php

namespace AliNauf\AzureStorage;

use AliNauf\AzureStorage\Exceptions\AzureStorageException;
use AliNauf\AzureStorage\Exceptions\BlobNotFoundException;
use AliNauf\AzureStorage\Exceptions\InvalidConfigurationException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class AzureBlobStorageClient
{
    protected string $apiVersion;

    public function __construct(
        protected string $accountName,
        protected string $accountKey,
        protected string $container,
        ?string $apiVersion = null,
    ) {
        $this->validateConfiguration();
        $this->apiVersion = $apiVersion ?? '2023-08-03';
    }

    /**
     * Validate the configuration.
     *
     * @throws InvalidConfigurationException
     */
    protected function validateConfiguration(): void
    {
        if (empty($this->accountName)) {
            throw InvalidConfigurationException::missingAccountName();
        }

        if (empty($this->accountKey)) {
            throw InvalidConfigurationException::missingAccountKey();
        }

        if (empty($this->container)) {
            throw InvalidConfigurationException::missingContainer();
        }
    }

    /**
     * Upload a blob to Azure Storage.
     */
    public function putBlob(string $path, string $contents, string $contentType = 'application/octet-stream'): Response
    {
        $url = $this->getBlobUrl($path);
        $date = $this->getDateHeader();
        $contentLength = strlen($contents);

        $headers = [
            'x-ms-date' => $date,
            'x-ms-version' => $this->apiVersion,
            'x-ms-blob-type' => 'BlockBlob',
            'Content-Type' => $contentType,
            'Content-Length' => $contentLength,
        ];

        $signature = $this->generateSignature(
            verb: 'PUT',
            contentLength: $contentLength,
            contentType: $contentType,
            headers: $headers,
            path: $path,
        );

        $headers['Authorization'] = "SharedKey {$this->accountName}:{$signature}";

        return $this->http()
            ->withHeaders($headers)
            ->withBody($contents, $contentType)
            ->put($url);
    }

    /**
     * Get a blob from Azure Storage.
     */
    public function getBlob(string $path): Response
    {
        $url = $this->getBlobUrl($path);
        $date = $this->getDateHeader();

        $headers = [
            'x-ms-date' => $date,
            'x-ms-version' => $this->apiVersion,
        ];

        $signature = $this->generateSignature(
            verb: 'GET',
            headers: $headers,
            path: $path,
        );

        $headers['Authorization'] = "SharedKey {$this->accountName}:{$signature}";

        return $this->http()->withHeaders($headers)->get($url);
    }

    /**
     * Delete a blob from Azure Storage.
     */
    public function deleteBlob(string $path): Response
    {
        $url = $this->getBlobUrl($path);
        $date = $this->getDateHeader();

        $headers = [
            'x-ms-date' => $date,
            'x-ms-version' => $this->apiVersion,
        ];

        $signature = $this->generateSignature(
            verb: 'DELETE',
            headers: $headers,
            path: $path,
        );

        $headers['Authorization'] = "SharedKey {$this->accountName}:{$signature}";

        return $this->http()->withHeaders($headers)->delete($url);
    }

    /**
     * Get blob properties (metadata, size, last modified, etc.).
     *
     * @return array{content_length: int, content_type: string, last_modified: int, etag: string}
     *
     * @throws BlobNotFoundException
     * @throws AzureStorageException
     */
    public function getBlobProperties(string $path): array
    {
        $url = $this->getBlobUrl($path);
        $date = $this->getDateHeader();

        $headers = [
            'x-ms-date' => $date,
            'x-ms-version' => $this->apiVersion,
        ];

        $signature = $this->generateSignature(
            verb: 'HEAD',
            headers: $headers,
            path: $path,
        );

        $headers['Authorization'] = "SharedKey {$this->accountName}:{$signature}";

        $response = $this->http()->withHeaders($headers)->head($url);

        if ($response->status() === 404) {
            throw BlobNotFoundException::forPath($path);
        }

        if (! $response->successful()) {
            throw AzureStorageException::fromResponse($response->body(), $response->status());
        }

        return [
            'content_length' => (int) $response->header('Content-Length'),
            'content_type' => $response->header('Content-Type') ?? 'application/octet-stream',
            'last_modified' => strtotime($response->header('Last-Modified') ?? 'now'),
            'etag' => $response->header('ETag') ?? '',
        ];
    }

    /**
     * Check if a blob exists.
     */
    public function blobExists(string $path): bool
    {
        try {
            $this->getBlobProperties($path);

            return true;
        } catch (BlobNotFoundException) {
            return false;
        }
    }

    /**
     * List blobs in the container.
     *
     * @param  string  $prefix  Filter blobs by prefix
     * @param  int  $maxResults  Maximum number of results to return
     * @return array{blobs: array<array{name: string, size: int, last_modified: int, content_type: string}>, next_marker: ?string}
     *
     * @throws AzureStorageException
     */
    public function listBlobs(string $prefix = '', int $maxResults = 5000, ?string $marker = null): array
    {
        $date = $this->getDateHeader();

        $queryParams = [
            'restype' => 'container',
            'comp' => 'list',
            'maxresults' => $maxResults,
        ];

        if ($prefix !== '') {
            $queryParams['prefix'] = $prefix;
        }

        if ($marker !== null) {
            $queryParams['marker'] = $marker;
        }

        $url = $this->getContainerUrl().'?'.http_build_query($queryParams);

        $headers = [
            'x-ms-date' => $date,
            'x-ms-version' => $this->apiVersion,
        ];

        $signature = $this->generateSignature(
            verb: 'GET',
            headers: $headers,
            path: '',
            queryParams: $queryParams,
        );

        $headers['Authorization'] = "SharedKey {$this->accountName}:{$signature}";

        $response = $this->http()->withHeaders($headers)->get($url);

        if (! $response->successful()) {
            throw AzureStorageException::fromResponse($response->body(), $response->status());
        }

        return $this->parseListBlobsResponse($response->body());
    }

    /**
     * Copy a blob to a new location.
     *
     * @throws AzureStorageException
     */
    public function copyBlob(string $source, string $destination): Response
    {
        $url = $this->getBlobUrl($destination);
        $sourceUrl = $this->getBlobUrl($source);
        $date = $this->getDateHeader();

        $headers = [
            'x-ms-date' => $date,
            'x-ms-version' => $this->apiVersion,
            'x-ms-copy-source' => $sourceUrl,
        ];

        $signature = $this->generateSignature(
            verb: 'PUT',
            headers: $headers,
            path: $destination,
        );

        $headers['Authorization'] = "SharedKey {$this->accountName}:{$signature}";

        return $this->http()->withHeaders($headers)->put($url);
    }

    /**
     * Get the public URL for a blob.
     */
    public function getPublicUrl(string $path): string
    {
        return $this->getBlobUrl($path);
    }

    /**
     * Get the account name.
     */
    public function getAccountName(): string
    {
        return $this->accountName;
    }

    /**
     * Get the container name.
     */
    public function getContainer(): string
    {
        return $this->container;
    }

    /**
     * Get the HTTP client instance.
     */
    protected function http(): PendingRequest
    {
        return Http::withOptions([
            'timeout' => 300,
            'connect_timeout' => 30,
        ]);
    }

    /**
     * Get the full blob URL.
     */
    protected function getBlobUrl(string $path): string
    {
        $path = ltrim($path, '/');

        return "https://{$this->accountName}.blob.core.windows.net/{$this->container}/{$path}";
    }

    /**
     * Get the container URL.
     */
    protected function getContainerUrl(): string
    {
        return "https://{$this->accountName}.blob.core.windows.net/{$this->container}";
    }

    /**
     * Get the current date in RFC 1123 format.
     */
    protected function getDateHeader(): string
    {
        return gmdate('D, d M Y H:i:s').' GMT';
    }

    /**
     * Generate the authorization signature for a request.
     *
     * @param  array<string, string>  $headers
     * @param  array<string, string|int>  $queryParams
     */
    protected function generateSignature(
        string $verb,
        int $contentLength = 0,
        string $contentType = '',
        array $headers = [],
        string $path = '',
        array $queryParams = [],
    ): string {
        $canonicalizedHeaders = $this->buildCanonicalizedHeaders($headers);
        $canonicalizedResource = $this->buildCanonicalizedResource($path, $queryParams);

        // For Content-Length, empty string if zero (per Azure docs)
        $contentLengthStr = $contentLength > 0 ? (string) $contentLength : '';

        $stringToSign = implode("\n", [
            $verb,                    // VERB
            '',                       // Content-Encoding
            '',                       // Content-Language
            $contentLengthStr,        // Content-Length
            '',                       // Content-MD5
            $contentType,             // Content-Type
            '',                       // Date (empty when using x-ms-date)
            '',                       // If-Modified-Since
            '',                       // If-Match
            '',                       // If-None-Match
            '',                       // If-Unmodified-Since
            '',                       // Range
            $canonicalizedHeaders.$canonicalizedResource,
        ]);

        $key = base64_decode($this->accountKey);
        $signature = hash_hmac('sha256', $stringToSign, $key, true);

        return base64_encode($signature);
    }

    /**
     * Build the canonicalized headers string.
     *
     * @param  array<string, string>  $headers
     */
    protected function buildCanonicalizedHeaders(array $headers): string
    {
        $canonicalized = [];

        foreach ($headers as $name => $value) {
            $lowerName = strtolower($name);
            if (str_starts_with($lowerName, 'x-ms-')) {
                $canonicalized[$lowerName] = trim($value);
            }
        }

        ksort($canonicalized);

        $result = '';
        foreach ($canonicalized as $name => $value) {
            $result .= "{$name}:{$value}\n";
        }

        return $result;
    }

    /**
     * Build the canonicalized resource string.
     *
     * @param  array<string, string|int>  $queryParams
     */
    protected function buildCanonicalizedResource(string $path, array $queryParams = []): string
    {
        $path = ltrim($path, '/');

        $resource = "/{$this->accountName}/{$this->container}";

        if ($path !== '') {
            $resource .= "/{$path}";
        }

        // Add query parameters in alphabetical order
        if (! empty($queryParams)) {
            ksort($queryParams);
            foreach ($queryParams as $key => $value) {
                $resource .= "\n{$key}:{$value}";
            }
        }

        return $resource;
    }

    /**
     * Parse the List Blobs XML response.
     *
     * @return array{blobs: array<array{name: string, size: int, last_modified: int, content_type: string}>, next_marker: ?string}
     */
    protected function parseListBlobsResponse(string $xml): array
    {
        $blobs = [];
        $nextMarker = null;

        $doc = simplexml_load_string($xml);
        if ($doc === false) {
            return ['blobs' => [], 'next_marker' => null];
        }

        if (isset($doc->NextMarker) && (string) $doc->NextMarker !== '') {
            $nextMarker = (string) $doc->NextMarker;
        }

        if (isset($doc->Blobs->Blob)) {
            foreach ($doc->Blobs->Blob as $blob) {
                $blobs[] = [
                    'name' => (string) $blob->Name,
                    'size' => (int) ($blob->Properties->{'Content-Length'} ?? 0),
                    'last_modified' => strtotime((string) ($blob->Properties->{'Last-Modified'} ?? 'now')),
                    'content_type' => (string) ($blob->Properties->{'Content-Type'} ?? 'application/octet-stream'),
                ];
            }
        }

        return [
            'blobs' => $blobs,
            'next_marker' => $nextMarker,
        ];
    }
}
