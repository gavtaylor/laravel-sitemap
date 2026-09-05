# Contribution Guide

Thank you for considering contributing to Laravel Sitemap! Please review the following guidelines before submitting a pull request.

For significant changes, please open an issue first so we can discuss the approach.

## Process

1. Fork the project
2. Create a new branch
3. Code, test, commit, and push
4. Open a pull request detailing your changes

## Guidelines

- Ensure the coding style passes by running `composer lint`.
- Send a coherent commit history, making sure each commit in your pull request is meaningful.
- You may need to [rebase](https://git-scm.com/book/en/v2/Git-Branching-Rebasing) to avoid merge conflicts.
- Please remember that we follow [SemVer](http://semver.org/).

## Coding Standards

This package is a native-focused Laravel package - so it holds itself to [Laravel's own contribution coding standards](https://laravel.com/framework/docs/contributions#coding-style), not just "a" style:

- [PSR-2](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md) coding style and [PSR-4](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md) autoloading, enforced automatically by [Laravel Pint](https://laravel.com/framework/docs/pint) using its `laravel` preset (`composer lint`) - the same tool the framework itself uses.
- PHPDoc blocks follow Laravel's own convention: omit `@param`/`@return` entirely when a native type already says everything (e.g. `function handle(AudioProcessor $processor): void`), but keep them when the native type is generic (`array`, `iterable`) so the element type is documented (e.g. `@return array<int, SitemapUrl>`). Where kept, `@param` is followed by two spaces, the type, two more spaces, then the variable name.
- `@throws` is documented on any method that can deliberately let an exception propagate to its caller.

`composer test` runs Pint, PHPStan (Larastan, level 7), 100% type coverage, and the full Pest suite - all four must be clean before a pull request is reviewed.

## Setup

Clone your fork, then install the dev dependencies:

```bash
composer install
```

## Lint

Lint your code:

```bash
composer lint
```

## Tests

Run all tests:

```bash
composer test
```
