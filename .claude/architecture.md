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

### Recursion Guard (Cycle Detection)

`EntityTranslator::processTranslation()` marks a `(tuuid, locale)` pair in-progress before
running the handler chain for it, and unmarks it in a `finally` — the flag stays set for the
whole frame, including any handler recursion below it. A bidirectional association whose
translation walks back to an entity already mid-translation for that same pair (the ordinary
shape, since the direct ManyToOne/OneToOne form runs the full entity pipeline) hits that mark and gets the untranslated **source instance itself** back,
instead of recursing forever — this is the cycle-guard fallback, and it is indistinguishable
from a legitimate "no translation happened" return except by comparing identity and locale.

`BidirectionalOneToManyHandler`, `BidirectionalManyToManyHandler` and
`UnidirectionalManyToManyHandler` are the three handlers that receive a *collection* of such
results and decide whether to add each one to the translated owner's collection (and, for the
two bidirectional handlers, repair its back-reference). All three check, right
before that add/back-reference write, whether the result is `===` the item they handed in *and*
that item's own locale is still the source locale — if so, it is the cycle-guard fallback and is
skipped outright, rather than mutating the source entity's own FK or back-reference collection.
An item returned unchanged because it *already* carries the target locale is a genuine existing
translation, not the guard, and is still added/re-pointed as before. See
[UPGRADING.md § 8](../UPGRADING.md#8-a-cycle-guard-fallback-never-mutates-the-source-entity).

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
pre-resolved by `EntityTranslator` from the property's attributes before dispatch — a
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
Field value is copied from the source when a translation is created, and — with
`propagate_shared_on_flush`, which is **on by default** — a later edit on *any* locale variant
reaches every sibling inside the same `flush()`; see
[Shared-Value Propagation](#shared-value-propagation). With the flag off there is no
update-time propagation and later edits diverge silently (a deliberate mode for content that
varies per locale); reconcile with `tmi:translation:sync-shared` and gate CI on drift with
`--check`.

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
- Stored as `CHAR(36)` via custom `TuuidType` (extends Doctrine's `GuidType`)
- Groups all language variants of an entity

## Events

Both extend `TranslateEvent` and are dispatched by class, not by a string event
name — listen with `#[AsEventListener(event: PreTranslateEvent::class)]` or
`addListener(PreTranslateEvent::class, ...)`.

| Event | When |
|-------|------|
| `PreTranslateEvent` | Before translation starts |
| `PostTranslateEvent` | After successful translation |

## Tuuid Linkage Integrity

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
  `TranslatableRemover` delegate to it. `TranslatableEntityHandler` does not: it performs no
  existence check of its own — see the Performance section below.
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

## Per-Locale Completeness

- `Translation/LocaleCompletenessResolver` — per enabled locale: does a variant exist, is
  its translatable content complete? Baseline-relative: a variant is complete when every
  translatable (non-shared, non-system, non-id) property filled on the default-locale row
  is filled on it too. `resolveBatch()` answers many Tuuids with one query. "Shared" comes
  from `SharedValueSynchronizer::sharedProperties()` — no discovery of its own.
- `ValueObject/LocaleCompleteness` + `ValueObject/TranslationStatus` (enum
  `Missing`/`Incomplete`/`Complete`) — the returned value objects.

## Shared-Value Propagation

- `Doctrine/SharedValueSynchronizer` — the one discovery + copy of `#[SharedAmongstTranslations]`
  values with the **edited row as source**: `syncFrom()` (every sibling, returns the changed
  ones managed and unflushed), `siblingsOf()`, `sync()`/`compare()` (one sibling, with/without
  writing → `ValueObject/SharedValueSyncReport`: `changed()`, `readonlyDrift()`), memoized
  `sharedProperties(class)` — mapped columns, embeddables in all three declaration places, to-one
  associations to a non-translatable target; never a collection or an association to a
  translatable target. Every entry carries the printed property path and the UnitOfWork
  change-set keys it maps to (a whole shared embeddable = one key per inner column).
  `SyncSharedTranslationsCommand` is a thin client of it (streaming + default-locale source +
  reporting only), so command and listener never disagree on what is shared.
- `Doctrine/EventListener/SharedValuePropagationListener` — `onFlush`, always registered,
  `propagate_shared_on_flush` gates it at runtime. Snapshot of scheduled updates → intersect
  each translatable's change set with the shared paths (minus paths the listener itself wrote
  onto that entity: per-(entity, path) ping-pong guard) → siblings from the database **plus**
  same-Tuuid entities scheduled for insertion, **minus** any sibling scheduled for deletion in
  the same flush (hydrated but no longer managed — it receives nothing) → conflict check (`SharedValueConflictException`
  when an update-scheduled sibling carries a *different* new value for the same path; an
  insertion never conflicts, the updated source wins) → `sync()` each sibling →
  `UnitOfWork::recomputeSingleEntityChangeSet()` on every sibling that changed (merges into an
  existing change set, schedules a managed sibling that had none, merges into an insertion).
  No `flush()`, no `persist()` inside `onFlush`. One `debug` line per propagating entity with
  `enable_logging`.
- `Doctrine/LocaleVariantFinder::streamGroupedByTuuid()` — streams a whole table grouped by
  Tuuid (managed, locale filter suspended for the iteration, restored in the generator's
  `finally`); the shared core of the scanner and the command. Consumers detach each group
  themselves and never `clear()` mid-iteration (the next group's first row is already hydrated).
- `Doctrine/SharedDriftScanner` — read-only `scan(class)` → `\Generator<ValueObject/SharedDrift>`
  (entity class, Tuuid, path, source locale, drifted locale, readonly flag; locations, never
  values), one per drifted sibling row and property, detaching each group as it goes;
  `pickSource()` = the command's canonical-row rule (default-locale row, else the first).
- `tmi:translation:sync-shared --tuuid=<uuid> [--source-locale=<locale>]` — one record, every
  locale variant, copied from the named row instead of the default-locale row; `--source-locale`
  is refused without `--tuuid`.
- Class-level `#[SharedAmongstTranslations]` is honoured on embeddables only; on an entity class
  it is inert (documented in 4.1, unchanged behaviour).

## Console Commands

| Command | Purpose |
|---------|---------|
| `tmi:translation:doctor` | Scan for standalone/incomplete/duplicate anomalies plus `null-tuuid` (a literal DB `NULL`, only reachable via a write outside the entity layer); `--entity=<FQCN>` restricts the scan; exits non-zero on findings |
| `tmi:translation:sync-shared` | Back-fill `#[SharedAmongstTranslations]` values across existing locale variants from the default-locale row — columns, embeddables and to-one associations to a non-translatable target; `--dry-run`, `--check` (CI gate), `--entity`, `--tuuid` + `--source-locale` (one record from the named row); prints a `Property \| Tuuids \| Rows \| Writable` drift table; a translation root reference the siblings disagree on is reported as not writable and fails the run |
| `tmi:translation:adopt-root` | Create translation roots for the `tuuid` groups that predate them, through the registered `RootAdopterInterface` per hierarchy; classifies every group before writing (`mismatched`/`new`/`complete`/`partial`/`drift`/`ambiguous`), aborts write mode on mismatched/drift/ambiguous; `--dry-run`, `--check` (CI gate: only `complete` groups, no root without rows, every `tuuid_orphan_counter` at 0), `--entity` (streams the hierarchy root) |

## Translation Roots

- `Doctrine/Model/TranslationRootInterface` + `TranslationRootTrait` — the root's identity
  contract (`hasTuuid()`, `adoptTuuid()`, `getTuuid()` on the interface; `mintTuuid()` and the
  unique `tuuid` column on the trait). Never `TranslatableInterface`, never PHP `readonly`.
- `Doctrine/Attribute/TranslationRoot` — optional marker; the reference is a root reference by
  TYPE (`Utils/AttributeHelper::isTranslationRootReference()`: `ManyToOne` + declared type
  implementing the interface). `isEffectivelyShared()` = attribute OR root reference, asked by
  `EntityTranslator::runHandlers()` and `SharedValueSynchronizer::discover()`.
- `Exception/TranslationRootContractException` — one class, named factories, `Solution:` line;
  per-property checks in `AttributeHelper::collectValidationErrors()`, per-class checks (one
  root reference; constructor rule for a non-nullable one) in
  `DependencyInjection/Compiler/AttributeValidationPass`, which also publishes
  `tmi_translation.translation_root_classes`.
- `DependencyInjection/Compiler/RootAdopterPass` (after the validation pass) collects tag
  `tmi_translation.root_adopter` (required attribute `class`) into `Doctrine/Root/RootAdopterRegistry`
  and cross-checks it against that parameter; `TuuidOrphanCounterPass` collects
  `tmi_translation.tuuid_orphan_counter` into `Doctrine/Root/RootCheckAggregator` (roots without
  rows via `NOT EXISTS`, filter suspended).
- `Command/AdoptRootCommand` — see the table above; `Exception/RootAdoptionException` for a
  factory result that already carries a `tuuid` or is of the wrong class.
- `SharedValueSynchronizer` flags a root reference `root` in its `SharedProperty` entries and
  reports a mismatch as `SharedValueSyncReport::rootDrift()`; it is never written by
  `reconcile()`, and `SharedValuePropagationListener` skips it entirely.

## Performance

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
├── CacheWarmer/          # TranslatableEntityValidationWarmer (cache:warmup validation pass)
├── Command/              # Diagnostic / maintenance console commands
├── DependencyInjection/  # Bundle configuration
├── Doctrine/             # ORM integration (models, types, filters, listeners)
│   └── Root/             # RootAdopterInterface, RootAdopterRegistry, TuuidOrphanCounterInterface, RootCheckAggregator (5.1)
├── Event/                # Translation events
├── EventSubscriber/      # LocaleFilterConfigurator (toggles the locale filter per request)
├── Exception/            # Bundle exceptions
├── Resources/            # config/services.yaml (service definitions)
├── Translation/          # Core translation logic
│   ├── EntityTranslator.php # Main orchestrator
│   └── Handlers/         # Handler chain
├── Twig/                 # TmiTranslationExtension (the tmi_locales global)
├── Utils/                # Helpers (AttributeHelper)
└── ValueObject/          # Tuuid, LocaleCompleteness and TranslationStatus
```
