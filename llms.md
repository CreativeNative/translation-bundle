# CreativeNative Translation Bundle -- Developer & AI Guide
*(for Symfony 8.0+, Doctrine ORM 3.5+, PHP 8.4+)*

## Overview
The bundle stores every locale variant of a Doctrine entity as a row in the entity's own
table -- same Tuuid, no join table, ever -- with control over which fields are
**language-specific** and which are **shared across translations**. It operates by cloning
or sharing entities/properties, using a priority-ordered handler chain and attributes to
guide behaviour.

**Two arguments, both enforced by a test, not asserted in prose:**

- **Performance.** Every query-cost number this guide states is an exact assertion
  (`assertSame`, not a ceiling) in [`tests/Performance/QueryBudgetTest.php`](tests/Performance/QueryBudgetTest.php).
  `find()` under the active locale filter costs 1 query; `translate()` into an
  already-existing variant costs 1 query and 0 inserts. See [Performance (v4.0)](#performance-v40)
  for the full table.
- **Verified quality.** 100% **line** coverage is a CI gate (`composer test`), not a
  snapshot; PHPStan runs at **level max** with the strict-rules/doctrine/symfony/phpunit
  extensions; PHPUnit runs in strict mode (`failOnWarning`/`failOnNotice`/`failOnRisky`/
  `failOnDeprecation`). As of this release: **639 tests, 5,110 assertions**, all green.
  Every bug fix ships with a negative-proof test -- demonstrably red against the old code,
  not merely green after the fix -- visible directly in the commit history.

Key components:
- `EntityTranslator` -- central translation orchestrator; also the `preload()` entry point for batch imports (v4.0).
- `Handlers` -- classes that manage translation of entities, embeddables, collections etc.
- `PropertyAccessor` -- used to read/write object properties generically.
- `TranslationContext` -- abstract base of the two typed containers holding the context of a translation operation, `EntityTranslationContext`/`PropertyTranslationContext` (v4.0; replaces the single `TranslationArgs` DTO).
- `AttributeHelper` -- utility to inspect attributes/annotations like `#[SharedAmongstTranslations]` or `#[EmptyOnTranslate]`; memoizes per class::property::attribute (v4.0).
- `LocaleVariantFinder` (v4.0) -- the one place that queries across every locale variant of a Tuuid, filter-suspended.
- `TranslatableRemover` (v4.0) -- removes a Tuuid's sibling locale variants together, or exactly one while leaving its siblings.

---

## Glossary

**Tuuid** (Translation UUID): UUIDv7 value object that groups all language variants of an entity. Stored as VARCHAR(36). Each translatable entity shares the same Tuuid across all its translations.

**Translatable entity**: Any Doctrine entity implementing `TranslatableInterface` and using `TranslatableTrait`. These entities can be translated into multiple locales.

**Handler**: A class implementing `TranslationHandlerInterface` that processes specific field types during translation. Each handler specializes in one type of data (scalars, relations, embedded objects, etc.).

**Handler chain**: Priority-ordered sequence of handlers where the first handler whose `supports()` method returns true processes the field. Higher priority numbers are checked first.

**Locale**: Language/region code (e.g., "en", "fr", "de") identifying a translation variant.

**Source entity**: The original entity being translated from.

**Target entity**: The new entity being created for the target locale.

---

## Handler Chain Decision Tree

When a field needs translation, the EntityTranslator routes it through the handler chain based on field type. This ASCII diagram shows the routing logic:

```
Field Processing Flow
=====================

                    [Field to translate]
                            |
                    Is it a primary key?
                      /            \
                   YES              NO
                    |                |
            PrimaryKeyHandler        |
              (priority 100)         |
                    |                |
                 Returns null        |
                                     |
                            Is it scalar/DateTime?
                              /            \
                           YES              NO
                            |                |
                     ScalarHandler           |
                      (priority 90)          |
                            |                |
                       Copies value          |
                                             |
                                    Is it embedded?
                                      /          \
                                   YES            NO
                                    |              |
                             EmbeddedHandler       |
                              (priority 80)        |
                                    |              |
                               Clones object       |
                                                   |
                                    Is it ManyToOne with inversedBy?
                                            /              \
                                         YES                NO
                                          |                  |
                        BidirectionalManyToOneHandler        |
                               (priority 70)                 |
                                          |                  |
                           Clones and translates parent      |
                                                             |
                                        Is it OneToMany with mappedBy?
                                                /                    \
                                             YES                      NO
                                              |                        |
                              BidirectionalOneToManyHandler            |
                                       (priority 60)                   |
                                              |                        |
                                   Translates collection               |
                                                                       |
                                    Is it OneToOne with mappedBy/inversedBy?
                                                /                          \
                                             YES                            NO
                                              |                              |
                                BidirectionalOneToOneHandler                 |
                                       (priority 50)                         |
                                              |                              |
                                 Clones and maintains link                   |
                                                                             |
                                        Is it ManyToMany bidirectional?
                                                  /                \
                                               YES                  NO
                                                |                    |
                              BidirectionalManyToManyHandler         |
                                       (priority 40)                 |
                                                |                    |
                                   Translates both sides              |
                                                                     |
                                             Is it ManyToMany unidirectional?
                                                      /                    \
                                                   YES                      NO
                                                    |                        |
                                  UnidirectionalManyToManyHandler            |
                                           (priority 30)                     |
                                                    |                        |
                                       Translates one side only              |
                                                                             |
                                                Does it implement TranslatableInterface?
                                                             /                           \
                                                          YES                             NO
                                                           |                               |
                                                 TranslatableEntityHandler                 |
                                                      (priority 20)                        |
                                                           |                               |
                                          Recursively translates entity                    |
                                                                                           |
                                                            Is it a Doctrine-managed object?
                                                                         /               \
                                                                      YES                 NO
                                                                       |                   |
                                                          DoctrineObjectHandler      No handler
                                                               (priority 10)            matches
                                                                       |
                                                          Clones and translates
                                                             properties
```

### Why Priority Order Matters

The handler chain uses **priority-based routing** where higher numbers are checked first. This order is critical for correctness:

**100 - PrimaryKeyHandler**: Must run first to ensure entity IDs are never translated. IDs are database-generated identifiers that must remain null for new translations.

**90 - ScalarHandler**: Catches simple values (strings, integers, booleans, DateTime) before relationship handlers. This prevents scalars from being misinterpreted as relations.

**80 - EmbeddedHandler**: Processes embedded value objects (like Address, Money) before relationship handlers, since embedded objects use different metadata than relations.

**70-30 - Relationship Handlers**: Ordered by specificity, from most specific to least:
- **70 - BidirectionalManyToOne**: Most specific (has inversedBy)
- **60 - BidirectionalOneToMany**: Next (has mappedBy)
- **50 - BidirectionalOneToOne**: Bidirectional singular relation
- **40 - BidirectionalManyToMany**: Bidirectional collection
- **30 - UnidirectionalManyToMany**: Least specific (no mappedBy/inversedBy)

**20 - TranslatableEntityHandler**: Handles nested translatable entities. Lower priority ensures relationships are processed by their specific handlers first.

**10 - DoctrineObjectHandler**: Fallback for any Doctrine-managed object not caught by specialized handlers. Lowest priority means it only runs when nothing else matches.

If handlers were out of order, critical issues would occur. For example, if DoctrineObjectHandler (10) ran before PrimaryKeyHandler (100), IDs might be incorrectly cloned, causing database constraint violations.

---

## Core Concepts

### Translation vs. Shared Fields vs. Empty Fields

#### 1. Translatable Fields
- Fields whose values differ per locale (e.g., title, description).
- Each translated entity gets its own independent value.
- During translation:
  - Scalar values are copied.
  - Objects or embedded values are cloned (deep copy).

#### 2. Shared Fields (#[SharedAmongstTranslations])
- Fields or embeddables whose value is copied from the source when a translation is created.
- Scalar and association fields reference the same object instance at translate time. Embeddables are the exception: `EmbeddedHandler` always returns a clone (never the source instance) — the clone's property values match the source, so persisted data is identical, but each locale still holds its own embeddable object.
- **Copy-on-translate, not an enforced invariant**: editing the field on one locale variant afterwards diverges it silently (deliberately — consumers may vary such values per locale). `tmi:translation:sync-shared` reconciles; `--check` gates CI on drift.
- If the attribute is on the embeddable, the whole object's values are shared (each locale still gets its own cloned instance).
- If the attribute is on properties within an embeddable, only those properties' values are shared; others may still be cloned/reset.
- Intentionally inert on a class that does not implement `TranslatableInterface`: nothing reads the attribute outside the translate() pipeline, so a trait shared between translatable and non-translatable classes (e.g. a `GeoLocatableTrait` mixed into both) can carry it on a property with no effect on the classes that merely reuse the trait.

#### 3. Empty-on-Translate Fields (#[EmptyOnTranslate])
- Fields that must be reset when creating a new translation.
- For nullable fields, values are set to null.
- For non-nullable scalar fields, type-safe defaults are used: string='', int=0, float=0.0, bool=false (via TypeDefaultResolver).
- Collection properties are emptied by their handler (a fresh, empty collection). `TypeDefaultResolver` is not consulted for them.
- Every other non-nullable type without a zero-value — objects, enums, `iterable`, intersection types — throws LogicException with guidance to make it nullable or use #[SharedAmongstTranslations].
- Embedded objects are replaced with a new, empty instance (or null for nullable embeddables).
- #[SharedAmongstTranslations] and #[EmptyOnTranslate] on the **same** property is a configuration error, not a precedence question: `AttributeValidationPass` rejects it at compile time with an `AttributeConflictException`.
- #[EmptyOnTranslate] on a `readonly` property is rejected the same way (`ReadonlyPropertyException`) — a readonly property cannot be re-assigned after hydration.

#### 4. Priority of Rules
1. #[SharedAmongstTranslations] → wins over the default cloning behaviour (it cannot co-exist with #[EmptyOnTranslate] on the same property).
2. #[EmptyOnTranslate] → clears the value.
3. Otherwise → default translation cloning behavior.
4. If `copy_source: false` (v2.0 default) and field has #[EmptyOnTranslate]: type-safe defaults used instead of null for non-nullable types.

---

### Workflow
1. A source entity (locale A) is passed to EntityTranslator to produce a target translation entity (locale B).
2. Handlers inspect each property of the source:
  - If the property is marked `#[SharedAmongstTranslations]`, the same value is reused/propagated across siblings.
  - If the property is marked `#[EmptyOnTranslate]`, the target value will be set to null (nullable types) or type-safe defaults (non-nullable scalars: string='', int=0, float=0.0, bool=false), a new empty instance (embeddables) or an empty collection (to-many associations), regardless of the source.
  - Otherwise, a clone or new value may be created for the target locale, depending on other attributes and the property type.
3. PropertyAccessor is used to read source values and write to the target.
4. The result is a consistent set of entities: one per locale, sharing or translating fields as configured.

---

## Key Components  

### [EntityTranslator](src/Translation/EntityTranslator.php)
- Class/interface: [`EntityTranslatorInterface`](src/Translation/EntityTranslatorInterface.php) (provided by the bundle).  
- Responsible for initiating translation: taking a source object + sourceLocale + targetLocale, and returning the translated object.
- Internally delegates to appropriate handler(s) depending on object type (entity vs embeddable vs collection).
- Ensures metadata (locale property, Tuuid) is set correctly.
- Translating an entity into the locale it already carries is the **identity operation**: the same instance comes back, nothing is cloned and nothing is cached, and nothing is logged. `TranslatableEventSubscriber`'s own `prePersist`/`postLoad` normalise an entity's locale before `translate()` is ever consulted, so `translate($entity, $entity->getLocale())` is the call shape every flush makes -- not usually, always. (v3.x had four `EntityTranslator` lifecycle hooks -- `afterLoad`/`beforePersist`/`beforeUpdate`/`beforeRemove` -- that existed to intercept exactly this identity call; v4.0 removed them because every single invocation, on every flush, was a no-op.)
- Translating into a **different** locale is **get-or-create, not a live sync**: if a variant for the source's Tuuid and the target locale already exists (see `TranslatableEntityHandler` below), `translate()` returns it as-is instead of re-running the handler chain — in-memory edits made to the source *after* that variant was created are not propagated into it. That is deliberate for the same idempotency reason as the identity operation above. Propagating a changed value into existing siblings is `#[SharedAmongstTranslations]` + `tmi:translation:sync-shared`'s job, not `translate()`'s.
- `#[EmptyOnTranslate]` on a **collection** property is emptied by its handler (a fresh empty collection). Only non-collection, non-nullable properties fall back to `TypeDefaultResolver`.

### Translation Handlers

All handlers implement [`TranslationHandlerInterface`](src/Translation/Handlers/TranslationHandlerInterface.php), which defines two methods, both taking a typed [`TranslationContext`](src/Translation/Context/TranslationContext.php) (v4.0 — `EntityTranslationContext` or `PropertyTranslationContext`; replaces the four-method `TranslationArgs` contract):
- `supports(TranslationContext $context): bool` — Determines if the handler can process the data.
- `translate(TranslationContext $context): mixed` — Performs the actual translation logic. `EntityTranslator` resolves `#[SharedAmongstTranslations]`/`#[EmptyOnTranslate]` from the property's attributes *before* dispatch and stamps the answer onto the context (`$context->isShared()`/`isEmpty()`); a handler branches on those at the top of `translate()` for the cases the old `handleSharedAmongstTranslations()`/`handleEmptyOnTranslate()` methods used to cover.

---

#### [PrimaryKeyHandler](src/Translation/Handlers/PrimaryKeyHandler.php)
- **Purpose:** Handles **primary key properties** (IDs).
- **Priority:** 100
- **Dependencies:** `AttributeHelper`.
- **Methods:**
    - `supports()` — Returns true if property is a primary key.
    - `translate()` — Always returns `null`, regardless of `isShared()`/`isEmpty()`.
- **Notes:** Ensures entity identity is immutable, excluded from translation logic.

---

#### [ScalarHandler](src/Translation/Handlers/ScalarHandler.php)
- **Purpose:** Handles **scalar values** and `DateTime`.
- **Priority:** 90
- **Dependencies:** None.
- **Methods:**
  - `supports()` — Returns true if value is scalar or `DateTime`.
  - `translate()`:
    - `isShared()` — Returns original value (falls through, same as the default case below).
    - `isEmpty()` — Returns `null` for nullable fields; non-nullable fields never reach the handler here — `EntityTranslator` resolves them to type-safe defaults (string='', int=0, float=0.0, bool=false) via `TypeDefaultResolver` before dispatch.
    - Otherwise — Returns original value.
- **Notes:** Leaf handler in the translation pipeline; no delegation required.

---

#### [EmbeddedHandler](src/Translation/Handlers/EmbeddedHandler.php)
- **Purpose:** Handles **Doctrine embeddable objects** (`@Embeddable`).
- **Priority:** 80
- **Dependencies:** `AttributeHelper`.
- **Methods:**
  - `supports()` — Returns true if property is an embeddable.
  - `translate()`:
    - `isShared()` — Returns a cloned embeddable (never the original instance), with property values left untouched so persisted data still matches across locale siblings.
    - `isEmpty()` — Returns null when the outer property itself is `#[EmptyOnTranslate]`; otherwise clones the embeddable and clears each inner property carrying its own `#[EmptyOnTranslate]` (type-safe default for a non-nullable one).
    - Otherwise — Returns a cloned embeddable, resolved property by property through the three-level cascade (property attribute → class attribute → class default).
- **Notes:** Works on value objects embedded in entities, preserves immutability.

---

#### [BidirectionalManyToOneHandler](src/Translation/Handlers/BidirectionalManyToOneHandler.php)
- **Purpose:** Handles translation of **bidirectional ManyToOne associations**, in either of the two
  shapes the property can be reached in: the **direct form** (the property is declared on a
  *different*, owning class -- e.g. `Product::$category` -- reached via
  `DoctrineObjectHandler::translateProperties()`) and the **back-reference form** (the property is
  the child's own field pointing back at its parent, reached via `BidirectionalOneToManyHandler`).
- **Priority:** 70
- **Dependencies:** `AttributeHelper`, `EntityManagerInterface`, `PropertyAccessorInterface`, `TranslatableEntityHandler`.
- **Methods:**
  - `supports()` — Returns true for an `EntityTranslationContext` with a ManyToOne association having `inversedBy`.
  - `translate()`:
    - `isShared()` — Throws exception; unsupported.
    - `isEmpty()` — Returns `null`.
    - Otherwise — Delegates the clone itself to `TranslatableEntityHandler::translate()` (the entity's own property pipeline, generated-id reset, locale -- existence of a target-locale variant was already resolved before this handler ran, by `EntityTranslator::processTranslation()`'s own `preload()`-then-cache-check for this same subject). Direct form: the translated target is returned as-is (get-or-create) -- there is no scalar back-reference field to repair. Back-reference form: the entity's own field matching `$propertyName` is repaired to the parent already known to be under translation, since the pipeline's own recursive lookup for that same field hits the translator's in-progress guard and would otherwise leave the untranslated source in place.
- **Notes:** Never mutates the source; integrates with `TranslatableEntityHandler`/`EntityTranslator` for nested translations.

---

#### [BidirectionalOneToManyHandler](src/Translation/Handlers/BidirectionalOneToManyHandler.php)
- **Purpose:** Handles translation of **bidirectional OneToMany associations**.
- **Priority:** 60
- **Dependencies:** `AttributeHelper`, `EntityTranslatorInterface`, `EntityManagerInterface`.
- **Methods:**
    - `supports()` — Returns true for a `PropertyTranslationContext` whose value is a `Collection` and whose property is a OneToMany having `mappedBy`.
    - `translate()`:
        - `isShared()` — Throws exception; unsupported.
        - `isEmpty()` — Returns an empty `ArrayCollection`.
        - Otherwise — Iterates over child collection, translates each child recursively, sets inverse property to maintain bidirectional consistency, returns translated `ArrayCollection`.
- **Notes:** Maintains bidirectional integrity, ensures clones are used, integrates with `EntityTranslator`.

---

#### [BidirectionalOneToOneHandler](src/Translation/Handlers/BidirectionalOneToOneHandler.php)
- **Purpose:** Handles translation of **bidirectional OneToOne associations**.
- **Priority:** 50
- **Dependencies:** `EntityManagerInterface`, `PropertyAccessor`, `AttributeHelper`, `TranslatableEntityHandler`.
- **Methods:**
  - `supports()` — Returns true for an `EntityTranslationContext` with OneToOne having `mappedBy` or `inversedBy`.
  - `translate()`:
    - `isShared()` — Throws exception; unsupported.
    - `isEmpty()` — Returns `null`.
    - Otherwise — Delegates the clone itself to `TranslatableEntityHandler::translate()` (the related entity's own property pipeline, generated-id reset, locale -- existence of a target-locale variant was already resolved before this handler ran, by `EntityTranslator::processTranslation()`'s own `preload()`-then-cache-check for this same subject), then repairs the back-reference field to the parent already known to be under translation -- the pipeline's own recursive lookup for that same field hits the translator's in-progress guard and would otherwise leave the untranslated source parent in place.
- **Notes:** Ensures bidirectional integrity between parent and child, never mutates the source, works with `TranslatableEntityHandler`/`EntityTranslator`.

---

#### [BidirectionalManyToManyHandler](src/Translation/Handlers/BidirectionalManyToManyHandler.php)
- **Purpose:** Translates **bidirectional ManyToMany Doctrine associations** in `TranslatableInterface` entities.
- **Priority:** 40
- **Dependencies:** `AttributeHelper`, `EntityManagerInterface`, `EntityTranslatorInterface`.
- **Methods:**
    - `supports()` — Returns true for a `PropertyTranslationContext` whose value is a `Collection` and whose property is a ManyToMany association having `mappedBy` or `inversedBy`.
    - `translate()`:
        - `isShared()` — Throws exception if `#[SharedAmongstTranslations]` is present (the common case, since `EntityTranslator` only sets `isShared()` when it is); otherwise falls through to the same collection-translation logic as the default case, via a private `translateCollection()` helper (calling `translate()` again would re-enter this same branch).
        - `isEmpty()` — Best-effort clears the target collection on the translated parent, and returns an empty `ArrayCollection`.
        - Otherwise — Builds a new collection of translated related entities and points each one back at the translated owner (via `mappedBy`, or `inversedBy` when the translated entity owns the relation). The back-reference is added, never replaced, and the source entities are left untouched. Avoids duplicate entries.
- **Notes:** Maintains bidirectional integrity, ensures cloned translations do not affect originals, integrates with `EntityTranslator`.

---

#### [UnidirectionalManyToManyHandler](src/Translation/Handlers/UnidirectionalManyToManyHandler.php)
- **Purpose:** Handles translation of **unidirectional ManyToMany associations** in `TranslatableInterface` entities.
- **Priority:** 30
- **Dependencies:** `AttributeHelper`, `EntityTranslatorInterface`, `EntityManagerInterface`.
- **Methods:**
  - `supports()` — Returns true for a `PropertyTranslationContext` whose value is a `Collection` and whose property is a ManyToMany association **without** `mappedBy` or `inversedBy` (unidirectional).
  - `translate()`:
    - `isShared()` — Throws a `RuntimeException` if `#[SharedAmongstTranslations]` is applied (unsupported); otherwise falls through to the same collection-translation logic as the default case, via a private `translateCollection()` helper (calling `translate()` again would re-enter this same branch).
    - `isEmpty()` — Returns a new empty `ArrayCollection`.
    - Otherwise — Translates each item in the collection:
      - `TranslatableInterface` items are translated for the target locale using `EntityTranslator`; every other item (plain entities such as tags or categories, the most common shape for a unidirectional ManyToMany — plus any item when no target locale is available) is added to the result **as-is**, not dropped.
      - Collects them into a **new** `ArrayCollection`, preventing duplicates (same instance check for both translated and passed-through items).
      - Never clears the collection currently held by the translated parent — a clone shares that instance with the source entity, so clearing it would wipe the source association. The caller assigns the returned collection.
- **Notes:**
  - Ensures safe translation of unidirectional ManyToMany relations without affecting the original collection.
  - Maintains Doctrine collection integrity while cloning translated items.
  - Prevents shared translation attributes from being misused on unidirectional relations.
  - Non-translatable items in the collection are preserved rather than silently dropped — mirrors `BidirectionalManyToManyHandler`'s pass-through behaviour for the same case.

---

#### [TranslatableEntityHandler](src/Translation/Handlers/TranslatableEntityHandler.php)
- **Purpose:** Handles **entities implementing `TranslatableInterface`**.
- **Priority:** 20
- **Dependencies:** `DoctrineObjectHandler`, `AttributeHelper`. (v4.0: no longer `LocaleVariantFinder` — see below.)
- **Methods:**
    - `supports()` — Returns true when the context is an `EntityTranslationContext`.
    - `translate()`:
        - `isEmpty()` — Returns `null`. (No `isShared()` branch here: the bidirectional handlers above never delegate to this one while shared — their own `translate()` branches on `isShared()` first and never reaches this call.)
        - Otherwise — Always clones and translates via `DoctrineObjectHandler`. Automatically resets generated IDs (`#[ORM\Id]` + `#[ORM\GeneratedValue]`) on cloned translations (v2.1).
- **Notes:** Integrates entity-level and property-level translation. Since v2.1, callers no longer need to manually reset auto-generated IDs on cloned translations. **v4.0:** no longer checks for an existing target-locale variant itself — `EntityTranslator::processTranslation()` resolves that exactly once, via its own `preload()`-then-cache-check, before dispatching to *any* handler (see that method's docblock). This handler is reached only (a) from `EntityTranslator::runHandlers()`, always after that check ran for the same subject, or (b) from `BidirectionalManyToOneHandler`/`BidirectionalOneToOneHandler`, themselves reached the same way for the same subject. Calling `translate()` any other way — bypassing `EntityTranslatorInterface::translate()`/`processTranslation()` — skips the check entirely and always clones, minting a duplicate row for a Tuuid that already has a variant in the target locale.

---

#### [DoctrineObjectHandler](src/Translation/Handlers/DoctrineObjectHandler.php)
- **Purpose:** Handles **basic Doctrine-managed objects**. Entry point for translating full entities.
- **Priority:** 10
- **Dependencies:** `EntityManagerInterface`, `EntityTranslatorInterface`, optional `PropertyAccessorInterface`.
- **Methods:**
    - `supports()` — Returns true if object/class is Doctrine-managed; handles proxies.
    - `translate()`:
        - `isShared()` — Returns the original subject unchanged (`getSubject()`).
        - `isEmpty()` — Returns `null`.
        - Otherwise — Clones the subject (`setSubject()`), calls `translateProperties()` for recursive translation.
    - `translateProperties()` — Iterates properties, delegates to `EntityTranslator`, sets translated values via accessor or reflection.
- **Notes:** Core handler for property-level translation, ensures original entities are never mutated.

---

#### Notes for Handlers
- Handlers can be extended or replaced to implement custom translation logic.
- `AttributeHelper` is used throughout to detect Doctrine mapping types (`OneToMany`, `ManyToOne`, `Embedded`, `Id`, `OneToOne`, etc.).
- `TranslationContext` (abstract base of `EntityTranslationContext`/`PropertyTranslationContext`, v4.0 — replaces `TranslationArgs`) encapsulates:
    - the subject being translated: `getSubject()` (mixed, either shape) plus the typed accessor for the concrete class — `getEntity(): TranslatableInterface` or `getValue(): mixed`
    - `sourceLocale` / `targetLocale`
    - `translatedParent` (for bidirectional associations)
    - `property` (ReflectionProperty being translated)
    - `isShared()` / `isEmpty()` — attribute facts `EntityTranslator` resolves before dispatch, replacing the two removed interface methods
- `EntityTranslatorInterface` orchestrates recursive property translation, delegating to appropriate handlers.

---

## Translation Cache Service

### [TranslationCacheInterface](src/Translation/Cache/TranslationCacheInterface.php)

Abstraction for translation caching and circular-reference detection. Replaces the internal `$translationCache` and `$inProgress` arrays from v1.x EntityTranslator.

**Interface methods:**
- `get(string $tuuid, string $locale): TranslatableInterface|null` -- Get cached translation
- `set(string $tuuid, string $locale, TranslatableInterface $entity): void` -- Store translation
- `markInProgress(string $tuuid, string $locale): void` -- Mark translation as in-progress (cycle detection)
- `unmarkInProgress(string $tuuid, string $locale): void` -- Remove in-progress mark
- `isInProgress(string $tuuid, string $locale): bool` -- Check if translation is in-progress

`EntityTranslator` always clears the in-progress mark in a `finally`, so a failing handler cannot leave a stale mark behind. A cache hands back managed instances or null; a custom implementation backed by a persistent store must reload through the `EntityManager` with the locale filter suspended -- in-progress markers are per-process by definition and are never expected to outlive the request that set them.

**Identity-safe across `EntityManager::clear()` (v4.0):** a cache hit is only ever handed back
when `$entityManager->getUnitOfWork()->getEntityState($cached)` is not `STATE_DETACHED`.
Before v4.0, a hit that survived a `clear()` call (import batches, long-running workers) was
still treated as reusable even though the `UnitOfWork` no longer tracked it --
`getOrTranslate()`'s `persist()` call then re-inserted that detached instance as a brand-new
row instead of reusing the existing one, silently, no exception. A detached hit is now a miss:
it falls through to a fresh lookup, which reloads (or reuses) a managed instance and
overwrites the stale cache entry.

**No `has()` on the contract:** `TranslationCacheInterface` deliberately has no existence check besides `get()`. A `has()` the bundle shipped up to v3.3.0 was removed in v3.4.0, because on a persistent backend key presence proves nothing: a row deleted since it was cached, or an entry written in an older format, leaves the key behind while the entry no longer loads -- exactly the v3.2.1 trap (see Revision History), where a check-then-get let that gap surface as a `TypeError` in production. The one reliable check is `get() !== null`, which also costs one pool round-trip instead of two. A custom cache implementation that still declares a `has()` method keeps working (an extra public method is harmless) -- just delete it.

### Default Implementation: InMemoryTranslationCache

Stores translations in PHP arrays, scoped to the current request. Registered as the default and only bundled implementation; the interface is aliased to it and there is no other option to switch to via configuration (v4.0 removed the bundled `Psr6TranslationCache` -- see Revision History). It also implements `ResetInterface`, tagged `kernel.reset` explicitly in `services.yaml` (Symfony does not autoconfigure that tag): a long-running worker (a Messenger consumer, `services_resetter`) resets the cache between units of work instead of handing a later one an entity an earlier one cached -- and possibly, since, detached.

A cache hands back managed instances or null, never a detached or stale one. `InMemoryTranslationCache` never outlives the request, but it can still hold an instance that `EntityManager::clear()` has detached since it was cached. `EntityTranslator` therefore checks every hit against the UnitOfWork (`getEntityState()` without an assumed state) and treats a `STATE_DETACHED` hit as a miss, reloading through `LocaleVariantFinder` instead of handing back an instance that `persist()` would re-insert as a new row (v4.0, identity-safe cache). A persistent, cross-request implementation (Redis, filesystem, ...) is possible via a custom `TranslationCacheInterface` -- see below -- but it must reload the entity through the `EntityManager` on every hit, with the locale filter suspended, rather than serializing the entity itself: a serialized Doctrine entity carries dead proxy/EntityManager references across requests or processes, and reloading also lets a row deleted since it was cached resolve to a clean miss instead of a stale object.

### Custom Implementation

To use a custom cache (e.g., Redis):

```php
use Tmi\TranslationBundle\Translation\Cache\TranslationCacheInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;

class RedisTranslationCache implements TranslationCacheInterface
{
    public function __construct(private RedisClient $redis) {}

    public function get(string $tuuid, string $locale): TranslatableInterface|null
    {
        $entity = $this->redis->get("translation.{$tuuid}.{$locale}");

        return $entity instanceof TranslatableInterface ? $entity : null;
    }

    // ... implement remaining 4 methods
}
```

Register via DI:
```yaml
# config/services.yaml
Tmi\TranslationBundle\Translation\Cache\TranslationCacheInterface:
    alias: App\Cache\RedisTranslationCache
```

---

## Type-Safe Defaults (v2.0)

### [TypeDefaultResolver](src/Translation/TypeDefaultResolver.php)

Resolves default values for non-nullable properties marked with `#[EmptyOnTranslate]`. Eliminates the v1.x requirement that EmptyOnTranslate fields must be nullable.

**Resolution rules:**
| Type | Default Value |
|------|--------------|
| `?string` (nullable) | `null` |
| `string` (non-nullable) | `""` (empty string) |
| `int` | `0` |
| `float` | `0.0` |
| `bool` | `false` |
| `array` | `[]` |
| Non-nullable object | Throws `LogicException` with guidance |
| Non-nullable enum | Throws `LogicException` with guidance |
| Non-nullable `iterable` / `object` / any other built-in without a zero-value | Throws `LogicException` with guidance |
| Intersection type (never nullable) | Throws `LogicException` with guidance |

`null` is only ever returned for types that actually accept it — a non-nullable type without a
safe default fails here with an actionable message rather than as a `TypeError` on assignment.

### Usage

```php
#[ORM\Entity]
class Product implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Column]
    #[EmptyOnTranslate]
    private string $title;     // Gets "" on translate

    #[ORM\Column]
    #[EmptyOnTranslate]
    private int $viewCount;    // Gets 0 on translate

    #[ORM\Column]
    #[EmptyOnTranslate]
    private float $rating;     // Gets 0.0 on translate

    #[ORM\Column]
    #[EmptyOnTranslate]
    private bool $published;   // Gets false on translate
}
```

### Decision Tree

```
Property has #[EmptyOnTranslate]?
├── NO → Normal translation (copy or clone)
└── YES
    ├── Has #[SharedAmongstTranslations]? → Shared wins (value copied)
    ├── Nullable type? → null
    ├── string? → ""
    ├── int? → 0
    ├── float? → 0.0
    ├── bool? → false
    ├── array? → []
    ├── enum? → LogicException
    └── object? → LogicException
```

---

## Fallback Control (copy_source)

### Global Configuration

Controls whether new translations start with cloned source content (v1.x behavior) or type-safe defaults:

```yaml
# config/packages/tmi_translation.yaml
tmi_translation:
    copy_source: false  # Default: new translations start empty with defaults
    # copy_source: true  # v1.x behavior: clone source content into new translation
```

### Per-Entity Override

Use the `#[Translatable]` attribute to override the global setting per entity:

```php
use Tmi\TranslationBundle\Doctrine\Attribute\Translatable;

#[ORM\Entity]
#[Translatable(copySource: true)]   // Always clone source (override global false)
class Article implements TranslatableInterface { ... }

#[ORM\Entity]
#[Translatable(copySource: false)]  // Always start empty (override global true)
class Product implements TranslatableInterface { ... }

#[ORM\Entity]
#[Translatable(copySource: null)]   // Use global config (default, same as omitting)
class Page implements TranslatableInterface { ... }
```

### Behavior Matrix

| Global `copy_source` | Entity `copySource` | Result |
|----------------------|---------------------|--------|
| `false` | `null` (default) | Empty with defaults |
| `false` | `true` | Clone source |
| `false` | `false` | Empty with defaults |
| `true` | `null` (default) | Clone source |
| `true` | `true` | Clone source |
| `true` | `false` | Empty with defaults |

**Note:** `#[SharedAmongstTranslations]` fields are always copied from source regardless of copy_source setting.

### Seeding hook for empty variants

With `copy_source: false` a new variant is seeded empty — including fields the application
treats as mandatory (name, slug), which collide on `(slug, locale)` unique keys and can leak
placeholders to public URLs when published untouched. The supported seam is
`TranslateEvent::POST_TRANSLATE`: it fires right after the variant is constructed and before
it is persisted, so a listener can mint locale-correct placeholder values (e.g.
`draft-<locale>-<tuuid>`) on it. Do not clone the source's slug instead. Gate publication
with `LocaleCompletenessResolver` — a variant still carrying seeded emptiness reports as
`Incomplete`. Both events also fire for translatable entities reached through associations,
so listeners must check the entity class.

---

## Compile-Time Validation (v2.0)

v2.0 validates translatable entity configuration at compile time (`cache:warmup` / `cache:clear`), catching errors before production.

### AttributeValidationPass (Compiler Pass)

Runs during container compilation. Scans all Doctrine-mapped TranslatableInterface entities via reflection.

**Validates:**
- No class-level `#[SharedAmongstTranslations]` + `#[EmptyOnTranslate]` conflict
- No property-level `#[SharedAmongstTranslations]` + `#[EmptyOnTranslate]` conflict
- No `#[EmptyOnTranslate]` on readonly properties
- Locale property exists (via TranslatableTrait or manual definition)

**Error format:** Single LogicException listing all errors found across all entities.

**`strict_discovery` (v4.0, config, default `false`):** compile-time discovery walking the
Doctrine attribute-metadata driver's mapped directories can legitimately find zero
`TranslatableInterface` classes (a project with none yet) -- by default that only logs. With
`strict_discovery: true` it becomes a hard `LogicException`
(`"...tmi_translation.strict_discovery" is enabled, which turns this into a hard failure..."`),
catching doctrine-bundle silently changing the shape of its `attribute_metadata_driver`
service definitions instead of the pass just finding nothing. Either way,
`AttributeValidationPass` also publishes the discovered classes as a container parameter,
`tmi_translation.discovered_translatable_classes` (sorted `list<class-string>`, `[]` when
Doctrine itself is not configured).

### TranslatableEntityValidationWarmer (Cache Warmer)

Runs at `cache:warmup` time (after container compilation, with EntityManager access).

**Validates:**
- No single-column `unique: true` on translatable entity fields (except id, tuuid, locale)
- Table-level unique constraints include locale column

**Correct pattern for unique fields:**

```php
// WRONG: Single-column unique (fails validation)
#[ORM\Column(length: 255, unique: true)]
private string $slug;

// CORRECT: Composite unique (field + locale)
#[ORM\Entity]
#[ORM\UniqueConstraint(
    name: 'uniq_product_slug_locale',
    fields: ['slug', 'locale']
)]
class Product implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Column(length: 255)]  // No unique: true
    private string $slug;
}
```

---

### PropertyAccessor  
- The bundle uses Symfony’s `PropertyAccess` component (or a custom `PropertyAccessorInterface`) to generically get and set object properties.  
- In `DoctrineObjectHandler::translateProperties()`, for each property:  
  - Read the current value (via accessor or reflection fallback).  
  - Build a nested `EntityTranslationContext` (when the value is `TranslatableInterface`) or `PropertyTranslationContext` (otherwise) for that property value.
  - Delegate translation of the property value to the translator.  
  - Set the translated value back on the cloned object.

### TranslationContext (v4.0; replaces TranslationArgs)
- Abstract base class `TranslationContext` holds:
  - `sourceLocale`, `targetLocale`.
  - `translatedParent` (optional) — the parent object in nested translation contexts.
  - `property` (optional) — the `ReflectionProperty` being processed (for nested translation).
  - `isShared()` / `isEmpty()` — attribute facts resolved before dispatch.
  - `getSubject()` / `setSubject()` — abstract; each subclass proxies to its own payload.
- `EntityTranslationContext` adds `getEntity(): TranslatableInterface` — the object or value being translated when it is a `TranslatableInterface` entity.
- `PropertyTranslationContext` adds `getValue(): mixed` — the object or value being translated when it is a property's value (scalar, embeddable, `Collection`).
- Provides context so handlers and translator know how to process nested values (property of object, collection element, etc).

### AttributeHelper  
- Utility service to introspect attributes (PHP 8 attributes like `#[SharedAmongstTranslations]`, `#[EmptyOnTranslate]`, etc).  
- Example usage: in `EmbeddedHandler::supports()`, check if property is embeddable:  
  ```php
  $this->attributeHelper->isEmbedded($context->getProperty())
  ```  
- Also used to detect `SharedAmongstTranslations` (and potentially other custom logic) so that translation logic can branch accordingly.

---

## Minimal Working Example

This walkthrough demonstrates transforming a standard Doctrine entity into a translatable entity. You have a Product entity with name, description, price, and a category relationship. To make it translatable, follow these steps:

### Starting Point: Standard Product Entity

```php
#[ORM\Entity]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $price;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'products')]
    private ?Category $category = null;

    // getters/setters...
}
```

### Step 1: Add the Interface and Trait

**What to do:** Add `implements TranslatableInterface` to the class declaration and `use TranslatableTrait` inside the class body.

**Why this matters:**
- **TranslatableInterface** tells the bundle this entity can be translated. The TranslatableEntityHandler (priority 20) checks for this interface using `supports()` to determine if it should process the entity.
- **TranslatableTrait** provides two essential properties automatically:
  - `$tuuid` — Groups all language variants together (same Tuuid = same product in different languages)
  - `$locale` — Identifies which language this specific entity represents

Without the interface, the entity would fall through to DoctrineObjectHandler (priority 10), which doesn't understand translation semantics. Without the trait, you'd have to manually implement these properties and their getters/setters.

```php
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
class Product implements TranslatableInterface
{
    use TranslatableTrait;

    // ... rest of entity
}
```

### Step 2: Identify Shared vs Translated Fields

Now decide which fields should be **shared across all translations** and which should be **translated per locale**.

**Shared fields (same in all languages):**
- **Price:** Typically the same regardless of language (unless you have locale-specific pricing). A laptop costs €999 whether the page is in English or French.
- **Category:** The product belongs to one category regardless of language. The category itself might be translatable, but the relationship remains the same.

**Translated fields (different per language):**
- **Name:** "Laptop" in English, "Ordinateur portable" in French
- **Description:** Product details written in each language

**Why this distinction matters:**
The handler chain processes each field during translation. By default, ScalarHandler (priority 90) copies scalar values, and relationship handlers clone relations. Using `#[SharedAmongstTranslations]` overrides this behavior, ensuring all translations reference the same instance instead of creating copies.

### Step 3: Apply SharedAmongstTranslations Attribute

Mark the fields identified as shared:

```php
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;

#[SharedAmongstTranslations]
#[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
private string $price;

#[SharedAmongstTranslations]
#[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'products')]
private ?Category $category = null;
```

**Why the attribute matters:**
When EntityTranslator processes these properties, it checks for `#[SharedAmongstTranslations]` via AttributeHelper. If present, it calls `translate($context->setShared(true))` — the handler's own `isShared()` branch returns the original value unchanged instead of resolving a new one. This ensures all language variants share the same price and category reference.

### Complete Translatable Product Entity

```php
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
class Product implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;           // Translated per locale

    #[ORM\Column(type: Types::TEXT)]
    private string $description;    // Translated per locale

    #[SharedAmongstTranslations]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $price;          // Same across all locales

    #[SharedAmongstTranslations]
    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'products')]
    private ?Category $category = null;  // Same category for all locales

    // getters/setters remain unchanged
}
```

### Using the Translatable Entity

```php
// Create English product
$product = new Product();
$product->setName('Laptop');
$product->setDescription('High-performance laptop with 16GB RAM');
$product->setPrice('999.00');
$product->setCategory($electronicsCategory);
$entityManager->persist($product);
$entityManager->flush();

// Create French translation
$frenchProduct = $entityTranslator->translate($product, 'fr');
$frenchProduct->setName('Ordinateur portable');
$frenchProduct->setDescription('Ordinateur portable haute performance avec 16 Go de RAM');
// Note: price and category are automatically shared
$entityManager->persist($frenchProduct);
$entityManager->flush();

// Both share the same Tuuid - they're the same product in different languages
$product->getTuuid() === $frenchProduct->getTuuid(); // true

// But they have different locales
$product->getLocale(); // 'en'
$frenchProduct->getLocale(); // 'fr'

// Price and category are identical references
$product->getPrice() === $frenchProduct->getPrice(); // true (same value)
$product->getCategory() === $frenchProduct->getCategory(); // true (same object)
```

### What Happens During Translation

When you call `$entityTranslator->translate($product, 'fr')`:

1. **TranslatableEntityHandler** (priority 20) recognizes the entity implements TranslatableInterface
2. It checks the database for an existing translation with the same Tuuid and locale 'fr'
3. If not found, it delegates to **DoctrineObjectHandler** to clone the entity
4. DoctrineObjectHandler iterates through each property:
   - **$id**: **PrimaryKeyHandler** (100) returns null — new entity needs new ID
   - **$name**: **ScalarHandler** (90) copies the value — you'll update this manually
   - **$description**: **ScalarHandler** (90) copies the value — you'll update this manually
   - **$price**: Marked `#[SharedAmongstTranslations]` → returns original value
   - **$category**: Marked `#[SharedAmongstTranslations]` → returns original value
5. The Tuuid is copied (same product group), locale is set to 'fr', and the new entity is returned

---

## Practical Usage Scenarios  

### A. Shared Embeddable (Address)  
Suppose you have an entity `Rental` which embeds an `Address` object, and you want the address to be identical across locale variants.

```php
#[ORM\Entity]
class Rental
{
    // ...
    #[ORM\Embedded(class: Address::class, columnPrefix: false)]
    #[SharedAmongstTranslations]
    protected Address $address;
}
```

**How it works:**  
- The `address` property is marked shared.  
- In translation of `Rental`, the handler sees the attribute and the bundled logic should reuse the same `Address` instance (or clone it but treat as shared) rather than expect locale‑specific values.  
- You don’t need to mark each field in `Address` with `#[SharedAmongstTranslations]`; the property marker is sufficient.

### B. Regular Translatable Fields  
```php
#[ORM\Column(type:"string", length:255)]
protected string $title;
```
No special attribute => treated as locale‑specific. The translator clones the value (or sets empty if defined) for each new locale version.

### C. One‑to‑Many Photos (shared vs translation‑specific)  
- If you want photos shared across all locales: mark the relation property with `#[SharedAmongstTranslations]`.  
- If you want each locale to have its own photo set: leave it unmarked and customise the handler accordingly (maybe override to clear or clone).

---

## Step‑by‑Step Integration

1. **Install bundle via Composer** and enable in `bundles.php`.
2. **Configure enabled locales** in your framework configuration:
   ```yaml
   # config/packages/framework.yaml
   framework:
       enabled_locales: [en, fr, de, es]
   ```
3. For any entity you wish to translate:
   - Add a locale field (e.g., `$locale`, or use your own strategy).
   - Add a Tuuid field (e.g., `$tuuid`) so you can link all variants.
   - Implement or tag the entity as "translatable" (depending on bundle setup).
4. On properties that should be shared across locale versions, add the `#[SharedAmongstTranslations]` attribute.
5. In your code when creating a translation:
   ```php
   $translated = $entityTranslator->translate($sourceEntity, $targetLocale);
   $entityManager->persist($translated);
   $entityManager->flush();
   ```
   This will clone and handle all fields using handlers.
6. For relations and embeddables, verify if they should be shared or translatable — use attributes accordingly.
7. If you require custom behaviour (e.g., clearing a field on translation, propagating changes across siblings when shared fields are updated), you may:
   - Configure custom handler by implementing `TranslationHandlerInterface`.
   - Write a Doctrine Event Subscriber to post‑update shared fields across sibling entities (if your bundle does *not* yet automatically propagate).
8. Make sure your repository/finder logic considers Tuuid and locale filters so you fetch the correct variant for current locale or fallback.

---

## Troubleshooting

### Locale Not Allowed

**Symptom:** `LogicException: Locale "xx" is not allowed`

**Cause:** Target locale not configured in Symfony's enabled locales (v2.0 reads from framework.enabled_locales)

**Fix:** Add the locale to `framework.enabled_locales` in your framework configuration file:

```yaml
# config/packages/framework.yaml
framework:
    enabled_locales: [en, fr, de, es]  # Add your target locale here
```

### EmptyOnTranslate on Non-Nullable Field

**Symptom:** `LogicException: Property ... is a non-nullable object and cannot have a type-safe default`

**Cause:** `#[EmptyOnTranslate]` attribute applied to a non-nullable object property. In v2.0, non-nullable scalar fields (string/int/float/bool) automatically get type-safe defaults, but non-nullable objects cannot be safely defaulted.

**Fix:** For non-nullable scalar fields, v2.0 handles them automatically with type-safe defaults (string='', int=0, etc.). For non-nullable objects, choose one of these options:

```php
// Option 1: Make nullable (allows null as empty value)
#[EmptyOnTranslate]
#[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
private ?\DateTimeImmutable $publishedAt = null;

// Option 2: Remove #[EmptyOnTranslate] (copy value from source)
#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
private \DateTimeImmutable $publishedAt;

// Option 3: Use #[SharedAmongstTranslations] (same value across locales)
#[SharedAmongstTranslations]
#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
private \DateTimeImmutable $publishedAt;

// Non-nullable scalars work automatically in v2.0:
#[EmptyOnTranslate]
#[ORM\Column]
private string $title;  // Gets "" on translate

#[EmptyOnTranslate]
#[ORM\Column]
private int $viewCount;  // Gets 0 on translate
```

### Missing TranslatableInterface

**Symptom:** Entity not recognized by TranslatableEntityHandler; translation fails silently or entity is not cloned

**Cause:** Entity class does not implement `TranslatableInterface`

**Fix:** Add `implements TranslatableInterface` and use `TranslatableTrait`:

```php
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
class Product implements TranslatableInterface
{
    use TranslatableTrait;
    // ...
}
```

### Missing Tuuid Property

**Symptom:** Translation fails with null tuuid; `InvalidArgumentException` or database constraint violation

**Cause:** TranslatableTrait expects `$tuuid` property but entity lacks proper initialization

**Fix:** Ensure `TranslatableTrait` is used. The trait provides the `$tuuid` property automatically. If you're implementing manually, initialize it:

```php
use Tmi\TranslationBundle\Doctrine\ValueObject\Tuuid;

private Tuuid $tuuid;

public function __construct()
{
    $this->tuuid = Tuuid::generate();
}
```

### Doctrine Filter Not Enabled

**Symptom:** Queries return entities from all locales instead of filtering by current locale

**Cause:** The locale filter is not enabled in Doctrine configuration

**Fix:** Enable the filter in your Doctrine configuration or manually via EntityManager:

```yaml
# config/packages/doctrine.yaml
doctrine:
    orm:
        filters:
            tmi_translation_locale_filter:
                class: Tmi\TranslationBundle\Doctrine\Filter\LocaleFilter
                enabled: true
```

Or enable at runtime:

```php
$entityManager->getFilters()->enable('tmi_translation_locale_filter');
```

The filter name is available as the `LocaleFilter::NAME` constant -- use it instead of the
literal string wherever possible.

### SharedAmongstTranslations on Bidirectional Relation

**Symptom:** `RuntimeException` when translating entity with bidirectional relation

**Cause:** Bidirectional relation handlers (ManyToOne, OneToMany, OneToOne, ManyToMany) throw when `#[SharedAmongstTranslations]` is present because sharing bidirectional relations creates circular reference issues

**Fix:** Remove `#[SharedAmongstTranslations]` from bidirectional relations. Use unidirectional relations if sharing is required, or accept that each locale will have its own copy:

```php
// DON'T: SharedAmongstTranslations on bidirectional
#[SharedAmongstTranslations]
#[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'products')]
private ?Category $category = null;

// DO: Remove attribute, each locale gets its own relation
#[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'products')]
private ?Category $category = null;

// OR: Use unidirectional relation if sharing is needed
#[SharedAmongstTranslations]
#[ORM\ManyToOne(targetEntity: Category::class)]  // No inversedBy
private ?Category $category = null;
```

> The unidirectional escape hatch only applies to **to-one** relations. A `ManyToMany` is rejected
> in either direction — `UnidirectionalManyToManyHandler` throws for the attribute as well. For
> collections, share the related entity's own columns instead of the association.

> The "DO" form above (a plain `ManyToOne` with `inversedBy`, no `SharedAmongstTranslations`) is the
> **direct form**: `Category` is not itself a field the owning side (`Product`) has a scalar
> back-reference to, so `BidirectionalManyToOneHandler` simply translates `$category` to the
> matching locale (get-or-create) and assigns the result — there is nothing to repair on `Category`
> itself. This means each translated `Product` gets its own, independently get-or-created `Category`
> variant sharing the same Tuuid, not a shared reference to one `Category` row (that requires
> `#[SharedAmongstTranslations]`, above). The target needs `cascade: ['persist']` on the mapping (or
> an explicit `$entityManager->persist($product->getCategory())`) for a newly created variant to be
> saved.

### Translations Not Persisted

**Symptom:** Translation appears to work but translated entity is not in the database

**Diagnosis:** Check if `persist()` and `flush()` were called on the translated entity. The translator creates a NEW entity, not an update to existing.

**Resolution:** Use `translateAndPersist()` or `getOrTranslate()` (v2.1) to auto-persist, or manually persist:

```php
// v2.1 recommended: auto-persist
$frenchProduct = $entityTranslator->translateAndPersist($product, 'fr');
$entityManager->flush();

// v2.1 find-or-create: returns existing or creates + persists new
$frenchProduct = $entityTranslator->getOrTranslate($product, 'fr');
$entityManager->flush();

// Manual (v2.0 pattern):
$frenchProduct = $entityTranslator->translate($product, 'fr');
$entityManager->persist($frenchProduct);  // Required!
$entityManager->flush();
```

### Wrong Handler Processes Field

**Symptom:** Field value unexpected after translation (null when should have value, or vice versa)

**Diagnosis:** Check handler priority order in the decision tree. More specific handlers must have higher priority. Examine Doctrine mapping annotations - handler selection depends on metadata.

**Resolution:** Verify your field's Doctrine annotations match the expected handler:
- `#[ORM\Id]` → PrimaryKeyHandler (always null)
- Scalar types → ScalarHandler (copies value)
- `#[ORM\Embedded]` → EmbeddedHandler (clones object)
- Relations with `inversedBy`/`mappedBy` → Bidirectional handlers

If annotations are correct but behavior is wrong, check for attribute conflicts (`#[SharedAmongstTranslations]` vs `#[EmptyOnTranslate]`).

### Embedded Object Values Diverged Across Locales

**Symptom:** An embedded value copied identically into every locale at creation time has since drifted between locales

**Cause:** `EmbeddedHandler` always clones the embeddable — even when `#[SharedAmongstTranslations]` applies — so no two locale variants ever hold the *same* in-memory instance. What `#[SharedAmongstTranslations]` guarantees is that the clone's property values match the source **at the moment a new translation is created**; it is not an enforced invariant afterwards (same rule as scalar shared fields — see "Shared Fields" above). Editing the embeddable on one locale variant post-creation does not propagate to siblings.

**Resolution:** Use `#[SharedAmongstTranslations]` (on the entity property, the embeddable class, or an inner property) so new variants start with matching values, and run `tmi:translation:sync-shared` to back-fill or reconcile values that have since diverged:

```php
// Shared: every new locale variant's Address starts with the same values
// (each locale still gets its own Address instance)
#[SharedAmongstTranslations]
#[ORM\Embedded(class: Address::class)]
private Address $address;

// Per-locale: each translation gets a cloned Address with class-default values
#[ORM\Embedded(class: Address::class)]
private Address $address;
```

### Collection Translation Creates Duplicates

**Symptom:** OneToMany or ManyToMany collection has duplicate items after translation

**Diagnosis:** Check if collection items implement `TranslatableInterface`. If they do, the handler recursively translates them. If they don't, items might be copied incorrectly.

**Resolution:** Ensure child entities in the collection are themselves translatable if they need per-locale variants:

```php
// If Photo needs translation (different caption per locale)
#[ORM\Entity]
class Photo implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Column]
    private string $caption;  // Translated

    #[SharedAmongstTranslations]
    #[ORM\Column]
    private string $url;  // Same across locales
}

```

> `#[SharedAmongstTranslations]` is **not** available for association collections. Every
> relation handler (OneToMany, ManyToOne, OneToOne, bidirectional and unidirectional
> ManyToMany) throws when it sees the attribute, because one collection shared between
> locale variants makes the owning side of the relation ambiguous. Share the individual
> scalar columns of the related entity instead, as shown above.

### Compile-Time Validation Error

**Symptom:** `LogicException: TMI Translation Bundle: Compile-time validation failed` during `cache:warmup` or `cache:clear`

**Cause:** Attribute conflicts or missing locale property detected at compile time

**Fix:** Read the error message carefully - it lists all violations. Common fixes:
- Remove conflicting `#[SharedAmongstTranslations]` + `#[EmptyOnTranslate]` on same field/class
- Remove `#[EmptyOnTranslate]` from readonly properties
- Add `use TranslatableTrait;` to provide the locale property

### Unique Constraint Validation Error

**Symptom:** `LogicException: TMI Translation Bundle: Unique constraint validation failed` during `cache:warmup`

**Cause:** Translatable entity has single-column unique constraints that would conflict across locales

**Fix:** Replace single-column `unique: true` with composite unique constraint including locale:

```php
// Replace: #[ORM\Column(length: 255, unique: true)]
// With:
#[ORM\UniqueConstraint(name: 'uniq_product_slug_locale', fields: ['slug', 'locale'])]
// And: #[ORM\Column(length: 255)]  // Remove unique: true
```

### Custom Handler Never Runs

**Symptom:** A handler tagged `tmi_translation.translation_handler` is registered but its `translate()` is never called — the field is processed by a built-in handler instead.

**Cause 1 — `supports()` never matches.** It must test the shape of `$context->getValue()` (on a `PropertyTranslationContext`), which is the *property value*, not the entity that declares it. For a `OneToMany` / `ManyToMany` property that value is the `Collection`; guarding on `instanceof EntityTranslationContext` makes `supports()` permanently false.

```php
// WRONG for a to-many property: the value is a Collection, on a PropertyTranslationContext
if (!$context instanceof EntityTranslationContext) {
    return false;
}

// RIGHT
if (!$context instanceof PropertyTranslationContext || !$context->getValue() instanceof Collection) {
    return false;
}
```

**Cause 2 — priority.** The chain is first-match-wins. Without an explicit `priority` on the tag the handler defaults to 0 and runs after every built-in, so a broad handler such as `DoctrineObjectHandler` (10) claims the value first. Give the tag a priority that places it ahead of whatever would otherwise match:

```yaml
tags:
    - { name: 'tmi_translation.translation_handler', priority: 75 }
```

Handlers sharing a priority keep their registration order.

### Translated Entity Is the Same Instance

**Symptom:** `translate()` returns the object you passed in rather than a clone.

**Cause:** The requested locale is the one the entity already carries. Translating an entity into its own locale is the identity operation — nothing is cloned and nothing is cached. This is also the call shape `TranslatableEventSubscriber`'s locale defaulting makes on every flush.

**Fix:** Nothing to fix; pass a different target locale. To read an existing sibling, use `findAllLocaleVariants()` (see Locale Variant DX).

### Deleted Entity Still Served in Another Locale

**Symptom:** After `$em->remove($product); $em->flush();`, a query in a different locale still returns a row for the same product.

**Cause:** Plain `$em->remove()` removes exactly the row you pass it. Nothing links a translatable entity's sibling locale variants for Doctrine to cascade through on its own — a naive delete only ever touches the current-locale row.

**Fix:** Inject `Tmi\TranslationBundle\Doctrine\TranslatableRemover` and call `removeAllLocaleVariants($entity)` (schedules every sibling, `$entity` included; flush once afterwards), or set `cascade_remove_locale_variants: true` to make every plain `$em->remove()` on a translatable entity cascade automatically. See [Removal Semantics (v4)](#removal-semantics-v4).

### Duplicate Variant Under the Locale Filter

**Symptom:** Calling `translate($entity, $locale)` repeatedly, or importing under an active locale filter, mints a new row every time instead of reusing the one already there.

**Cause:** Before v4.0, the existing-variant lookup (`EntityTranslator`'s internal warmup, `TranslatableEntityHandler::translate()`) queried through the entity's own repository/query builder. Under an **active** locale filter pinned to the source locale, Doctrine's `SQLFilter` combined that filter's own locale condition with the lookup's explicit target-locale condition into a contradiction that could never match — so every `translate()` call under an active filter minted a duplicate row.

**Fix:** As of v4.0 both lookups go through `LocaleVariantFinder`, which suspends the filter for the query and restores it afterwards — there is nothing to work around. On a pre-4.0 version, disable the filter yourself before calling `translate()`, or upgrade.

### Detached Entity in an Import

**Symptom:** An import loop that calls `$entityManager->clear()` between batches produces a second row with a new id for a Tuuid it already translated earlier in the same run.

**Cause:** Before v4.0, a cache hit surviving `clear()` was still handed back as reusable even though the `UnitOfWork` no longer tracked it (`STATE_DETACHED`); `getOrTranslate()`'s `persist()` call then re-inserted that detached instance as a brand-new row — Doctrine's `persist()` assumes `STATE_NEW` for anything the `UnitOfWork` does not track.

**Fix:** As of v4.0 a cache hit is checked against the entity's real `UnitOfWork` state; a detached hit is a miss and falls through to a fresh lookup instead of being re-inserted. Nothing to change in your import code — see [Performance (v4.0)](#performance-v40) for `preload()`, which is the recommended way to batch these lookups regardless.

### Rows Reported as `null-tuuid`

**Symptom:** `tmi:translation:doctor` reports one or more `null-tuuid` rows.

**Cause:** The `tuuid` column on that row is a literal database `NULL`. As of v4.0 the column is `NOT NULL`, so a normal `persist()` can no longer produce this — it only happens through a write that bypasses the entity layer entirely: a raw `INSERT`, or a row left over from before the v4 schema migration in `UPGRADING.md`.

**Fix:** There is no automatic repair — the doctor is read-only by design. Assign the row a real, correctly-linked Tuuid, or delete it, at the database level. Run the NULL-row sweep from `UPGRADING.md` §2 before migrating the columns to `NOT NULL` to avoid ever reaching this state in the first place.

---

## Tips & Best Practices  

- Always define a clear **shared vs translate** decision at entity design time. Changing this later is error‑prone.  
- Use the `AttributeHelper` to inspect attributes rather than manually checking metadata — this helps keep future changes consistent.  
- For performance: if you have many shared fields across thousands of locale variants, consider updating shared values only once (via batch update) rather than cloning each time.  
- Document inside your code which fields are shared vs per‑locale — this helps for maintenance and for AI assistants to provide accurate answers.  
- When using embeddables, marking the embedded property as `#[SharedAmongstTranslations]` is sufficient; you do *not* need to mark each column inside the embeddable.  
- If your bundle does *not yet* automatically propagate updates to shared fields across existing locale siblings, consider writing a Subscriber or service for that. (Because the handler logic supports the attribute, but may not handle cross‑entity propagation.)

---

## Locale Variant DX (v2.1)

### Convenience Methods on EntityTranslatorInterface

Two new methods reduce boilerplate for common translation workflows:

- **`translateAndPersist(entity, locale)`** — Calls `translate()` then `persist()`. Useful when you always want to save immediately.
- **`getOrTranslate(entity, locale)`** — Calls `translate()`, checks if the result is already managed by the EntityManager. If not (new translation), persists it. Avoids double-persisting existing translations found in the database.

```php
// Always creates + persists (even if DB already has it)
$translation = $entityTranslator->translateAndPersist($product, 'fr');

// Smarter: only persists if it's a new clone
$translation = $entityTranslator->getOrTranslate($product, 'fr');
```

### Auto-Reset Generated IDs

`TranslatableEntityHandler` now automatically resets properties marked with both `#[ORM\Id]` and `#[ORM\GeneratedValue]` to `null` on cloned translations. This eliminates the need for consumers to manually reset IDs via reflection after calling `translate()`.

### [TranslatableRepositoryTrait](src/Doctrine/Repository/TranslatableRepositoryTrait.php)

A trait for Doctrine entity repositories that provides batch locale variant lookups:

- **`findAllLocaleVariants(Tuuid $tuuid): array<string, TranslatableInterface>`** — Returns all locale variants for a single Tuuid, keyed by locale.
- **`findAllLocaleVariantsBatch(list<Tuuid> $tuuids): array<string, array<string, TranslatableInterface>>`** — Batch lookup for multiple Tuuids, grouped by tuuid string then locale.

Both methods temporarily disable the `tmi_translation_locale_filter` (if enabled) to query across all locales, then re-enable it in a `finally` block. As of v4.0, both delegate to `Tmi\TranslationBundle\Doctrine\LocaleVariantFinder` -- inject the finder directly wherever a repository isn't the natural fit (a service, a console command); it also offers single-locale lookups the trait does not expose, `findLocaleVariant(class, tuuid, locale)` and `findLocaleVariantsBatch(class, tuuids, locale)`.

```php
use Doctrine\ORM\EntityRepository;
use Tmi\TranslationBundle\Doctrine\Repository\TranslatableRepositoryTrait;

class ProductRepository extends EntityRepository
{
    use TranslatableRepositoryTrait;
}
```

Usage:
```php
$variants = $productRepository->findAllLocaleVariants($product->getTuuid());
// ['en_US' => Product, 'de_DE' => Product, ...]

$batch = $productRepository->findAllLocaleVariantsBatch([$tuuid1, $tuuid2]);
// ['<tuuid1>' => ['en_US' => Product, ...], '<tuuid2>' => ['de_DE' => Product, ...]]
```

Use `@phpstan-require-extends \Doctrine\ORM\EntityRepository` in the trait for PHPStan level max compatibility.

### [LocaleCompletenessResolver](src/Translation/LocaleCompletenessResolver.php) (v3.1)

Answers, per enabled locale, whether a Tuuid has a variant and whether that variant's
translatable content is complete. Returns a [LocaleCompleteness](src/ValueObject/LocaleCompleteness.php)
value object with a [TranslationStatus](src/ValueObject/TranslationStatus.php) per locale
(`Missing` | `Incomplete` | `Complete`).

- **`resolve(class-string $class, Tuuid $tuuid): LocaleCompleteness`**
- **`resolveForEntity(TranslatableInterface $entity): LocaleCompleteness`**
- **`resolveBatch(class-string $class, list<Tuuid> $tuuids): array<string, LocaleCompleteness>`** — one query for many Tuuids; admin lists must not issue N queries. One entry per requested Tuuid, even when no rows exist.

Semantics: completeness is relative to a **baseline variant** (default-locale row, else the
first variant found; `baselineLocale()` names it). A variant is `Complete` when every
translatable property filled on the baseline is filled on it too — optional properties left
empty on the baseline never count against translations. Translatable property = mapped
column minus identifier, system columns (tuuid/locale/translations) and
`#[SharedAmongstTranslations]`; embedded fields contribute their non-shared inner
properties; associations are not inspected. "Filled" = not null, and for strings not blank.
The locale filter is suspended for the lookup and restored afterwards.

`LocaleCompleteness` API: `statusOf($locale)`, `hasVariant($locale)`, `statuses()`,
`missingLocales()`, `incompleteLocales()`, `completeLocales()`, `isFullyTranslated()`,
`baselineLocale()`, `tuuid()`.

---

## Tuuid Linkage Integrity (v2.2)

Every locale variant of an entity must share one `Tuuid`. Translations produced by
`EntityTranslator::translate()` inherit it automatically. The danger is application code that
bypasses the translator — `new Entity()` + `setLocale('de_DE')` without `setTuuid(...)` — which
mints a fresh, *standalone* Tuuid. The "translation" is then linked to nothing, and Tuuid-keyed
features (locale variants, `hreflang`, shared media) resolve only on the canonical locale.

v2.2 makes this whole failure class visible:

### `TranslatableEntityRepository`

A ready-made repository base class (`extends EntityRepository`, `use TranslatableRepositoryTrait`).
Extend it instead of hand-rolling locale-variant queries — those routinely mishandle the
`tmi_translation_locale_filter` and return only the current-locale row.

```php
/** @extends TranslatableEntityRepository<Product> */
class ProductRepository extends TranslatableEntityRepository {}
```

### Composite `(tuuid, locale)` index

`TranslatableIndexListener` injects a composite index into every translatable entity at
`loadClassMetadata` time (a trait cannot declare a class-level `#[ORM\Index]`). Without it,
`WHERE tuuid IN (...)` lookups run unindexed scans. Set `unique_locale_variants: true` to
promote it to a `UNIQUE` constraint — a DB-level guard against duplicate locale rows. Enable
it only after `tmi:translation:doctor` reports the data is clean.

### Orphan detection at flush time

`TranslatableEventSubscriber` flags an entity persisted in a **non-default locale** with
**no shared Tuuid**, but the verdict is settled at **flush time**: if a translation created
in the same flush adopted the entity's Tuuid (the `translate()` + `persist()` + `flush()`
shape), the entity is linked and nothing is reported. The `strict_orphan_check` option
controls the reaction to an entity still orphaned at flush:

| Value          | Behavior                                                      |
|----------------|---------------------------------------------------------------|
| `true`         | Throws `OrphanTranslationException`                           |
| `false`        | Logs a PSR-3 `warning` (requires `enable_logging: true`)      |
| `null` (default) | *Auto* — throws when `kernel.debug` is on, warns otherwise   |

The warning respects the bundle's opt-in logging: with `enable_logging: false` (the
default) no logger is injected and nothing is logged. An entity flushed alone and only
linked in a *later* flush is still reported — `tmi:translation:doctor` is the authoritative
audit for data at rest.

This per-flush scoping is a documented limitation, not a bug. The verdict is settled with a
`\WeakMap` inside `onFlush`, checked only against the entities scheduled for insertion in
that same flush — the subscriber has no visibility into a translate() + persist() that
happens in a later, separate flush. Carrying the pending-orphan state across flush
boundaries to close this gap would mean an ever-growing map with no clear point to forget
entries, and would defeat the point of strict mode: the exception has to surface in the
flush() call that actually created the inconsistency, not in some unrelated later one.

### `tmi:translation:doctor`

Scans every translatable table (locale filter disabled) and reports four anomaly classes:

1. **standalone** — a Tuuid carried by a single locale row;
2. **incomplete** — a Tuuid with fewer locale rows than configured locales;
3. **duplicate** — more than one row sharing a `(tuuid, locale)` pair;
4. **null-tuuid** (v4.0) — a row whose `tuuid` column is a literal database `NULL`. Only
   reachable through a write that bypasses the entity layer (a raw insert, a pre-v4 legacy
   row), since the column is `NOT NULL` as of v4.0 -- a normal `persist()` cannot produce one.

`--entity=<FQCN>` (v4.0) restricts the scan to a single entity class -- validated against
Doctrine's metadata directly, so a concrete subclass of an inheritance hierarchy is accepted
even though the scan itself only ever enumerates each hierarchy's root (SINGLE_TABLE/JOINED
hierarchies are counted once, from the root, as of v4.0 -- see Revision History).

Exits non-zero when anomalies are found — run it as a post-migration / CI integrity gate:

```
php bin/console tmi:translation:doctor
php bin/console tmi:translation:doctor --entity="App\Entity\Product"
```

### `tmi:translation:sync-shared`

Back-fills `#[SharedAmongstTranslations]` **column** values: copies them from the
default-locale row to every sibling. This fixes the ordering caveat — shared values only
propagate to translations created *after* the value was set, so data translated later keeps
stale siblings. Options: `--dry-run` (preview), `--check` (write nothing, exit non-zero when
any shared value has drifted — a CI gate for "no shared property has diverged"),
`--entity=<FQCN>` (restrict to one class).
Shared *associations* are out of scope (and unsupported by the bundle).

Embedded fields are detected in all three places sharing can be declared — on the entity
property (whole embeddable), on the embeddable class (every inner property that does not
override it with `#[EmptyOnTranslate]`), or on a single inner property — mirroring
`EmbeddedHandler`'s translate-time resolution.

`readonly` shared properties cannot be written after hydration. They are reported (in
`--dry-run` too), skipped rather than crashing the run, and make the command exit non-zero;
the remaining shared values still sync.

**Every run prints a table (v4.0)** — `Property | Tuuids | Rows | Writable` — naming each
drifted property, how many distinct Tuuid groups and sibling rows it touched, and whether it
was writable, right after the existing count line. Sorted descending by row count; omitted
entirely when nothing drifted.

---

## Removal Semantics (v4)

Plain `$em->remove($entity)` removes exactly the row you pass it — nothing links a
translatable entity's sibling locale variants for Doctrine to cascade through on its own, so a
naive delete leaves every other locale's copy of the same content online. This is the bug
class `Tmi\TranslationBundle\Doctrine\TranslatableRemover` exists to close (see also
"Deleted Entity Still Served in Another Locale" above).

```php
use Tmi\TranslationBundle\Doctrine\TranslatableRemover;

public function __construct(private TranslatableRemover $remover, private EntityManagerInterface $entityManager) {}

// Schedules every locale variant sharing $product's Tuuid for removal, $product included.
// Does not flush -- call flush() yourself, once, after.
$removed = $this->remover->removeAllLocaleVariants($product);
$this->entityManager->flush();

// Removes only this one variant. Its siblings are left untouched.
$this->remover->removeSingleLocaleVariant($productDe);
$this->entityManager->flush();
```

Every variant goes through `EntityManager::remove()` individually — **never a bulk DQL
DELETE** — so ORM cascades, `orphanRemoval` and lifecycle callbacks fire exactly as they
would removing one entity at a time.

### Opt-in cascade: `cascade_remove_locale_variants`

```yaml
# config/packages/tmi_translation.yaml
tmi_translation:
    cascade_remove_locale_variants: true
```

With this on, a plain `$em->remove($entity)` on *any* translatable entity cascades to its
sibling locale variants automatically, via `LocaleVariantRemovalListener` (a `preRemove`
Doctrine listener, always registered -- the config flag decides at runtime whether it does
anything) calling `TranslatableRemover::cascadeFromPreRemove()`. With the flag on,
`removeSingleLocaleVariant()` becomes the escape hatch for the rarer case — deleting one
variant while its siblings stay online.

### Re-entrancy: why this doesn't recurse or double-fire

`TranslatableRemover` holds two pieces of process-local state, like `EntityTranslator`'s own
in-progress cycle guard — both are long-lived services holding state across calls, not
stateless value objects:

- an **in-progress guard** (`array<string, true>`, keyed by the Tuuid's string value —
  `Tuuid` is a value object, so identity comparison would never match here) that stops
  `cascadeFromPreRemove()` from re-discovering siblings it is already scheduling.
  `EntityManager::remove()` fires `preRemove` synchronously, so removing sibling B from
  inside sibling A's `preRemove` would otherwise trigger B's own `cascadeFromPreRemove()`,
  which would try to schedule A again — and A is not yet `STATE_REMOVED` at that point
  (`preRemove` listeners run *before* `scheduleForDelete()`), so `remove($a)` would fire A's
  `preRemove` a second time instead of being the no-op a repeat `remove()` on an
  already-scheduled entity would be.
- an **exemption map** (`\WeakMap<TranslatableInterface, true>`, keyed by object identity, not
  Tuuid — a Tuuid-keyed guard would exempt every sibling, not just the one variant being
  removed) that `removeSingleLocaleVariant()` sets for the duration of its one `remove()` call,
  so the cascade listener — if enabled — leaves that variant's siblings alone.

Both are set before the removal work and cleared in a `finally`.

---

## Performance (v4.0)

Every number below is enforced by an exact assertion (`assertSame`, not a ceiling) in
[`tests/Performance/QueryBudgetTest.php`](tests/Performance/QueryBudgetTest.php) — a
`QueryCounter` ([`tests/Support/QueryCounter.php`](tests/Support/QueryCounter.php)) wired into
the test kernel behind DBAL's own logging middleware, counting one message per executed
statement (transaction control -- `beginTransaction()`/`commit()`/`rollBack()` -- logs under a
different message and is deliberately not counted, so `flush()`'s implicit transaction never
inflates a budget).

| Operation                                                                   | Queries       |
|------------------------------------------------------------------------------|---------------|
| `find()` a translatable entity under the active locale filter                | 1             |
| `translate()` into an already-existing variant                               | 1 (0 inserts) |
| `translate()` a parent with *K* already-translated `OneToMany` children       | 1 + *K*       |
| `LocaleCompletenessResolver::resolveBatch()` for 100 Tuuids                   | 1             |
| `LocaleVariantFinder::findAllLocaleVariantsBatch()`                          | 1             |
| `tmi:translation:doctor` (per root class scanned, or with `--entity`)         | 2             |
| Import of *N* new entities via `preload()` + `getOrTranslate()` + `flush()`   | 1 + *N*       |

### `preload()`: batch import lookups per class, not per entity, and remembered misses

```php
$entityTranslator->preload($batch, 'de_DE');   // one query per class, not per entity

foreach ($batch as $entity) {
    $entityTranslator->getOrTranslate($entity, 'de_DE');
}
$entityManager->flush();
```

`preload()` groups the given entities by class and issues one
`LocaleVariantFinder::findLocaleVariantsBatch()` query per class, filling the translation
cache ahead of time. `translate()` (called internally by `getOrTranslate()`) already warms
its own cache entry before running the handler chain, so a bare loop calling `translate()` one
entity at a time still costs one lookup query per entity regardless — calling `preload()`
with the whole batch first is what turns that into one query per class.

`EntityTranslator` also remembers, per (tuuid, locale) pair, every Tuuid a batched `preload()`
query looked up and found nothing for. `translate()`'s own internal single-entity `preload()`
call checks that memory first and skips its query for a remembered miss instead of asking the
database the same question again — the mechanism that keeps the import row above at `1 + N`
(the upfront batch query plus *N* `INSERT`s) rather than `1 + 3N` (upfront query, each entity's
own redundant preload query, and `TranslatableEntityHandler`'s former redundant existence check,
removed in v4.0 — see [§ 7 of UPGRADING.md](UPGRADING.md#7-translatableentityhandler-no-longer-checks-for-an-existing-variant-itself)).
The memory entry for a pair is dropped the instant `EntityTranslator` itself caches a
translation for it, so a variant this translator creates is always found again, even across an
`EntityManager::clear()`. **The one caveat:** a variant for a remembered pair created by
anything other than this translator (a manual `persist()`, another process) stays invisible to
`preload()` until this translator creates one for that pair or the service is reset —
`EntityTranslator` is tagged `kernel.reset` for exactly this (see Long-running workers below).
Re-running the same import afterwards costs one query total: every Tuuid is now an actual
cache hit, not just a remembered miss.

### Reflection is cached, not repeated

`AttributeHelper` (per `declaringClass::property::attribute`) and
`ReflectionHelper::getHierarchyProperties()` (per proxy-unwrapped class) memoize for the life
of the process — both are class-level facts, immutable once the class is loaded. Before
v4.0, the hot path inside `translate()` re-walked a class's attributes and property hierarchy
on every property, on every call.

### The `(tuuid, locale)` index and a bare `locale = ?` predicate

The composite index (`TranslatableIndexListener`, see Tuuid Linkage Integrity above) does not
cover a bare `locale = ?` predicate on its own — `locale` is not its leftmost column. A
dedicated locale-only index is not worth adding regardless: with 2-10 distinct locale values,
a query planner would usually ignore it anyway.

### Long-running workers

Both `InMemoryTranslationCache` and `EntityTranslator` are tagged `kernel.reset`
(`ResetInterface`) — Symfony does not autoconfigure that tag, so the bundle wires each
explicitly. In a process that outlives one request or job (a queue consumer, a long-running
import), the cache clears itself between units of work instead of handing the next one an
entity the previous one cached — and, since v4.0's identity fix, an entity possibly since
detached by an `EntityManager::clear()` call in between — and `EntityTranslator` forgets its
own `preload()` miss memory (see above), so a variant created by another unit of work becomes
visible again immediately instead of staying a remembered miss.

---

## “How can I achieve X?” Quick Answers

- **"How do I share the address across locales?"**
  Mark the embedded property with `#[SharedAmongstTranslations]`, ensure all locale entities share the same Tuuid, and use the translator to clone/translate the rest.

- **“How do I translate only title and description but keep category and tags shared?”**  
  On the entity: mark category and tags with `#[SharedAmongstTranslations]`, leave title & description un‑marked. On translation, only title/description will be locale‑specific.

- **"How do I propagate a change in a shared field (e.g., latitude) to all language variants after creation?"**
  Run `php bin/console tmi:translation:sync-shared` — it copies every `#[SharedAmongstTranslations]` column value from the default-locale row to its siblings. Use `--dry-run` to preview, `--check` to gate CI on drift, and `--entity=<FQCN>` to limit the scope.

- **"How do I find translations that were accidentally unlinked?"**
  Run `php bin/console tmi:translation:doctor`. It reports standalone/incomplete translations and duplicate `(tuuid, locale)` pairs, and exits non-zero so it can gate CI or post-migration checks.

- **“How can I handle OneToMany relations differently for shared vs per‑locale?”**  
  If the relation should be shared: mark property `#[SharedAmongstTranslations]`. If per‑locale: leave un‑marked. Use or extend handler logic if custom merging is needed.

- **"How do I delete a translatable entity along with every other locale's copy?"** (v4.0)
  Inject `TranslatableRemover` and call `removeAllLocaleVariants($entity)`, then `flush()` once. A plain `$em->remove()` only ever touches the one row you pass it. See [Removal Semantics (v4)](#removal-semantics-v4). To make every plain `$em->remove()` do this automatically, set `cascade_remove_locale_variants: true`.

- **"How do I speed up a bulk import?"** (v4.0)
  Call `$entityTranslator->preload($batch, $locale)` once before looping `getOrTranslate()` over the batch — it turns *N* per-entity lookup queries into one query per class. See [Performance (v4.0)](#performance-v40).

---

## Summary
This bundle gives you a robust way to manage multilingual domain models in Symfony/Doctrine with precise control over shared vs locale‑specific fields. By leveraging the EntityTranslator, the set of handlers, the PropertyAccessor, TranslationContext, and AttributeHelper, you create a consistent and maintainable translation architecture.

Proper annotation (`#[SharedAmongstTranslations]`), common Tuuids, and correct use of the translator service are the keys to making this work smoothly.

---

## AI Skills

The bundle ships with three AI skills (in `.agents/skills/`) that provide guided workflows for common tasks. These work with any AI coding assistant that supports skill files.

### [Entity Translation Setup](.agents/skills/entity-translation-setup/SKILL.md)
Guided workflow for making any Doctrine entity translatable. Analyzes entity fields, walks through shared vs. translated decisions, applies `TranslatableInterface`, `TranslatableTrait`, and attribute configuration. Supports quick mode (defaults) and guided mode (step-by-step decisions).

**Trigger phrases:** "make this entity translatable", "add translations to [Entity]", "translate [Entity] fields"

### [Translation Debugger](.agents/skills/translation-debugger/SKILL.md)
Systematic diagnostic tool for translation configuration issues. Runs a multi-layer check sequence: entity configuration, attribute conflicts, handler chain mapping, runtime configuration, and compile-time validation. See [diagnostics reference](.agents/skills/translation-debugger/references/diagnostics.md) for the full check list.

**Trigger phrases:** "translation not working", "translation error", "why isn't translation working?"

### [Custom Handler Creator](.agents/skills/custom-handler-creator/SKILL.md)
Step-by-step guide for building custom translation handlers for field types not covered by the built-in handler chain (encrypted fields, computed properties, value objects, file paths, third-party objects). Includes priority selection via the [handler priority guide](.agents/skills/custom-handler-creator/references/handler-priority.md) and [real-world examples](.agents/skills/custom-handler-creator/references/examples.md).

**Trigger phrases:** "create custom handler", "handle encrypted fields", "extend handler chain"

---

## Revision History
- v1.0: Initial methodology documented.
- v2.0: Added cache service, type-safe defaults, fallback control, compile-time validation documentation.
- v2.0.1: Added AI Skills section (entity-translation-setup, translation-debugger, custom-handler-creator).
- v2.1.0: Added locale variant DX improvements: `translateAndPersist()`, `getOrTranslate()`, `TranslatableRepositoryTrait`, auto-reset generated IDs.
- v2.2.0: Added Tuuid linkage integrity: `TranslatableEntityRepository`, composite `(tuuid, locale)` index, orphan detection (`strict_orphan_check`), and the `tmi:translation:doctor` / `tmi:translation:sync-shared` commands.
- v3.0.0: Requires Symfony `^8.0` (PHP `>=8.4`). Correctness release from an adversarial bug hunt — collection properties are now actually translated (all three to-many handlers were unreachable), translating into an entity's own locale is the identity operation, handler tag priority is honoured, the locale filter is restored after sub-requests, and the in-progress flag is always cleared. `#[SharedAmongstTranslations]` on a bidirectional ManyToMany now throws as documented instead of being silently ignored.
- v3.0.1: Documentation only, no code change. Corrected claims that no longer matched the code (or never did): the entity translator service id, the `Doctrine\Model\` namespaces, the `AttributeHelper` service reference in the custom-handler template, the `TranslationCacheInterface` example signatures, and the description of `#[SharedAmongstTranslations]` + `#[EmptyOnTranslate]` as a precedence rule rather than a compile-time conflict. `UPGRADING.md` gained the v3.0 behavioural changes it was missing.
- v3.1.0: Consumer-findings release from the first Terra Mia production audit. Corrected the `#[SharedAmongstTranslations]` contract everywhere (copy-on-translate, not an enforced invariant), added `tmi:translation:sync-shared --check` (writes nothing, exits non-zero on drift — a CI gate), added the per-locale completeness API (`LocaleCompletenessResolver` + `LocaleCompleteness` / `TranslationStatus`), documented `TranslateEvent::POST_TRANSLATE` as the seeding hook for `copy_source: false` variants, and made the orphan check accurate: verdict at flush time (same-flush translations count as linked) with the warning gated behind `enable_logging`.
- v3.1.1: Dependency guard, no behavioural change. Added a composer `conflict` with `doctrine/orm` 3.6.8 — its `GenerateSchemaEventArgs::setSchema()` throws `BadMethodCallException` unless the unreleased `doctrine/dbal` 4.5 provides `Schema::edit()`, so on Symfony 8.0.x any `SchemaTool` run explodes; excluding exactly 3.6.8 resolves 3.6.7 and self-heals once 3.6.9 ships. The test kernel now wires a `NullLogger` so test output stays deterministic without monolog.
- v3.2.0: Fix release from the second adversarial bug hunt. Unidirectional ManyToMany translation now preserves non-translatable collection items instead of silently dropping them (consistent with the bidirectional handler). `tmi:translation:sync-shared` streams entities grouped by Tuuid via `toIterable()` with batched flushes instead of `findAll()`, so memory stays bounded on large tables, and `DateTimeInterface` shared values compare by instant without `serialize()`. `Psr6TranslationCache` now stores `[class, id]` references and reloads entities through the `EntityManager` — persistent backends (Redis, filesystem) are safe; its constructor gained a required `EntityManagerInterface` (see `UPGRADING.md`). Embeddables marked `#[SharedAmongstTranslations]` are always cloned in both translate paths (values synced, instances never shared), and `translate()`'s idempotent get-or-create contract is documented explicitly.
- v3.2.1: Consumer-reported fix release, no API or config change. Every property walk now sees private parent-class properties (`ReflectionClass::getProperties()` never lists them): a generated id declared private on a mapped superclass is reset on fresh variants — `getId()` no longer reports the source's id before flush — and such columns now run through the whole pipeline (`#[SharedAmongstTranslations]` / `#[EmptyOnTranslate]` honoured, completeness counted, `sync-shared` back-fills them, compile-time validation covers them), centralised in `ReflectionHelper::getHierarchyProperties()`. `EntityTranslator` treats a cache hit that cannot be loaded as a miss: on a PSR-6 pool `has()` can report a key whose entry no longer reloads (row deleted since caching, pre-3.2 entry format), and the old check-then-get let that `null` escape as a `TypeError` from `translate()` under `zend.assertions=-1`; both read sites collapse into a single `get()` (halves pool round-trips) and warmup no longer skips tuuids behind stale keys.
- v3.3.0: Backlog maintenance release, no API or config change — but validation may newly flag entities it previously skipped. Compile-time class discovery now tokenizes files (`PhpToken::tokenize()`) instead of regex-matching the raw text: a "class Foo" mention in a docblock or string before the real declaration no longer derails extraction, `::class` fetches and anonymous classes are ignored, and every class a file declares is validated — entities the old first-match regex silently skipped now run through validation and may surface new compile-time errors (that is the point). A new integration test loads the real doctrine-bundle extension as an early-warning tripwire for the `attribute_metadata_driver` service shape the discovery depends on, and the compiler pass logs (never throws) when Doctrine is configured but zero translatable classes are found. Documented: `has()` staleness on PSR-6 pools (prefer `get() !== null`; `has()` is a removal candidate for v4), attribute inertness on non-translatable classes as the feature enabling shared traits, and the per-flush orphan verdict as a strict-mode-preserving limitation.
- v3.4.0: `TranslationCacheInterface::has()` is removed — interface and both implementations. Technically an API removal in a minor, decided deliberately while the bundle has no external consumers (zero Packagist dependents; TMI and NRP verified free of callers): the method's answer is inherently unreliable on persistent backends — a key can exist while the entry no longer loads — so the unsafe check-then-get pattern is taken off the table before anyone adopts it, instead of being deprecated across a major cycle. Removing an interface method breaks only callers, never implementors: a custom cache still declaring `has()` keeps working and can simply delete it. `get() !== null` is the one canonical existence check (see `UPGRADING.md`).
- v4.0.0: Limited, deliberate breaks on the existing storage model and handler-chain architecture — not a rewrite (full detail in `UPGRADING.md`). One line per work package:
  - `Psr6TranslationCache` removed; `TranslationCacheInterface` aliases only to `InMemoryTranslationCache` now.
  - `LocaleVariantFinder` (all cross-locale lookups, filter-suspended) and `TranslatableRemover` (`removeAllLocaleVariants()` / `removeSingleLocaleVariant()` / `cascadeFromPreRemove()`) — see Removal Semantics (v4) above.
  - Opt-in `cascade_remove_locale_variants` + `LocaleVariantRemovalListener` cascade a plain `$em->remove()` to sibling locale variants automatically.
  - `preload()`'s internal warmup now goes through `LocaleVariantFinder` — an active locale filter no longer mints a duplicate row on `translate()`.
  - `TranslatableEntityHandler` no longer checks for an existing target-locale variant itself (its own such check, redundant with `preload()`'s, is removed along with its `LocaleVariantFinder` dependency); `EntityTranslator` remembers a `preload()` batch's misses so a per-entity `getOrTranslate()` import loop after it costs no further lookup queries, and is now `kernel.reset`-tagged to forget that memory between units of work.
  - The translation cache is identity-safe across `EntityManager::clear()` (a detached hit is a miss, not a re-inserted duplicate); `InMemoryTranslationCache` implements `ResetInterface` (`kernel.reset`).
  - `tmi:translation:doctor` / `tmi:translation:sync-shared` / `TranslatableEntityValidationWarmer` walk each inheritance hierarchy's root once, resolving each hydrated row's own concrete class for its property list — no more double-counted SINGLE_TABLE/JOINED rows.
  - The direct `ManyToOne`/`OneToOne` form (a field on the *owning* class, not a back-reference) now translates its target through the full entity pipeline (get-or-create) instead of returning the untranslated source.
  - Proxy-safe `#[Translatable(copySource: ...)]` resolution; a fresh `ArrayCollection` for every `#[EmptyOnTranslate]` collection (no longer shared with the source); `ReflectionHelper::getProperty()` walks the hierarchy for a `mappedBy` field declared on a mapped superclass.
  - The four no-op `EntityTranslator` lifecycle hooks (`afterLoad`/`beforePersist`/`beforeUpdate`/`beforeRemove`) are removed — every call was the identity operation, always; the info log now sits after the identity check.
  - `tmi:translation:sync-shared` prints a `Property | Tuuids | Rows | Writable` table naming every drifted shared property, not just an aggregate count.
  - `strict_discovery` (config) turns a `0 translatable entities discovered` compile-time result into a `LogicException`; `tmi_translation.discovered_translatable_classes` container parameter.
  - The `tuuid` DBAL type self-registers via `prepend()` (zero-config); `tmi:translation:doctor --entity=<FQCN>` and the `null-tuuid` anomaly class.
  - The dead `translations` JSON column and its four trait accessors (`getTranslations()` et al.) are gone.
  - `tuuid`/`locale` columns are `NOT NULL`, `locale` grows to length 16; `TuuidType` converts a database `NULL` to PHP `null` instead of inventing a fresh Tuuid.
  - Every bundle service is private (autowire by interface/class, not `container->get('tmi_translation....')`); the Twig global `locales` is renamed `tmi_locales`; `PreTranslateEvent`/`PostTranslateEvent` (dispatched by class) replace the `TranslateEvent::PRE_TRANSLATE`/`POST_TRANSLATE` string constants.
  - `TranslationHandlerInterface` narrows from four methods to two: `supports(TranslationContext)`/`translate(TranslationContext)`. The single, mutable `TranslationArgs` DTO is replaced by two typed contexts sharing an abstract `TranslationContext` base — `EntityTranslationContext` (`getEntity(): TranslatableInterface`) and `PropertyTranslationContext` (`getValue(): mixed`) — plus `getSubject()`/`setSubject()` on the base for a handler reachable either way. `isShared()`/`isEmpty()` on the context (set by `EntityTranslator` from the property's attributes before dispatch) replace the two removed interface methods; a handler merges its old `handleSharedAmongstTranslations()`/`handleEmptyOnTranslate()` bodies into `translate()`, gated on those two booleans. See `UPGRADING.md` § 6 for the full migration guide and a before/after custom handler.
  - `AttributeHelper` and `ReflectionHelper::getHierarchyProperties()` cache per class; `EntityTranslator::preload()` batches import lookups per class instead of per entity; `tests/Performance/QueryBudgetTest.php` asserts an exact query count for every operation in the Performance table above.
  - README, llms.txt, this file and the three `.agents/skills/` guides rewritten around the two arguments this section opens with — every performance and quality claim in them is backed by a named test or CI gate, not an estimate.
- Next: Add examples for custom handler registration, event subscriber propagation, batch aside.

