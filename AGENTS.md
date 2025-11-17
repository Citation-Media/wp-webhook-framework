# WordPress Webhook Framework - Agent Guidelines

## Commands
- `composer install` - Install dependencies
- `composer run-script phpstan` - Run static analysis (PHPStan level 6)
- `composer run-script phpcs` - Lint code (WordPress coding standards)
- `composer run-script phpcbf` - Auto-fix code style issues

## Code Style
- **PHP 8.0+ library** with WordPress coding standards (WPCS 3.1)
- **Namespace**: `Citation\WP_Webhook_Framework` (PSR-4)
- **Strict types**: Always use `declare(strict_types=1);` after opening PHP tag
- **Naming**: snake_case for methods/properties/functions (WordPress convention), PascalCase for classes
- **Yoda conditions**: Use for all comparisons (e.g., `null === $var`)
- **Early returns**: Exit early to reduce nesting
- **PHPStan annotations**: Required for complex types, especially arrays (e.g., `@phpstan-var array<string,string>`)
- **Docblocks**: Required for all classes, methods, properties; explain *why* not *what*
- **Type hints**: Use strict PHP 8.0 types + PHPStan types for precision
- **Error handling**: Direct exception throwing in Action Scheduler context; use `wp_trigger_error()` elsewhere
- **Dependencies**: Uses WooCommerce Action Scheduler 3.7+ for async webhooks
- **Testing**: No test suite currently configured

## Architecture
Entity-based webhook framework using registry pattern. Webhooks extend abstract `Webhook` class, implement `init()` method, and register via `Service_Provider`.

**Core structure**: See @README.md for architecture overview and quick examples.

## Documentation
- **Location**: Add all documentation to `/docs` directory
- **Style**: Keep docs brief but fully describe functionality—no unnecessary prose, just essential details
- **Maintenance**: After making changes to functionality, architecture, or APIs, update relevant documentation in `/docs` to keep it current

## Reference Documentation
Add new docs with an "@" mention to the "AGENTS.md" including a quick explanation. Keep the docs always up to date.
- @README.md - Quick start, basic usage, architecture overview
- @docs/custom-webhooks.md - Creating and registering custom webhooks, registry pattern
- @docs/hooks-and-filters.md - All available hooks, filters with examples (includes newly documented `wpwf_headers` filter)
- @docs/configuration.md - Constants, configuration methods, precedence rules
- @docs/third-party-integration.md - WooCommerce, ACF, CF7, Gravity Forms, EDD integration examples
- @docs/webhook-statefulness.md - Webhook statefulness rules and best practices
- @docs/failure-handling.md - Failure monitoring, blocking behavior, email notifications
