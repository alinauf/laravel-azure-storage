<?php

namespace AliNauf\AzureStorage\Exceptions;

use Exception;

class AzureStorageException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
        public readonly ?int $statusCode = null,
        ?Exception $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Create an exception from an Azure error response.
     */
    public static function fromResponse(string $body, int $statusCode): self
    {
        $errorCode = null;
        $message = $body;

        // Try to parse XML error response
        if (str_contains($body, '<?xml')) {
            try {
                $xml = simplexml_load_string($body);
                if ($xml !== false) {
                    $errorCode = (string) ($xml->Code ?? null);
                    $message = (string) ($xml->Message ?? $body);
                }
            } catch (Exception) {
                // Keep original body as message
            }
        }

        return new self($message, $errorCode, $statusCode);
    }
}
