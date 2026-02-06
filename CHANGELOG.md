# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Container-level visibility support via `getContainerAcl()` and `setContainerAcl()` on the client
- `visibility()` now returns the actual container access level mapped to Flysystem visibility
- `setVisibility()` can change container access level when `allow_set` is enabled
- Visibility caching to avoid repeated API calls within a request lifecycle
- Visibility configuration options (`visibility.default` and `visibility.allow_set`)
- "Private Files & Temporary URLs" documentation section in README

### Changed
- `visibility()` no longer always returns `public` — it queries the container's actual access level
- `setVisibility()` now throws `UnableToSetVisibility` by default instead of silently doing nothing

## [1.0.0] - 2026-01-13

### Added
- Initial release
- Azure Blob Storage REST API client with Shared Key authentication
- Full Flysystem adapter implementation
- Laravel Storage facade integration
- File operations: read, write, delete, copy, move
- Directory operations: list contents, delete directory
- File metadata: size, last modified, MIME type
- SAS token generation for temporary URLs
- CDN/custom URL support
- Comprehensive test suite
- Laravel 11 and 12 support
- PHP 8.2, 8.3, and 8.4 support
