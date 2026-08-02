# Testing

We use PHPUnit for Unit Testing.

## Folder Structure
Tests are located in the `tests/` directory.
- `tests/Unit/` for isolated unit tests.

## Running Tests
To run tests locally:
```bash
./vendor/bin/phpunit
```

## Release Verification
Before tagging a release, run the full verification pipeline in CI or a local environment that has PHP and Composer available:

```bash
composer install
composer dump-autoload
php -l path/to/changed-file.php
./vendor/bin/phpunit
```

Static analysis and coding standards should be added once the tools are installed as development dependencies:

```bash
./vendor/bin/phpcs
./vendor/bin/phpstan analyse
```

Do not treat local sandbox failures caused by a missing `php` executable as project failures. They should be verified in CI before release.

## Mocking
We use PHPUnit's built-in mocking capabilities to isolate classes.
- When testing a Service, mock the Repositories and EventManager.
- Ensure that the database is not accessed during Unit tests (except for Repository-specific tests if an in-memory DB is used).

## Example: Testing Requests
Requests are easy to test. Create an anonymous class extending `AbstractRequest` in your test, pass in rules and dummy data, and assert the validation result. See `tests/Unit/AbstractRequestTest.php` for examples.
