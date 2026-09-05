# Diagnostic Check Procedures

Execute checks in order. Earlier failures may cause later checks to fail.

---

## Layer 1: Entity Configuration (BLOCKING)

These checks determine if the entity is recognized as translatable at all.

### Check 1.1: TranslatableInterface Implementation

**What to look for:** `implements TranslatableInterface` in class declaration

**How to check:**
```php
// Look for this pattern
class Product implements TranslatableInterface
```

**If missing:**
- **Severity:** BLOCKING
- **Error:** Entity not recognized by TranslatableEntityHandler (priority 20)
- **Symptom:** Entity falls through to DoctrineObjectHandler, translation semantics ignored
- **Fix:** Add `implements TranslatableInterface` to class declaration
- **llms.md:** See "Missing TranslatableInterface" troubleshooting entry

### Check 1.2: TranslatableTrait Usage

**What to look for:** `use TranslatableTrait;` inside class body

**How to check:**
```php
class Product implements TranslatableInterface
{
    use TranslatableTrait;  // Must be present
```

**If missing:**
- **Severity:** BLOCKING
- **Error:** Missing $tuuid, $locale properties (both `NOT NULL` as of v4.0; the trait's
  `$translations` column and its accessors were removed in v4.0 -- nothing to expect there)
- **Symptom:** Translation fails with property access errors
- **Fix:** Add `use TranslatableTrait;` after class opening brace
- **llms.md:** See "Missing TranslatableInterface" troubleshooting entry

### Check 1.3: Tuuid Property Initialization

**What to look for:** TranslatableTrait provides $tuuid automatically. If manually implemented, check constructor initialization.

**How to check:**
```php
// If using trait: automatic
use TranslatableTrait;

// If manual: check constructor
public function __construct()
{
    $this->tuuid = Tuuid::generate();
}
```

**If missing initialization (manual implementation):**
- **Severity:** BLOCKING
- **Error:** `InvalidArgumentException` or database constraint violation
- **Symptom:** Tuuid is null when persisting
- **Fix:** Initialize tuuid in constructor with `Tuuid::generate()`
- **llms.md:** See "Missing Tuuid Property" troubleshooting entry

### Check 1.4: Locale Property

**What to look for:** TranslatableTrait provides $locale automatically.

**How to check:**
```php
// TranslatableTrait provides this
private ?string $locale = null;
```

**If manually implemented without setter:**
- **Severity:** WARNING
- **Error:** Locale not set on translated entity
- **Symptom:** Queries return wrong entities, filter doesn't work
- **Fix:** Use TranslatableTrait or implement setLocale() method

---

## Layer 2: Attribute Configuration (ERROR/WARNING)

These checks verify attribute usage on fields.

### Check 2.1: SharedAmongstTranslations on an Association to a Translatable Entity

**What to look for:** `#[SharedAmongstTranslations]` on a `ManyToOne`/`OneToOne` (with or
without `inversedBy`/`mappedBy`), a `OneToMany`, or a `ManyToMany` (either direction) whose
target is itself a translatable entity

**How to check:**
```php
// INVALID - Will throw RuntimeException
#[SharedAmongstTranslations]
#[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'products')]
private ?Category $category = null;

// INVALID - Will throw RuntimeException
#[SharedAmongstTranslations]
#[ORM\OneToMany(targetEntity: Photo::class, mappedBy: 'product')]
private Collection $photos;

// INVALID too - no inversedBy does NOT make this work; still throws RuntimeException
#[SharedAmongstTranslations]
#[ORM\ManyToOne(targetEntity: Category::class)]
private ?Category $category = null;
```

**If found:**
- **Severity:** ERROR
- **Error:** `RuntimeException` — every handler that can reach an association whose target is
  translatable rejects the attribute: `BidirectionalManyToOneHandler`,
  `BidirectionalOneToOneHandler`, and `BidirectionalOneToManyHandler` (bidirectional
  `ManyToOne`/`OneToOne`/`OneToMany`); `BidirectionalManyToManyHandler` and
  `UnidirectionalManyToManyHandler` (`ManyToMany` in either direction); and
  `TranslatableEntityHandler` (v4.0), the catch-all for a *unidirectional*
  `ManyToOne`/`OneToOne` — no `inversedBy`/`mappedBy` — which none of the other five handle
- **Symptom:** Translation fails completely when processing this field
- **Fix options:**
  1. Remove `#[SharedAmongstTranslations]` (each locale gets its own relation, translated normally)
  2. Share the related entity's own scalar columns instead of the association — the only way to
     keep a value actually shared across locales, for every association shape (`ManyToOne`,
     `OneToOne`, `OneToMany`, `ManyToMany`), bidirectional or not. Removing `inversedBy`/`mappedBy`
     from a to-one relation does **not** avoid the `RuntimeException` — it just moves which
     handler throws it, from a bidirectional handler to `TranslatableEntityHandler` (v4.0).
     `OneToMany` (`mappedBy` is intrinsic to how the bundle recognizes the relation) and
     `ManyToMany` (`UnidirectionalManyToManyHandler` rejects the attribute too, in either
     direction) have no unidirectional form to fall back to at all.
  3. Keep the association out of the translation pipeline (a plain, non-translatable reference)
     if a single shared row is a hard requirement

  None of this applies when the association's target is **not** translatable — sharing that
  (a `GeoPlace`/`Owner`/`User`-style reference) is unaffected and returns the identical instance.
- **llms.md:** See "SharedAmongstTranslations on an Association to a Translatable Entity"
  troubleshooting entry

### Check 2.2: EmptyOnTranslate on Non-Nullable Scalar Fields

**What to look for:** `#[EmptyOnTranslate]` on non-nullable fields

**v2.0 behavior:** Non-nullable scalar fields (string, int, float, bool) now get type-safe defaults instead of throwing LogicException:
- `string` -> `""`
- `int` -> `0`
- `float` -> `0.0`
- `bool` -> `false`

**Still invalid in v2.0:**
```php
// INVALID - LogicException (non-nullable object)
#[EmptyOnTranslate]
#[ORM\Column(type: Types::DATETIME_MUTABLE)]
private \DateTime $publishedAt;  // Object type, not scalar!
```

**If found (non-nullable object with EmptyOnTranslate):**
- **Severity:** ERROR
- **Error:** `LogicException: Property X is a non-nullable object and cannot have a type-safe default`
- **Fix options:**
  1. Make property nullable: `private ?\DateTime $publishedAt = null;`
  2. Remove `#[EmptyOnTranslate]` attribute
  3. Use `#[SharedAmongstTranslations]` instead

### Check 2.3: Both SharedAmongstTranslations and EmptyOnTranslate

**What to look for:** Both attributes on same field

**How to check:**
```php
// INVALID - rejected at compile time, not a precedence question
#[SharedAmongstTranslations]
#[EmptyOnTranslate]
#[ORM\Column]
private ?string $field = null;
```

**If found:**
- **Severity:** ERROR
- **Error:** `AttributeConflictException`, collected by `AttributeValidationPass` and thrown as a
  single `LogicException` at `cache:warmup` / `cache:clear`; `AttributeHelper::validateProperty()`
  raises the same conflict as a `ValidationException` at translate time
- **Symptom:** Container compilation fails — the two attributes state contradictory intents
- **Fix:** Remove EmptyOnTranslate if sharing is intended, or remove SharedAmongstTranslations if emptying is intended
- **llms.md:** See "Priority of Rules" in Core Concepts section

---

## Layer 3: Handler Chain Mapping (WARNING)

These checks verify handler compatibility with field types.

### Check 3.1: Field Type Handler Compatibility

**Handler Priority Reference:**

| Priority | Handler | Supports |
|----------|---------|----------|
| 100 | PrimaryKeyHandler | `#[ORM\Id]` fields |
| 90 | ScalarHandler | string, int, float, bool, DateTime |
| 80 | EmbeddedHandler | `#[ORM\Embedded]` fields |
| 70 | BidirectionalManyToOneHandler | ManyToOne with `inversedBy` |
| 60 | BidirectionalOneToManyHandler | OneToMany with `mappedBy` |
| 50 | BidirectionalOneToOneHandler | OneToOne with `mappedBy` or `inversedBy` |
| 40 | BidirectionalManyToManyHandler | ManyToMany with `mappedBy` or `inversedBy` |
| 30 | UnidirectionalManyToManyHandler | ManyToMany without `mappedBy`/`inversedBy` |
| 20 | TranslatableEntityHandler | Entities implementing TranslatableInterface |
| 10 | DoctrineObjectHandler | Any Doctrine-managed object (fallback) |

**What to check:** Verify each field's Doctrine mapping matches expected handler.

**If unexpected handler processes field:**
- **Severity:** WARNING
- **Error:** Field value unexpected after translation
- **Symptom:** Null when should have value, or vice versa
- **Diagnosis:** Check Doctrine annotations match expected handler
- **llms.md:** See "Wrong Handler Processes Field" troubleshooting entry

### Check 3.2: Embedded Object Sharing Behavior

**What to look for:** Embedded objects with/without SharedAmongstTranslations

**How to check:**
```php
// Shared - all locales reference same instance
#[SharedAmongstTranslations]
#[ORM\Embedded(class: Address::class)]
private Address $address;

// Cloned - each locale gets own copy
#[ORM\Embedded(class: Address::class)]
private Address $address;
```

**If unexpected sharing:**
- **Severity:** WARNING
- **Error:** Changing embedded value on one locale changes all locales
- **Symptom:** Data corruption across translations
- **Fix:** Add or remove SharedAmongstTranslations based on intent
- **llms.md:** See "Embedded Object Shared Unexpectedly" troubleshooting entry

---

## Layer 4: Runtime Configuration (ERROR/WARNING/INFO)

These checks verify environment and configuration.

### Check 4.1: Target Locale in Configuration

**What to look for:** Locale exists in `framework.enabled_locales` config

**How to check:**
```yaml
# config/packages/framework.yaml
framework:
    enabled_locales: [en, fr, de, es]
```

**If locale missing:**
- **Severity:** ERROR
- **Error:** `LogicException: The tmi/translation-bundle requires framework.enabled_locales to be configured`
- **Symptom:** Translation fails immediately
- **Fix:** Add target locale to `framework.enabled_locales` in config/packages/framework.yaml
- **llms.md:** See "Locale Not Allowed" troubleshooting entry

### Check 4.2: Doctrine Filter Enabled

**What to look for:** Translation filter configured and enabled

**How to check:**
```yaml
# config/packages/doctrine.yaml
doctrine:
    orm:
        filters:
            tmi_translation_locale_filter:
                class: Tmi\TranslationBundle\Doctrine\Filter\LocaleFilter
                enabled: true
```

**If not configured:**
- **Severity:** WARNING
- **Error:** No error, but queries return all locales
- **Symptom:** Multiple translations returned instead of current locale
- **Fix:** Add filter configuration or enable at runtime
- **llms.md:** See "Doctrine Filter Not Enabled" troubleshooting entry

### Check 4.3: Entity Persistence After Translation

**What to look for:** persist() and flush() called on translated entity

**How to check:**
```php
// v2.1 recommended: auto-persist convenience methods
$translated = $entityTranslator->translateAndPersist($source, 'fr');
$entityManager->flush();

// v2.1 find-or-create: returns existing or creates + persists new
$translated = $entityTranslator->getOrTranslate($source, 'fr');
$entityManager->flush();

// Manual (v2.0 pattern):
$translated = $entityTranslator->translate($source, 'fr');
$entityManager->persist($translated);  // Required!
$entityManager->flush();
```

**If missing:**
- **Severity:** INFO
- **Error:** No error during translation
- **Symptom:** Translation not in database
- **Reminder:** `translate()` creates a NEW entity that must be persisted. Use `translateAndPersist()` or `getOrTranslate()` (v2.1) to auto-persist.
- **llms.md:** See "Translations Not Persisted" troubleshooting entry

### Check 4.4: Collection Translation Duplicates

**What to look for:** OneToMany/ManyToMany with duplicate items after translation

**How to check:**
- Count items before and after translation
- Check if collection items implement TranslatableInterface

**If duplicates found:**
- **Severity:** WARNING
- **Error:** Items incorrectly copied or translated
- **Symptom:** Doubled collection items
- **Fix:** Ensure child entities are translatable if needed. `SharedAmongstTranslations` is **not**
  an option here — the association handlers reject it.
- **llms.md:** See "Collection Translation Creates Duplicates" troubleshooting entry

### Check 4.5: Collection not translated at all

**What to look for:** After translating a parent, its `OneToMany` / `ManyToMany` children are still
in the source locale — or the translated parent and the source share the *same* collection instance.

**How to check:**
```php
$translation = $translator->translate($parent, 'de_DE');

// Both must hold: a separate collection, with translated children
var_dump($translation->getChildren() === $parent->getChildren()); // must be false
var_dump($translation->getChildren()->first()->getLocale());      // must be 'de_DE'
```

**If found:**
- **Severity:** ERROR
- **Cause:** A collection handler's `supports()` is not matching. The value of a to-many property is
  the `Collection`, not the owning entity — a custom handler guarding on `instanceof
  TranslatableInterface` will never match and silently drops out of the chain.
- **Note:** the bundle's own three collection handlers had exactly this bug before **v3.0.0**. On
  an older version, upgrade rather than working around it.
- **Fix:** Guard on `instanceof Collection` in the custom handler, and confirm its tag priority puts
  it ahead of any broader handler.

---

## Layer 5: Compile-Time Validation (v2.0)

These checks verify v2.0 compile-time validation results.

### Check 5.1: Attribute Conflicts (AttributeValidationPass)

**What to look for:** Class-level or property-level attribute conflicts detected at cache:warmup

**How to check:**
```bash
bin/console cache:warmup
# Look for: "TMI Translation Bundle: Compile-time validation failed"
```

**Common violations:**
- `#[SharedAmongstTranslations]` + `#[EmptyOnTranslate]` on same class
- `#[SharedAmongstTranslations]` + `#[EmptyOnTranslate]` on same property
- `#[EmptyOnTranslate]` on readonly property
- Missing locale property (no TranslatableTrait)

**If found:**
- **Severity:** ERROR
- **Fix:** Remove conflicting attributes, add TranslatableTrait for locale
- **llms.md:** See "Compile-Time Validation" section

### Check 5.2: Unique Constraint Validation (TranslatableEntityValidationWarmer)

**What to look for:** Single-column unique constraints on translatable entity fields

**How to check:**
```bash
bin/console cache:warmup
# Look for: "TMI Translation Bundle: Unique constraint validation failed"
```

**If found:**
- **Severity:** ERROR
- **Fix:** Replace `unique: true` with composite `#[ORM\UniqueConstraint]` including locale
- **llms.md:** See "Compile-Time Validation" section

### Check 5.3: v1.x Config Migration

**What to look for:** Removed v1.x config keys that throw LogicException

**How to check:** Look for these in config/packages/tmi_translation.yaml:
- `tmi_translation.locales` -> removed, use `framework.enabled_locales`
- `tmi_translation.logging.enabled` -> use `tmi_translation.enable_logging: true`

**If found:**
- **Severity:** BLOCKING
- **Error:** LogicException with migration guidance
- **Fix:** Follow the error message guidance or see UPGRADING.md

### Check 5.4: `strict_discovery` (v4.0)

**What to look for:** A compile-time failure mentioning `tmi_translation.strict_discovery`.

**How to check:**
```bash
bin/console cache:warmup
# Look for: "...tmi_translation.strict_discovery" is enabled, which turns this into a hard failure..."
```

**Cause:** `AttributeValidationPass` found zero `TranslatableInterface` classes under the
configured Doctrine attribute mapping directories, and `strict_discovery: true` turns that
from a logged message into a hard `LogicException`.

**If found:**
- **Severity:** ERROR (only when `strict_discovery: true`; otherwise this is just a logged
  message, not a failure)
- **Fix:** Either the project genuinely has no translatable entities yet (set
  `strict_discovery: false`, or add one), or something changed the shape of doctrine-bundle's
  `attribute_metadata_driver` service definitions and compile-time discovery is silently
  finding nothing -- investigate that before disabling the check. The container parameter
  `tmi_translation.discovered_translatable_classes` names exactly what discovery found (empty
  when the failure fires).

---

## Layer 6: Tuuid Linkage Integrity (v2.2) & Removal Semantics (v4.0)

This layer inspects *data*, not configuration — broken linkage between locale rows.

### Check 6.1: Run the doctor command

**What to look for:** Locale rows that share no `Tuuid`, incomplete translation sets,
duplicate `(tuuid, locale)` pairs, or (v4.0) a literal database `NULL` in the `tuuid` column.

**How to check:**

```bash
php bin/console tmi:translation:doctor
php bin/console tmi:translation:doctor --entity="App\Entity\Product"   # v4.0: restrict to one class
```

**Interpreting the output:**

- **Standalone translations** — a `Tuuid` carried by a single locale row. Symptom: an entity
  resolves only in its original locale; `hreflang` alternates and shared media are missing on
  other locales. Cause: the row was created with `new Entity()` + `setLocale(...)` instead of
  `EntityTranslator::translate()`, minting a fresh unlinked `Tuuid`.
- **Incomplete translations** — a `Tuuid` with fewer locale rows than configured locales. The
  entity simply has not been translated into every locale yet.
- **Duplicate `(tuuid, locale)` pairs** — two rows claiming to be the same locale variant.
- **`null-tuuid` (v4.0)** — the `tuuid` column itself is a literal database `NULL`. As of v4.0
  the column is `NOT NULL`, so this only happens through a write that bypassed the entity
  layer entirely (a raw INSERT, or a row left over from before the v4 schema migration).

**Severity:** ERROR (standalone, duplicate, null-tuuid) / INFO (incomplete).

**Fix:**
- Standalone/duplicate/null-tuuid rows must be repaired in the database (re-point the
  `tuuid`, or remove the row). There is no automatic fix — the doctor is read-only by design.
- Prevent recurrence: always create translations via `EntityTranslator::translate()` (or
  `translateAndPersist()` / `getOrTranslate()`), and enable `strict_orphan_check` so the
  bundle throws `OrphanTranslationException` instead of silently minting an orphan.

### Check 6.2: Stale `#[SharedAmongstTranslations]` values

**What to look for:** A shared field whose value differs between locale variants — typically
because the value was set in one locale and the entity translated to others *afterwards*.

**How to check / fix:**

```bash
php bin/console tmi:translation:sync-shared --dry-run   # preview
php bin/console tmi:translation:sync-shared             # apply
php bin/console tmi:translation:sync-shared --check     # CI gate: exit non-zero on drift
```

The command copies every `#[SharedAmongstTranslations]` value from the default-locale row to
its siblings — mapped columns, embedded fields whose sharing is declared on the embeddable
class or on an inner property, and (v4.1) to-one associations to a non-translatable target.
`readonly` shared properties cannot be written after hydration: the command lists them and
exits non-zero instead of writing, so a non-zero exit with a "readonly shared value(s)"
warning means those rows need manual or DB-level correction. **Severity:** WARNING.

**Repair a record edited in a NON-default locale (v4.1):** the whole-table write mode copies the
default-locale row and would overwrite the edit. Name the record and the row instead:

```bash
php bin/console tmi:translation:sync-shared --tuuid=<uuid> --source-locale=it_IT --dry-run
php bin/console tmi:translation:sync-shared --tuuid=<uuid> --source-locale=it_IT
```

For a scheduled watch on production, `SharedDriftScanner::scan($class)` (alias
`tmi_translation.doctrine.shared_drift_scanner`) streams the same drift as `--check`, one
`SharedDrift` per row and property, without console-output parsing.

**Prevent recurrence (v4.1):** if the drift came from an edit made on one locale row after the
translations existed (a form bound to the admin's UI-locale row, an import), enable
`tmi_translation.propagate_shared_on_flush: true` — a shared change on *any* variant is then
copied onto every sibling inside the same `flush()`, and two variants flushed with different
new values for one shared property throw `SharedValueConflictException` instead of one
silently winning. Precondition: `--check` reports zero drift and no deliberately per-locale
field still carries the attribute. Application code that needs the copy without the flag calls
`SharedValueSynchronizer::syncFrom($editedRow)` (alias
`tmi_translation.doctrine.shared_value_synchronizer`).

### Check 6.3: Orphan check configuration

**What to look for:** `strict_orphan_check` left implicitly off in non-debug environments.

**How to check:** Inspect `config/packages/tmi_translation.yaml`. Default (`null`) throws only
when `kernel.debug` is on. For production safety prefer explicit logging or strict mode.

**Severity:** INFO.

### Check 6.4: Removal leaves sibling locale variants online (v4.0)

**What to look for:** Code that calls `$em->remove($entity)` directly on a translatable
entity, or a hand-rolled `findBy(['tuuid' => ...])` loop before removing.

**How to check:**
```php
// A plain remove() only ever touches the one row passed to it
$em->remove($product);
$em->flush();
// Any other locale's copy of the same product is still in the database.
```

**If found:**
- **Severity:** ERROR (silent data bug — the entity looks deleted in the locale the user was
  working in, but resolves fine, stale, in every other locale)
- **Fix:** Inject `Tmi\TranslationBundle\Doctrine\TranslatableRemover` and call
  `removeAllLocaleVariants($entity)` (every sibling, `$entity` included) or
  `removeSingleLocaleVariant($entity)` (just this one), then `flush()` once. To make every
  plain `$em->remove()` cascade automatically, set `cascade_remove_locale_variants: true`.
- **llms.md:** See "Removal Semantics (v4)" and the "Deleted Entity Still Served in Another
  Locale" troubleshooting entry.

**Related, both fixed by the v4.0 upgrade itself (nothing to change in application code):**
- **Duplicate variant on every `translate()` call under an active locale filter** — before
  v4.0, the existing-variant lookup queried through the entity's own repository, which the
  filter silently rewrote into a query that could never match.
- **Detached entity duplicated during an import** (`$em->clear()` between batches) — before
  v4.0, a cache hit surviving `clear()` was handed back as reusable and re-inserted as a new
  row by `persist()`.

---

## Diagnostic Summary Template

After running all checks, compile results:

```
TRANSLATION DIAGNOSTIC REPORT
=============================
Entity: [EntityName]
Scanned: [timestamp]

LAYER 1: Entity Configuration
  [X] TranslatableInterface implemented
  [X] TranslatableTrait used
  [X] Tuuid initialized
  [ ] Locale property present

LAYER 2: Attribute Configuration
  [ ] SharedAmongstTranslations + bidirectional: 1 violation
  [X] EmptyOnTranslate + non-nullable: No violations
  [X] Attribute priority conflicts: None

LAYER 3: Handler Chain Mapping
  [X] All fields map to expected handlers
  [X] Embedded objects correctly configured

LAYER 4: Runtime Configuration
  [X] Target locale configured
  [ ] Doctrine filter enabled
  [X] Persistence reminders noted

LAYER 5: Compile-Time Validation (v2.0)
  [X] No attribute conflicts
  [X] No single-column unique constraints
  [X] No removed v1.x config keys
  [X] strict_discovery not tripped (v4.0)

LAYER 6: Tuuid Linkage Integrity (v2.2) & Removal Semantics (v4.0)
  [X] tmi:translation:doctor reports no anomalies (incl. null-tuuid, v4.0)
  [X] Shared values in sync across locale variants
  [X] strict_orphan_check configured
  [X] Deletions go through TranslatableRemover, not a plain $em->remove()

ISSUES FOUND: 2
  - ERROR: SharedAmongstTranslations on bidirectional 'category'
  - WARNING: Doctrine filter not enabled

RECOMMENDED FIX ORDER:
  1. Fix SharedAmongstTranslations issue (blocking translation)
  2. Enable Doctrine filter (prevents query issues)
```
