# Architecture & Patterns

## Core Concept

Translations are stored in the same table as the source entity using:
- `tuuid` (Translation UUID) - Groups all language variants
- `locale` - Distinguishes translations
- `translations` JSON field - Metadata

## Handler Chain Pattern

Translation uses a priority-based handler chain. Each handler implements `TranslationHandlerInterface`:

| Priority | Handler | Purpose |
|----------|---------|---------|
| 100 | PrimaryKeyHandler | ID fields |
| 90 | ScalarHandler | Primitives, DateTime |
| 80 | EmbeddedHandler | Embedded objects |
| 70 | BidirectionalManyToOneHandler | ManyToOne relations |
| 60 | BidirectionalOneToManyHandler | OneToMany relations |
| 50 | BidirectionalOneToOneHandler | OneToOne relations |
| 40 | BidirectionalManyToManyHandler | ManyToMany relations |
| 30 | UnidirectionalManyToManyHandler | Unidirectional M2M |
| 20 | TranslatableEntityHandler | TranslatableInterface entities |
| 10 | DoctrineObjectHandler | Generic Doctrine objects |

### Adding New Handlers

```php
#[AsTaggedItem('tmi_translation.translation_handler', priority: 75)]
class MyCustomHandler implements TranslationHandlerInterface
{
    public function supports(mixed $value, TranslationArgs $args): bool;
    public function translate(mixed $value, TranslationArgs $args): mixed;
    public function handleSharedAmongstTranslations(mixed $value, TranslationArgs $args): mixed;
    public function handleEmptyOnTranslate(mixed $value, TranslationArgs $args): mixed;
}
```

## Key Attributes

### `#[SharedAmongstTranslations]`
Field value stays identical across all locales. When updated, all translations sync.

```php
#[SharedAmongstTranslations]
#[ORM\Column]
private string $videoUrl;
```

### `#[EmptyOnTranslate]`
Field is emptied when creating a new translation. Must be nullable or Collection.

```php
#[EmptyOnTranslate]
#[ORM\Column(nullable: true)]
private ?string $cachedSlug = null;
```

## Value Objects

### Tuuid (Translation UUID)
- Immutable value object using UUIDv7
- Stored as `VARCHAR(36)` via custom `TuuidType`
- Groups all language variants of an entity

## Events

| Event | When |
|-------|------|
| `TranslateEvent::PRE_TRANSLATE` | Before translation starts |
| `TranslateEvent::POST_TRANSLATE` | After successful translation |

## Tuuid Linkage Integrity (v2.2)

- `Doctrine/EventListener/TranslatableIndexListener` — injects a composite `(tuuid, locale)`
  index into every translatable entity at `loadClassMetadata`. `unique_locale_variants: true`
  promotes it to a `UNIQUE` constraint.
- `TranslatableEventSubscriber::prePersist()` — flags entities persisted in a non-default
  locale without a shared Tuuid. `strict_orphan_check` (`true` / `false` / `null` = auto on
  `kernel.debug`) decides between `OrphanTranslationException` and a PSR-3 warning.
- `Doctrine/Repository/TranslatableEntityRepository` — ready-made repository base class.
- `Doctrine/TranslatableEntityLocator` — discovers all `TranslatableInterface` entity classes.

## Console Commands

| Command | Purpose |
|---------|---------|
| `tmi:translation:doctor` | Scan for standalone/incomplete translations and duplicate `(tuuid, locale)` pairs; exits non-zero on findings |
| `tmi:translation:sync-shared` | Back-fill `#[SharedAmongstTranslations]` column values across existing locale variants |

## Directory Structure

```
src/
├── Command/                 # Diagnostic / maintenance console commands
├── DependencyInjection/     # Bundle configuration
├── Doctrine/                # ORM integration (models, types, filters, listeners)
├── Translation/             # Core translation logic
│   ├── EntityTranslator.php # Main orchestrator
│   └── Handlers/            # Handler chain
├── Event/                   # Translation events
├── Exception/               # Bundle exceptions
├── Utils/                   # Helpers (AttributeHelper)
└── ValueObject/             # Tuuid value object
```
