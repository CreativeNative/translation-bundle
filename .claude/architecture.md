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
final class MyCustomHandler implements TranslationHandlerInterface
{
    public function supports(TranslationContext $context): bool;
    public function translate(TranslationContext $context): mixed;
}
```

`$context` is an `EntityTranslationContext` (subject: `getEntity(): TranslatableInterface`) or a
`PropertyTranslationContext` (subject: `getValue(): mixed`), both extending the shared
`TranslationContext` base (`getSubject()`, `getProperty()`, `getTranslatedParent()`,
`isShared()`/`isEmpty()`, source/target locale, `copySource`). `isShared()`/`isEmpty()` are
pre-resolved by `EntityTranslator` from the property's attributes before dispatch (v4.0) — a
handler that used to implement `handleSharedAmongstTranslations()`/`handleEmptyOnTranslate()`
branches on those two booleans at the top of `translate()` instead. See
[UPGRADING.md § 6](../UPGRADING.md#6-translationhandlerinterface-is-two-methods-on-typed-contexts).

```yaml
# config/services.yaml
App\Translation\Handler\MyCustomHandler:
    tags: [{ name: 'tmi_translation.translation_handler', priority: 75 }]
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

Both extend `TranslateEvent` and are dispatched by class (v4.0), not by a string event
name — listen with `#[AsEventListener(event: PreTranslateEvent::class)]` or
`addListener(PreTranslateEvent::class, ...)`.

| Event | When |
|-------|------|
| `PreTranslateEvent` | Before translation starts |
| `PostTranslateEvent` | After successful translation |

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
  `TranslatableRepositoryTrait`, `LocaleCompletenessResolver`, `EntityTranslator` and
  `TranslatableRemover` delegate to it. `TranslatableEntityHandler` does not (v4.0): it no
  longer checks for an existing variant itself — see the Performance section below.
- `Doctrine/TranslatableRemover` — removes every locale variant sharing a Tuuid (or exempts one
  variant from that) via `EntityManager::remove()` per variant, so ORM cascades / `orphanRemoval`
  / lifecycle callbacks fire per variant — never a bulk DQL DELETE. `$em->remove()` alone only
  ever touches the one row it is given. Two process-local state maps (an in-progress guard
  keyed by Tuuid string, a `\WeakMap` exemption keyed by object identity) prevent recursion
  and double `preRemove` firing when the cascade below is active — both are set before the
  work and cleared in a `finally`.
- `Doctrine/EventListener/LocaleVariantRemovalListener` — opt-in `preRemove` listener (always
  registered; `cascade_remove_locale_variants` decides at runtime whether it does anything)
  that calls `TranslatableRemover::cascadeFromPreRemove()` so a plain `$em->remove()` on any
  translatable entity cascades to its sibling locale variants automatically.

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
| `tmi:translation:doctor` | Scan for standalone/incomplete/duplicate anomalies plus `null-tuuid` (v4.0: a literal DB `NULL`, only reachable via a write outside the entity layer); `--entity=<FQCN>` restricts the scan; exits non-zero on findings |
| `tmi:translation:sync-shared` | Back-fill `#[SharedAmongstTranslations]` column values across existing locale variants; `--dry-run`, `--check` (CI gate), `--entity`; prints a `Property \| Tuuids \| Rows \| Writable` drift table (v4.0) |

## Performance (v4.0)

- `Utils/AttributeHelper` (per `declaringClass::property::attribute`) and
  `Utils/ReflectionHelper::getHierarchyProperties()` (per proxy-unwrapped class) memoize for
  the process lifetime — the `translate()` hot path no longer re-walks a class's attributes
  and property hierarchy on every property, every call.
- `EntityTranslator::preload(iterable $entities, string $locale): void` batches an import's
  existing-variant lookups per class instead of per entity (one `LocaleVariantFinder` query
  per class); `getOrTranslate()`'s internal warmup calls it with a single entity, so a bare
  loop still costs one lookup per entity — call `preload()` with the whole batch first. A
  batch's misses are remembered per (tuuid, locale) pair, so the per-entity loop after it
  costs no further lookup queries at all — dropped the instant this translator caches a
  translation for that pair, so a variant it creates is always found again across an
  `EntityManager::clear()`; the one gap is a variant for a remembered pair created by some
  other means, invisible until this translator creates one or the service is reset.
- `BidirectionalOneToManyHandler`, `BidirectionalManyToManyHandler` and
  `UnidirectionalManyToManyHandler` each call `preload()` with their whole collection once,
  before iterating it, instead of leaving every child's own `translate()` call to query for
  itself — a parent with *K* already-translated association children of one class costs 2
  queries total (the parent's own miss + one batched children lookup), not `1 + K`. This is
  automatic for any collection reached through these bundled handlers; the "bare loop" above
  is about code that calls `translate()`/`getOrTranslate()` directly over a batch of top-level
  entities, which still needs its own upfront `preload()` call.
- `TranslatableEntityHandler` no longer checks for an existing target-locale variant itself —
  that question is resolved exactly once, by `processTranslation()`'s own
  `preload()`-then-cache-check, before any handler runs; the handler always clones.
- `InMemoryTranslationCache` **and** `EntityTranslator` are tagged `kernel.reset`
  (`ResetInterface`, explicit tag — Symfony does not autoconfigure it) so a long-running
  worker resets the cache, and forgets `preload()`'s miss memory, between units of work.
- `tests/Performance/QueryBudgetTest.php` asserts an exact query count (`assertSame`, not a
  ceiling) for every operation in this list, via `tests/Support/QueryCounter.php` behind
  DBAL's logging middleware — see README.md § Performance for the numbers.

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
