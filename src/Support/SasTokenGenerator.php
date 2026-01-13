<?php

namespace AliNauf\AzureStorage\Support;

use DateTimeInterface;

class SasTokenGenerator
{
    /**
     * SAS permission constants.
     */
    public const PERMISSION_READ = 'r';

    public const PERMISSION_WRITE = 'w';

    public const PERMISSION_DELETE = 'd';

    public const PERMISSION_LIST = 'l';

    public const PERMISSION_ADD = 'a';

    public const PERMISSION_CREATE = 'c';

    /**
     * SAS signed resource types.
     */
    protected const RESOURCE_BLOB = 'b';

    protected const RESOURCE_CONTAINER = 'c';

    public function __construct(
        protected string $accountName,
        protected string $accountKey,
        protected string $apiVersion = '2023-08-03',
    ) {}

    /**
     * Generate a SAS token for a blob.
     *
     * @param  string  $container  The container name
     * @param  string  $blob  The blob path
     * @param  DateTimeInterface  $expiry  Token expiration time
     * @param  string  $permissions  Permission string (e.g., 'r', 'rw', 'rwd')
     * @param  DateTimeInterface|null  $start  Token start time
     * @param  string|null  $ipRange  IP range restriction (e.g., '168.1.5.60-168.1.5.70')
     * @param  string  $protocol  Protocol restriction ('https' or 'https,http')
     * @return string The SAS query string (without leading '?')
     */
    public function generateBlobSas(
        string $container,
        string $blob,
        DateTimeInterface $expiry,
        string $permissions = self::PERMISSION_READ,
        ?DateTimeInterface $start = null,
        ?string $ipRange = null,
        string $protocol = 'https',
    ): string {
        $blob = ltrim($blob, '/');

        $params = [
            'sp' => $permissions,
            'st' => $start?->format('Y-m-d\TH:i:s\Z'),
            'se' => $expiry->format('Y-m-d\TH:i:s\Z'),
            'spr' => $protocol,
            'sv' => $this->apiVersion,
            'sr' => self::RESOURCE_BLOB,
            'sip' => $ipRange,
        ];

        // Filter out null values
        $params = array_filter($params, fn ($value) => $value !== null);

        // Generate signature
        $stringToSign = $this->buildBlobStringToSign(
            permissions: $permissions,
            start: $start,
            expiry: $expiry,
            container: $container,
            blob: $blob,
            ipRange: $ipRange,
            protocol: $protocol,
        );

        $signature = $this->sign($stringToSign);
        $params['sig'] = $signature;

        return http_build_query($params);
    }

    /**
     * Generate a SAS token for a container.
     *
     * @param  string  $container  The container name
     * @param  DateTimeInterface  $expiry  Token expiration time
     * @param  string  $permissions  Permission string (e.g., 'rl', 'rwdl')
     * @param  DateTimeInterface|null  $start  Token start time
     * @param  string|null  $ipRange  IP range restriction
     * @param  string  $protocol  Protocol restriction
     * @return string The SAS query string (without leading '?')
     */
    public function generateContainerSas(
        string $container,
        DateTimeInterface $expiry,
        string $permissions = self::PERMISSION_READ.self::PERMISSION_LIST,
        ?DateTimeInterface $start = null,
        ?string $ipRange = null,
        string $protocol = 'https',
    ): string {
        $params = [
            'sp' => $permissions,
            'st' => $start?->format('Y-m-d\TH:i:s\Z'),
            'se' => $expiry->format('Y-m-d\TH:i:s\Z'),
            'spr' => $protocol,
            'sv' => $this->apiVersion,
            'sr' => self::RESOURCE_CONTAINER,
            'sip' => $ipRange,
        ];

        // Filter out null values
        $params = array_filter($params, fn ($value) => $value !== null);

        // Generate signature
        $stringToSign = $this->buildContainerStringToSign(
            permissions: $permissions,
            start: $start,
            expiry: $expiry,
            container: $container,
            ipRange: $ipRange,
            protocol: $protocol,
        );

        $signature = $this->sign($stringToSign);
        $params['sig'] = $signature;

        return http_build_query($params);
    }

    /**
     * Generate a full signed URL for a blob.
     *
     * @param  string  $container  The container name
     * @param  string  $blob  The blob path
     * @param  DateTimeInterface  $expiry  Token expiration time
     * @param  string  $permissions  Permission string
     * @return string The full signed URL
     */
    public function generateSignedUrl(
        string $container,
        string $blob,
        DateTimeInterface $expiry,
        string $permissions = self::PERMISSION_READ,
    ): string {
        $blob = ltrim($blob, '/');
        $sas = $this->generateBlobSas($container, $blob, $expiry, $permissions);

        return "https://{$this->accountName}.blob.core.windows.net/{$container}/{$blob}?{$sas}";
    }

    /**
     * Build the string to sign for a blob SAS.
     */
    protected function buildBlobStringToSign(
        string $permissions,
        ?DateTimeInterface $start,
        DateTimeInterface $expiry,
        string $container,
        string $blob,
        ?string $ipRange,
        string $protocol,
    ): string {
        $canonicalizedResource = "/blob/{$this->accountName}/{$container}/{$blob}";

        return implode("\n", [
            $permissions,                                    // signedPermissions
            $start?->format('Y-m-d\TH:i:s\Z') ?? '',        // signedStart
            $expiry->format('Y-m-d\TH:i:s\Z'),              // signedExpiry
            $canonicalizedResource,                          // canonicalizedResource
            '',                                              // signedIdentifier
            $ipRange ?? '',                                  // signedIP
            $protocol,                                       // signedProtocol
            $this->apiVersion,                               // signedVersion
            self::RESOURCE_BLOB,                             // signedResource
            '',                                              // signedSnapshotTime
            '',                                              // signedEncryptionScope
            '',                                              // rscc (Cache-Control)
            '',                                              // rscd (Content-Disposition)
            '',                                              // rsce (Content-Encoding)
            '',                                              // rscl (Content-Language)
            '',                                              // rsct (Content-Type)
        ]);
    }

    /**
     * Build the string to sign for a container SAS.
     */
    protected function buildContainerStringToSign(
        string $permissions,
        ?DateTimeInterface $start,
        DateTimeInterface $expiry,
        string $container,
        ?string $ipRange,
        string $protocol,
    ): string {
        $canonicalizedResource = "/blob/{$this->accountName}/{$container}";

        return implode("\n", [
            $permissions,                                    // signedPermissions
            $start?->format('Y-m-d\TH:i:s\Z') ?? '',        // signedStart
            $expiry->format('Y-m-d\TH:i:s\Z'),              // signedExpiry
            $canonicalizedResource,                          // canonicalizedResource
            '',                                              // signedIdentifier
            $ipRange ?? '',                                  // signedIP
            $protocol,                                       // signedProtocol
            $this->apiVersion,                               // signedVersion
            self::RESOURCE_CONTAINER,                        // signedResource
            '',                                              // signedSnapshotTime
            '',                                              // signedEncryptionScope
            '',                                              // rscc (Cache-Control)
            '',                                              // rscd (Content-Disposition)
            '',                                              // rsce (Content-Encoding)
            '',                                              // rscl (Content-Language)
            '',                                              // rsct (Content-Type)
        ]);
    }

    /**
     * Sign a string using HMAC-SHA256.
     */
    protected function sign(string $stringToSign): string
    {
        $key = base64_decode($this->accountKey);
        $signature = hash_hmac('sha256', $stringToSign, $key, true);

        return base64_encode($signature);
    }
}
