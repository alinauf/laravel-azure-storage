<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Azure Storage Account Name
    |--------------------------------------------------------------------------
    |
    | Your Azure Storage account name. This can be found in the Azure Portal
    | under your storage account's "Access keys" section.
    |
    */

    'account_name' => env('AZURE_STORAGE_ACCOUNT_NAME'),

    /*
    |--------------------------------------------------------------------------
    | Azure Storage Account Key
    |--------------------------------------------------------------------------
    |
    | Your Azure Storage account key. This can be found in the Azure Portal
    | under your storage account's "Access keys" section.
    |
    */

    'account_key' => env('AZURE_STORAGE_ACCOUNT_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Container Name
    |--------------------------------------------------------------------------
    |
    | The default container to use for blob storage operations.
    |
    */

    'container' => env('AZURE_STORAGE_CONTAINER', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Custom URL
    |--------------------------------------------------------------------------
    |
    | If you're using a CDN or custom domain, specify the base URL here.
    | Leave empty to use the default Azure blob URL format.
    |
    */

    'url' => env('AZURE_STORAGE_URL'),

    /*
    |--------------------------------------------------------------------------
    | API Version
    |--------------------------------------------------------------------------
    |
    | The Azure Storage REST API version to use for requests.
    |
    */

    'api_version' => env('AZURE_STORAGE_API_VERSION', '2023-08-03'),

    /*
    |--------------------------------------------------------------------------
    | SAS Token Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for generating Shared Access Signature (SAS) tokens
    | for temporary URL generation.
    |
    */

    'sas' => [
        // Default expiry time in seconds (1 hour)
        'default_expiry' => 3600,

        // Default permissions for SAS tokens (r=read, w=write, d=delete, l=list)
        'default_permissions' => 'r',
    ],

    /*
    |--------------------------------------------------------------------------
    | Visibility Settings
    |--------------------------------------------------------------------------
    |
    | Azure Blob Storage controls access at the container level, not per-file.
    | The 'default' value is returned when the container access level cannot
    | be determined. Set 'allow_set' to true to allow changing container
    | access level via setVisibility() (requires appropriate permissions).
    |
    */

    'visibility' => [
        'default' => env('AZURE_STORAGE_VISIBILITY', 'private'),
        'allow_set' => env('AZURE_STORAGE_ALLOW_SET_VISIBILITY', false),
    ],

];
