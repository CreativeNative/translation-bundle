# Codebase Structure

**Analysis Date:** 2026-02-02

## Directory Layout

```
translation-bundle/
├── src/                                    # Main source code
│   ├── DependencyInjection/               # Symfony DI configuration
│   │   ├── Compiler/                      # Compiler passes for service registration
│   │   ├── Configuration.php              # Config schema definition
│   │   └── TmiTranslationExtension.php    # Bundle extension & service loader
│   ├── Doctrine/                          # Doctrine ORM integration
│   │   ├── Attribute/                     # PHP 8 attributes for translation behavior
│   │   ├── EventSubscriber/               # Doctrine lifecycle listeners
│   │   ├── Filter/                        # SQL filters (LocaleFilter)
│   │   ├── Model/                         # Entity interfaces and traits
│   │   └── Type/                          # Custom Doctrine types (TuuidType)
│   ├── Event/                             # Domain events
│   ├── EventSubscriber/                   # Symfony kernel event listeners
│   ├── Resources/                         # Bundle resources
│   │   └── config/                        # YAML service configuration
│   ├── Translation/                       # Core translation logic
│   │   ├── Args/                          # TranslationArgs DTO
│   │   ├── Handlers/                      # Handler implementations (chain of responsibility)
│   │   ├── EntityTranslator.php           # Main orchestrator
│   │   └── EntityTranslatorInterface.php  # Public contract
│   ├── Twig/                              # Twig template integration
│   ├── Utils/                             # Utility services
│   ├── ValueObject/                       # Immutable value objects
│   └── TmiTranslationBundle.php           # Bundle entry point
├── tests/                                 # Test suite (PHPUnit)
│   ├── DependencyInjection/               # DI configuration tests
│   ├── Doctrine/                          # Doctrine integration tests
│   ├── Event/                             # Event tests
│   ├── EventSubscriber/                   # Event subscriber tests
│   ├── Fixtures/                          # Test fixtures and entities
│   ├── Translation/                       # Translation handler tests
│   └── ...                                # Additional test directories
├── composer.json                          # PHP dependencies & scripts
└── phpstan.neon                           # Static analysis configuration
```

## Directory Purposes

**`src/`**
- Purpose: All production source code for the bundle
- Contains: PHP classes organized by feature/concern
- Key files: Bundle entry point, interfaces, implementations

**`src/DependencyInjection/`**
- Purpose: Symfony dependency injection configuration
- Contains: Bundle extension that loads services, configuration schema, compiler passes
- Key files:
  - `TmiTranslationExtension.php`: Main bundle extension, Doctrine type registration
  - `Configuration.php`: Configuration schema (locales, default_locale, disabled_firewalls)
  - `Compiler/TranslationHandlerPass.php`: Auto-wires handlers to EntityTranslator

**`src/Doctrine/`**
- Purpose: Doctrine ORM integration and lifecycle management
- Contains: Event subscribers, custom types, filters, entity contracts
- Structure:
  - `Attribute/`: `#[SharedAmongstTranslations]`, `#[EmptyOnTranslate]` attributes
  - `EventSubscriber/`: Hooks into prePersist, postLoad, onFlush
  - `Filter/`: `LocaleFilter` for SQL WHERE clause injection
  - `Model/`: `TranslatableInterface`, `TranslatableTrait` (tuuid, locale, translations storage)
  - `Type/`: `TuuidType` custom Doctrine column type

**`src/Translation/`**
- Purpose: Core translation processing and handler implementations
- Contains: Main orchestrator, handler chain, DTOs
- Structure:
  - `Handlers/`: 10 handler implementations (scalar, primary key, embedded, 6 relation types)
  - `Args/`: `TranslationArgs` context object for handler chain
  - `EntityTranslator.php`: Main service with caching, cycle detection, event dispatch
  - `EntityTranslatorInterface.php`: Public contract (translate, afterLoad, beforePersist, etc.)

**`src/Event/`**
- Purpose: Symfony events for translation lifecycle
- Contains: `TranslateEvent` (PRE_TRANSLATE, POST_TRANSLATE)

**`src/EventSubscriber/`**
- Purpose: Symfony kernel event listeners
- Contains: `LocaleFilterConfigurator` - integrates LocaleFilter with request/security context

**`src/Utils/`**
- Purpose: Shared utility services
- Contains: `AttributeHelper` - reflection-based metadata inspection for detecting attributes

**`src/Twig/`**
- Purpose: Twig template integration
- Contains: `TmiTranslationExtension` - provides `is translatable` test and `locales` global

**`src/ValueObject/`**
- Purpose: Immutable domain objects
- Contains: `Tuuid` (Translation UUID - UUIDv7 based)

**`src/Resources/config/`**
- Purpose: YAML service configuration
- Contains: `services.yaml` - all service definitions, handler registration, priorities

**`tests/`**
- Purpose: PHPUnit test suite
- Contains: Mirrored directory structure matching `src/`
- Special subdirectories:
  - `Fixtures/`: Test entities, embedded objects, test database schemas

## Key File Locations

**Entry Points:**

- `src/TmiTranslationBundle.php`: Bundle class, registers compiler pass
- `src/DependencyInjection/TmiTranslationExtension.php`: Loads services and registers Doctrine types
- `src/Doctrine/EventSubscriber/TranslatableEventSubscriber.php`: Doctrine lifecycle hooks (prePersist, postLoad, onFlush)
- `src/Translation/EntityTranslator.php`: Main translation orchestrator
- `src/Twig/TmiTranslationExtension.php`: Twig integration

**Configuration:**

- `src/DependencyInjection/Configuration.php`: Configuration schema
- `src/Resources/config/services.yaml`: Service definitions with handler priority ordering
- `composer.json`: Package manifest and script definitions

**Core Logic:**

- `src/Translation/EntityTranslator.php`: Translation processing with caching and cycle detection
- `src/Translation/Handlers/`: 10 handler implementations (280+ LOC total)
- `src/Utils/AttributeHelper.php`: Metadata inspection service
- `src/Doctrine/Model/TranslatableTrait.php`: Entity mixin providing translation storage

**Testing:**

- `tests/`: Full test mirror of `src/` structure
- `phpstan.neon`: Static analysis configuration
- `composer.json`: test script runs `phpunit` with coverage

## Naming Conventions

**Files:**

- **Interfaces:** `{Name}Interface.php` (e.g., `TranslatableInterface.php`, `TranslationHandlerInterface.php`)
- **Traits:** `{Name}Trait.php` (e.g., `TranslatableTrait.php`)
- **Attributes:** `{Attribute}.php` in `Doctrine/Attribute/` (e.g., `SharedAmongstTranslations.php`)
- **Handlers:** `{Type}Handler.php` in `Translation/Handlers/` (e.g., `BidirectionalManyToOneHandler.php`)
- **Event Classes:** `{Event}Event.php` (e.g., `TranslateEvent.php`)
- **Extensions:** `{Name}Extension.php` (e.g., `TmiTranslationExtension.php`, `TmiTranslationBundle.php`)

**Directories:**

- **Plural for collections:** `src/Handlers/`, `src/Attributes/`, `src/EventSubscriber/`
- **Feature-organized:** Grouped by Doctrine, Translation, Event, Twig concerns
- **Compiler passes:** Nested in `Compiler/` subdirectory

**Classes:**

- **PascalCase:** `EntityTranslator`, `TranslateEvent`, `TranslatableInterface`
- **Immutable value objects:** `readonly class` (e.g., `Tuuid`)
- **Final classes:** Most implementation classes are `final` (EntityTranslator, ScalarHandler, etc.)

**Methods:**

- **camelCase:** `processTranslation()`, `addTranslationHandler()`, `generateTuuid()`
- **Boolean getters:** `isSharedAmongstTranslations()`, `isEmptyOnTranslate()`, `isNullable()`
- **Doctrine hooks:** `prePersist()`, `postLoad()`, `onFlush()`

## Where to Add New Code

**New Translation Handler:**
1. Create `src/Translation/Handlers/{Type}Handler.php`
2. Implement `TranslationHandlerInterface`
3. Register in `src/Resources/config/services.yaml` with `tmi_translation.translation_handler` tag and priority
4. Add tests in `tests/Translation/Handlers/{Type}HandlerTest.php`

**New Attribute/Behavior:**
1. Create attribute class in `src/Doctrine/Attribute/{AttributeName}.php`
2. Add detection method in `src/Utils/AttributeHelper.php`
3. Update handlers to check for attribute in `processTranslation()` flow
4. Add tests for attribute behavior

**New Entity Type Support:**
1. Add handler for the type in `src/Translation/Handlers/`
2. Implement `supports()` to match your type criteria
3. Implement translation logic in `translate()`, `handleSharedAmongstTranslations()`, `handleEmptyOnTranslate()`
4. Register handler with appropriate priority in services.yaml

**Twig Features:**
1. Add methods to `src/Twig/TmiTranslationExtension.php`
2. Register as tests, filters, or functions via `getTests()`, `getFilters()`, `getFunctions()`
3. Update global variables in `getGlobals()` if needed

**Utilities:**
- Shared helper logic: `src/Utils/{Name}.php`
- Reflect on this once similar patterns emerge (currently only `AttributeHelper.php` exists)

## Special Directories

**`src/Resources/config/`:**
- Purpose: YAML service configuration
- Generated: No
- Committed: Yes
- Structure: Single `services.yaml` with all service definitions

**`tests/Fixtures/`:**
- Purpose: Test data and fixture entities
- Generated: No
- Committed: Yes
- Contains: `Entity/` subdirectory with test entity classes, `Embedded/` with embedded objects

**`.planning/`:**
- Purpose: GSD planning documents
- Generated: Yes (by GSD commands)
- Committed: Yes
- Contains: `codebase/` subdirectory with architecture analysis documents

---

*Structure analysis: 2026-02-02*
