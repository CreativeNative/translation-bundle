# Technology Stack

**Analysis Date:** 2026-02-02

## Languages

**Primary:**
- PHP 8.4+ (minimum requirement) - Core language for the bundle
- PHP extensions: json, mbstring (required), pdo, pdo_sqlite, sqlite3 (dev/testing)

**Secondary:**
- YAML - Symfony configuration files

## Runtime

**Environment:**
- PHP 8.4-fpm (Docker container: `php:8.4-fpm`)
- Xdebug enabled for debugging in development

**Package Manager:**
- Composer 2
- Lockfile: `composer.lock` (present)

## Frameworks

**Core:**
- Symfony Framework 7.3+ or 8.0+ - Main framework for bundle integration
  - symfony/framework-bundle - Bundle and kernel integration
  - symfony/security-bundle - Security context for firewall configuration
  - symfony/yaml - YAML configuration parsing
  - symfony/property-access - Reflection-less property access via PropertyAccessor
  - symfony/uid - UUID/ULID generation (Symfony Uid component)
  - symfony/translation-contracts - Translation service contracts
  - symfony/dependency-injection - Dependency injection container

**ORM & Data:**
- Doctrine ORM 3.5.7+ - Core persistence framework
- doctrine/doctrine-bundle 2.18+ or 3.0+ - Symfony integration for Doctrine
- Doctrine DBAL - Low-level database abstraction

**Templating:**
- Twig/twig 3.22.0+ - Template engine with Twig extension

**Testing:**
- PHPUnit 12.4.4+ - Test runner and assertions
- symfony/phpunit-bridge - Symfony PHPUnit integration for deprecation handling
- PHPUnit Framework Attributes (modern PHP annotations)

**Build/Dev:**
- friendsofphp/php-cs-fixer (stable) - Code style formatting (PSR-12 + Symfony standard)
- phpstan/phpstan 2.1.32+ - Static analysis
  - phpstan/extension-installer - For auto-registering extensions
  - phpstan/phpstan-deprecation-rules - Detect deprecated code
  - phpstan/phpstan-strict-rules - Strict type checking rules
  - phpstan/phpstan-symfony - Symfony-specific rules
  - phpstan/phpstan-phpunit - PHPUnit-specific rules
- php-parallel-lint/php-parallel-lint - Syntax validation
- rector/rector 2.2.9+ - Automated code upgrade/modernization
- roave/security-advisories - Security vulnerability checking

## Key Dependencies

**Critical:**
- doctrine/doctrine-bundle - DBAL type registration and Doctrine integration
- doctrine/orm - Entity persistence and relationships
- symfony/framework-bundle - Dependency injection and bundle system
- symfony/security-bundle - Security firewall integration (LocaleFilterConfigurator)

**Infrastructure:**
- symfony/uid - UUID generation for Tuuid custom type
- symfony/property-access - Dynamic property access for translation handlers
- symfony/event-dispatcher - Event system for pre/post translate hooks
- twig/twig - Template rendering with custom Twig extension

**PHP Extensions:**
- ext-json - JSON encoding/decoding
- ext-mbstring - Multibyte string handling
- ext-pdo, ext-pdo_sqlite, ext-sqlite3 - Testing/database layer

## Configuration

**Environment:**
- Configuration via YAML files in `src/Resources/config/`
- Service definitions loaded from `services.yaml`
- Symfony DependencyInjection with autowiring/autoconfiguration
- Environment variables: `COMPOSER_ALLOW_XDEBUG`, `XDEBUG_MODE`, `SYMFONY_DEPRECATIONS_HELPER`

**Build:**
- `.php-cs-fixer.dist.php` - PSR-12 + Symfony standard with custom rules
  - Short array syntax `[]`
  - Union type nullable declaration syntax `?Type | Type`
  - Single quotes for strings
  - Ordered imports (alphabetical)
  - Trailing commas in multiline
  - Strict types declaration enforced
- `phpstan.neon` - Static analysis with level 1+ (max level available)
  - Strict rules enabled (loose comparison, empty disallowed, variable variables disallowed)
  - Specific ignores for PHPUnit dynamic calls in tests
- `rector.php` - Automated code modernization
  - PHP 8.4 target version
  - Doctrine code quality rules
  - PHPUnit attributes migration
  - Type coverage level 3
- `phpunit.xml` - PHPUnit test configuration
  - Coverage tracking via XDEBUG
  - Clover XML and HTML coverage reports
  - Strict test mode (fail on deprecations, notices, warnings)

## Platform Requirements

**Development:**
- PHP 8.4+
- Composer 2
- Docker & Docker Compose (optional, for containerized development)
- Xdebug (optional, for debugging)
- Git

**Production:**
- PHP 8.4+
- Symfony 7.3+ or 8.0+ application
- Doctrine ORM 3.5.7+
- Supported databases: Any supported by Doctrine (MySQL, PostgreSQL, SQLite, SQL Server, etc.)

**Testing:**
- PHPUnit 12.4.4+
- SQLite 3 (for test database)
- Coverage requirements: Tracked but not enforced via CLI

---

*Stack analysis: 2026-02-02*
