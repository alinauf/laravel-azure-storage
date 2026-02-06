# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Laravel package that provides an Azure Blob Storage filesystem driver using the Azure REST API directly (no Azure SDK dependency). Registers as a Flysystem adapter so it works seamlessly with Laravel's `Storage` facade.

- **Package**: `alinauf/laravel-azure-storage`
- **Namespace**: `AliNauf\AzureStorage`
- **PHP**: 8.2+
- **Laravel**: 11.x / 12.x
- **Flysystem**: 3.x

## Commands

```bash
composer test                  # Run all tests (PHPUnit 11)
vendor/bin/phpunit --filter=MethodName  # Run a single test by method name
vendor/bin/phpunit tests/Unit          # Run only unit tests
vendor/bin/phpunit tests/Feature       # Run only feature tests
composer format                # Fix code style (Laravel Pint)
composer format-check          # Check code style without fixing
```

CI runs Pint lint check + PHPUnit across PHP 8.2/8.3/8.4 and Laravel 11/12.

## Architecture

### Three-layer design

1. **`AzureBlobStorageClient`** — Low-level HTTP client that talks directly to the Azure Blob Storage REST API. Handles HMAC-SHA256 Shared Key authentication (signature generation, canonicalized headers/resource). Uses Laravel's `Http` facade. Returns raw `Response` objects.

2. **`AzureBlobStorageAdapter`** — Implements `League\Flysystem\FilesystemAdapter`. Wraps the client and translates Flysystem operations (read, write, delete, list, copy, move) into client calls. Handles virtual directory semantics (Azure has no real directories).

3. **`AzureBlobStorageServiceProvider`** — Registers the `azure` filesystem driver via `Storage::extend()`. Wires up the client, adapter, and Flysystem instance. Registers `temporaryUrl` support using `buildTemporaryUrlsUsing()`.

### Supporting classes

- **`SasTokenGenerator`** — Generates Shared Access Signature (SAS) tokens for blobs and containers. Used by the service provider for `temporaryUrl()` and can be used standalone.
- **`MimeTypeGuesser`** — Static extension-to-MIME-type lookup map used when writing blobs.
- **`AzureStorage` facade** — Convenience facade that proxies to `Storage::disk('azure')`.

### Exceptions

All in `AliNauf\AzureStorage\Exceptions\`:
- `AzureStorageException` — Base exception, parses Azure XML error responses
- `BlobNotFoundException` — 404 from blob operations
- `InvalidConfigurationException` — Missing account name/key/container

## Testing

Tests use **Orchestra Testbench** for the Laravel application context. The base `TestCase` at `tests/TestCase.php` registers the service provider and sets test config values.

- **Unit tests** (`tests/Unit/`) — Test `SasTokenGenerator` and `MimeTypeGuesser` directly without Laravel (extend PHPUnit's `TestCase`).
- **Feature tests** (`tests/Feature/`) — Test the adapter and service provider with `Http::fake()` to mock Azure API responses (extend the package's `TestCase`).

Tests use the `#[Test]` and `#[DataProvider]` PHPUnit attributes (not `@test` annotations).

## Code Style

Laravel Pint with the `laravel` preset (`pint.json`). CI enforces this.

## Release Process

Package is on Packagist with a webhook — pushing a git tag triggers the release automatically. No GitHub Releases.

1. Update `CHANGELOG.md`: move items from `[Unreleased]` to `[x.y.z] - YYYY-MM-DD`
2. Commit: `release: vx.y.z`
3. Tag: `git tag vx.y.z`
4. Push: `git push && git push origin vx.y.z`

Follow semver: patch for fixes, minor for new features, major for breaking changes.

## Commit Style

- Short subject line only, no description body
- No "Co-Authored-By" lines
- Examples: `release: v1.1.0`, `feat: add container-level visibility control`
