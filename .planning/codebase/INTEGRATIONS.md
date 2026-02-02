# External Integrations

**Analysis Date:** 2026-02-02

## APIs & External Services

**None detected.**

This is a library/bundle with no outbound HTTP or API integrations. All functionality is internal to the Symfony/Doctrine ecosystem.

## Data Storage

**Databases:**
- Any database supported by Doctrine ORM (MySQL, PostgreSQL, SQLite, SQL Server, Oracle, etc.)
  - Connection: Via Doctrine DBAL through `doctrine.dbal.default_connection` service
  - Client: Doctrine ORM (via `doctrine.orm.entity_manager` service)
  - Configuration: Handled by host application's `config/doctrine.yaml` or equivalent

**File Storage:**
- Not applicable - Bundle stores data in database only

**Caching:**
- Doctrine Query Result Cache - Optional, configured by host application
- Translation Cache - Internal in-memory cache per request
  - Location: `src/Translation/EntityTranslator.php` - `$translationCache` array
  - Scope: Single request lifecycle
  - Strategy: Tuuid + locale composite key

## Authentication & Identity

**Auth Provider:**
- Custom (Internal to host application)
  - Implementation: Symfony Security Bundle integration
  - Locale filtering respects security firewalls via `LocaleFilterConfigurator`
  - Files: `src/EventSubscriber/LocaleFilterConfigurator.php`
  - Disabled firewalls configurable: `tmi_translation.disabled_firewalls`

## Monitoring & Observability

**Error Tracking:**
- Not detected - Monitoring delegated to host application

**Logs:**
- Not detected - Logging delegated to host application
- No direct logging integration; uses Symfony's built-in error handling

## CI/CD & Deployment

**Hosting:**
- Deployment-agnostic - Works in any Symfony 7.3+/8.0+ environment
- Docker image available: `php:8.4-fpm` with Xdebug support

**CI Pipeline:**
- GitHub Actions (`.github/workflows/php.yml`)
  - Platform: Ubuntu latest
  - PHP 8.4 matrix testing
  - Steps: Composer validation, dependency caching, PHPUnit with coverage, PHP-CS-Fixer check, PHPStan analysis
  - Coverage reporting: Codecov integration (`codecov/codecov-action@v5`)
  - Environment: `CODECOV_ORG_TOKEN` secret required

## Environment Configuration

**Required env vars (host application):**
- `DATABASE_URL` - Doctrine connection (format: `driver://user:password@host:port/database`)
- `APP_ENV` - Application environment (test, dev, prod)
- `KERNEL_DEFAULT_LOCALE` - Default application locale

**Development/Testing env vars:**
- `COMPOSER_ALLOW_XDEBUG` - Enable/disable Xdebug (set to "0" to allow Composer with Xdebug)
- `XDEBUG_MODE` - Xdebug mode (coverage for testing, debug for debugging)
- `SYMFONY_DEPRECATIONS_HELPER` - Deprecation behavior (weak_vendors in tests)

**Secrets location:**
- Managed by host application - Not applicable to bundle itself
- CI/CD secrets: `CODECOV_ORG_TOKEN` stored in GitHub Secrets

## Webhooks & Callbacks

**Incoming:**
- None

**Outgoing:**
- Symfony Event System (Internal)
  - `tmi_translation.pre_translate` - Fired before entity translation
  - `tmi_translation.post_translate` - Fired after entity translation
  - Host applications can listen via event subscribers
  - Files: `src/Event/TranslateEvent.php`

**Doctrine Event Hooks:**
- Entity lifecycle events integrated:
  - `onFlush` - During Doctrine flush
  - `postLoad` - After entity loading from database
  - `prePersist` - Before entity persistence
  - Files: `src/Doctrine/EventSubscriber/TranslatableEventSubscriber.php`

**Symfony Event Hooks:**
- Kernel event subscriber for locale filtering
  - Event: `LocaleFilterConfigurator` registers as kernel event subscriber
  - Scope: Per-request locale filtering based on security context
  - Files: `src/EventSubscriber/LocaleFilterConfigurator.php`

## Service Registration

**Dependency Injection:**
- All services registered in `src/Resources/config/services.yaml`
- Auto-wiring enabled by default
- Key services:
  - `Tmi\TranslationBundle\Translation\EntityTranslator` - Main translation service
  - `Tmi\TranslationBundle\Translation\Handlers\*` - Handler chain (8 handlers with priority tags)
  - `Tmi\TranslationBundle\Doctrine\EventSubscriber\TranslatableEventSubscriber` - Doctrine integration
  - `Tmi\TranslationBundle\EventSubscriber\LocaleFilterConfigurator` - Security integration
  - `Tmi\TranslationBundle\Twig\TmiTranslationExtension` - Twig integration

**Tagged Services:**
- `tmi_translation.translation_handler` - Translation handler chain with priority ordering
- `twig.extension` - Twig extension registration
- `kernel.event_subscriber` - Kernel event listening
- `doctrine.event_subscriber` - Doctrine event listening

## Type Mappings

**Doctrine Custom Types:**
- `TuuidType` - Custom Doctrine type mapping
  - Class: `src/Doctrine/Type/TuuidType.php`
  - Database column type: Platform-specific UUID type
  - PHP representation: `Tuuid` value object
  - Registered during bundle extension load phase

---

*Integration audit: 2026-02-02*
