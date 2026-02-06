# Laravel Azure Storage

[![Tests](https://github.com/alinauf/laravel-azure-storage/actions/workflows/tests.yml/badge.svg)](https://github.com/alinauf/laravel-azure-storage/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/alinauf/laravel-azure-storage.svg)](https://packagist.org/packages/alinauf/laravel-azure-storage)
[![License](https://img.shields.io/packagist/l/alinauf/laravel-azure-storage.svg)](https://packagist.org/packages/alinauf/laravel-azure-storage)

Azure Blob Storage filesystem driver for Laravel using the Azure REST API directly. No deprecated SDK dependencies.

## Features

- **Full Flysystem Integration** - Works seamlessly with Laravel's Storage facade
- **REST API Based** - Uses Azure Blob Storage REST API directly, no deprecated SDKs
- **Shared Key Authentication** - Secure HMAC-SHA256 authentication
- **SAS Token Support** - Generate temporary signed URLs for secure access
- **Full CRUD Operations** - Read, write, delete, copy, move files
- **Directory Operations** - List contents, delete directories
- **Metadata Support** - Get file size, last modified time, MIME type
- **CDN Support** - Custom URL support for Azure CDN or custom domains

## Requirements

- PHP 8.2+
- Laravel 11.0+ or 12.0+

## Installation

Install the package via Composer:

```bash
composer require alinauf/laravel-azure-storage
```

The service provider will be auto-discovered by Laravel.

### Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag=azure-storage-config
```

## Configuration

Add the following environment variables to your `.env` file:

```env
AZURE_STORAGE_ACCOUNT_NAME=your-account-name
AZURE_STORAGE_ACCOUNT_KEY=your-account-key
AZURE_STORAGE_CONTAINER=your-container-name
AZURE_STORAGE_URL=https://your-cdn-url.com  # Optional: for CDN or custom domain
```

Then configure a disk in `config/filesystems.php`:

```php
'disks' => [
    'azure' => [
        'driver' => 'azure',
        'account_name' => env('AZURE_STORAGE_ACCOUNT_NAME'),
        'account_key' => env('AZURE_STORAGE_ACCOUNT_KEY'),
        'container' => env('AZURE_STORAGE_CONTAINER'),
        'url' => env('AZURE_STORAGE_URL'),  // Optional
    ],
],
```

## Usage

### Basic File Operations

```php
use Illuminate\Support\Facades\Storage;

// Use the azure disk
$disk = Storage::disk('azure');

// Upload a file
$disk->put('path/to/file.txt', 'Contents');

// Upload from a stream
$disk->putStream('path/to/file.txt', fopen('/local/file.txt', 'r'));

// Read a file
$contents = $disk->get('path/to/file.txt');

// Check if file exists
if ($disk->exists('path/to/file.txt')) {
    // ...
}

// Delete a file
$disk->delete('path/to/file.txt');

// Copy a file
$disk->copy('source.txt', 'destination.txt');

// Move a file
$disk->move('old-location.txt', 'new-location.txt');
```

### Getting File Information

```php
// Get file size in bytes
$size = $disk->size('path/to/file.txt');

// Get last modified timestamp
$timestamp = $disk->lastModified('path/to/file.txt');

// Get MIME type
$mimeType = $disk->mimeType('path/to/file.txt');
```

### Directory Operations

```php
// List files in a directory
$files = $disk->files('path/to/directory');

// List all files recursively
$allFiles = $disk->allFiles('path/to/directory');

// List directories
$directories = $disk->directories('path/to/directory');

// Delete a directory and all its contents
$disk->deleteDirectory('path/to/directory');
```

### URLs

```php
// Get the public URL
$url = $disk->url('path/to/file.jpg');
// Returns: https://your-account.blob.core.windows.net/container/path/to/file.jpg

// Generate a temporary signed URL (SAS token)
$url = $disk->temporaryUrl('path/to/file.jpg', now()->addHours(1));

// With custom permissions
$url = $disk->temporaryUrl('path/to/file.jpg', now()->addHours(1), [
    'permissions' => 'rw',  // read + write
]);
```

### Using as Default Disk

To use Azure as your default filesystem disk, set in your `.env`:

```env
FILESYSTEM_DISK=azure
```

Then you can use the Storage facade directly:

```php
use Illuminate\Support\Facades\Storage;

Storage::put('file.txt', 'Contents');
$url = Storage::url('file.txt');
```

## Private Files & Temporary URLs

Azure Blob Storage controls access at the **container level**, not per-file like S3. This means all blobs in a container share the same access level. The recommended approach for serving private files is:

1. Keep your container **private** (the default)
2. Use `temporaryUrl()` to generate time-limited SAS-signed URLs for frontend display

### Visibility Model

| Azure Access Level | Flysystem Visibility | Description |
|---|---|---|
| Private (no header) | `private` | No anonymous access. All requests require authentication or SAS token. |
| Blob | `public` | Anonymous read access for individual blobs only. |
| Container | `public` | Anonymous read and list access for all blobs. |

### Recommended Workflow

```php
use Illuminate\Support\Facades\Storage;

$disk = Storage::disk('azure');

// Store a file (private by default)
$disk->put('invoices/invoice-001.pdf', $pdfContents);

// Generate a temporary URL for display (expires in 30 minutes)
$url = $disk->temporaryUrl('invoices/invoice-001.pdf', now()->addMinutes(30));

// Use in a Blade template
// <a href="{{ $url }}">Download Invoice</a>
// <img src="{{ $url }}" alt="Preview">
```

### Visibility Configuration

Add visibility settings to your disk config in `config/filesystems.php`:

```php
'azure' => [
    'driver' => 'azure',
    'account_name' => env('AZURE_STORAGE_ACCOUNT_NAME'),
    'account_key' => env('AZURE_STORAGE_ACCOUNT_KEY'),
    'container' => env('AZURE_STORAGE_CONTAINER'),
    'visibility' => [
        'default' => env('AZURE_STORAGE_VISIBILITY', 'private'),
        'allow_set' => env('AZURE_STORAGE_ALLOW_SET_VISIBILITY', false),
    ],
],
```

- **`visibility.default`** — The fallback visibility when the container access level cannot be determined. Defaults to `private`.
- **`visibility.allow_set`** — When `false` (default), calling `setVisibility()` throws an exception with a helpful message. Set to `true` to allow changing the container's public access level via the Flysystem API.

```php
// Check the container's visibility
$visibility = $disk->getVisibility('any-path'); // 'public' or 'private'

// Set visibility (only when allow_set is true)
$disk->setVisibility('any-path', 'private');
```

> **Note:** Since Azure controls access at the container level, the `$path` argument to `visibility()` and `setVisibility()` is ignored — the operation applies to the entire container.

## SAS Token Generation

For advanced SAS token generation, you can use the `SasTokenGenerator` directly:

```php
use AliNauf\AzureStorage\Support\SasTokenGenerator;

$generator = new SasTokenGenerator(
    accountName: config('azure-storage.account_name'),
    accountKey: config('azure-storage.account_key'),
);

// Generate a blob SAS token
$sas = $generator->generateBlobSas(
    container: 'mycontainer',
    blob: 'path/to/file.jpg',
    expiry: now()->addHours(2),
    permissions: 'r',  // read only
);

// Generate a full signed URL
$url = $generator->generateSignedUrl(
    container: 'mycontainer',
    blob: 'path/to/file.jpg',
    expiry: now()->addHours(2),
);

// Generate a container-level SAS token
$containerSas = $generator->generateContainerSas(
    container: 'mycontainer',
    expiry: now()->addDays(7),
    permissions: 'rl',  // read + list
);
```

### SAS Permissions

| Permission | Description |
|------------|-------------|
| `r` | Read |
| `w` | Write |
| `d` | Delete |
| `l` | List |
| `a` | Add |
| `c` | Create |

## Direct Client Usage

For advanced use cases, you can use the client directly:

```php
use AliNauf\AzureStorage\AzureBlobStorageClient;

$client = new AzureBlobStorageClient(
    accountName: config('azure-storage.account_name'),
    accountKey: config('azure-storage.account_key'),
    container: config('azure-storage.container'),
);

// Upload a blob
$response = $client->putBlob('path/to/file.txt', 'Contents', 'text/plain');

// Get blob properties
$properties = $client->getBlobProperties('path/to/file.txt');
// Returns: ['content_length' => 123, 'content_type' => 'text/plain', 'last_modified' => 1234567890, 'etag' => '...']

// List blobs with prefix
$result = $client->listBlobs('path/to/', 100);
// Returns: ['blobs' => [...], 'next_marker' => '...']

// Copy a blob
$response = $client->copyBlob('source.txt', 'destination.txt');
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details on:

- Setting up the development environment
- Running tests
- Code style guidelines
- Submitting pull requests

## Security

If you discover a security vulnerability, please email directly instead of using the issue tracker.

## Credits

- [Ali Nauf](https://github.com/alinauf)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
