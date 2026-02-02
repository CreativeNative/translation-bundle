# Architecture

**Analysis Date:** 2026-02-02

## Pattern Overview

**Overall:** Plugin-based Handler Chain pattern with Doctrine ORM integration

**Key Characteristics:**
- Symfony Bundle structure with dependency injection and compiler passes
- Pluggable handler chain for different translation scenarios (scalar, relations, embeddables)
- Doctrine event lifecycle integration (prePersist, postLoad, onFlush)
- Translation caching with cycle detection for recursive relationships
- Attribute-driven configuration for entity translation behavior

## Layers

**Presentation/Twig Layer:**
- Purpose: Provide template utilities for translation-aware views
- Location: `src/Twig/TmiTranslationExtension.php`
- Contains: Twig tests and global variables
- Depends on: Locales configuration
- Used by: Twig templates for checking if objects are translatable

**Translation Orchestration Layer:**
- Purpose: Main translation processing orchestrator that chains handlers
- Location: `src/Translation/EntityTranslator.php`
- Contains: Core translation logic, caching, cycle detection, event dispatching
- Depends on: Translation handlers, attribute helpers, event dispatcher, Doctrine ORM
- Used by: Doctrine event subscribers, external code requesting translations

**Handler Layer:**
- Purpose: Specialized handlers for different data types and relationships
- Location: `src/Translation/Handlers/`
- Contains: 10 handler implementations (ScalarHandler, PrimaryKeyHandler, EmbeddedHandler, bidirectional/unidirectional relation handlers)
- Depends on: TranslationArgs, AttributeHelper, EntityManager, property accessors
- Used by: EntityTranslator via chain of responsibility pattern

**Data Access Layer:**
- Purpose: Doctrine ORM integration and entity lifecycle management
- Location: `src/Doctrine/`
- Contains: EventSubscriber (prePersist, postLoad, onFlush), Locale filters, custom types
- Depends on: Doctrine ORM, TranslatableInterface
- Used by: Doctrine lifecycle events

**Configuration/DI Layer:**
- Purpose: Symfony bundle configuration and service registration
- Location: `src/DependencyInjection/`
- Contains: Extension, Configuration, Compiler passes
- Depends on: Symfony DependencyInjection
- Used by: Symfony Kernel during bootstrap

**Attribute/Utility Layer:**
- Purpose: Metadata inspection and attribute detection
- Location: `src/Utils/` and `src/Doctrine/Attribute/`
- Contains: AttributeHelper for reflection-based property inspection
- Depends on: PHP Reflection API
- Used by: Handlers and EntityTranslator for conditional logic

**Value Objects:**
- Purpose: Immutable domain objects
- Location: `src/ValueObject/Tuuid.php`
- Contains: Translation UUID (Tuuid) - UUIDv7 implementation
- Depends on: Symfony UID component
- Used by: TranslatableTrait and translation caching

## Data Flow

**Translation Request Flow:**

1. Entity change triggers Doctrine event (prePersist, postLoad, onFlush)
2. `TranslatableEventSubscriber` intercepts and calls `EntityTranslator.processTranslation()`
3. `EntityTranslator` receives `TranslationArgs` containing entity, source/target locales, property
4. Cache check: if translation cached (key: tuuid:locale), return immediately
5. Cycle detection: if entity in progress, return to prevent infinite recursion
6. Warmup existing translations from database by class and locale
7. Iterate through registered handlers in priority order (100 to 10)
8. First handler that `supports()` the TranslationArgs processes it:
   - Checks for `#[SharedAmongstTranslations]` attribute - return unchanged
   - Checks for `#[EmptyOnTranslate]` attribute - return null/empty
   - Checks for `#[Embedded]` - handle embedded object properties
   - Otherwise translates via `translate()` method
9. Handler invokes specific logic (scalar copy, relation handling, embeddable processing)
10. On success: emit POST_TRANSLATE event, cache result
11. Return translated entity/value

**Attribute Processing:**

```
Property marked with attribute?
├─ SharedAmongstTranslations → Return unchanged (shared across translations)
├─ EmptyOnTranslate → Return null (emptied when translating)
├─ Embedded → Process nested properties recursively
└─ None → Handler-specific logic
```

**State Management:**

- **Translation Cache:** `array<string, array<string, TranslatableInterface>>` (tuuid => locale => entity)
- **In-Progress Tracking:** `array<string, true>` (cacheKey => true) for cycle detection
- **Handler Chain:** Priority-ordered array of TranslationHandlerInterface implementations
- **Per-Request State:** TranslationArgs DTO holds context for current translation operation

## Key Abstractions

**TranslationHandlerInterface:**
- Purpose: Define contract for handling specific translation scenarios
- Examples: `src/Translation/Handlers/ScalarHandler.php`, `src/Translation/Handlers/BidirectionalManyToOneHandler.php`
- Pattern: Chain of Responsibility - each handler checks `supports()`, processes if matched

**TranslatableInterface:**
- Purpose: Mark entities as translatable and provide translation metadata
- Examples: Implemented by user entity models
- Pattern: Marker interface with required methods (getTuuid, getLocale, getTranslations)

**TranslatableTrait:**
- Purpose: Reusable implementation of TranslatableInterface
- Location: `src/Doctrine/Model/TranslatableTrait.php`
- Pattern: Trait providing tuuid, locale, and translations array storage

**TranslationArgs:**
- Purpose: Context object passed through handler chain
- Location: `src/Translation/Args/TranslationArgs.php`
- Pattern: DTO holding data to translate, locales, parent context, and ReflectionProperty

**AttributeHelper:**
- Purpose: Centralized metadata inspection service
- Location: `src/Utils/AttributeHelper.php`
- Pattern: Static attribute class mapping with reflection-based queries

## Entry Points

**Bundle Registration:**
- Location: `src/TmiTranslationBundle.php`
- Triggers: Symfony kernel boot
- Responsibilities: Register compiler pass for handler auto-registration

**Doctrine Lifecycle Events:**
- Location: `src/Doctrine/EventSubscriber/TranslatableEventSubscriber.php`
- Triggers: prePersist, postLoad, onFlush events
- Responsibilities: Generate tuuid, set default locale, trigger translation processing at lifecycle junctures

**Public API:**
- `EntityTranslatorInterface.translate(entity, locale)` - Direct translation request
- `EntityTranslator.processTranslation(TranslationArgs)` - Low-level translation with context

**Twig Integration:**
- Location: `src/Twig/TmiTranslationExtension.php`
- Triggers: Template rendering
- Responsibilities: Provide `is translatable` test and `locales` global variable

## Error Handling

**Strategy:** Exceptions and validation at critical points

**Patterns:**

- **Invalid Locale:** Throws `LogicException` if requested locale not in configured allowed locales (EntityTranslator.php:82)
- **Non-Nullable EmptyOnTranslate:** Throws `LogicException` if property marked EmptyOnTranslate but not nullable (EntityTranslator.php:146)
- **Immutable Tuuid:** Throws `LogicException` if attempting to reassign Tuuid after initial set (TranslatableTrait.php:51)
- **Invalid UUID:** Throws `InvalidArgumentException` if Tuuid value is not valid UUID (Tuuid.php:26)

## Cross-Cutting Concerns

**Logging:** None explicit. Uses console/file logging via Symfony standard patterns.

**Validation:**
- Locale validation against configured allowed locales
- Type validation on attributes via reflection
- UUID format validation in Tuuid constructor

**Authentication:**
- No built-in auth. Relies on Symfony Security for user/firewall context
- `LocaleFilterConfigurator` integrates with `security.firewall.map` if available
- Supports disabled firewalls configuration (`tmi_translation.disabled_firewalls`)

---

*Architecture analysis: 2026-02-02*
