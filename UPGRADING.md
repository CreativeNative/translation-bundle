# UPGRADE FROM 3.4 to 4.0

Version 4.0 is a set of deliberate, targeted breaks on top of the existing storage model and
handler-chain architecture — not a rewrite. **Unchanged:** the same-table-per-locale storage
model, the handler chain and its priority-ordered dispatch, `#[SharedAmongstTranslations]` /
`#[EmptyOnTranslate]` semantics, every console command's name and most of its options, and
`TranslatableTrait`'s public API except the one dead column removed in § 1. What changed is a
handful of things that were either dead weight (`translations`, the four lifecycle hooks), a
correctness gap that could not be fixed without a signature change (nullable Tuuid/locale
columns, the locale filter silently ignored by cross-locale lookups), or a deliberate
narrowing of the public surface (private services). Each is its own numbered section below,
with a migration path.

## Table of Contents

> Heading names below are suffixed "(4.0)" only to keep them from colliding with the
> identically-titled sections further down this same file (2.x→3.0 and 1.x→2.0 each have their
> own "Breaking Changes" / "Behavioural Changes" / "Upgrade Checklist") — GitHub's anchor
> generator would otherwise silently renumber those older sections' own anchors out from under
> their own tables of contents.

- [Breaking Changes (4.0)](#breaking-changes-40)
  - [1. The `translations` JSON column is gone](#1-the-translations-json-column-is-gone)
  - [2. `tuuid` and `locale` columns are now `NOT NULL`; `locale` grows to 16 characters](#2-tuuid-and-locale-columns-are-now-not-null-locale-grows-to-16-characters)
  - [3. The four `EntityTranslator` lifecycle hooks were removed](#3-the-four-entitytranslator-lifecycle-hooks-were-removed)
  - [4. Every bundle service is private; the Twig global is renamed; events are typed classes](#4-every-bundle-service-is-private-the-twig-global-is-renamed-events-are-typed-classes)
  - [5. `Psr6TranslationCache` is gone](#5-psr6translationcache-is-gone)
  - [6. `TranslationHandlerInterface` is two methods on typed contexts](#6-translationhandlerinterface-is-two-methods-on-typed-contexts)
- [Behavioural Changes (4.0)](#behavioural-changes-40)
  - [1. The direct ManyToOne/OneToOne form now translates the target entity](#1-the-direct-manytoonoonetoone-form-now-translates-the-target-entity)
  - [2. Cross-locale lookups no longer bypass the locale filter](#2-cross-locale-lookups-no-longer-bypass-the-locale-filter)
  - [3. The translation cache is identity-safe across `EntityManager::clear()`](#3-the-translation-cache-is-identity-safe-across-entitymanagerclear)
  - [4. Inheritance hierarchies are counted once per concrete class](#4-inheritance-hierarchies-are-counted-once-per-concrete-class)
  - [5. `copy_source` resolution is proxy-safe; `EmptyOnTranslate` collections are never shared](#5-copy_source-resolution-is-proxy-safe-emptyontranslate-collections-are-never-shared)
  - [6. `tmi:translation:sync-shared` names the properties that drifted](#6-tmitranslationsync-shared-names-the-properties-that-drifted)
  - [7. TranslatableEntityHandler no longer checks for an existing variant itself](#7-translatableentityhandler-no-longer-checks-for-an-existing-variant-itself)
- [New in 4.0](#new-in-40)
- [Upgrade Checklist (4.0)](#upgrade-checklist-40)

---

## Breaking Changes (4.0)

### 1. The `translations` JSON column is gone

**BREAKING (schema + trait):** `TranslatableTrait`'s `$translations` column and its four
accessors — `setTranslations()`, `getTranslations()`, `setTranslation()`, `getTranslation()`
— are gone, along with `TranslatableInterface::getTranslations()`. `'translations'` is also
dropped from the two `SYSTEM_PROPERTIES` allowlists (`tmi:translation:sync-shared`,
`LocaleCompletenessResolver`) that used to exclude it from shared-value sync and completeness
checks.

**Why:** the column was write-only dead weight — nothing in the bundle ever read it. It
predates the Tuuid-based translation model and had no reader or writer left anywhere in
`src/`.

**Migration Steps:**
1. `DROP COLUMN translations` on every translatable table (`doctrine:migrations:diff`
   generates this once the trait no longer maps the column — combine it with § 2's migration
   below if convenient).
2. A custom `TranslatableInterface` implementation that declared `getTranslations()` directly
   (not via the trait) keeps working — removing a method from an interface does not break
   implementors. Delete it at your convenience.

---

### 2. `tuuid` and `locale` columns are now `NOT NULL`; `locale` grows to 16 characters

**BREAKING (schema):** `TranslatableTrait`'s `tuuid` column (`length: 36`) and `locale`
column go from nullable to `NOT NULL`; `locale` also grows from `length: 5` to `length: 16`,
to fit longer BCP-47 tags (`sr_Latn_RS`). The PHP property types stay `Tuuid|null` /
`string|null` — a freshly `new`-ed, not-yet-persisted entity legitimately has neither yet, and
`TranslatableEventSubscriber::prePersist()` still assigns both before insert, unchanged.
`getTuuid(): Tuuid` and `getLocale(): ?string` keep their existing signatures.

`TuuidType::convertToPHPValue()` / `convertToDatabaseValue()` also change: a database `NULL`
now converts to PHP `null` — DBAL's own null-in/null-out convention — instead of **inventing a
fresh, unrelated Tuuid**, which is what it did through v3.4. This is not a relaxation:
Doctrine's `AbstractHydrator::gatherRowData()` calls `convertToPHPValue()` for every selected
column of every hydrated row, including a translatable relation reached through a `LEFT JOIN`
with no match, where the joined columns are all `NULL` by construction — throwing there would
crash every such fetch-join query outright.

**Why:** a NULL `tuuid` or `locale` is always a data bug — a row written outside the entity
layer — and the old `TuuidType` behavior actively hid that bug behind an invented, unrelated
identity instead of surfacing it. `NOT NULL` plus the new [`tmi:translation:doctor` `null-tuuid`
check](README.md#diagnostics) close the gap.

**Migration Steps:**
1. Before upgrading, sweep every translatable table for existing NULL rows:
   ```sql
   SELECT COUNT(*) FROM product WHERE tuuid IS NULL OR locale IS NULL;
   ```
   Resolve or delete any rows you find. A NULL row left in place still round-trips under v4's
   application code (`getTuuid()` mints an in-memory Tuuid on a `null` property, unchanged
   trait behaviour) — but it will violate the `NOT NULL` constraint the moment you run the
   schema migration below, and `tmi:translation:doctor`'s new `null-tuuid` check will keep
   reporting it as broken linkage until the underlying row is actually fixed.
2. Migrate the columns (combine with § 1's `DROP COLUMN translations` in the same migration
   if convenient):

   **MySQL / MariaDB:**
   ```sql
   ALTER TABLE product
       MODIFY tuuid CHAR(36) NOT NULL,
       MODIFY locale VARCHAR(16) NOT NULL;
   ```

   **PostgreSQL:**
   ```sql
   ALTER TABLE product
       ALTER COLUMN tuuid SET NOT NULL,
       ALTER COLUMN locale TYPE VARCHAR(16),
       ALTER COLUMN locale SET NOT NULL;
   ```

   **SQLite** cannot alter a column's type or nullability in place — rebuild the table
   (`doctrine:migrations:diff` generates the rebuild for you).
3. Run the NULL sweep — and the migration — **before** deploying v4's application code
   against a table that still has NULL rows. The write path is safe either way (it never
   writes a NULL `tuuid`/`locale`), but you want the schema migration to be the thing that
   catches leftover NULL rows, not a silent, unfixed `null-tuuid` report every doctor run
   after.

---

### 3. The four `EntityTranslator` lifecycle hooks were removed

**BREAKING:** `EntityTranslatorInterface::afterLoad()` / `beforePersist()` / `beforeUpdate()`
/ `beforeRemove()` are gone, and `TranslatableEventSubscriber` no longer calls them.

**Why:** `TranslatableEventSubscriber::prePersist()` / `postLoad()` already normalise an
entity's own locale before any hook ran, so every hook call was
`translate($entity, $entity->getLocale())` — the identity operation — on every single flush,
always. They translated nothing, ever, "not usually, always."

**Migration Steps:**
- A decorator or custom `EntityTranslatorInterface` implementation that declares these four
  methods keeps working — removing a method from an interface does not break implementors.
  Delete them at your convenience.
- If you called one of these hooks directly rather than relying on the Doctrine listener,
  replace it with an explicit `translate()` / `translateAndPersist()` / `getOrTranslate()`
  call, or hook into Doctrine's own lifecycle events, or `PreTranslateEvent` /
  `PostTranslateEvent` (§ 4).
- Non-breaking side effect worth knowing about: `EntityTranslator::translate()` no longer logs
  an info message for an identity call (`translate($e, $e->getLocale())`) — it did,
  unconditionally, before. If you scraped that log line for anything, it is gone.

---

### 4. Every bundle service is private; the Twig global is renamed; events are typed classes

**BREAKING (three independent changes, one release):**

**a. Every service this bundle registers is now private.** `$container->get('tmi_translation.…')`
no longer works outside a test container. Autowire against the matching interface or class
instead — `EntityTranslatorInterface`, `Tmi\TranslationBundle\Doctrine\LocaleVariantFinder`,
`Tmi\TranslationBundle\Doctrine\TranslatableRemover`, `LocaleCompletenessResolver`, and so on.
Commands, the Twig extension, the cache warmer and every listener/subscriber were always
reached through their tag, never through `container->get()`, so nothing about how those work
changes.

**b. The Twig global `locales` is renamed to `tmi_locales`** — less likely to collide with an
application's own global of the same name.

**c. `EntityTranslator` now dispatches `PreTranslateEvent` / `PostTranslateEvent`** — new
classes, both extending the shared `TranslateEvent` base — by class, instead of `TranslateEvent`
plus the `TranslateEvent::PRE_TRANSLATE` / `POST_TRANSLATE` string constants, which are
removed.

**Migration Steps:**
- Replace `$container->get('tmi_translation.translation.entity_translator')` (or any other
  `tmi_translation.…` service id) with constructor injection of the matching interface/class.
- Replace `{{ locales }}` with `{{ tmi_locales }}` in your Twig templates.
- Replace a listener wired as `on: tmi_translation.post_translate` (or subscribing to the
  removed `TranslateEvent::POST_TRANSLATE` / `PRE_TRANSLATE` string constants) with
  `#[AsEventListener(event: PostTranslateEvent::class)]` (or `PreTranslateEvent::class`), or
  `$dispatcher->addListener(PostTranslateEvent::class, ...)`.

---

### 5. `Psr6TranslationCache` is gone

**BREAKING:** the bundled PSR-6 cache adapter — `Psr6TranslationCache` — no longer exists.
`TranslationCacheInterface` still aliases to `InMemoryTranslationCache`, which is now the
*only* built-in implementation.

**Why:** within one request both implementations were equivalent — `preload()` (formerly
`warmupTranslations()`) already batches per class into one indexed query, and a PSR-6 hit
reloaded through `find()` against the already-warm identity map anyway. Cross-request, a
PSR-6 hit cost a cache-backend round-trip *plus* a `find()` against the cold identity map (a
primary-key query) — against exactly one indexed `(tuuid, locale)` query on an in-memory miss.
The only thing lost by removing it is cross-process reuse in bulk workers — a throughput
concern, not a correctness one — and `TranslationCacheInterface` stays open for anyone who
wants to build that back with a persistent, identity-safe implementation of their own (see its
docblock for the identity contract any implementation must honour: reload through the
`EntityManager` with the locale filter suspended, never hand back a serialized/detached
instance).

**Migration Steps:**
- If your application overrode the `TranslationCacheInterface` alias to point at
  `Psr6TranslationCache`, remove that override — the interface now has only one built-in
  target, `InMemoryTranslationCache`.
- If you instantiated `Psr6TranslationCache` directly (tests, a custom service definition),
  either drop it in favour of the default, or supply your own persistent
  `TranslationCacheInterface` implementation built on the identity contract above.

---

### 6. `TranslationHandlerInterface` is two methods on typed contexts

**BREAKING:** every custom `TranslationHandlerInterface` implementation. The interface goes
from four methods to two:

**Before (v3.4):**
```php
interface TranslationHandlerInterface
{
    public function supports(TranslationArgs $args): bool;
    public function translate(TranslationArgs $args): mixed;
    public function handleSharedAmongstTranslations(TranslationArgs $args): mixed;
    public function handleEmptyOnTranslate(TranslationArgs $args): mixed;
}
```

**After (v4.0):**
```php
interface TranslationHandlerInterface
{
    public function supports(TranslationContext $context): bool;
    public function translate(TranslationContext $context): mixed;
}
```

The single, mutable `TranslationArgs` DTO is replaced by two typed contexts, both extending an
abstract `TranslationContext` base — `Tmi\TranslationBundle\Translation\Context\TranslationContext`,
`EntityTranslationContext`, `PropertyTranslationContext`, all in that same namespace. Which one a
handler receives says what shape of thing is being translated:

- **`EntityTranslationContext`** — "translate this entity". Constructed with a
  `TranslatableInterface $entity` (plus optional source/target locale). `getEntity():
  TranslatableInterface` is the typed accessor. This is what `EntityTranslator::translate()`
  builds for a top-level call, and what the association handlers (`TranslatableEntityHandler`,
  `BidirectionalManyToOneHandler`, `BidirectionalOneToOneHandler`,
  `BidirectionalOneToManyHandler`) build when they walk into a related entity.
- **`PropertyTranslationContext`** — "translate this property value". Constructed with a
  `mixed $value` (plus optional source/target locale). `getValue(): mixed` is the accessor —
  unlike `getEntity()`, this one stays untyped, because a property's value can be a scalar, an
  embeddable object, or a `Collection`. This is the shape `DoctrineObjectHandler::translateProperties()`
  builds for every non-entity property, and the shape the `OneToMany`/`ManyToMany` handlers
  receive (the *collection*, never the owning entity).

Members common to both, declared on the abstract `TranslationContext` base:

- **`getSubject(): mixed` / `setSubject(mixed): static`** — abstract on the base, implemented by
  each subclass to proxy to `$entity` or `$value` respectively (`EntityTranslationContext::setSubject()`
  asserts `TranslatableInterface`; `PropertyTranslationContext::setSubject()` accepts anything).
  Use this when a handler is genuinely reachable with either context shape and needs one
  implementation instead of branching by `instanceof` — `DoctrineObjectHandler` is the bundle's
  own example: it clones whatever it is handed (`$context->setSubject($clone)`) and hands the
  clone's properties to the translator, regardless of whether the clone came from an entity or a
  property value.
- **`getSourceLocale()` / `setSourceLocale(?string): static`**, **`getTargetLocale()` /
  `setTargetLocale(?string): static`** — same nullable-string pair `TranslationArgs` had, resolved
  lazily by `EntityTranslator` when still `null`.
- **`getCopySource(): ?bool` / `setCopySource(?bool): static`** — `null` means "not yet resolved
  for this entity"; `EntityTranslator` resolves it once per entity before dispatch, unchanged.
- **`getProperty(): ?\ReflectionProperty` / `setProperty(?\ReflectionProperty): static`** — the
  property this call was reached through. `null` for a top-level `EntityTranslationContext`; set
  for the association case and for every `PropertyTranslationContext`.
- **`getTranslatedParent(): ?object` / `setTranslatedParent(?object): static`** — the
  already-translated owner this subject belongs to, for back-reference repair. Same
  null-for-top-level rule as `getProperty()`.
- **`isShared(): bool` / `setShared(bool): static`**, **`isEmpty(): bool` / `setEmpty(bool):
  static`** — **new**, and the reason the interface lost two methods. `EntityTranslator` resolves
  `#[SharedAmongstTranslations]` / `#[EmptyOnTranslate]` from the property's attributes *before*
  dispatching to the handler chain, the same as it always has, and now stamps the answer onto the
  context (`$context->setShared(true)` / `$context->setEmpty(true)`) instead of calling a second
  or third method. Every getter above is inherited by both `EntityTranslationContext` and
  `PropertyTranslationContext` unchanged; only `getSubject()`/`setSubject()` are abstract and
  reimplemented per subclass, and only `getEntity()`/`getValue()` are unique to one side.

Every direct call site changes in the same way: `EntityTranslatorInterface::processTranslation()`
now takes a `TranslationContext` instead of a `TranslationArgs` — relevant if a handler
recursively calls `$this->translator->processTranslation(...)` (or `->translate(...)`, whose
signature is unchanged) to delegate a sub-value to the handler chain.

**Migration Steps:**

1. Change your handler's `use` of `Tmi\TranslationBundle\Translation\Args\TranslationArgs` (now
   deleted) to `Tmi\TranslationBundle\Translation\Context\TranslationContext` (plus
   `EntityTranslationContext` / `PropertyTranslationContext` if `supports()` narrows on one), and
   retype both interface method signatures.
2. Replace `$args->getDataToBeTranslated()` with whichever accessor matches what your handler
   actually expects: `$context->getEntity()` (typed `TranslatableInterface`) if `supports()`
   already guarantees an `EntityTranslationContext`, `$context->getValue()` (mixed) for a
   `PropertyTranslationContext`, or `$context->getSubject()` (mixed, either shape) if your
   handler — like `DoctrineObjectHandler` — is reachable both ways.
3. Delete your `handleSharedAmongstTranslations()` and `handleEmptyOnTranslate()` methods,
   merging each one's body into `translate()`, gated on `$context->isShared()` /
   `$context->isEmpty()` at the top — the same branch order `EntityTranslator` used to pick which
   method to call, now picked by the handler itself.
4. If your handler recursively delegates a sub-value through
   `$this->translator->processTranslation($subArgs)`, build a `PropertyTranslationContext` (or
   `EntityTranslationContext`, if the sub-value is itself `TranslatableInterface`) instead of a
   `TranslationArgs`, and use its fluent setters (`->setProperty()->setTranslatedParent()->setCopySource()`)
   exactly as before — every setter still returns `static`.

**Before/after, a complete custom handler** — a collection handler that hands a locale variant
fresh, empty to-many collections for a configured `class => properties` map (a common pattern for
associations that should never carry the source's children into a new locale):

**Before (v3.4):**
```php
<?php

declare(strict_types=1);

namespace App\Translation\Handler;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\Handlers\TranslationHandlerInterface;

final class EmptyOnVariantCollectionHandler implements TranslationHandlerInterface
{
    /** @var array<class-string, list<string>> */
    private const array EMPTY_ON_VARIANT = [
        \App\Entity\Article::class  => ['tags', 'comments'],
        \App\Entity\Category::class => ['articles'],
    ];

    public function supports(TranslationArgs $args): bool
    {
        if (!$args->getDataToBeTranslated() instanceof Collection) {
            return false;
        }

        $property = $args->getProperty();

        return null !== $property
            && \in_array($property->name, self::EMPTY_ON_VARIANT[$property->class] ?? [], true);
    }

    public function translate(TranslationArgs $args): ArrayCollection
    {
        return new ArrayCollection();
    }

    public function handleEmptyOnTranslate(TranslationArgs $args): ArrayCollection
    {
        return new ArrayCollection();
    }

    public function handleSharedAmongstTranslations(TranslationArgs $args): never
    {
        $property = $args->getProperty();

        throw new \LogicException(sprintf(
            'SharedAmongstTranslations is not supported on to-many association "%s::$%s".',
            null !== $property ? $property->class : 'unknown',
            null !== $property ? $property->name : 'unknown',
        ));
    }
}
```

**After (v4.0):**
```php
<?php

declare(strict_types=1);

namespace App\Translation\Handler;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Tmi\TranslationBundle\Translation\Context\PropertyTranslationContext;
use Tmi\TranslationBundle\Translation\Context\TranslationContext;
use Tmi\TranslationBundle\Translation\Handlers\TranslationHandlerInterface;

final class EmptyOnVariantCollectionHandler implements TranslationHandlerInterface
{
    /** @var array<class-string, list<string>> */
    private const array EMPTY_ON_VARIANT = [
        \App\Entity\Article::class  => ['tags', 'comments'],
        \App\Entity\Category::class => ['articles'],
    ];

    public function supports(TranslationContext $context): bool
    {
        if (!$context instanceof PropertyTranslationContext || !$context->getValue() instanceof Collection) {
            return false;
        }

        $property = $context->getProperty();

        return null !== $property
            && \in_array($property->name, self::EMPTY_ON_VARIANT[$property->class] ?? [], true);
    }

    public function translate(TranslationContext $context): ArrayCollection
    {
        if ($context->isShared()) {
            $property = $context->getProperty();

            throw new \LogicException(sprintf(
                'SharedAmongstTranslations is not supported on to-many association "%s::$%s".',
                null !== $property ? $property->class : 'unknown',
                null !== $property ? $property->name : 'unknown',
            ));
        }

        // isEmpty() and the ordinary (non-shared, non-empty) path both want the same
        // result here -- a locale variant always starts these collections empty.
        return new ArrayCollection();
    }
}
```

Note what collapsed: `translate()` and `handleEmptyOnTranslate()` returned the identical
`new ArrayCollection()` before, because this handler always empties the collection regardless of
which of the three methods the framework called — that duplication is gone, and the one
`isShared()` branch that used to throw unconditionally (`never` return type) now sits inside
`translate()` next to the case it guards against.

**Why:** the four-method shape asked every handler to implement `handleSharedAmongstTranslations()`
/ `handleEmptyOnTranslate()` even when — as above — both bodies were one-liners duplicating logic
`translate()` already had, or trivial pass-throughs (`PrimaryKeyHandler`'s three methods all
returned `null`). Collapsing the two extra methods into pre-resolved `isShared()`/`isEmpty()`
facts on the context removes that duplication and lets each handler's `translate()` read as a
single decision tree instead of three separate entry points the framework picks between. Splitting
the payload into two typed contexts additionally lets `supports()` narrow with `instanceof`
instead of duck-typing a `mixed` payload, and let several `instanceof TranslatableInterface` /
`is_object()` guards inside built-in handlers' `translate()` bodies disappear — the type system
now guarantees what those guards used to check by hand.

---

## Behavioural Changes (4.0)

None of these require a code change to keep compiling. They change what the bundle *does*, so
verify anything in your application that depends on the old behaviour — several fix a data bug
serious enough that you may want to audit existing data.

### 1. The direct ManyToOne/OneToOne form now translates the target entity

**This is the change most likely to affect you.**

A plain `#[ORM\ManyToOne(inversedBy: ...)]` (or `#[ORM\OneToOne]`) field pointing at another
translatable entity — declared on the *owning* side, not reached as a back-reference from a
`OneToMany`/`ManyToOne` pair — used to silently return the **untranslated source** through
v3.4: `Product::$category` stayed pointed at the English `Category` row no matter what locale
`Product` was translated into.

**After v4:** the target is translated to the matching locale (get-or-create, the same
existing-variant lookup and clone pipeline a top-level `translate()` call uses) and the
translated `Product` points at it.

**What to check:**
- If a shared reference across all locales was actually what you wanted (one `Category` row,
  every `Product` locale pointing at it regardless of language), mark the association
  `#[SharedAmongstTranslations]` instead of relying on the old pass-through bug.
- If you do want the target translated, give the association `cascade: ['persist']` (or
  persist the translated target explicitly) — a newly created target variant needs to be
  saved like any other new entity.
- Association direction matters here: this only affects the *direct* form (the field lives on
  the class that declares the association). The *back-reference* form — reached through a
  bidirectional `OneToMany`'s children — already repaired its parent reference correctly
  before v4 and is unaffected.

### 2. Cross-locale lookups no longer bypass the locale filter

The lookup that searches for "does a variant of this Tuuid already exist in the target
locale" — `EntityTranslator`'s internal warmup (now `preload()`) — used to query through the
entity's own repository/query builder. Under an **active** `tmi_translation_locale_filter`
pinned to the source locale, Doctrine's `SQLFilter` silently rewrote that query, combining the
explicit "target locale" condition with the filter's own "source locale" condition into a
contradiction that could never match — so `translate()` under an active filter minted a
**duplicate row** on every call instead of reusing the one already there.

**After v4:** the lookup goes through `LocaleVariantFinder`, which suspends the filter for the
query and restores it afterwards. If your application always disables the locale filter before
calling `translate()`, this changes nothing observable for you; if it translates entities
while the filter is active (a request handler, a locale-scoped import), duplicate rows that
used to appear silently no longer will. (`TranslatableEntityHandler::translate()` ran a second,
identical existing-variant check of its own at the time this fix landed; a later 4.0 change
removed it entirely as redundant — existence is now resolved exactly once, by the `preload()`
above, before any handler runs. See [§ 7](#7-translatableentityhandler-no-longer-checks-for-an-existing-variant-itself).)

### 3. The translation cache is identity-safe across `EntityManager::clear()`

A cache hit that survived an `EntityManager::clear()` call (import batches, long-running
workers) used to still be treated as reusable, even though the `UnitOfWork` no longer tracked
it. `getOrTranslate()` then called `persist()` on that stale, detached instance — and
Doctrine's `persist()` assumes `STATE_NEW` for anything the `UnitOfWork` does not track,
**re-inserting the detached instance as a brand-new row** instead of reusing the existing one.
No exception, just a silent duplicate.

**After v4:** a cache hit is checked against the entity's real `UnitOfWork` state; a detached
hit is treated as a miss and falls through to a fresh lookup, which reloads (or reuses) a
managed instance and overwrites the stale cache entry. If your import/batch code calls
`clear()` between translations of the same Tuuid, this is now correct without any code change
on your part.

### 4. Inheritance hierarchies are counted once per concrete class

`tmi:translation:doctor`, `tmi:translation:sync-shared` and `TranslatableEntityValidationWarmer`
used to enumerate every concrete subclass of a `SINGLE_TABLE`/`JOINED` hierarchy *in addition
to* its root — even though querying the root is already polymorphic and returns every
subclass's rows. A hierarchy of *N* subclasses was walked *N+1* times: `doctor` double
(or more) counted the same physical rows as separate anomalies, and `sync-shared` /
`TranslatableEntityValidationWarmer` repeated the same work once per subclass on top of the
root.

**After v4:** only the hierarchy root is enumerated, and each hydrated row's *own* concrete
class is used to resolve which properties are shared/checked/validated on it — so a field
declared only on a subclass is still seen correctly. If you were dividing an old `doctor`
anomaly count by the number of subclasses as a workaround, stop — the count is now accurate on
its own. `sync-shared --entity` also now validates against Doctrine's metadata directly, so a
concrete subclass name is accepted where it used to be wrongly rejected.

### 5. `copy_source` resolution is proxy-safe; `EmptyOnTranslate` collections are never shared

Two related clone-pipeline bugs:

- Resolving a per-entity `#[Translatable(copySource: ...)]` override used to reflect the
  entity directly, which finds nothing when the entity arrives as a lazily-loaded Doctrine
  proxy subclass (PHP attributes are never inherited by a generated subclass) — the override
  silently fell back to the global `copy_source` setting instead of the entity's own.
- An empty `Collection` property skipped by `#[EmptyOnTranslate]`'s handler used to leave the
  clone's property pointing at the **exact same** `Collection` instance as the source's — an
  `add()` on the "translated" entity's collection silently mutated the source's collection
  too.

**After v4:** proxy-loaded entities resolve their own `#[Translatable]` override correctly, and
an emptied collection on a clone is always a fresh, independent `ArrayCollection`. Also fixed
in the same change: a `mappedBy` back-reference property declared on a mapped superclass
*above* the entity (not on the entity's own class) now resolves through the same hierarchy
walk the rest of the bundle already used elsewhere, instead of throwing.

### 6. `tmi:translation:sync-shared` names the properties that drifted

The command used to report only an aggregate count ("N translation(s) need updating") plus a
separate readonly-drift listing — an operator could not tell *which* shared properties were
diverging without re-running with `--dry-run` and reading the entity source.

**After v4:** every run prints a table — `Property | Tuuids | Rows | Writable` — naming each
drifted property, how many Tuuid groups and rows it touched, and whether it was writable,
right after the existing count line. Exit codes, sums, and streaming/detach behaviour are
unchanged; if you parse this command's output, the new table is additional output after what
was already there.

### 7. TranslatableEntityHandler no longer checks for an existing variant itself

`TranslatableEntityHandler::translate()` used to run its own "does a variant in the target
locale already exist" query before cloning — a duplicate of the check
`EntityTranslator::processTranslation()` already runs via `preload()` before dispatching to
any handler (see [§ 2](#2-cross-locale-lookups-no-longer-bypass-the-locale-filter)). Nothing
this second check could still find slipped past the first one, except one case:
`processTranslation()`'s own detached-cache-hit handling (see
[§ 3](#3-the-translation-cache-is-identity-safe-across-entitymanagerclear)) originally left a
narrow window after an `EntityManager::clear()` where `preload()` still skipped its query, and
this handler's own lookup was what caught it. That window is closed directly in `preload()`
now (it applies the same detached-hit check `processTranslation()` always has), so the second
check had nothing left to do.

**After v4:** `TranslatableEntityHandler::translate()` always clones — it takes only
`DoctrineObjectHandler` and `AttributeHelper` in its constructor, no longer
`LocaleVariantFinder`. This is a **breaking constructor change** if you construct this class
directly (decorating it, or a unit test built on `new TranslatableEntityHandler(...)`) — drop
the `LocaleVariantFinder` argument. Autowired consumers (the normal case: this class is
private in `services.yaml` and only ever reached through the handler chain) see no change.
Calling `translate()` on this handler any other way than through
`EntityTranslatorInterface::translate()`/`processTranslation()` now always clones, existence
check or not — see the class's own docblock.

Also new in this change: `EntityTranslator` remembers, per (tuuid, locale) pair, when a
`preload()` query already looked up a Tuuid and found nothing — so a batch `preload()` call
followed by a per-entity `getOrTranslate()` loop (the recommended import shape, see
[Performance](README.md#-performance)) no longer re-asks the database once per entity. Like
`InMemoryTranslationCache`, `EntityTranslator` is now tagged `kernel.reset`: a long-running
worker forgets that memory between units of work, so a variant created by some other means in
the meantime becomes visible again immediately.

---

## New in 4.0

Additive, non-breaking (unless you implement `EntityTranslatorInterface` yourself — see the
last bullet):

- **`TranslatableRemover`** (`Tmi\TranslationBundle\Doctrine\TranslatableRemover`, alias
  `tmi_translation.doctrine.translatable_remover`) — removes every locale variant sharing a
  Tuuid, or exactly one variant while leaving its siblings, always through
  `EntityManager::remove()` per variant so ORM cascades/`orphanRemoval`/lifecycle callbacks
  fire correctly. See [Removing a translatable entity](README.md#removing-a-translatable-entity).
- **`cascade_remove_locale_variants`** (config, default `false`) — opt in to make a plain
  `$em->remove($entity)` on any translatable entity cascade to its sibling locale variants
  automatically, via a `preRemove` listener.
- **`strict_discovery`** (config, default `false`) — turn a `0 translatable entities
  discovered` result at compile time from a logged warning into a hard `LogicException`, once
  you know your project always has at least one.
- **Zero-config `TuuidType`** — the `tuuid` DBAL type registers itself; the manual
  `doctrine.dbal.types` entry earlier versions' docs asked for is no longer necessary (a
  manual entry, if you still have one, is harmless and simply wins through normal config merge
  order).
- **`tmi:translation:doctor --entity=<FQCN>`** and the **`null-tuuid`** anomaly class — restrict
  a doctor scan to one entity, and catch rows whose `tuuid` column is a literal database
  `NULL` (only reachable via a write that bypasses the entity layer, now that the column is
  `NOT NULL` — see § 2).
- **`EntityTranslatorInterface::preload(iterable $entities, string $locale): void`** — batch-
  loads each entity's `$locale` variant ahead of time, one query per class instead of one per
  entity; call it once before looping `getOrTranslate()` over an import batch.
  `EntityTranslator`'s own implementation also remembers, per (tuuid, locale) pair, when a
  batched query already found nothing, so a per-entity `getOrTranslate()` loop after the
  upfront `preload()` call costs no further lookup queries at all (see
  [§ 7](#7-translatableentityhandler-no-longer-checks-for-an-existing-variant-itself)) — the
  one caveat: a variant for such a pair created by anything other than this translator (a
  manual `persist()`, another process) stays invisible until this translator creates one for
  that pair or the service is reset (`EntityTranslator` is tagged `kernel.reset` for exactly
  this). This is an **additive interface method**: if you implement `EntityTranslatorInterface`
  directly (rather than decorating or extending `EntityTranslator`), you must add it to keep
  implementing the interface. See [Performance](README.md#-performance) for the numbers this
  enables.

---

## Upgrade Checklist (4.0)

1. Run the NULL-row sweep from [§ 2](#2-tuuid-and-locale-columns-are-now-not-null-locale-grows-to-16-characters)
   against your **current** (v3.4 or earlier) schema, and resolve or delete anything it finds.
2. Run `composer update tmi/translation-bundle` to pull in 4.0.
3. Write and run the schema migration: `DROP COLUMN translations` (§ 1) and the `NOT NULL` /
   length changes on `tuuid`/`locale` (§ 2), combined into one migration if convenient
   (`doctrine:migrations:diff` generates it once the entity mapping no longer describes the
   old shape).
4. Search your codebase for `$container->get('tmi_translation.…')` and replace it with
   constructor injection ([§ 4a](#4-every-bundle-service-is-private-the-twig-global-is-renamed-events-are-typed-classes)).
5. Search your Twig templates for `{{ locales }}` and replace it with `{{ tmi_locales }}`
   ([§ 4b](#4-every-bundle-service-is-private-the-twig-global-is-renamed-events-are-typed-classes)).
6. Search for listeners on `TranslateEvent::PRE_TRANSLATE` / `POST_TRANSLATE`, or on
   `tmi_translation.pre_translate` / `post_translate`, and move them to
   `PreTranslateEvent::class` / `PostTranslateEvent::class`
   ([§ 4c](#4-every-bundle-service-is-private-the-twig-global-is-renamed-events-are-typed-classes)).
7. If you overrode `TranslationCacheInterface` to `Psr6TranslationCache`, or instantiated it
   directly, remove that wiring ([§ 5](#5-psr6translationcache-is-gone)).
8. Search your entities for a direct `ManyToOne`/`OneToOne` to another translatable entity and
   decide, per association, whether you want it translated (add `cascade: ['persist']`) or
   shared (`#[SharedAmongstTranslations]`) — [Behavioural Change 1](#1-the-direct-manytoonoonetoone-form-now-translates-the-target-entity).
9. Search for hand-rolled locale-variant deletion (a loop over `findBy(['tuuid' => ...])`, or
   a single `$em->remove()` you expected to remove every locale) and replace it with
   `TranslatableRemover` — see [New in 4.0](#new-in-40).
10. If you implement `EntityTranslatorInterface` directly, add `preload()`
    ([New in 4.0](#new-in-40)).
11. If you implemented the removed `afterLoad()`/`beforePersist()`/`beforeUpdate()`/
    `beforeRemove()` hooks, either delete them (harmless — see [§ 3](#3-the-four-entitytranslator-lifecycle-hooks-were-removed))
    or move their logic to `PreTranslateEvent`/`PostTranslateEvent` or Doctrine's own
    lifecycle events.
12. If you implement `TranslationHandlerInterface` (a custom handler), retype both methods to
    `TranslationContext`, replace `getDataToBeTranslated()` with `getEntity()` / `getValue()` /
    `getSubject()`, and merge `handleSharedAmongstTranslations()`/`handleEmptyOnTranslate()` into
    `translate()`, gated on `$context->isShared()`/`isEmpty()`
    ([§ 6](#6-translationhandlerinterface-is-two-methods-on-typed-contexts)).
13. If you construct `TranslatableEntityHandler` directly (decorating it, or a unit test built
    on `new TranslatableEntityHandler(...)`), drop the `LocaleVariantFinder` constructor
    argument — see
    [§ 7](#7-translatableentityhandler-no-longer-checks-for-an-existing-variant-itself).
14. Run your test suite. Behavioural Changes 1-7 above are the ones most likely to surface as
    test failures rather than compile errors — a green suite on v3.4 is not evidence your
    expectations matched the (buggy) old behaviour.

---

# UPGRADE FROM 3.3 to 3.4

## `TranslationCacheInterface::has()` was removed

**BREAKING (callers only), shipped deliberately in a minor:** `has()` is gone from
`TranslationCacheInterface`, `InMemoryTranslationCache` and `Psr6TranslationCache`.

**Why in a minor and not v4:** the method's answer is inherently unreliable on persistent
backends — a key can exist while the entry no longer loads (row deleted since caching, older
entry format), which is precisely the check-then-get trap that produced a production
`TypeError` before v3.2.1. The bundle itself has not called `has()` since v3.2.1. At the
time of removal the package has no known external consumers (zero Packagist dependents; both
in-house consumers verified free of callers), so the trap is being removed before anyone can
adopt it, rather than deprecated across a major cycle.

**Migration Steps:**
- Calling `$cache->has($tuuid, $locale)`: replace with `$cache->get($tuuid, $locale) !== null`.
  This is the only reliable check and costs one pool round-trip instead of two.
- A custom `TranslationCacheInterface` implementation that declares `has()` keeps working —
  removing a method from an interface does not break implementors, an extra public method is
  simply no longer part of the contract. Delete it at your convenience.

---

# UPGRADE FROM 3.1 to 3.2

## `Psr6TranslationCache` now requires an `EntityManagerInterface`

**BREAKING (constructor signature only):** `Psr6TranslationCache::__construct()` takes a
second, required argument.

**Before (v3.1):**
```php
new Psr6TranslationCache($cachePool);
```

**After (v3.2):**
```php
new Psr6TranslationCache($cachePool, $entityManager);
```

**Why:** `set()` used to store the full translated entity in the pool. On a persistent
backend (Redis, filesystem, ...) that entity deserializes as a detached object with dead
proxy/EntityManager references, and `EntityTranslator` handed it straight to consumers --
silent corruption on any backend other than an in-memory one. `set()` now stores the
entity's class and identifier instead, and `get()` reloads through the EntityManager on
every hit. A row deleted after it was cached now reloads to `null` (a clean cache miss)
rather than handing back a stale object, and an entity with no identifier yet (not
persisted, or persisted but not flushed) is no longer cached at all.

**Migration Steps:**
- Wired through the bundle's `services.yaml` (the default): nothing to do — the service
  definition now passes `@doctrine.orm.entity_manager` automatically.
- Instantiating `Psr6TranslationCache` directly (tests, a custom service definition): pass
  an `EntityManagerInterface` as the second argument.
- If you back this cache with a persistent pool, no code change is needed to *keep it
  working* — but existing entries written by v3.1 or earlier are in the old (raw-entity)
  format. `get()` recognises them as unrecognised values and treats them as a miss rather
  than erroring, so they are simply re-translated and re-cached in the new format; no
  manual purge is required, though clearing the pool once avoids paying for that first
  re-translation.

---

# UPGRADE FROM 3.0 to 3.1

Version 3.1 is backwards compatible — no signatures moved, nothing was removed. It ships a
per-locale completeness API, a CI gate for shared-value drift, a corrected
`#[SharedAmongstTranslations]` contract, and a more accurate orphan check. One item deserves
a re-read of your own code:

## Re-read your code against the real `SharedAmongstTranslations` contract

The attribute was documented as *"value stays identical across all locales: if you update
this field in any translation, all the others will be synchronized"*. **That was never
true.** The bundle copies shared values from the source **once, when a variant is created**
via `translate()`; there is no update-time propagation — deliberately, since consumers may
legitimately vary such values per locale (for example, publishing one language at a time).

What to do:

- If you relied on the documented-but-nonexistent synchronization, either stop marking the
  property `#[SharedAmongstTranslations]` (it is per-locale data), or reconcile explicitly
  with `bin/console tmi:translation:sync-shared`.
- If shared values must never diverge, gate CI on the new
  `bin/console tmi:translation:sync-shared --check` — it writes nothing and exits non-zero
  as soon as any shared value (writable or readonly) has drifted.

## Orphan check moved to flush time; the warning respects `enable_logging`

The *"persisted in non-default locale without a shared Tuuid"* check ran at `persist()`
time — before a same-flush `translate()` could link the new variants to the entity — so it
reported entities that ended up correctly linked, and it logged its warning even with
`enable_logging: false`.

- The verdict is now settled at **flush time**: an entity whose Tuuid was adopted by another
  insertion in the same flush is linked, not orphaned. With `strict_orphan_check` on,
  `OrphanTranslationException` is thrown from `flush()` instead of `persist()` — adjust any
  code that caught it around `persist()`.
- The warning is only emitted when `enable_logging: true`; with the default `false` the
  subscriber receives no logger, matching the bundle's opt-in logging contract.

## New in 3.1

- **`LocaleCompletenessResolver`** — answers, per enabled locale, whether a Tuuid has a
  variant and whether its translatable content is complete relative to the baseline
  (default-locale) variant. `resolveBatch()` answers many Tuuids with one query for admin
  lists. Returns `LocaleCompleteness` / `TranslationStatus` (`Missing` | `Incomplete` |
  `Complete`) value objects.
- **`tmi:translation:sync-shared --check`** — the CI drift gate described above.
- **Documented seeding hook** — with `copy_source: false` new variants are seeded empty
  (empty name, empty slug: `(slug, locale)` unique-key collisions and placeholder leaks are
  yours to prevent). Listen to `TranslateEvent::POST_TRANSLATE`, which fires after the
  variant is constructed and before it is persisted, to mint locale-correct placeholders —
  do not clone the source's slug.

---

# UPGRADE FROM 2.x to 3.0

Version 3.0 raises the minimum Symfony requirement to **8.0** and fixes a set of bugs that were
silently producing wrong data. The **public API is unchanged** — no signatures moved, nothing was
renamed. But several of the fixes change *behaviour* at runtime, and code written against the old
(broken) behaviour can notice. Read [Behavioural Changes](#behavioural-changes) before upgrading.

## Table of Contents

- [Breaking Changes](#breaking-changes)
  - [Minimum Symfony version is now 8.0](#minimum-symfony-version-is-now-80)
- [Behavioural Changes](#behavioural-changes)
  - [1. To-many associations are actually translated now](#1-to-many-associations-are-actually-translated-now)
  - [2. SharedAmongstTranslations on association collections throws](#2-sharedamongsttranslations-on-association-collections-throws)
  - [3. Translating into the entity's own locale returns the same instance](#3-translating-into-the-entitys-own-locale-returns-the-same-instance)
  - [4. Custom handler tag priority is now honoured](#4-custom-handler-tag-priority-is-now-honoured)
  - [5. TypeDefaultResolver throws for more types](#5-typedefaultresolver-throws-for-more-types)
  - [6. readonly properties no longer crash translation](#6-readonly-properties-no-longer-crash-translation)
  - [7. sync-shared exits non-zero on readonly drift](#7-sync-shared-exits-non-zero-on-readonly-drift)
- [Upgrade Checklist](#upgrade-checklist)

---

## Breaking Changes

### Minimum Symfony version is now 8.0

**Before (v2.x):**
```json
"symfony/framework-bundle": "^7.3 || ^8.0"
```

**After (v3.0):**
```json
"symfony/framework-bundle": "^8.0"
```

The same floor applies to `symfony/console`, `symfony/security-bundle`, `symfony/yaml`, `symfony/property-access` and `symfony/uid`.

**Why:** The `^7.3` half of that range was never installable in practice. Every candidate Symfony 7.3 release is blocked by published security advisories, so `composer` refuses to resolve it for any project using `roave/security-advisories` — as this bundle's own test suite does. CI never exercised the 7.3 branch either: it resolves the highest allowed Symfony on every run. The constraint advertised support that could not be obtained, so 3.0 narrows it to the branch that is actually verified.

Symfony 8.0 is confirmed working: the full suite (494 tests, 100% coverage) passes against `symfony/framework-bundle` v8.0.14.

**Migration Steps:**
1. Upgrade your application to Symfony 8.0 or later first.
2. Run `composer update tmi/translation-bundle`.
3. Work through the [Upgrade Checklist](#upgrade-checklist) — the public API is unchanged, but runtime behaviour is not.

**Still on Symfony 7.x?** Pin to `tmi/translation-bundle:^2.2`. Note that installing it alongside `roave/security-advisories` will still fail for the reason above; the constraint change in 3.0 simply makes that honest.

---

## Behavioural Changes

None of these require a code change to keep compiling. They change what the bundle *does*, so
verify anything in your application that depends on the old behaviour.

### 1. To-many associations are actually translated now

**This is the change most likely to affect you.**

`supports()` on all three collection handlers (`BidirectionalOneToManyHandler`,
`BidirectionalManyToManyHandler`, `UnidirectionalManyToManyHandler`) required the value being
translated to be a `TranslatableInterface`. The value of a `OneToMany` / `ManyToMany` property is
the `Collection`, so every one of them always returned `false`. No handler ever claimed a
collection property.

**Before (v2.x):** the translated parent received the **same collection instance** as the source
entity, holding the **same untranslated children**.

```php
$translation = $translator->translate($product, 'de_DE');

$translation->getPhotos() === $product->getPhotos();      // true  -- one shared collection
$translation->getPhotos()->first()->getLocale();          // 'en_US' -- never translated
```

**After (v3.0):** the translated parent gets its own collection of translated children, and the
source graph is left untouched.

```php
$translation->getPhotos() === $product->getPhotos();      // false -- separate collections
$translation->getPhotos()->first()->getLocale();          // 'de_DE'
$product->getPhotos()->first()->getLocale();              // 'en_US' -- source unchanged
```

**What to check:**
- Remove any workaround that translated collection children by hand after calling `translate()` —
  you will now get duplicates.
- Child entities in a translated collection are new rows. If you were relying on the translation
  pointing at the *same* child records, that is no longer the case.
- Tests asserting on collection contents after translation will need updating. Several of this
  bundle's own tests had pinned the broken behaviour, so a green suite on v2.x is not evidence
  that your expectations were right.

### 2. SharedAmongstTranslations on association collections throws

The relation handlers have always documented that `#[SharedAmongstTranslations]` is unsupported on
associations, but because the collection handlers never ran, the attribute was silently ignored on
`OneToMany` and `ManyToMany` properties.

**After (v3.0):** it throws a `RuntimeException` during translation.

```php
// Now throws: "SharedAmongstTranslations is not allowed on bidirectional ManyToMany associations."
#[SharedAmongstTranslations]
#[ORM\ManyToMany(targetEntity: Tag::class, mappedBy: 'products')]
private Collection $tags;
```

**Fix:** remove the attribute, and share the related entity's own scalar columns instead. Making
the association unidirectional does **not** help for `ManyToMany` —
`UnidirectionalManyToManyHandler` rejects it too. (For a **to-one** relation, dropping
`inversedBy`/`mappedBy` does make sharing legal.)

### 3. Translating into the entity's own locale returns the same instance

`translate($entity, $entity->getLocale())` is now the identity operation: the same instance comes
back, nothing is cloned and nothing is written to the translation cache.

**Why it changed:** the Doctrine hooks (`afterLoad`, `beforePersist`, `beforeUpdate`,
`beforeRemove`) request exactly that on every flush. The clone that produced was cached under
`(tuuid, locale)` and then handed to every later `translate()` for that pair — so a subsequent
lookup could return a detached clone with a `null` id instead of the managed entity.

**What to check:** if you called `translate()` with the entity's current locale expecting a copy,
use `clone` explicitly, or `findAllLocaleVariants()` to fetch a genuine sibling.

### 4. Custom handler tag priority is now honoured

`TranslationHandlerPass` ignored the `priority` attribute on the
`tmi_translation.translation_handler` tag; handlers were appended in raw container registration
order. Since the chain is first-match-wins, a custom handler typically landed *after* broad
built-ins like `DoctrineObjectHandler` and never ran.

**After (v3.0):** priority decides position, highest first. Handlers sharing a priority keep their
registration order.

```yaml
services:
    App\Translation\Handler\MoneyHandler:
        tags:
            # Between EmbeddedHandler (80) and BidirectionalManyToOneHandler (70)
            - { name: 'tmi_translation.translation_handler', priority: 75 }
```

**What to check:** a custom handler that never fired on v2.x may start firing now. One tagged
without a priority defaults to `0` and runs after every built-in — give it an explicit priority
if it needs to win.

### 5. TypeDefaultResolver throws for more types

`#[EmptyOnTranslate]` on a non-nullable type with no zero-value used to return `null` for
`iterable`, `object` and intersection types, which surfaced later as an opaque `TypeError` on
assignment. It now throws the same actionable `LogicException` already used for non-nullable
enums and objects. `null` is only ever returned for types that actually accept it.

Collection-typed properties are unaffected — they are emptied by their handler and never reach
`TypeDefaultResolver`.

### 6. readonly properties no longer crash translation

A `readonly` property combined with `#[SharedAmongstTranslations]` is a legal combination (only
`readonly` + `#[EmptyOnTranslate]` is rejected by validation), but translating such an entity died
with `Error: Cannot modify readonly property` — the clone's property is already initialised and
PHP rejects even an identical write. The write is now skipped when nothing would change, so the
combination works.

If a handler resolves a genuinely *different* value for a readonly property, you get a
`LogicException` naming the property instead of a raw `Error`.

### 7. sync-shared exits non-zero on readonly drift

`tmi:translation:sync-shared` used to crash mid-run on a readonly shared property whose value had
drifted. It now reports those rows, syncs everything else, and **exits non-zero** — including in
`--dry-run`. If you run this command in CI, a non-zero exit no longer means "the command failed".

The command also detects sharing declared on an embeddable **class** or on a property **inside** an
embeddable, not just on the entity property. Rows that were previously reported as "already in
sync" may now be updated.

---

## Upgrade Checklist

1. Move to Symfony 8.0+ and run `composer update tmi/translation-bundle`.
2. Search your entities for `#[SharedAmongstTranslations]` on `OneToMany` / `ManyToMany`
   properties — these now throw. ([#2](#2-sharedamongsttranslations-on-association-collections-throws))
3. Review code that reads collections off a translated entity, and drop any manual
   translate-the-children workarounds. ([#1](#1-to-many-associations-are-actually-translated-now))
4. Give every custom translation handler an explicit tag `priority`, and re-check that it fires
   where you expect. ([#4](#4-custom-handler-tag-priority-is-now-honoured))
5. If CI runs `tmi:translation:sync-shared`, handle the new non-zero exit for readonly drift.
   ([#7](#7-sync-shared-exits-non-zero-on-readonly-drift))
6. Run your test suite and treat collection-related assertions with suspicion — they may have been
   encoding the old behaviour.

---

# UPGRADE FROM 1.x to 2.0

Version 2.0 is a major release with breaking changes that improve alignment with Symfony standards, add type safety, and introduce powerful new features. This guide covers every change needed to upgrade from v1.x to v2.0.

## Table of Contents

- [Breaking Changes](#breaking-changes-1)
  - [1. Locale Configuration](#1-locale-configuration)
  - [2. Config Structure Flattening](#2-config-structure-flattening)
  - [3. Non-Nullable getTuuid()](#3-non-nullable-gettuuid)
- [New Features (Non-Breaking)](#new-features-non-breaking)
  - [1. Translation Cache Service](#1-translation-cache-service)
  - [2. Type-Safe EmptyOnTranslate Defaults](#2-type-safe-emptyontranslate-defaults)
  - [3. Fallback Control (copy_source)](#3-fallback-control-copy_source)
  - [4. Compile-Time Attribute Validation](#4-compile-time-attribute-validation)
  - [5. Composite Unique Constraint Validation](#5-composite-unique-constraint-validation)
- [Complete v2.0 Configuration Reference](#complete-v20-configuration-reference)

---

## Breaking Changes

### 1. Locale Configuration

**BREAKING:** The `tmi_translation.locales` configuration option has been removed. You must now use Symfony's standard `framework.enabled_locales` configuration.

**Before (v1.x):**
```yaml
# config/packages/tmi_translation.yaml
tmi_translation:
    locales: [en, fr, de]
    default_locale: en
```

**After (v2.0):**
```yaml
# config/packages/framework.yaml
framework:
    enabled_locales: [en, fr, de]
    default_locale: en

# config/packages/tmi_translation.yaml
tmi_translation:
    # locales removed - bundle reads from framework.enabled_locales
    # default_locale: en  # Optional: defaults to framework.default_locale if not set
```

**Why:** Aligns with Symfony 7.3+ standard locale configuration, reducing duplication and improving consistency across your application.

**Migration Steps:**
1. Move your locale list from `tmi_translation.locales` to `framework.enabled_locales` in `config/packages/framework.yaml`
2. Remove the `locales` key from your `tmi_translation.yaml` configuration
3. Optionally set `tmi_translation.default_locale` (if different from `framework.default_locale`)

**Note:** Using the old `tmi_translation.locales` key will throw a `LogicException` with migration guidance at container compile time.

---

### 2. Config Structure Flattening

**BREAKING:** The nested `tmi_translation.logging.enabled` configuration has been flattened to `tmi_translation.enable_logging`.

**Before (v1.x):**
```yaml
# config/packages/tmi_translation.yaml
tmi_translation:
    logging:
        enabled: true
```

**After (v2.0):**
```yaml
# config/packages/tmi_translation.yaml
tmi_translation:
    enable_logging: true  # Root-level boolean (default: false)
```

**Why:** Simplifies configuration structure for a single boolean flag.

**Migration Steps:**
1. Replace `tmi_translation.logging.enabled` with `tmi_translation.enable_logging`
2. Remove the nested `logging` key

**Note:** Using the old nested `logging` key will throw a `LogicException` with migration guidance at container compile time.

---

### 3. Non-Nullable getTuuid()

**BREAKING:** The `TranslatableInterface::getTuuid()` method return type has changed from `?Tuuid` (nullable) to `Tuuid` (non-nullable).

**Before (v1.x):**
```php
<?php

use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;

interface TranslatableInterface
{
    public function getTuuid(): ?Tuuid;  // Could return null
}

// Code typically had null checks:
$entity = $repository->find($id);
if ($entity->getTuuid() !== null) {
    // Work with tuuid
}
```

**After (v2.0):**
```php
<?php

use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;

interface TranslatableInterface
{
    public function getTuuid(): Tuuid;  // Always returns Tuuid
}

// Null checks can be removed - TranslatableTrait guarantees non-null
$entity = $repository->find($id);
$tuuid = $entity->getTuuid();  // Always safe - never null
```

**Why:** The `TranslatableTrait` auto-generates a Tuuid in the constructor, ensuring it's never null. Making the return type non-nullable improves type safety and removes unnecessary null checks.

**Migration Steps:**
1. Remove null checks on `getTuuid()` calls throughout your codebase
2. If you have custom implementations of `TranslatableInterface`, update the return type from `?Tuuid` to `Tuuid`
3. Ensure your entity constructors call `$this->generateTuuid()` (the `TranslatableTrait` handles this automatically)

**Impact:** Minimal for most users. If you're using the `TranslatableTrait` (recommended), no code changes are needed except removing unnecessary null checks.

---

## New Features (Non-Breaking)

These features are new in v2.0 but don't require code changes unless you want to use them.

### 1. Translation Cache Service

v2.0 introduces `TranslationCacheInterface`, allowing you to implement custom cache strategies for translation lookups.

**Default Behavior:**
The bundle uses `InMemoryTranslationCache` by default (same behavior as v1.x internal arrays).

**Custom Implementation:**
You can now implement custom cache backends (Redis, PSR-6, etc.) by implementing `TranslationCacheInterface`:

```php
<?php

namespace App\Cache;

use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Translation\Cache\TranslationCacheInterface;

class RedisTranslationCache implements TranslationCacheInterface
{
    public function __construct(private \Redis $redis) {}

    public function has(string $tuuid, string $locale): bool
    {
        return $this->redis->exists("translation.{$tuuid}.{$locale}") > 0;
    }

    public function get(string $tuuid, string $locale): TranslatableInterface|null
    {
        $data = $this->redis->get("translation.{$tuuid}.{$locale}");
        if (false === $data) {
            return null;
        }

        $value = unserialize($data);

        return $value instanceof TranslatableInterface ? $value : null;
    }

    public function set(string $tuuid, string $locale, TranslatableInterface $entity): void
    {
        $this->redis->set("translation.{$tuuid}.{$locale}", serialize($entity));
    }

    public function markInProgress(string $tuuid, string $locale): void
    {
        // Always give the in-progress marker a TTL. It is only meaningful inside the
        // translation frame that set it, and a marker that outlives its process would
        // make every later translation of this tuuid+locale hit cycle detection and
        // return the untranslated entity.
        $this->redis->set("translation.{$tuuid}.{$locale}.in_progress", '1', 60);
    }

    public function unmarkInProgress(string $tuuid, string $locale): void
    {
        $this->redis->del("translation.{$tuuid}.{$locale}.in_progress");
    }

    public function isInProgress(string $tuuid, string $locale): bool
    {
        return $this->redis->exists("translation.{$tuuid}.{$locale}.in_progress") > 0;
    }
}
```

**Register your custom cache:**
```yaml
# config/services.yaml
services:
    Tmi\TranslationBundle\Translation\Cache\TranslationCacheInterface:
        alias: App\Cache\RedisTranslationCache
```

**See:** [llms.md](llms.md) for detailed documentation and the PSR-6 adapter example.

---

### 2. Type-Safe EmptyOnTranslate Defaults

In v1.x, using `#[EmptyOnTranslate]` on non-nullable scalar fields would throw a `LogicException` at translation time. v2.0 provides type-safe defaults instead.

**Behavior:**
When `#[EmptyOnTranslate]` is applied to non-nullable scalar fields, v2.0 automatically provides sensible defaults:

| Type      | Default Value |
|-----------|---------------|
| `string`  | `""`          |
| `int`     | `0`           |
| `float`   | `0.0`         |
| `bool`    | `false`       |
| `array`   | `[]`          |

**Example:**
```php
<?php

use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
class Product implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Column]
    #[EmptyOnTranslate]
    private string $title;  // Gets "" in new translations (not an error)

    #[ORM\Column]
    #[EmptyOnTranslate]
    private int $viewCount;  // Gets 0 in new translations

    #[ORM\Column]
    #[EmptyOnTranslate]
    private float $rating;  // Gets 0.0 in new translations

    #[ORM\Column]
    #[EmptyOnTranslate]
    private bool $published;  // Gets false in new translations
}
```

**Impact:** No migration needed. Fields that previously threw `LogicException` will now receive type-safe defaults automatically.

---

### 3. Fallback Control (copy_source)

v2.0 introduces `copy_source` configuration, giving you control over whether new translations clone source content or start empty.

**Global Configuration:**
```yaml
# config/packages/tmi_translation.yaml
tmi_translation:
    copy_source: false  # Default: new translations start empty with type-safe defaults
    # copy_source: true   # v1.x behavior: clone source content
```

**Per-Entity Override:**
```php
<?php

use Tmi\TranslationBundle\Doctrine\Attribute\Translatable;

// Override global setting for this entity
#[Translatable(copySource: true)]  // Always clone source content
#[ORM\Entity]
class Article implements TranslatableInterface
{
    use TranslatableTrait;
    // Fields will be cloned from source when translating
}

#[Translatable(copySource: false)]  // Always use type-safe defaults
#[ORM\Entity]
class Product implements TranslatableInterface
{
    use TranslatableTrait;
    // Fields will start empty (or with type-safe defaults)
}

#[Translatable(copySource: null)]  // Use global config (default)
#[ORM\Entity]
class Category implements TranslatableInterface
{
    use TranslatableTrait;
}
```

**Default Behavior (v2.0):**
- `copy_source: false` by default (change from v1.x)
- Fields marked with `#[SharedAmongstTranslations]` are always copied (regardless of `copy_source`)
- Fields marked with `#[EmptyOnTranslate]` are always cleared (regardless of `copy_source`)

**Migration from v1.x:**
If you want to preserve v1.x behavior (clone all content), set `copy_source: true` in your configuration.

---

### 4. Compile-Time Attribute Validation

v2.0 adds `AttributeValidationPass`, a compiler pass that validates attribute usage at `cache:warmup` time instead of runtime.

**What It Catches:**
- Conflicting `#[SharedAmongstTranslations]` + `#[EmptyOnTranslate]` on the same property
- `#[EmptyOnTranslate]` on readonly properties (unsupported)
- Translatable entities missing the required `$locale` property
- Multiple attribute conflicts reported in a single error message

**Example Error:**
```
LogicException: Found 2 attribute validation errors in App\Entity\Product:

1. Property $title cannot have both #[SharedAmongstTranslations] and #[EmptyOnTranslate]
2. Property $description is readonly and cannot use #[EmptyOnTranslate]
```

**When It Runs:**
- During `bin/console cache:warmup`
- During `bin/console cache:clear`
- At application boot in dev mode (container rebuild)

**Migration:** No changes required. Existing valid configurations work unchanged. Invalid configurations that would have caused runtime errors now fail fast during cache warmup.

---

### 5. Composite Unique Constraint Validation

v2.0 adds `TranslatableEntityValidationWarmer`, a cache warmer that validates unique constraints on translatable entities.

**Problem It Solves:**
Single-column unique constraints (e.g., `unique: true` on a slug field) cause constraint violations when translating entities, because each translation is a separate database row with the same slug.

**What It Catches:**
- Single-column `unique: true` constraints on translatable entity fields
- Provides actionable guidance to use composite constraints (field + locale)

**Example Error:**
```
LogicException: Translatable entity App\Entity\Product has single-column unique constraint on property $slug.

This will cause constraint violations when translating entities.

Solution: Use a composite unique constraint with the locale field:

#[ORM\UniqueConstraint(
    name: 'uniq_product_slug_locale',
    columns: ['slug', 'locale']
)]
```

**Recommended Pattern:**
```php
<?php

use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
#[ORM\UniqueConstraint(
    name: 'uniq_product_slug_locale',
    columns: ['slug', 'locale']  // Unique per locale, not globally
)]
class Product implements TranslatableInterface
{
    use TranslatableTrait;  // Provides $locale property

    #[ORM\Column(length: 255)]
    private string $slug;  // No unique: true
}
```

**When It Runs:**
- During `bin/console cache:warmup`
- During `bin/console cache:clear`

**Migration:** Review any translatable entities with unique constraints. Update single-column constraints to composite constraints (field + locale).

---

## Complete v2.0 Configuration Reference

> Snapshot of the configuration **as of v2.0**. Later versions added `strict_orphan_check` and
> `unique_locale_variants` (both v2.2) — see the [README](README.md#-configuration) for the current
> full reference.

```yaml
# config/packages/framework.yaml
framework:
    enabled_locales: [en, fr, de]  # Required: available locales for translation bundle
    default_locale: en              # Required: default locale for the application

# config/packages/tmi_translation.yaml
tmi_translation:
    # default_locale: en              # Optional: defaults to framework.default_locale
    # disabled_firewalls: []          # Optional: firewalls where locale filter is disabled
    # enable_logging: false           # Optional: enable PSR-3 debug logging (opt-in)
    # copy_source: false              # Optional: clone source content (true = v1.x behavior)
```

**Configuration Keys:**

| Key                  | Type       | Default                          | Description                                                                 |
|----------------------|------------|----------------------------------|-----------------------------------------------------------------------------|
| `default_locale`     | `string`   | `%kernel.default_locale%`        | Default locale (must be in `framework.enabled_locales`)                     |
| `disabled_firewalls` | `array`    | `[]`                             | Firewalls where the locale filter is disabled (e.g., `['admin']`)           |
| `enable_logging`     | `bool`     | `false`                          | Enable PSR-3 debug logging when a logger is available (opt-in)              |
| `copy_source`        | `bool`     | `false`                          | When `false`, new translations start empty. When `true`, clone source content (v1.x behavior) |

**Per-Entity Configuration:**

Use the `#[Translatable]` attribute to override global settings:

```php
<?php

use Tmi\TranslationBundle\Doctrine\Attribute\Translatable;

#[Translatable(copySource: true|false|null)]  // Override copy_source for this entity
class MyEntity implements TranslatableInterface
{
    // null = use global config (default)
    // true = always clone source content
    // false = always use type-safe defaults
}
```

---

## Additional Resources

- [README.md](README.md) - Full documentation, installation, and quick start guide
- [llms.md](llms.md) - Comprehensive developer and AI guide with handler chain decision tree
- [GitHub Releases](https://github.com/CreativeNative/translation-bundle/releases) - Detailed release notes for every version

---

**Questions or Issues?** Please [open an issue](https://github.com/CreativeNative/translation-bundle/issues) on GitHub.
