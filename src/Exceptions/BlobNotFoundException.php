<?php

namespace AliNauf\AzureStorage\Exceptions;

class BlobNotFoundException extends AzureStorageException
{
    public static function forPath(string $path): self
    {
        return new self(
            message: "Blob not found: {$path}",
            errorCode: 'BlobNotFound',
            statusCode: 404
        );
    }
}
