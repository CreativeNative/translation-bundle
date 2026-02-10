# UPGRADE FROM 1.x to 2.0

Version 2.0 is a major release with breaking changes that improve alignment with Symfony standards, add type safety, and introduce powerful new features. This guide covers every change needed to upgrade from v1.x to v2.0.

## Table of Contents

- [Breaking Changes](#breaking-changes)
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

use Tmi\TranslationBundle\Translation\Cache\TranslationCacheInterface;

class RedisTranslationCache implements TranslationCacheInterface
{
    public function __construct(private \Redis $redis) {}

    public function has(string $tuuid, string $locale): bool
    {
        return $this->redis->exists("translation.{$tuuid}.{$locale}") > 0;
    }

    public function get(string $tuuid, string $locale): mixed
    {
        $data = $this->redis->get("translation.{$tuuid}.{$locale}");
        return $data ? unserialize($data) : null;
    }

    public function set(string $tuuid, string $locale, mixed $translation): void
    {
        $this->redis->set("translation.{$tuuid}.{$locale}", serialize($translation));
    }

    public function markInProgress(string $tuuid, string $locale): void
    {
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
- [GitHub Releases](https://github.com/CreativeNative/translation-bundle/releases) - Detailed release notes for v2.0

---

**Questions or Issues?** Please [open an issue](https://github.com/CreativeNative/translation-bundle/issues) on GitHub.
