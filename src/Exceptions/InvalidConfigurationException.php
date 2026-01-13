<?php

namespace AliNauf\AzureStorage\Exceptions;

use InvalidArgumentException;

class InvalidConfigurationException extends InvalidArgumentException
{
    public static function missingAccountName(): self
    {
        return new self('Azure Storage account name is required.');
    }

    public static function missingAccountKey(): self
    {
        return new self('Azure Storage account key is required.');
    }

    public static function missingContainer(): self
    {
        return new self('Azure Storage container name is required.');
    }
}
