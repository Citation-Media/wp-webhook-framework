AGENTS — guidance for automated agents

Quick commands
- Install: composer install
- Static analysis: composer run-script phpstan  (or ./vendor/bin/phpstan analyse)
- Lint: composer run-script phpcs  (or ./vendor/bin/phpcs --standard=phpcs.xml)
- Auto-fix: composer run-script phpcbf  (or ./vendor/bin/phpcbf)
- Single-file checks: ./vendor/bin/phpstan analyse src/Entities/PostEmitter.php
  ./vendor/bin/phpcs src/Entities/PostEmitter.php
- Tests: none present; if adding PHPUnit run one test with
  ./vendor/bin/phpunit --filter TestClass::testMethod

Style & conventions
- PSR-4 + PSR-12; project enforces WordPress Coding Standards via phpcs.xml.
- Add `declare(strict_types=1);` to all new PHP files.
- Use native type hints (params/returns). Docblocks only when necessary.
- Naming: Classes PascalCase, methods/properties camelCase, constants UPPER_SNAKE_CASE.
- Imports: one `use` per line, remove unused imports.
- Error handling: validate inputs, prefer exceptions or WP_Error; never use die()/exit().
- Comments: concise English docblocks describing purpose and rationale.

Cursor/Copilot rules
- No repository `.cursor` or `.github/copilot-instructions.md` detected; follow the rules above.

Run phpcs + phpstan before committing.