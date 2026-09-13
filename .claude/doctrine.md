# Doctrine Integration

## Making Entities Translatable

Implement `TranslatableInterface` and use `TranslatableTrait`:

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
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

    #[ORM\Column]
    private string $name;
}
```

## Trait Provides

The `TranslatableTrait` adds (both columns `NOT NULL` — the PHP properties stay
`?Tuuid`/`?string`, since a freshly `new`-ed, not-yet-persisted entity legitimately has
neither yet; `TranslatableEventSubscriber::prePersist()` assigns both before insert):

| Field | Type | Column | Purpose |
|-------|------|--------|---------|
| `tuuid` | `?Tuuid` | `tuuid`, `length: 36`, NOT NULL | Groups translations (auto-generated) |
| `locale` | `?string` | `Types::STRING`, `length: 16`, NOT NULL | Entity's language |

## Custom Doctrine Type

`TuuidType` handles Tuuid ↔ database conversion:

```php
// In entity
#[ORM\Column(type: 'tuuid')]
private ?Tuuid $tuuid = null;
```

Registered automatically by the bundle.

## Locale Filter

`LocaleFilter` automatically filters queries by current locale. Locales come from Symfony's
own `framework.enabled_locales`, not a bundle-specific key — configured via:

```yaml
# config/packages/framework.yaml
framework:
    enabled_locales: ['en_US', 'de_DE', 'it_IT']

# config/packages/tmi_translation.yaml
tmi_translation:
    default_locale: 'en_US'
    disabled_firewalls: ['api']  # Disable for specific firewalls
```

Each locale must be 2-16 characters and match `language[_SUBTAG...]` — validated at compile
time, so a locale the `locale` column cannot hold fails fast instead of truncating.

### Sub-requests

`LocaleFilterConfigurator` sets the filter locale on every `kernel.request`, including
sub-requests (fragments rendered with `render(controller(...))`, ESI, forwards), so a
fragment queries in its own locale. On `kernel.finish_request` it re-applies the parent
request's locale, mirroring Symfony's `LocaleAwareListener`. Without that restore the
shared EntityManager filter would keep the fragment's locale for the rest of the parent
request.

### Disabling Filter Temporarily

```php
use Tmi\TranslationBundle\Doctrine\Filter\LocaleFilter;

$this->entityManager->getFilters()->disable(LocaleFilter::NAME); // 'tmi_translation_locale_filter'
// ... query all locales
$this->entityManager->getFilters()->enable(LocaleFilter::NAME);
```

Prefer `LocaleVariantFinder::withoutLocaleFilter(callable $query)` (below) over hand-rolling
this disable/try/finally/enable dance — it is exactly this pattern, already correct.

## Event Subscriber

`TranslatableEventSubscriber` handles Doctrine lifecycle events:

- Sets `tuuid` on persist if not set
- Defaults an empty locale to `default_locale` in `prePersist()` and `postLoad()` — it does not
  validate the locale against the enabled locales; that check lives in
  `EntityTranslator::processTranslation()`, which throws `LogicException` ("Locale ... is not
  allowed") for one outside `%kernel.enabled_locales%`
- Maintains translation metadata
- Flags **orphaned translations** — an entity persisted in a non-default locale without a
  shared `Tuuid`, settled at flush time: a same-flush translation adopting the `Tuuid` clears
  the flag. Governed by `strict_orphan_check` (`true` throws `OrphanTranslationException`,
  `false` logs a warning — only with `enable_logging: true`, `null` = auto: throws when
  `kernel.debug` is on).

## Composite Index on `(tuuid, locale)`

`TranslatableIndexListener` listens on `loadClassMetadata` and injects a composite
`(tuuid, locale)` index into every `TranslatableInterface` entity — a trait cannot declare a
class-level `#[ORM\Index]`, so without it the `tuuid` column ships unindexed. The index name
is `<table>_tuuid_locale_idx` (table-prefixed for SQLite's database-wide uniqueness).

```yaml
# config/packages/tmi_translation.yaml
tmi_translation:
    unique_locale_variants: true   # promote the index to a UNIQUE constraint
```

Enable `unique_locale_variants` only once `tmi:translation:doctor` confirms no duplicate
`(tuuid, locale)` rows exist — otherwise the schema migration fails.

## Cross-Locale Lookups and Removal

`LocaleVariantFinder` is the one place that queries across every locale variant of a Tuuid — it
suspends the locale filter for the query and restores it afterwards, whatever state it was in.
`TranslatableRepositoryTrait::findAllLocaleVariants()` / `findAllLocaleVariantsBatch()` and
`LocaleCompletenessResolver` delegate to it; use it directly for anything else that needs to see
every locale of an entity regardless of the current request's locale.

`TranslatableRemover` removes every locale variant sharing a Tuuid — `$em->remove()` only ever
acts on the one row it is given, so a naive "delete this" under a locale-filtered query only ever
removes the current-locale row, leaving the others online. `removeAllLocaleVariants()` schedules
every variant (via `EntityManager::remove()` per variant, never a bulk DQL DELETE, so ORM
cascades / `orphanRemoval` / lifecycle callbacks still fire); `removeSingleLocaleVariant()` removes
just one, exempting it from the opt-in cascade-removal listener.

Set `cascade_remove_locale_variants: true` to make a **plain** `$em->remove($entity)` on any
translatable entity cascade to its sibling locale variants automatically, via
`LocaleVariantRemovalListener` (a `preRemove` Doctrine listener, always registered — the flag
gates it at runtime) calling `TranslatableRemover::cascadeFromPreRemove()`. With the flag on,
`removeSingleLocaleVariant()` is the escape hatch for removing one variant while its siblings
stay online.

## Shared-Value Propagation

`SharedValueSynchronizer` copies `#[SharedAmongstTranslations]` values from one locale variant
onto its siblings with the **edited row as source** — `syncFrom($editedRow)` returns the
siblings that changed, managed and not flushed, so the caller's `flush()` writes them in the
same transaction. Discovery covers mapped columns, embeddables (attribute on the entity property,
on the embeddable class, or on an inner property) and to-one associations to a non-translatable
target; never a collection or an association to a translatable target.

```yaml
# config/packages/tmi_translation.yaml
tmi_translation:
    propagate_shared_on_flush: false   # the default is true
```

With the flag on — the default — `SharedValuePropagationListener` (`onFlush`, always registered) makes the
attribute a flush-time invariant: a shared property edited on *any* variant reaches every other
variant of the Tuuid — including one scheduled for insertion in the same flush, excluding one
scheduled for deletion in it — before the
statements run, field by field, via `recomputeSingleEntityChangeSet()`. Two variants flushed
with *different* new values for the same shared property throw `SharedValueConflictException`
and nothing is written. Enable it only after `tmi:translation:sync-shared --check` reports zero
drift and the attribute is gone from every property the application diverges on purpose.

## Translation Roots (5.1)

One NON-translatable row per logical object owning the `tuuid` every locale variant copies —
`TranslationRootInterface` + `TranslationRootTrait` (`mintTuuid()` once for a new object,
`adoptTuuid()` for an existing group's identity, `getTuuid()` never lazily mints, unique column,
not PHP `readonly` because Doctrine's `ReflectionReadonlyProperty` compares by identity). The
translation row references it with a `ManyToOne` whose declared type implements the interface —
that TYPE is the signal (`AttributeHelper::isTranslationRootReference()`); `#[TranslationRoot]`
is an optional marker. `translate()` reaffirms the reference to the identical root
(`isEffectivelyShared()` in `EntityTranslator::runHandlers()`); the shared-value machinery
discovers it as a shared association flagged `root`, compares by identity and REPORTS a mismatch
(`SharedValueSyncReport::rootDrift()`), never writes it — not `sync-shared` in write mode, not the
flush listener. Contract checks (reflection only, `TranslationRootContractException` with a
`Solution:` line) run in `AttributeValidationPass`; the constructor rule turns on the moment the
reference is non-nullable. `RootAdopterPass` cross-checks the `tmi_translation.root_adopter`
tags (required attribute `class`) against the classes the validation pass found — exactly one
adopter per root-declaring class — in a compiler pass, not the optional cache warmer.

`tmi:translation:adopt-root` creates roots for rows that predate them: streams the adopter's
hierarchy root (`--entity` with a leaf still streams the root), classifies EVERY group before
writing (`mismatched` first, then `new`/`complete`/`partial`/`drift`/`ambiguous`, by tuuid
string), aborts write mode before the first write on mismatched/drift/ambiguous, adopts `new`
groups (the factory's root must be tuuid-less and of `rootClassFor()`'s class — it adopts the
group's tuuid) and heals `partial` ones onto their existing root, in batches of whole groups
with per-entity detach. `--check` fails on anything but `complete`, on roots without rows (one
`NOT EXISTS` per root reference, filter suspended — `NOT IN` is the NULL trap) and on any
`tmi_translation.tuuid_orphan_counter` above zero; every counter is printed at 0 too, an
exception is `ERROR` + `FAILURE`.

Test fixtures: `tests/Fixtures/Entity/Root/` (`Listing`/`ListingA`/`ListingB` STI roots,
`Estate`/`EstateA`/`EstateB` phase-1 rows with the marker, `ArticleRoot`/`Article` phase-2 rows
without it), adopters in `tests/Support/Root/`, one directory per contract error in
`tests/Fixtures/Validation/Root/`. TestKernel registers the two adopters — the compile-time
cross-check requires them.

## Diagnostic Commands

- `php bin/console tmi:translation:adopt-root` — see Translation Roots above; `--dry-run`
  classifies only, `--check` is the CI gate on the root invariant, `--entity=<FQCN>` restricts
  to one hierarchy.
- `php bin/console tmi:translation:doctor` — scans translatable tables for broken linkage:
  orphan translations (a non-default-locale row without its source), duplicate `(tuuid, locale)`
  pairs, and `null-tuuid` rows (a literal DB `NULL` in the `tuuid` column — only reachable via a write
  outside the entity layer, since the column is `NOT NULL`); `--entity=<FQCN>`
  restricts the scan to one entity, checked against Doctrine's metadata so a concrete
  subclass is accepted; exits non-zero.
- `php bin/console tmi:translation:sync-shared` — propagates `#[SharedAmongstTranslations]`
  values from the default-locale row to all sibling locale variants — columns, embeddables and
  to-one associations to a non-translatable target, the same discovery as the flush-time
  propagation (`--dry-run`, `--check` for a CI drift gate, `--entity=<FQCN>`, and
  `--tuuid=<uuid> --source-locale=<locale>` to repair one record from the row you name); prints
  a `Property | Tuuids | Rows | Writable` table naming every drifted property. The read
  side is also a service: `SharedDriftScanner::scan($class)` streams one `SharedDrift` per
  drifted sibling row and property, on top of `LocaleVariantFinder::streamGroupedByTuuid()`.

## Relationship Handling

| Relationship | Handler | Notes |
|--------------|---------|-------|
| ManyToOne | BidirectionalManyToOneHandler | Translates to matching locale |
| OneToMany | BidirectionalOneToManyHandler | Clones collection with translations |
| OneToOne | BidirectionalOneToOneHandler | Translates owned side |
| ManyToMany (bidirectional) | BidirectionalManyToManyHandler | Clones with translations |
| ManyToMany (unidirectional) | UnidirectionalManyToManyHandler | Priority 30; matches a `ManyToMany` with neither `mappedBy` nor `inversedBy` |
| Embedded | EmbeddedHandler | Deep clones embedded objects |

## Known Limitations

1. **Translatable associations + SharedAmongstTranslations**: Rejected on every association
   shape whose target is itself translatable — `OneToMany`, `ManyToMany` (both directions), a
   bidirectional `ManyToOne`/`OneToOne` in the **direct form**, and a unidirectional
   `ManyToOne`/`OneToOne` (no `inversedBy`/`mappedBy`, rejected by `TranslatableEntityHandler`)
   — sharing would leave the relation's ownership ambiguous across locale variants. Share the
   related entity's own scalar columns instead. Unaffected: sharing an association whose target
   is *not* translatable (a `GeoPlace`/`Owner`/`User`-style reference) still returns the
   identical instance. **Exception since 5.1:** a `#[SharedAmongstTranslations]` `ManyToOne`
   **back-reference** reached through its parent's `OneToMany` (the child's own FK to the parent
   being translated) is not rejected — `BidirectionalManyToOneHandler` consumes the flag and the
   child's clone points at the parent's clone, the non-shared outcome. Known gap kept: that
   value returns through the shared early return in `EntityTranslator::runHandlers()`, which
   skips `PostTranslateEvent` and the translation cache for it.
2. **Unique constraints**: A single-column `unique: true` on a translatable field fails
   validation at `cache:warmup` — use a composite `field + locale` constraint.
3. **Row-per-locale**: every locale variant is a full row; *N* configured locales means up to
   *N*× the rows for a translatable entity, paid regardless of how many locales are actually
   filled in.

See UPGRADING.md and README.md § Limitations for detail and workarounds.
