# Contributing

Thank you for considering contributing to `victormgomes/query-params`!

## Local Development

1. Clone the repository.
2. Run `composer install`.
3. Run `composer run prepare` to setup the Testbench environment.

## Code Style

This package uses [Laravel Pint](https://laravel.com/docs/pint) for code styling. Before opening a Pull Request, please run:

```bash
composer run format
```

## Static Analysis

We enforce strict static analysis using [PHPStan](https://phpstan.org/). Ensure your changes pass level 5 analysis:

```bash
composer run analyse
```

## Testing

This package uses [Pest](https://pestphp.com/) for testing. All PRs must maintain or improve test coverage. 

To run the test suite:

```bash
composer run test
```

## Pull Requests

- Provide a clear, descriptive title.
- Explain *why* you are making the change.
- Ensure all tests and static analysis checks are passing.
- Keep the PR focused on a single feature or bug fix.
