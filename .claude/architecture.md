# Architecture & Patterns

## Core Concept

Translations are stored in the same table as the source entity using:
- `tuuid` (Translation UUID) - Groups all language variants
- `locale` - Distinguishes translations

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

The chain is first-match-wins and sorted by descending priority, so the tag priority decides
where a custom handler runs — 75 puts it between `EmbeddedHandler` (80) and
`BidirectionalManyToOneHandler` (70). Handlers tagged without a priority default to 0 and run
last, in registration order; handlers sharing a priority keep their registration order.

## Key Attributes

### `#[SharedAmongstTranslations]`
Field value is copied from the source when a translation is created (copy-on-translate).
There is **no update-time propagation** — later edits diverge silently, by design.
Reconcile with `tmi:translation:sync-shared`; gate CI on drift with `--check`.

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
- `TranslatableEventSubscriber` — flags entities persisted in a non-default locale without
  a shared Tuuid; the verdict is settled at flush time (a same-flush translation adopting
  the Tuuid clears the flag). `strict_orphan_check` (`true` / `false` / `null` = auto on
  `kernel.debug`) decides between `OrphanTranslationException` and a PSR-3 warning (the
  warning only fires with `enable_logging: true`).
- `Doctrine/Repository/TranslatableEntityRepository` — ready-made repository base class.
- `Doctrine/TranslatableEntityLocator` — discovers all `TranslatableInterface` entity classes.
- `Doctrine/LocaleVariantFinder` — the one place that queries across every locale variant of a
  Tuuid, suspending the locale filter for the query (`withoutLocaleFilter()`).
  `TranslatableRepositoryTrait` and `LocaleCompletenessResolver` delegate to it.
- `Doctrine/TranslatableRemover` — removes every locale variant sharing a Tuuid (or exempts one
  variant from that) via `EntityManager::remove()` per variant, so ORM cascades / `orphanRemoval`
  / lifecycle callbacks fire per variant — never a bulk DQL DELETE. `$em->remove()` alone only
  ever touches the one row it is given.

## Per-Locale Completeness (v3.1)

- `Translation/LocaleCompletenessResolver` — per enabled locale: does a variant exist, is
  its translatable content complete? Baseline-relative: a variant is complete when every
  translatable (non-shared, non-system, non-id) property filled on the default-locale row
  is filled on it too. `resolveBatch()` answers many Tuuids with one query.
- `ValueObject/LocaleCompleteness` + `ValueObject/TranslationStatus` (enum
  `Missing`/`Incomplete`/`Complete`) — the returned value objects.

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
