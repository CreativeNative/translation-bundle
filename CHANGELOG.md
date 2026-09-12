# Changelog

All notable changes to `tmi/translation-bundle`, newest first. This file is the single source
for release history — [`UPGRADING.md`](UPGRADING.md) carries the migration steps for each
breaking change, and the [GitHub releases](https://github.com/CreativeNative/translation-bundle/releases)
quote the entries below rather than restating them.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); the project follows
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Only the 4.x line and later is supported. Entries for 1.x–3.x are deliberately absent: their
migration paths are kept in [`UPGRADING.md`](UPGRADING.md#archive--unsupported-upgrade-paths)
and their notes in the GitHub releases.

## [5.0.0] — 2026-09-12

One breaking change — the `propagate_shared_on_flush` default — and everything that had
accumulated unreleased behind it. Migration:
[`UPGRADING.md` § UPGRADE FROM 4.1 to 5.0](UPGRADING.md#upgrade-from-41-to-50).

Consumers pinned `^4.x` do not receive this release: bump the constraint to `^5.0` once the
application has taken the two steps under "Before you upgrade".

### Changed

- **BREAKING — `propagate_shared_on_flush` defaults to `true`.** `#[SharedAmongstTranslations]`
  now keeps its promise on every write, not only at `translate()` time: a change to a shared
  property on any locale variant is copied onto every sibling of the same Tuuid inside the same
  `flush()`. 4.1 shipped the listener behind an opt-in so consumers could first strip the
  attribute from properties they diverge per locale on purpose and get
  `tmi:translation:sync-shared --check` to zero; that is now the precondition for taking the
  upgrade rather than for taking the flag. `propagate_shared_on_flush: false` restores the 4.x
  behaviour exactly, and is the supported choice for an application that varies a shared field
  per locale.
- `tmi:translation:sync-shared --tuuid` names the **rule** that picked the source row, not only
  the locale it landed on: `named by --source-locale`, `the default-locale rule, applied in
  every mode`, or `the group's first row` when the record has no default-locale variant at all.
  A repair run with `--source-locale=de_DE` followed by a plain `--check` printing
  `Source: locale it_IT` read as if the tool had dropped the decision; it had not —
  `--source-locale` is honoured in check mode exactly as in write mode, and a run that omits it
  now says which rule ran instead.
- `tmi:translation:sync-shared` in **write** mode over a whole table prints one note before the
  first `UPDATE`, naming its source rule: it copies each record from that record's
  default-locale row, so a record edited in another locale is reverted to the stale
  default-locale values. Repairing those first with `--tuuid --source-locale` was documented but
  not said by the command that does it. `--dry-run` and `--check` write nothing and print no
  note.
- `SharedValueConflictException`'s message names both ways out — edit the value on a single
  variant, or drop `#[SharedAmongstTranslations]` from a property the locales are meant to
  disagree on. With propagation on by default, whoever reads this message did not opt into
  anything and needs the fix, not only the rule.
- `SyncSharedTranslationsCommand::__construct()` takes the configured default locale as its
  sixth argument (for the two messages above). Autowired; only a hand-built instance is
  affected.
- `composer.json` declares `conflict: symfony/doctrine-bridge <8.1`. The 8.0 bridge calls
  `GenerateSchemaEventArgs::setSchema()` unguarded, which needs the unreleased `Schema::edit()`
  API and throws `BadMethodCallException` on dbal 4.4; the 8.1 bridge guards the same call in
  all four of its listeners. The dividing line is the bridge, not the ORM version —
  `doctrine/orm` 3.7 is fine under the 8.1 bridge, so no ORM constraint is imposed.

### Added

- `CHANGELOG.md` — this file. Release history lived in three places (`llms.md` § Revision
  History, `UPGRADING.md`, the GitHub releases); it now lives here, and `llms.md` links to it.
- `tests/Documentation/DocumentationReferencesTest.php` — the documentation is now gated in CI
  like the PHP is. Every bundle-owned document is checked four ways: it exists, its relative
  links resolve, its `#anchor`s resolve against GitHub's slug rules, and every
  `Tmi\TranslationBundle\…` name it mentions still resolves to a class, trait, enum or real
  namespace directory. It was written after one hand pass found three defects a fully green
  build had not: `llms.txt` still credited the 4.0-deleted `Psr6TranslationCache` with a 60s
  TTL, README described `copy_source: true` as v1.x behaviour, and `.claude/architecture.md`
  linked to an anchor that no longer existed.
- `tools/check-doc-claims.php` — the README and `llms.md` headline test counts are asserted
  against `var/junit.xml` instead of hand-copied. Wired into `composer check` (`@doc-claims`)
  and into CI next to the coverage threshold. A pull request that adds a test and leaves the
  documented number behind fails, which is the intended behaviour.
- A file-level table of contents and an `Archive — unsupported upgrade paths` divider in
  `UPGRADING.md`, so the supported paths are what a reader hits first in a 1,700-line file —
  without deleting a line of the older ones.
- `.gitattributes` `export-ignore` keeps `tests/`, `docker/`, `.github/`, `.claude/`, `tools/`,
  `bin/` and the tool configs out of the dist archive: **320 entries / 1,771,520 bytes → 111 /
  757,760** for what `composer require` downloads, 188 test files to zero. `README.md`,
  `UPGRADING.md`, `CHANGELOG.md`, `llms.md`, `llms.txt` and `.agents/skills/` stay in the
  archive on purpose — they are meant to be readable from inside a consumer's `vendor/`, by a
  developer or by an AI assistant.

### Fixed

- `#[EmptyOnTranslate]` was documented as requiring a nullable property or a `Collection`. It
  never did: `TypeDefaultResolver` gives a non-nullable scalar its zero value (`''`, `0`,
  `0.0`, `false`), a `Collection` a fresh empty one, and only a non-nullable object, enum,
  intersection or iterable throws `LogicException`. README now states the real rule.
- `(v1.x behavior)` removed from the `copy_source` config `info` string and the
  `#[Translatable]` docblock — both user-visible through `config:dump-reference`.

## [4.1.1] — 2026-09-05

Maintenance, no behaviour change. Migration:
[`UPGRADING.md` § 3](UPGRADING.md#3-411-localecompletenessresolver-is-constructed-with-the-synchronizer).

### Changed

- `LocaleCompletenessResolver` derives its "not translatable content" exclusions from
  `SharedValueSynchronizer::sharedProperties()` instead of carrying its own copy of the
  embedded-sharing discovery — the third copy of that logic, after the command's (already
  removed in 4.1) and the synchronizer's. Constructor: `SharedValueSynchronizer` replaces
  `AttributeHelper`.
- `AttributeHelper::isEmbeddableShared()`'s docblock named the sync command as its caller; the
  caller is the synchronizer.

## [4.1.0] — 2026-09-05

Additive: no schema change, no removed API, no changed default. `#[SharedAmongstTranslations]`
gains its second half. Migration:
[`UPGRADING.md` § UPGRADE FROM 4.0 to 4.1](UPGRADING.md#upgrade-from-40-to-41).

### Added

- **`propagate_shared_on_flush`** (config, default `false` in 4.x) and
  `SharedValuePropagationListener`: a shared change made on *any* locale variant is copied onto
  every sibling — including a variant scheduled for insertion in the same flush — inside the
  same `flush()`, field-level, via `recomputeSingleEntityChangeSet()`, with a per-(entity, path)
  ping-pong guard.
- **`SharedValueConflictException`** — two variants flushed with *different* new values for one
  shared property throw before anything is written. Never last-wins.
- **`SharedValueSynchronizer`** (alias `tmi_translation.doctrine.shared_value_synchronizer`) —
  the one discovery and copy of shared values with the **edited row** as source: `syncFrom()`,
  `siblingsOf()`, `sync()`/`compare()` → `SharedValueSyncReport`, memoized `sharedProperties()`,
  `valuesEqual()`.
- **`SharedDriftScanner::scan()`** (alias `tmi_translation.doctrine.shared_drift_scanner`) — the
  read side of `--check` as a service, streaming one `SharedDrift` per drifted sibling row and
  property (locations, never values), for an application's own scheduled drift watch.
- **`LocaleVariantFinder::streamGroupedByTuuid()`** — the bounded-memory streaming core the
  scanner and the command share.
- `tmi:translation:sync-shared --tuuid=<uuid> --source-locale=<locale>` — the targeted repair of
  one record from the row you name, for data edited in a non-default locale where the
  whole-table write mode would copy the stale default-locale values over the edit.
- A class-name alias for `AttributeHelper`.

### Changed

- `tmi:translation:sync-shared` is a thin client of `SharedValueSynchronizer` and therefore also
  covers single-valued associations to a **non**-translatable target. `--check` may newly report
  drift there (an owner or geo foreign key that diverged) on a database that reported none
  before.
- `SyncSharedTranslationsCommand::__construct()` takes the synchronizer.
- The class-level form of `#[SharedAmongstTranslations]` is documented as embeddable-only: on an
  entity class it was always inert, since `translate()`, `sync-shared` and the propagation all
  read the attribute per property.

## [4.0.0] — 2026-09-03

Limited, deliberate breaks on the existing storage model and handler-chain architecture — not a
rewrite. Unchanged: the same-table-per-locale storage model, the handler chain and its
priority-ordered dispatch, `#[SharedAmongstTranslations]` / `#[EmptyOnTranslate]` semantics, and
every console command's name and most of its options. Migration:
[`UPGRADING.md` § UPGRADE FROM 3.4 to 4.0](UPGRADING.md#upgrade-from-34-to-40).

### Removed

- The dead `translations` JSON column and its four trait accessors (`getTranslations()` et al.).
- `Psr6TranslationCache`; `TranslationCacheInterface` aliases only to `InMemoryTranslationCache`.
- The four no-op `EntityTranslator` lifecycle hooks (`afterLoad`, `beforePersist`,
  `beforeUpdate`, `beforeRemove`) — every call was the identity operation, always.
- The `TranslateEvent::PRE_TRANSLATE` / `POST_TRANSLATE` string constants, replaced by the
  `PreTranslateEvent` / `PostTranslateEvent` classes (dispatched by class).

### Changed

- **`TranslationHandlerInterface` narrows from four methods to two**: `supports()` /
  `translate()`, both on a typed context. The single mutable `TranslationArgs` DTO is replaced
  by `EntityTranslationContext` and `PropertyTranslationContext` over a shared
  `TranslationContext` base; `isShared()` / `isEmpty()` on the context replace the two removed
  interface methods. See `UPGRADING.md` § 6 for a before/after custom handler.
- `tuuid` and `locale` columns are `NOT NULL`; `locale` grows to length 16; `TuuidType` converts
  a database `NULL` to PHP `null` instead of inventing a fresh Tuuid.
- Every bundle service is private (autowire by interface or class); the Twig global `locales` is
  renamed `tmi_locales`.
- A direct `ManyToOne`/`OneToOne` (a field on the *owning* class) now translates its target
  through the full entity pipeline — get-or-create — instead of returning the untranslated
  source.
- `#[SharedAmongstTranslations]` on an association whose target is itself translatable throws
  `RuntimeException` from all six shapes. Three handlers previously threw `\ErrorException` (a
  PHP error wrapper, not a `RuntimeException` subclass), and `TranslatableEntityHandler` — the
  catch-all for a *unidirectional* `ManyToOne`/`OneToOne` — silently translated the target
  instead. Sharing an association to a **non**-translatable target is unaffected.
- `tmi:translation:doctor` and `tmi:translation:sync-shared` go through
  `TranslatableEntityLocator`, which walks each inheritance hierarchy's root once and resolves
  each hydrated row's own concrete class — no more double-counted `SINGLE_TABLE`/`JOINED` rows.
- `tmi:translation:sync-shared` prints a `Property | Tuuids | Rows | Writable` table naming
  every drifted property, not just an aggregate count.

### Added

- `LocaleVariantFinder` — every cross-locale lookup, with the locale filter suspended — and
  `TranslatableRemover` (`removeAllLocaleVariants()`, `removeSingleLocaleVariant()`,
  `cascadeFromPreRemove()`).
- Opt-in `cascade_remove_locale_variants` + `LocaleVariantRemovalListener`: a plain
  `$em->remove()` also removes the sibling locale variants.
- `strict_discovery` (config) turns a `0 translatable entities discovered` compile-time result
  into a `LogicException`; `tmi_translation.discovered_translatable_classes` container parameter.
- The `tuuid` DBAL type self-registers via `prepend()` (zero-config); `tmi:translation:doctor
  --entity=<FQCN>` and the `null-tuuid` anomaly class.
- `tests/Performance/QueryBudgetTest.php` asserts an exact query count for every operation in
  the README's performance table.

### Fixed

- The translation cache is identity-safe across `EntityManager::clear()` — a detached hit is a
  miss, not a re-inserted duplicate. `InMemoryTranslationCache` implements `ResetInterface`
  (`kernel.reset`), and so does `EntityTranslator`.
- `preload()`'s internal warmup goes through `LocaleVariantFinder`, so an active locale filter
  no longer mints a duplicate row on `translate()`.
- The three collection handlers (`BidirectionalOneToMany`, `BidirectionalManyToMany`,
  `UnidirectionalManyToMany`) no longer mutate the **source** entity when the translator's cycle
  guard hands back the very instance it was given, still at the source locale.
- `#[Translatable(copySource: …)]` resolution is proxy-safe; every `#[EmptyOnTranslate]`
  collection gets a fresh `ArrayCollection` instead of sharing the source's;
  `ReflectionHelper::getProperty()` walks the hierarchy for a `mappedBy` field declared on a
  mapped superclass.

### Performance

- `EntityTranslator::preload()` batches import lookups per class instead of per entity and
  remembers a batch's misses, so a per-entity `getOrTranslate()` loop after it costs no further
  lookup queries.
- The three collection handlers preload their whole collection in one batched query per child
  class: a parent with *K* already-translated association children costs 2 queries total, not
  `1 + K`.
- `AttributeHelper` and `ReflectionHelper::getHierarchyProperties()` cache per class.

## Earlier releases

1.x–3.x are unsupported. Their release notes are on the
[GitHub releases page](https://github.com/CreativeNative/translation-bundle/releases); their
migration paths are kept in full under
[`UPGRADING.md` § Archive](UPGRADING.md#archive--unsupported-upgrade-paths).

[5.0.0]: https://github.com/CreativeNative/translation-bundle/compare/v4.1.1...v5.0.0
[4.1.1]: https://github.com/CreativeNative/translation-bundle/compare/v4.1.0...v4.1.1
[4.1.0]: https://github.com/CreativeNative/translation-bundle/compare/v4.0.0...v4.1.0
[4.0.0]: https://github.com/CreativeNative/translation-bundle/compare/v3.4.0...v4.0.0
