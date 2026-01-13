# Contributing to Laravel Azure Storage

Thank you for considering contributing to Laravel Azure Storage! This document provides guidelines and instructions for contributing.

## Code of Conduct

Please be respectful and considerate in all interactions. We welcome contributors of all backgrounds and experience levels.

## How Can I Contribute?

### Reporting Bugs

Before creating a bug report, please check existing issues to avoid duplicates. When creating a bug report, include:

- **Clear title** describing the issue
- **Steps to reproduce** the behavior
- **Expected behavior** vs actual behavior
- **Environment details**: PHP version, Laravel version, package version
- **Code samples** if applicable
- **Error messages** and stack traces

### Suggesting Features

Feature requests are welcome! Please include:

- **Clear description** of the feature
- **Use case** - why is this feature needed?
- **Proposed implementation** (if you have ideas)

### Pull Requests

1. **Fork the repository** and create your branch from `main`
2. **Write tests** for your changes
3. **Follow the coding standards** (run Pint before committing)
4. **Update documentation** if needed
5. **Write a clear commit message**

## Development Setup

### Prerequisites

- PHP 8.2+
- Composer

### Installation

```bash
# Clone your fork
git clone https://github.com/YOUR_USERNAME/laravel-azure-storage.git
cd laravel-azure-storage

# Install dependencies
composer install
```

### Running Tests

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test file
./vendor/bin/phpunit tests/Unit/MimeTypeGuesserTest.php

# Run with coverage (requires Xdebug or PCOV)
./vendor/bin/phpunit --coverage-html coverage
```

### Code Style

This project uses [Laravel Pint](https://laravel.com/docs/pint) for code formatting:

```bash
# Check code style
./vendor/bin/pint --test

# Fix code style
./vendor/bin/pint
```

Always run Pint before committing your changes.

## Coding Standards

### PHP

- Follow PSR-12 coding standards
- Use PHP 8.2+ features (constructor property promotion, named arguments, etc.)
- Add type hints to all method parameters and return types
- Use strict comparison (`===`) unless loose comparison is explicitly needed

### Testing

- Write tests for all new features and bug fixes
- Use descriptive test method names: `it_can_upload_a_file()`
- Mock external HTTP calls using Laravel's `Http::fake()`
- Aim for high test coverage on critical paths

### Documentation

- Update README.md for user-facing changes
- Add PHPDoc blocks to public methods
- Include code examples where helpful

## Commit Messages

Write clear, concise commit messages:

```
feat: add support for blob snapshots

- Implement snapshot creation via REST API
- Add temporaryUrl support for snapshots
- Update documentation with examples
```

Use conventional commit prefixes:
- `feat:` - New feature
- `fix:` - Bug fix
- `docs:` - Documentation only
- `test:` - Adding or updating tests
- `refactor:` - Code change that neither fixes a bug nor adds a feature
- `chore:` - Maintenance tasks

## Pull Request Process

1. Ensure all tests pass locally
2. Update the CHANGELOG.md with your changes under "Unreleased"
3. Create a pull request with a clear description
4. Link any related issues
5. Wait for code review and address feedback

## Questions?

If you have questions, feel free to:
- Open an issue for discussion
- Reach out to the maintainers

## License

By contributing, you agree that your contributions will be licensed under the MIT License.
