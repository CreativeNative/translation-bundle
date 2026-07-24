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
