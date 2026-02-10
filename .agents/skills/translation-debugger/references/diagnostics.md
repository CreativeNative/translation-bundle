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
- **Error:** Missing $tuuid, $locale, $translations properties
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

### Check 2.1: SharedAmongstTranslations on Bidirectional Relations

**What to look for:** `#[SharedAmongstTranslations]` combined with `inversedBy` or `mappedBy`

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
```

**If found:**
- **Severity:** ERROR
- **Error:** `RuntimeException` thrown by bidirectional handlers
- **Symptom:** Translation fails completely when processing this field
- **Fix options:**
  1. Remove `#[SharedAmongstTranslations]` (each locale gets own relation)
  2. Remove `inversedBy`/`mappedBy` to make unidirectional
- **llms.md:** See "SharedAmongstTranslations on Bidirectional Relation" troubleshooting entry

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
// VALID but EmptyOnTranslate is ignored
#[SharedAmongstTranslations]
#[EmptyOnTranslate]
#[ORM\Column]
private ?string $field = null;
```

**If found:**
- **Severity:** WARNING
- **Error:** None - SharedAmongstTranslations takes precedence
- **Symptom:** Field is shared, not emptied (might be unexpected)
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
            translation_locale:
                class: Tmi\TranslationBundle\Doctrine\Filter\TranslationFilter
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
$translated = $entityTranslator->translate($source, 'fr');
$entityManager->persist($translated);  // Required!
$entityManager->flush();
```

**If missing:**
- **Severity:** INFO
- **Error:** No error during translation
- **Symptom:** Translation not in database
- **Reminder:** Translator creates NEW entity, must be persisted
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
- **Fix:** Ensure child entities are translatable if needed, or use SharedAmongstTranslations
- **llms.md:** See "Collection Translation Creates Duplicates" troubleshooting entry

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

ISSUES FOUND: 2
  - ERROR: SharedAmongstTranslations on bidirectional 'category'
  - WARNING: Doctrine filter not enabled

RECOMMENDED FIX ORDER:
  1. Fix SharedAmongstTranslations issue (blocking translation)
  2. Enable Doctrine filter (prevents query issues)
```
