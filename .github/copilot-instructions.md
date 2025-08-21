Quick commands
- Install: composer install
- Static analysis: composer run-script phpstan  (or ./vendor/bin/phpstan analyse)
- Lint: composer run-script phpcs  (or ./vendor/bin/phpcs --standard=phpcs.xml)
- Auto-fix: composer run-script phpcbf  (or ./vendor/bin/phpcbf)
- Single-file checks: ./vendor/bin/phpstan analyse src/Entities/PostEmitter.php
  ./vendor/bin/phpcs src/Entities/PostEmitter.php
- Tests: none present; if adding PHPUnit run one test with
  ./vendor/bin/phpunit --filter TestClass::testMethod

## Core Development Rules

@.github/instructions/base.instructions.md Use when working on any WordPress development task to understand coding standards, PHP guidelines, and general best practices.

@.github/instructions/error-handling.instructions.md Use when implementing error handling, validation, or debugging in WordPress applications.

@.github/instructions/queries.instructions.md Use when creating or optimizing WordPress queries, WP_Query, or database interactions.

## Quality Assurance

@.github/instructions/quality-assurance/phpstan.instructions.md Use when working on PHPStan configuration, static analysis, or type annotations.

## REST API Development

@.github/instructions/rest-api/validation-sanitization.instructions.md Use when implementing REST API endpoints, validation, or sanitization.

## Local Development

@.github/instructions/local-development/ddev.instructions.md Use when working with DDEV for local development environment setup.