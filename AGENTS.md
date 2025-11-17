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

**Webhook Statelessness Rule**: Webhook instances are singletons registered in the registry and MUST remain stateless. Never store per-emission data (payloads, dynamic headers) as instance properties. Pass emission-specific data as parameters to `emit()`. Only configuration data set during `init()` (via `allowed_retries()`, `timeout()`, `webhook_url()`, `headers()`) should use instance properties.

**Core structure**: See @README.md#architecture-overview for detailed explanation of core classes and data flow.

## Documentation
- **Location**: Add all documentation to `/docs` directory
- **Style**: Keep docs brief but fully describe functionality—no unnecessary prose, just essential details
- **Maintenance**: After making changes to functionality, architecture, or APIs, update relevant documentation in `/docs` to keep it current
