# TMI Translation Bundle - Doctrine Entity Translations for Symfony

[![CI](https://github.com/CreativeNative/translation-bundle/actions/workflows/php.yml/badge.svg)](https://github.com/CreativeNative/translation-bundle/actions/workflows/php.yml)
[![codecov](https://codecov.io/github/CreativeNative/translation-bundle/graph/badge.svg?token=D2PXJL5T2Y)](https://codecov.io/github/CreativeNative/translation-bundle)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-brightgreen.svg)](https://phpstan.org/)
[![Latest Version](https://img.shields.io/packagist/v/tmi/translation-bundle.svg)](https://packagist.org/packages/tmi/translation-bundle)
[![License](https://img.shields.io/packagist/l/tmi/translation-bundle.svg)](https://packagist.org/packages/tmi/translation-bundle)
[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-8892BF.svg)](https://php.net/)
[![Symfony 8.0+](https://img.shields.io/badge/Symfony-8.0%2B-000000.svg)](https://symfony.com/)
[![Doctrine ORM 3.5+](https://img.shields.io/badge/Doctrine-ORM%203.5%2B-FF6D00.svg)](https://www.doctrine-project.org/)
[![Total Downloads](https://img.shields.io/packagist/dt/tmi/translation-bundle.svg)](https://packagist.org/packages/tmi/translation-bundle)
[![GitHub Stars](https://img.shields.io/github/stars/CreativeNative/translation-bundle.svg?style=social)](https://github.com/CreativeNative/translation-bundle)

Stores every locale variant as a row in the entity's own table — one indexed lookup per variant, no joins, no translation tables.

## How it works

- Every locale variant of an entity is one row in the entity's own table, sharing a `Tuuid` with its siblings — no join table, ever.
- `LocaleFilter`, a Doctrine ORM filter, transparently scopes every query to the current request's locale.
- `translate()` clones a source entity through a first-match-wins handler chain. It is idempotent get-or-create, not a live sync — an existing variant is returned as-is, never re-synced.
- `tmi:translation:doctor`, `tmi:translation:sync-shared` and `LocaleCompletenessResolver` diagnose broken Tuuid linkage, shared-value drift, and per-locale completeness.
- `TranslatableRemover` removes a Tuuid's sibling locale variants together — `$em->remove()` alone only ever sees the one row you pass it.

## Why This Bundle

**Performance.** Every query-cost number this README states is enforced by an exact assertion (`assertSame`, not a ceiling) in [`tests/Performance/QueryBudgetTest.php`](tests/Performance/QueryBudgetTest.php) — see the full [Performance](#-performance) table below. Two headline numbers: finding a translatable entity under the active locale filter costs **1 query**; translating into an already-existing variant costs **1 query and 0 inserts**. Reading pays no per-row overhead — the bundle registers no lifecycle hook on load. Every cross-locale lookup is a single indexed `(tuuid, locale)` query, `preload()` batches import lookups per class instead of per entity, and the translation cache resets itself between jobs in long-running workers (`kernel.reset`).

**Verified quality.** 100% **line** coverage is a CI gate (`composer test`, tracked by the Codecov badge above), not a one-time snapshot. PHPStan runs at **level max** with the strict-rules, doctrine, symfony and phpunit extensions installed (`composer stan`). PHPUnit runs in [strict mode](phpunit.xml) — `failOnWarning`, `failOnNotice`, `failOnRisky` and `failOnDeprecation` are all `true`, so a stray warning fails the build the same as an assertion failure. As of this release: **967 tests, 8,909 assertions**, all green — and those two numbers are themselves a CI gate ([`tools/check-doc-claims.php`](tools/check-doc-claims.php) fails the build when this sentence stops matching the suite), as is the documentation itself: [`tests/Documentation/DocumentationReferencesTest.php`](tests/Documentation/DocumentationReferencesTest.php) asserts that every link, anchor and class name in these docs still resolves. Every bug fix in this codebase ships with a negative-proof test — one demonstrably red against the old code before the fix, not merely green after it — the discipline is visible directly in the commit history.

## ✨ Features

- **Same-table storage** — every locale variant is a row in the entity's own table, linked by a shared `Tuuid`; no join table.
- **Inheritance-aware** — `SINGLE_TABLE` and `JOINED` hierarchies are counted once per concrete row, not once per subclass scanned; properties declared privately on a parent class are inspected correctly, and a `mappedBy` property declared on a mapped superclass above the entity is found through the hierarchy walk, not just on the entity's own class.
- **Relations translate through the full pipeline** — a `ManyToOne`/`OneToOne` association to another translatable entity is itself translated (get-or-create) through the same handler chain as a top-level entity, not a shallow clone with a dangling id.
- **Removal semantics** — `TranslatableRemover` removes a Tuuid's sibling locale variants together, or exactly one variant while leaving its siblings; an opt-in `cascade_remove_locale_variants` listener does the former automatically on a plain `$em->remove()`.
- **Diagnostics** — `tmi:translation:doctor` fails on broken linkage (orphan, duplicate, `null-tuuid`) and lists untranslated and incomplete records for information (`--strict` counts those too), for every entity or, with `--entity`, just one; `tmi:translation:sync-shared` names every drifted `#[SharedAmongstTranslations]` property, how many Tuuid groups and rows it touched, and whether it was writable; `strict_discovery` fails the container compile, instead of only logging, when compile-time attribute discovery finds zero translatable entities.
- **Shared values that stay shared** — `#[SharedAmongstTranslations]` copies a value onto a new locale variant, and a later edit on *any* variant reaches every sibling inside the same `flush()`, field by field, with a `SharedValueConflictException` instead of last-wins when two variants disagree. On by default (`propagate_shared_on_flush`), switchable off for content that varies per locale, and the copy logic is the public `SharedValueSynchronizer` service, with the edited row as source.
- **Per-locale completeness** — `LocaleCompletenessResolver` answers, for one Tuuid or a batch of hundreds in a single query, whether each enabled locale has a variant and whether its content is complete relative to the baseline.
- **AI-ready** — [AI skills](#-ai-assisted-development) for Claude Code and other assistants guide setup, debugging and custom handlers.

## ⚠️ Limitations & When Not to Use It

* **Row-per-locale.** Every locale variant is a full row: *N* configured locales means up to *N*× the rows for a translatable entity, and that cost is paid per entity regardless of how many locales are actually filled in. If most of your content stays in one or two locales and only a handful of fields ever need translating, a dedicated translation-table design may cost fewer rows than this bundle's trade-off of no joins for full-row duplication.
* **Unique constraints need the locale column.** A single-column `unique: true` on a translatable field triggers a validation error at `cache:warmup` — the same value legitimately repeats once per locale. Use a composite constraint (`field + locale`) instead — see [Quick Fix for unique fields](#quick-fix-for-unique-fields).
* **`SharedAmongstTranslations` is not available on an association whose target is itself translatable** — `OneToMany`, `ManyToMany` (either direction), a bidirectional `ManyToOne`/`OneToOne` (`inversedBy`/`mappedBy` set), and a *unidirectional* `ManyToOne`/`OneToOne` (neither set) all reject it with a `RuntimeException`: sharing would leave the relation's ownership ambiguous across locale variants. Share the related entity's scalar columns instead. This only concerns a translatable target — a shared association to a non-translatable entity (a `GeoPlace`/`Owner`/`User`-style reference) is unaffected and keeps returning the identical instance. Associations themselves — including `ManyToMany` in both directions — are translated normally.
* Requires **PHP 8.4+**, **Symfony 8.0+**, **Doctrine ORM 3.5+** and **`symfony/doctrine-bridge` 8.1+**. The bridge is pulled in transitively by doctrine-bundle, so this is usually invisible — but a Flex application pinned to `extra.symfony.require: "^8.0"` resolves the 8.0 bridge and will not install. The 8.0 bridge calls `GenerateSchemaEventArgs::setSchema()` without guarding it, which needs a DBAL `Schema::edit()` that does not exist below `doctrine/dbal` 4.5 (unreleased), so every `SchemaTool` run dies with a `BadMethodCallException`. The 8.1 bridge guards the same call and is unaffected. Bump `extra.symfony.require` to `^8.1` if you hit this.

## 📦 Installation

```
composer require tmi/translation-bundle
```

Register the bundle to your `config/bundles.php`.

```php
return [
// ...
Tmi\TranslationBundle\TmiTranslationBundle::class => ['all' => true],
];
```

The `tuuid` Doctrine DBAL type registers itself at compile time — there is nothing to add to `config/packages/doctrine.yaml`. If your application already declares its own `dbal.types` entry for `tuuid` (for any reason), that manual entry still wins over the bundle's own registration.

## ⚙️ Configuration
Configure your available locales in framework.yaml and, optionally, additional bundle settings:

```yaml
# config/packages/framework.yaml
framework:
    enabled_locales: ['en_US', 'de_DE', 'it_IT']

# config/packages/tmi_translation.yaml
tmi_translation:
    # default_locale: 'en_US'                # Optional: uses framework.default_locale if not set
    # disabled_firewalls: ['main']           # Optional: disable filter for specific firewalls
    # enable_logging: false                  # Optional: enable PSR-3 debug logging
    # copy_source: false                     # Optional: true = clone the source content into a new variant instead of leaving it empty
    # strict_orphan_check: ~                 # Optional: throw on orphaned translations. null = auto (on when kernel.debug)
    # unique_locale_variants: false          # Optional: make the (tuuid, locale) index a UNIQUE constraint
    # cascade_remove_locale_variants: false  # Optional: $em->remove() also removes sibling locale variants
    # propagate_shared_on_flush: true        # Optional: copy a #[SharedAmongstTranslations] value edited on one locale onto every sibling inside the same flush()
    # strict_discovery: false                # Optional: fail compilation, instead of only logging, when 0 translatable entities are discovered
```

Each configured locale must be 2-16 characters long and match the `language[_SUBTAG...]` shape (`en`, `en_US`, `pt_BR_ROMANIAN`) — the bundle validates this at compile time, so a locale the `locale` column cannot hold fails fast instead of truncating silently or erroring at `INSERT`.

## 🚀 Quick Start

### Make your entity translatable

Implement `Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface` and use the trait
`Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait` on an entity you want to make translatable.
```php
<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
class Product implements TranslatableInterface
{
    use TranslatableTrait;
    
    #[ORM\Column]
    private string $name;
    
    // ... your other fields
}
```

### Translate your entity

Autowire `Tmi\TranslationBundle\Translation\EntityTranslatorInterface` and let Symfony wire it —
every service this bundle registers is private, so there is no `$container->get('tmi_translation....')`
shortcut outside a test container.

```php
public function __construct(private EntityTranslatorInterface $entityTranslator)
{
}

// Translate only (caller must persist)
$translatedEntity = $this->entityTranslator->translate($entity, 'de_DE');

// Translate and persist in one call
$translatedEntity = $this->entityTranslator->translateAndPersist($entity, 'de_DE');

// Find existing translation or create + persist a new one
$translatedEntity = $this->entityTranslator->getOrTranslate($entity, 'de_DE');
```

Every attribute of the source entity will be cloned into a new entity, unless specified otherwise with the `EmptyOnTranslate`
attribute. Generated IDs (properties with `#[ORM\Id]` + `#[ORM\GeneratedValue]`) are automatically reset to `null` on cloned translations.

**`translate()` is idempotent get-or-create, not a live sync.** If a locale variant for the
source's Tuuid and the target locale already exists, all three methods above return that
existing row as-is — the handler chain does not re-run over it, so edits made to the source
entity *after* the variant was created are **not** copied into it. This is deliberate:
persist/update hooks call `translate()` on every flush, and re-cloning on every call would
mint duplicate locale variants instead of returning the one already on record. Propagating a
changed value into existing siblings is `#[SharedAmongstTranslations]`'s job, not
`translate()`'s — it happens inside the editing `flush()`, or retroactively through
`tmi:translation:sync-shared` — see below.

## 🔧 Advanced Usage

Usually, you don't wan't to get **all** fields of your entity to be cloned. Some should be shared throughout all
translations, others should be emptied in a new translation. Two special attributes are provided in order to
solve this.

### SharedAmongstTranslations

This attribute copies the field's value from the source entity **when a new translation is
created**, and marks the field for retroactive reconciliation via `tmi:translation:sync-shared`.
On a relation to a **non**-translatable entity, that "copy" is the identical instance — object
identity is preserved by `translate()`, by `sync-shared` and by the flush-time propagation
below. A relation whose target is itself translatable cannot be shared at all — see the note
further down.

**Two modes.** By default (`propagate_shared_on_flush: true`) the attribute is a **flush-time
invariant**: a change to a shared property on *any* locale variant — whichever row a form, an
import or a command happened to edit — is copied onto every other variant of the same `Tuuid`
inside the same `flush()`, field by field (an unshared edit touches no sibling, and no sibling
is even looked up). Two variants flushed at once with *different* new values for the same
shared property throw `SharedValueConflictException` before anything is written — never
last-wins. A variant created with `translate()` earlier in the same request receives the update
too, so the new row is inserted from the updated source.

Switch it off and the copy happens **once**, at `translate()` time: updating the field on one
locale variant after the translations exist does **not** propagate to the siblings — the value
diverges silently. That is the right mode for an application that legitimately varies such a
value per locale (publishing one language at a time). Gate CI with
`tmi:translation:sync-shared --check` (exits non-zero on drift) and repair with
`tmi:translation:sync-shared`.

```yaml
# config/packages/tmi_translation.yaml
tmi_translation:
    propagate_shared_on_flush: false
```

Turning it back **on** for an existing database is a two-step move, not a config edit: get
`tmi:translation:sync-shared --check` to zero drift first, and remove the attribute from every
property the application diverges per locale on purpose. With the flag on, such a property is
no longer a dormant inconsistency — the next edit on any locale repairs it back to the source
value, undoing the divergence on purpose.

The copy itself is the public `SharedValueSynchronizer` service
(`Tmi\TranslationBundle\Doctrine\SharedValueSynchronizer`, alias
`tmi_translation.doctrine.shared_value_synchronizer`): `syncFrom($editedRow)` copies the shared
values onto every sibling with the **edited row** as source and returns the siblings that
changed — managed, not flushed, so the caller's `flush()` writes them in the same transaction.
That is the fan-out an application needs with the flag off, and `siblingsOf()` is the per-row
loop it may still need with the flag on (a per-locale status field, say). `sync()` and
`compare()` work on one sibling and report the changed and the readonly-but-drifted property
paths; `sharedProperties()` exposes the discovery. Its read-only counterpart over a whole table
is `SharedDriftScanner::scan($class)` (alias `tmi_translation.doctrine.shared_drift_scanner`):
one `SharedDrift` per drifted sibling row and property path — entity class, `Tuuid`, path,
source locale, drifted locale, readonly flag — streamed with bounded memory, for an
application's own scheduled drift watch instead of parsing `sync-shared --check` output.

> **Class-level form.** `#[SharedAmongstTranslations]` on a *class* is honoured on
> **embeddables** only (every inner property shared unless it overrides with
> `#[EmptyOnTranslate]`). On an *entity* class it is inert — `translate()`, `sync-shared` and
> the flush-time propagation all read the attribute per property; mark the properties.

***Note***: this attribute cannot be used on any association whose target is itself a
translatable entity — `OneToMany`, `ManyToMany` (either direction), a bidirectional
`ManyToOne`/`OneToOne` (`inversedBy`/`mappedBy` set), or a *unidirectional*
`ManyToOne`/`OneToOne` (neither set) all reject it: every handler that can reach one throws a
`RuntimeException`. Share the related entity's own columns instead. This does not apply to a
relation whose target is **not** translatable — the `Media` example below is that case, and
sharing it keeps working exactly as shown.

```php
#[ORM\ManyToOne(targetEntity: Media::class)]
#[SharedAmongstTranslations]
private Media $video; // Shared across all translations -- Media is not itself translatable

```

> **⚠️ Ordering caveat.** Shared values propagate to translations created *after* the value
> is set. If you set a shared field in one locale and translate the entity to other locales
> *later*, those earlier siblings keep their stale value. To back-fill existing data, run:
>
> ```
> php bin/console tmi:translation:sync-shared           # propagate shared columns FROM each record's default-locale row
> php bin/console tmi:translation:sync-shared --dry-run # preview without writing
> php bin/console tmi:translation:sync-shared --check   # CI gate: exit non-zero on drift
> php bin/console tmi:translation:sync-shared --tuuid=<uuid> --source-locale=it_IT   # repair ONE record from the row you name
> ```
>
> `--tuuid` restricts a run to one record — every locale variant of that `Tuuid`, found in
> any translatable class or only in `--entity` — and `--source-locale` names the row to copy
> **from** instead of the default-locale row. That is the targeted repair for a record edited
> in a non-default locale, where the whole-table write mode would overwrite the edited row
> with the stale default-locale values; `--source-locale` is only accepted together with
> `--tuuid`, because which row is right is a per-record decision. `--dry-run` and `--check`
> apply as usual.
>
> The command propagates `#[SharedAmongstTranslations]` values from the **default-locale** row
> to every sibling — mapped columns, embedded fields and single-valued associations
> to a **non**-translatable target (the same discovery the flush-time propagation uses; a shared
> collection, or an association to a translatable target, is rejected at translate time and is
> never synced). Every run prints a table naming each drifted property, how many Tuuid groups
> and rows it touched, and whether it was writable — nothing to enable, it always runs after
> the count line.
>
> Embedded fields are covered in all three places sharing can be declared: on the entity
> property (the whole embeddable is copied), on the embeddable class (every inner property
> except those overridden with `#[EmptyOnTranslate]`), or on an individual inner property.
> This mirrors how `EmbeddedHandler` resolves sharing at translate time.
>
> A `readonly` shared property cannot be written after hydration. The command reports such
> rows instead of crashing, syncs everything else, and exits non-zero — correct those values
> manually or at the database level.

### EmptyOnTranslate

This attribute will empty the field when creating a new translation. **ATTENTION**: the field
must be able to hold an empty value. A nullable property becomes `null`; a
`Doctrine\Common\Collections\Collection` becomes a fresh empty collection; a **non-nullable
scalar** gets a type-safe default (`string` → `''`, `int` → `0`, `float` → `0.0`, `bool` →
`false`, `array` → `[]`) and therefore does *not* have to be nullable. A nullable **value
object** — `DateTimeImmutable`, an enum, a uid, any object Doctrine has no mapping for — is
emptied to `null` like any nullable field (the same goes for `copy_source: false`). A
non-nullable **object**, **enum**, intersection type or `iterable`/`callable` has no safe empty
value: the container fails to compile (`EmptyOnTranslateTypeException`) naming the property.
Make those nullable, or use `#[SharedAmongstTranslations]` instead.

```php
#[ORM\ManyToOne(targetEntity: Owner::class, cascade: ['persist'], inversedBy: 'product')]
#[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id', nullable: true)]
#[EmptyOnTranslate]
private Owner|null $owner = null

#[ORM\Column(type: 'string', nullable: true)]
#[EmptyOnTranslate]
private string|null $title = null;
```
### Seeding new variants — what `copy_source: false` leaves behind

With `copy_source: false` (the default), a freshly created variant starts **empty**: nullable
columns are null, non-nullable scalars get type-safe defaults (`''`, `0`, `0.0`, `false`).
That includes fields your application treats as mandatory — a name, a slug. Two consequences
you must handle:

- an empty slug **collides on a `(slug, locale)` unique key** as soon as a second entity is
  seeded for the same locale;
- any placeholder written into the variant **goes public** if the variant can be published
  before an editor touches it — placeholder names have reached public URLs and image
  filenames this way.

Do not clone the source's slug as a workaround. Use the supported seeding hook instead:
listen for `PostTranslateEvent` and mint locale-correct placeholder values the moment the
variant is constructed — the event fires before anything is persisted:

```php
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Tmi\TranslationBundle\Event\PostTranslateEvent;

#[AsEventListener(event: PostTranslateEvent::class)]
final class ListingVariantSeeder
{
    public function __invoke(PostTranslateEvent $event): void
    {
        $variant = $event->getTranslatedEntity();

        if (!$variant instanceof Listing || null !== $variant->getSlug()) {
            return;
        }

        // Unique per variant, obviously non-public, easy to assert on later.
        $variant->setSlug(sprintf('draft-%s-%s', $event->getLocale(), $variant->getTuuid()));
    }
}
```

Before publication, assert the variant carries real content rather than placeholders — the
per-locale completeness API (`LocaleCompletenessResolver`, see below) reports a variant whose
required content is still missing as `Incomplete`.

### Translate events

You can alter the entities before and after a variant is constructed by listening for
`Tmi\TranslationBundle\Event\PreTranslateEvent` and `Tmi\TranslationBundle\Event\PostTranslateEvent`
— both extend the shared `TranslateEvent` base and are dispatched by class, never by a string
event name.

- `PreTranslateEvent` — dispatched before a handler builds the new variant; carries the source entity and the target locale.
- `PostTranslateEvent` — dispatched right after the new variant has been constructed and **before it is persisted**; carries source, locale and the translated entity. Mutations applied here land in the persisted row — this is the supported seeding hook (see above).

Subscribe with `#[AsEventListener(event: PreTranslateEvent::class)]` / `#[AsEventListener(event: PostTranslateEvent::class)]`, or `$dispatcher->addListener(PostTranslateEvent::class, ...)`.

Both events also fire for translatable entities reached through associations, so check the entity class in your listener.

### Filtering your contents

To fetch your contents out of your database in the current locale, you'd usually do something like `$repository->findByLocale($request->getLocale())`.

Alternatively, you can use the provided filter that will automatically filter any Translatable entity by the current locale, every time you query the ORM.
This way, you can simply do `$repository->findAll()` instead of the previous example.

Add this to your `config/packages/doctrine.yaml`:

```yaml
doctrine:
  orm:
    filters:
      # ...
      tmi_translation_locale_filter:
        class:   'Tmi\TranslationBundle\Doctrine\Filter\LocaleFilter'
        enabled: true
```  

The filter locale is set on every `kernel.request`, including sub-requests (fragments rendered
with `render(controller(...))`, ESI, forwards), so a fragment queries in its own locale. On
`kernel.finish_request` the parent request's locale is re-applied, mirroring Symfony's
`LocaleAwareListener` — the shared EntityManager filter never keeps a fragment's locale for the
rest of the parent request.

#### (Optional) Disable the filter for a specific firewall

Usually you'll need to administrate your contents.
For doing so, you can disable the filter by configuring the disabled_firewalls option in your configuration:

```yaml
# config/packages/tmi_translation.yaml
tmi_translation:
  disabled_firewalls: ['main']  # Disable filter for 'main' firewall
```

### Quick Fix for unique fields

If you need a translatable slug (or UUID), adjust your database schema to make the **slug unique per locale**, instead of globally. The `locale` column already belongs to `TranslatableTrait` — don't redeclare it, just reference it in the constraint:

```php
#[ORM\Entity]
#[ORM\Table(name: 'product')]
#[ORM\UniqueConstraint(
    name: 'uniq_slug_locale',
    columns: ['slug', 'locale']
)]
class Product implements TranslatableInterface
{
    use TranslatableTrait; // already provides the NOT NULL, length-16 $locale column

    #[ORM\Column(length: 255)]
    private string $slug;
}
```

### Querying Locale Variants

> **Always use these helpers for cross-locale lookups.** Hand-rolled `WHERE tuuid = ...`
> queries get the `tmi_translation_locale_filter` wrong and silently return only the
> current-locale row.

The quickest way is to extend the ready-made `TranslatableEntityRepository` base class:

```php
use Tmi\TranslationBundle\Doctrine\Repository\TranslatableEntityRepository;

/** @extends TranslatableEntityRepository<Product> */
class ProductRepository extends TranslatableEntityRepository
{
}
```

If your repository must extend something else, use `TranslatableRepositoryTrait` directly:

```php
use Doctrine\ORM\EntityRepository;
use Tmi\TranslationBundle\Doctrine\Repository\TranslatableRepositoryTrait;

class ProductRepository extends EntityRepository
{
    use TranslatableRepositoryTrait;
}
```

```php
// All locale variants for a single Tuuid, keyed by locale
$variants = $repository->findAllLocaleVariants($product->getTuuid());
// ['en_US' => Product, 'de_DE' => Product, ...]

// Batch lookup for multiple Tuuids
$batch = $repository->findAllLocaleVariantsBatch([$tuuid1, $tuuid2]);
// ['<tuuid1>' => ['en_US' => Product, ...], '<tuuid2>' => [...]]
```

Both methods temporarily disable the `tmi_translation_locale_filter` (if enabled) to query across all locales.

`TranslatableRepositoryTrait` offers exactly the two methods above and nothing else — a thin
wrapper around `Tmi\TranslationBundle\Doctrine\LocaleVariantFinder`. The finder itself carries two
more, single-locale lookups that have no trait equivalent: `findLocaleVariant(class, tuuid, locale)`
and `findLocaleVariantsBatch(class, tuuids, locale)`. Inject `LocaleVariantFinder` directly wherever
a repository isn't the natural fit (a service, a console command) or you need those two methods.

### Removing a translatable entity

Plain `$em->remove($entity)` removes exactly the row you pass it — nothing links a translatable
entity's sibling locale variants for Doctrine to cascade through on its own, so a naive delete
leaves every other locale's copy of the same content online.

Inject `Tmi\TranslationBundle\Doctrine\TranslatableRemover` to remove correctly:

```php
use Doctrine\ORM\EntityManagerInterface;
use Tmi\TranslationBundle\Doctrine\TranslatableRemover;

public function __construct(
    private TranslatableRemover $remover,
    private EntityManagerInterface $entityManager,
) {
}

// Schedules every locale variant sharing $product's Tuuid for removal, $product
// included. Does not flush -- call flush() yourself, once, after.
$removed = $this->remover->removeAllLocaleVariants($product);
$this->entityManager->flush();

// Removes only this one variant. Its siblings are left untouched.
$this->remover->removeSingleLocaleVariant($productDe);
$this->entityManager->flush();
```

Every variant goes through `EntityManager::remove()` individually — never a bulk DQL `DELETE`
— so ORM cascades, `orphanRemoval` and lifecycle callbacks fire exactly as they would removing
one entity at a time.

#### (Optional) Cascade it automatically

Set `cascade_remove_locale_variants: true` and a plain `$em->remove($entity)` on *any*
translatable entity removes its sibling locale variants too, via a `preRemove` listener:

```yaml
# config/packages/tmi_translation.yaml
tmi_translation:
    cascade_remove_locale_variants: true
```

With the flag on, `TranslatableRemover::removeSingleLocaleVariant()` becomes the escape hatch
for the rarer case — deleting one variant while its siblings stay online.

### Per-Locale Completeness

`LocaleCompletenessResolver` answers, per enabled locale, whether a Tuuid has a variant and
whether that variant's translatable content is complete — the building block for admin views
showing per-language translation status:

```php
use Tmi\TranslationBundle\Translation\LocaleCompletenessResolver;
use Tmi\TranslationBundle\ValueObject\TranslationStatus;

public function __construct(private LocaleCompletenessResolver $completeness) {}

$status = $this->completeness->resolveForEntity($product);

$status->statusOf('de_DE');    // TranslationStatus::Missing | Incomplete | Complete
$status->missingLocales();     // ['it_IT']
$status->isFullyTranslated();  // false

// Admin lists: hundreds of rows, one query
$byTuuid = $this->completeness->resolveBatch(Product::class, $tuuids);
$byTuuid[(string) $product->getTuuid()]->statuses(); // ['en_US' => Complete, ...]
```

Completeness is relative to a **baseline variant** — the default-locale row, or the first
variant when no default-locale row exists: a variant is *complete* when every translatable
property that is filled on the baseline is filled on it too. Optional fields left empty on
the baseline never count against the translations. Identifiers, system columns, shared
properties and associations are not inspected; embedded fields contribute their non-shared
inner properties; "filled" means not null and, for strings, not blank.

The `tuuid` column is automatically indexed: the bundle injects a composite `(tuuid, locale)`
index into every translatable entity at mapping time, so locale-variant lookups never hit an
unindexed scan. Set `unique_locale_variants: true` to promote it to a `UNIQUE` constraint —
do this only once existing data is free of duplicate locale rows (see `tmi:translation:doctor`).

### Translation roots

A translatable entity is one row per locale sharing a `tuuid`. Data that belongs to the *object*
rather than to a *language* — children, foreign keys, shared scalars — has nowhere to hang: it
either sits on one locale row or is keyed by the bare `tuuid` string with no foreign key. A
**translation root** fixes that: one non-translatable row per object, owning the `tuuid`, that
every locale variant references with a real `ManyToOne`. Doctrine then cascades natively.

```php
use Tmi\TranslationBundle\Doctrine\Model\TranslationRootInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslationRootTrait;

#[ORM\Entity]
class Listing implements TranslationRootInterface   // NOT translatable: no locale, no listeners
{
    use TranslationRootTrait;                        // unique `tuuid` column; mintTuuid() / adoptTuuid() / getTuuid()

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
}

#[ORM\Entity]
class Property implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\ManyToOne(targetEntity: Listing::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[TranslationRoot]                               // optional marker -- the TYPE makes it a root reference
    private Listing $listing;

    public function __construct(Listing $listing)
    {
        $this->listing = $listing;
        $this->setTuuid($listing->getTuuid());       // copy the identity, never mint one
    }
}

$listing = new Listing();
$listing->mintTuuid();                               // the one place an identity is born
$row = new Property($listing);
```

A property is a root reference **structurally** — a `ManyToOne` whose declared type implements
`TranslationRootInterface` — with or without the `#[TranslationRoot]` marker. `translate()`
then hands every clone the identical root instance, `tmi:translation:sync-shared --check`
compares it by identity (and never re-points it), and the compile-time validation refuses the
shapes that cannot work: two root references on one class, a root type that is also
translatable, `#[ORM\Id]` or `#[EmptyOnTranslate]` on the reference, a unique join column, a
marker on a non-root property. Once the reference is **non-nullable**, the constructor must
require the root — that is the switch that says "this class has finished migrating".

Existing rows predate their roots. `tmi:translation:adopt-root` creates them, one per `tuuid`
group, through an adopter you register for each translatable hierarchy:

```php
final class PropertyRootAdopter implements RootAdopterInterface
{
    public function getTranslatableClass(): string { return Property::class; }
    public function getRoot(TranslatableInterface $row): ?TranslationRootInterface { /* $row->getListing() */ }
    public function createRootFor(array $group): TranslationRootInterface { return new Listing(); }   // WITHOUT a tuuid
    public function attach(TranslatableInterface $row, TranslationRootInterface $root): void { /* setter or reflection */ }
    public function rootClassFor(TranslatableInterface $row): string { return Listing::class; }        // STI: from the discriminator
    public function coherenceKey(TranslatableInterface $row): string { return ''; }                     // what else must agree inside a group
}
```

```yaml
# config/services.yaml
App\Translation\PropertyRootAdopter:
    tags: [{ name: tmi_translation.root_adopter, class: App\Entity\Property }]
```

The command classifies **every** group before writing anything — `new`, `complete`, `partial`,
`drift` (a row whose `tuuid` differs from its root's), `ambiguous` (two roots in one group),
`mismatched` (rows disagree on root class or coherence key) — and refuses to write while any
group is drifted, ambiguous or mismatched. Otherwise it adopts in batches of whole groups: a new
group gets a root that *adopts* the group's `tuuid`, a partial group is healed onto its existing
root. `--check` writes nothing and fails on anything but `complete`, on roots without rows
(counted generically with `NOT EXISTS`), and on any `tmi_translation.tuuid_orphan_counter` service
above zero — your own count of rows in tables the bundle does not know that reference a `tuuid`
no row carries. Every counter is printed, at 0 too.

```
php bin/console tmi:translation:adopt-root --dry-run    # classify, write nothing
php bin/console tmi:translation:adopt-root              # adopt new + partial groups
php bin/console tmi:translation:adopt-root --check      # CI gate on the root invariant
```

Roll out in two phases without a configuration key: first a **nullable** reference (adopt-root
fills it, `--check` to zero), then make it non-nullable and add the constructor parameter.

### Diagnostics

Every locale variant of an entity shares one `Tuuid`. Translations created through
`EntityTranslator::translate()` inherit it automatically. If application code instead does
`new Entity()` + `setLocale('de_DE')` and persists it **without** a shared `Tuuid`, the result
is a *standalone* entity linked to no other locale — a silent data bug.

The bundle guards against this:

- **At flush time** — an entity persisted in a non-default locale without a shared `Tuuid` is
  flagged, and the verdict is settled when the flush runs: a translation created in the same
  flush that adopted the entity's `Tuuid` clears it. `strict_orphan_check` controls the
  reaction to an entity still orphaned at flush: `true` throws, `false` logs a warning
  (only when `enable_logging: true` — logging is opt-in), `null` (default) is *auto* —
  throws when `kernel.debug` is on, warns otherwise.
- **`tmi:translation:doctor`** — scans every translatable table, or with `--entity`, just one.
  Three findings are broken linkage and fail the run: orphan translations (a Tuuid whose only
  row is in a non-default locale — a translation without its source), duplicate
  `(tuuid, locale)` pairs, and `null-tuuid` rows — a `tuuid` column that is a literal database
  `NULL`, only reachable through a write that bypasses the entity layer (a raw insert, a row
  imported from elsewhere) since the column is mapped `NOT NULL`. Two are listed for
  information only: untranslated records (default locale only — a translation that has not
  happened yet) and incomplete ones (fewer locale rows than configured locales); `--strict`
  counts those too. Exits non-zero on anomalies, so it works as a post-migration / CI
  integrity gate:

  ```
  php bin/console tmi:translation:doctor
  php bin/console tmi:translation:doctor --entity="App\Entity\Product"
  ```

- **`tmi:translation:sync-shared`** — see above; `--check` for the CI gate, `--tuuid` +
  `--source-locale` for the targeted repair of one record from the row you name. A translation
  root reference the siblings disagree on is reported and fails the run, never re-pointed.
- **`tmi:translation:adopt-root`** — see [Translation roots](#translation-roots); `--check` proves
  that every `tuuid` group has exactly one root with the same identity, every root has rows, and
  every registered orphan counter is 0.
- **`SharedDriftScanner`** — the read side of `--check` as a service:
  `scan($class)` streams one `SharedDrift` per drifted sibling row and property path, for a
  scheduled drift watch against a production database.
- **`strict_discovery`** — off by default, an empty result from compile-time attribute
  discovery can be legitimate (no translatable entities yet). Turn it on once you have at
  least one, and a `0 translatable entities discovered` result becomes a hard
  `LogicException` at compile time instead of only a logged message — the fail-fast catches
  doctrine-bundle silently changing the shape of its attribute metadata driver definitions.

## ⚡ Performance

Every number in this table is enforced by an exact assertion (`assertSame`, not a ceiling) in
[`tests/Performance/QueryBudgetTest.php`](tests/Performance/QueryBudgetTest.php) — a query
counter wired into the test kernel behind DBAL's own logging middleware, one count per
executed statement.

| Operation                                                                   | Queries       |
|------------------------------------------------------------------------------|---------------|
| `find()` a translatable entity under the active locale filter                | 1             |
| `translate()` into an already-existing variant                               | 1 (0 inserts) |
| `translate()` a parent with *K* already-translated association children       | 2             |
| `translate()` a child already cloned through its parent's collection          | 0             |
| `LocaleCompletenessResolver::resolveBatch()` for 100 Tuuids                   | 1             |
| `LocaleVariantFinder::findAllLocaleVariantsBatch()`                          | 1             |
| `tmi:translation:doctor` (per root class scanned, or with `--entity`)         | 2             |
| Import of *N* new entities via `preload()` + `getOrTranslate()` + `flush()`   | 1 + *N*       |

**Why the association-children row is `2`, not `1 + K`.** `BidirectionalOneToManyHandler`,
`BidirectionalManyToManyHandler` and `UnidirectionalManyToManyHandler` each hand their whole
collection to `preload()` once, before looping — one batched `LocaleVariantFinder` query per
child class, whether it finds *K* hits, *K* misses, or a mix — instead of leaving each child's
own `translate()` call to query for itself. The parent's own `preload()` lookup (a miss, since
it is what triggered the translation) is the other query. A collection mixing several child
classes costs one query per class, not per child; a non-translatable item mixed into a
`ManyToMany` collection is skipped by `preload()` and never queried at all.

**Why the child row is `0`.** Every translation a handler produces is cached under its own
(tuuid, locale) — a child cloned while its parent was translated included, whether or not its
back-reference is `#[SharedAmongstTranslations]`. Translating that child on its own afterwards
is a cache hit. A removed row's entry is evicted on `postRemove`, so the hit is never a dead
instance.

**Why the import row is `1 + N`, not `1 + 3N`.** The upfront `preload()` remembers every Tuuid
its one batched query looked up and found nothing for, so each entity's own internal `preload()`
call — and the handler chain reached once that (already-known) miss falls through to it — never
asks the database the same question again; only the *N* `INSERT`s on `flush()` are left. The one
caveat: a variant for a remembered Tuuid created by anything other than this translator (a manual
`persist()`, another process) stays invisible to it until this translator creates one for that
pair or the service is reset — `EntityTranslator` is tagged `kernel.reset` for exactly this (see
[`preload()`](src/Translation/EntityTranslator.php)'s docblock).

**Reflection is cached, not repeated.** `AttributeHelper` and
`ReflectionHelper::getHierarchyProperties()` memoize per (proxy-unwrapped) class — the hot
path inside `translate()` never re-walks a class's attributes and property hierarchy per
property, per call.

**The `(tuuid, locale)` index doesn't cover a bare `locale = ?` predicate** (`locale` is not
its leftmost column) — a dedicated locale-only index is not worth adding regardless, since a
query planner facing 2-10 distinct locale values would usually ignore one anyway.

**Imports and long-running workers.** Call `preload($batch, $locale)` once before looping
`getOrTranslate()` over a batch (see the import row above) — and if your process outlives one
request or job (a queue consumer, a long-running import), both `InMemoryTranslationCache` and
`EntityTranslator` itself are tagged `kernel.reset`, so they clear themselves between units of
work instead of handing the next one an entity the last one cached (possibly one since
detached) or a stale "Tuuid not found" answer from `preload()`'s own miss memory.

## 🤖 AI-Assisted Development

This bundle includes AI skills that help you implement translations correctly. These skills work with [Claude Code](https://claude.com/product/claude-code) and other AI coding assistants.

### Available Skills

| Skill | Purpose | When to Use |
|-------|---------|--------------|
| **Entity Translation Setup** | Guides you through making any Doctrine entity translatable | "Make my Product entity translatable" |
| **Translation Debugger** | Diagnoses and fixes translation configuration issues | "Translation not working", "Why isn't my entity translating?" |
| **Custom Handler Creator** | Helps create custom handlers for specialized field types | "Create a handler for encrypted fields" |

### Using with Claude Code

If you're using [Claude Code](https://claude.ai/claude-code), the skills are automatically available when working in this project. Simply describe what you need:

```
# Make an entity translatable
"Make my Article entity translatable with shared author and translated title/content"

# Debug translation issues
"Translation is not working for my Product entity"

# Create custom handlers
"Create a custom handler for my Money value object"
```

Claude Code will automatically invoke the appropriate skill and guide you through the process.

### Using with Other AI Assistants

The skills are defined in `.agents/skills/` and follow a standard markdown format. Point your AI assistant to:

- [Entity Translation Setup](.agents/skills/entity-translation-setup/SKILL.md)
- [Translation Debugger](.agents/skills/translation-debugger/SKILL.md)
- [Custom Handler Creator](.agents/skills/custom-handler-creator/SKILL.md)

For comprehensive documentation optimized for AI assistants, see:
- [llms.txt](llms.txt) - Quick reference with all important links
- [llms.md](llms.md) - Detailed guide with handler chain decision tree and troubleshooting

## 📖 Upgrading

**5.0.0 is the current release and the only supported one.** Anything below it receives no
fixes — upgrade rather than reporting an issue against an older tag.

[CHANGELOG.md](CHANGELOG.md) is the single source for what changed in each release;
[UPGRADING.md](UPGRADING.md) carries the migration steps, newest path first, with the
unsupported paths kept below an [archive divider](UPGRADING.md#archive--unsupported-upgrade-paths)
as a historical reference only.

## 🤝 Contributing

We welcome contributions!

## 📄 License

This bundle is licensed under the MIT License.

## 🙏 Acknowledgments

Based on the original work by [umanit/translation-bundle](https://github.com/umanit/translation-bundle), now completely modernized for current PHP and Symfony ecosystems.

---

**⭐ If this bundle helps you, please give it a star on GitHub!**
