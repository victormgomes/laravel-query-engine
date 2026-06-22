# Contributing

Thank you for considering contributing to `victormgomes/query-params`!

## Local Development

You can execute all commands directly on your host machine if you have PHP and
Node.js installed, or you can use the provided isolated Docker environment.

1. Clone the repository.
2. Run `composer install` (or `docker compose run --rm dev composer install`).
3. Run `composer run prepare` to setup the Testbench environment.

## Code Style & Linting

This package enforces strict linting for PHP (Laravel Pint), Markdown
(markdownlint & Prettier), JSON, and YAML. Before opening a Pull Request, please
run:

```bash
composer run format:all
```

## Static Analysis

We enforce strict static analysis using [PHPStan](https://phpstan.org/). Ensure
your changes pass level 5 analysis:

```bash
composer run check:types
```

## Testing

This package uses [Pest](https://pestphp.com/) for testing. All PRs must
maintain or improve test coverage.

To run the test suite:

```bash
composer run test
```

## Pull Requests

- Provide a clear, descriptive title.
- Explain _why_ you are making the change.
- Ensure all tests and static analysis checks are passing.
- Keep the PR focused on a single feature or bug fix.
