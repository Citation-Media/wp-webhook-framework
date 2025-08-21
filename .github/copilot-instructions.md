# WordPress Webhook Framework

## Project Details

Library for managing webhooks in WordPress, providing a flexible framework for creating, filtering, and delivering webhook payloads.

### Quick Commands
- Install: `composer install`
- Static analysis: `composer run-script phpstan
- Lint: `composer run-script phpcs`
- Auto-fix: `composer run-script phpcbf`
- 
### Project Structure
- `src/` - Main source code
- `composer.json` - PHP dependencies
- `phpcs.xml` - Code style configuration
- `phpstan.neon` - Static analysis configuration

## Base Rules

### General Coding & Language Guidelines

#### All Languages
- **Adhere to WordPress code styling**
- **Provide docblocks** for all functions, classes, and files
- **English only**: Always generate code and comments in English, regardless of input language
- **Early Return/Exit**: ALWAYS use early return/exit patterns for condition checks to simplify nesting and enhance readability
- **Focus on readability & clean code principles**

#### PHP Specific Guidelines
- Ensure code is **PHPCS compatible**
- Use **Yoda Conditions** for all conditional expressions
- Default to a **minimum PHP compatibility of 8.0** (unless another version is explicitly requested)
- **Strong typings**: Use PHPStan doc block notation (especially for array type hints)
- **Naming**: Use lowercase letters in variable, action/filter, and function names (never camelCase). Separate words via underscores. Don't abbreviate variable names unnecessarily; let the code be unambiguous and self-documenting.
- **Object-Oriented Approach**: Write OOP code by default unless the user asks for procedural/non-OOP structure (e.g., `functions.php`)
- **File Operations**: Use `WP_Filesystem` APIs instead of PHP native file functions whenever possible
- **Error Handling & Exception Logging**:
  - In non-production: Use `wp_trigger_error( $function_name, $message, $error_level )` at the appropriate level
  - In specific contexts (e.g., Action Scheduler in production), direct Exception throwing may be required—do **not** use `wp_trigger_error` in those cases
- Add **translator comments** for internationalization, e.g.:
  `/* translators: 1: WordPress version number, 2: plural number of bugs. */`
- When working with times use DateTime or preferably DateTimeImmutable instances.
  - `current_datetime()` current time as DateTimeImmutable
  - `get_post_datetime()` post time as DateTimeImmutable
  - `get_post_timestamp()` post time as Unix timestamp.
  - `wp_date( string $format, int $timestamp = null, DateTimeZone $timezone = null ): string|false`. Timestamp is the curent time by default. Timezone defaults to timezone from site settings.

#### JavaScript Guidelines
- **Framework Selection**:
  Provide code for framework per user requirement: `"Vanilla Javascript"`, `"ReactJs"`, `"AlpineJs"`, or `"WordPress Interactivity API"`. Default to "Vanilla Javascript" if unspecified.
- **JSDoc Docblocks**:
  Every function/class must use JSDoc doc blocks, fully describing parameters, errors, and return values in full sentences
- **ESLint Comments**:
  Only allow `// eslint-disable-line` comments for: `camelcase`, `console.log`, and `console.error`
- **AlpineJs Pattern**:
  Keep AlpineJS `data` in its **own file** and import as:
  ```js
  import Alpine from 'alpinejs'
  import dropdown from './dropdown.js'
  Alpine.data('dropdown', dropdown)
  ```
  In context of the plugin boilerplate alpine components should be added to resources/admin/js or resources/frontend/js. Each component should be its own file with one default function like
  ```js
  export default () => ({
      open: false,

      toggle() {
          this.open = ! this.open
      }
  })
  ```

## Links to Other Rules

### Core Development Rules
@.github/instructions/error-handling.instructions.md Use when implementing error handling, validation, or debugging in WordPress applications.

@.github/instructions/queries.instructions.md Use when creating or optimizing WordPress queries, WP_Query, or database interactions.

### Quality Assurance
@.github/instructions/quality-assurance/phpstan.instructions.md Use when working on PHPStan configuration, static analysis, or type annotations.

### REST API Development
@.github/instructions/rest-api/validation-sanitization.instructions.md Use when implementing REST API endpoints, validation, or sanitization.

### Local Development
@.github/instructions/local-development/ddev.instructions.md Use when executing php, npm, yarn, composer commands or working with DDEV local environment.