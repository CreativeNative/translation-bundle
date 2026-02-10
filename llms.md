# CreativeNative Translation Bundle – Developer & AI Guide  
*(for Symfony 7.x, Doctrine ORM 3.5, PHP 8.4)*

## Overview
The bundle provides a framework to make Doctrine entities translatable into multiple locales, with control over which fields are **language‑specific** and which are **shared across translations**. It operates by cloning or sharing entities/properties, using handlers and attributes to guide behaviour.

Key components:
- `EntityTranslator` — central translation orchestrator.
- `Handlers` — classes that manage translation of entities, embeddables, collections etc.
- `PropertyAccessor` — used to read/write object properties generically.
- `TranslationArgs` — container holding the context of a translation operation.
- `AttributeHelper` — utility to inspect attributes/annotations like `#[SharedAmongstTranslations]` or `#[EmptyOnTranslate]`.

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
- Fields or embeddables that are identical across all translations of the same logical entity.
- All translations reference the same object instance.
- If the attribute is on the embeddable, the whole object is shared.
- If the attribute is on properties within an embeddable, only those properties are shared; others may still be cloned.

#### 3. Empty-on-Translate Fields (#[EmptyOnTranslate])
- Fields that must be reset when creating a new translation.
- For nullable fields, values are set to null.
- For non-nullable scalar fields, type-safe defaults are used: string='', int=0, float=0.0, bool=false (via TypeDefaultResolver).
- Non-nullable object types throw LogicException with guidance to make them nullable or use #[SharedAmongstTranslations].
- Embedded objects are replaced with a new, empty instance (or null for nullable embeddables).
- Shared fields override this rule: if a field has both #[SharedAmongstTranslations] and #[EmptyOnTranslate], the shared behavior takes precedence and the value is not cleared.

#### 4. Priority of Rules
1. #[SharedAmongstTranslations] → always overrides others.
2. #[EmptyOnTranslate] → only applies if not shared.
3. Otherwise → default translation cloning behavior.
4. If `copy_source: false` (v2.0 default) and field has #[EmptyOnTranslate]: type-safe defaults used instead of null for non-nullable types.

---

### Workflow
1. A source entity (locale A) is passed to EntityTranslator to produce a target translation entity (locale B).
2. Handlers inspect each property of the source:
  - If the property is marked `#[SharedAmongstTranslations]`, the same value is reused/propagated across siblings.
  - If the property is marked `#[EmptyOnTranslate]`, the target value will be set to null (nullable types) or type-safe defaults (non-nullable scalars: string='', int=0, float=0.0, bool=false), or a new empty instance (embeddables), regardless of the source.
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

### Translation Handlers

All handlers implement [`TranslationHandlerInterface`](src/Translation/Handlers/TranslationHandlerInterface.php), which defines four core methods:
- `supports(TranslationArgs $args): bool` — Determines if the handler can process the data.
- `handleSharedAmongstTranslations(TranslationArgs $args): mixed` — Handles data marked as shared across translations.
- `handleEmptyOnTranslate(TranslationArgs $args): mixed` — Handles empty translation cases.
- `translate(TranslationArgs $args): mixed` — Performs the actual translation logic.

---

#### [PrimaryKeyHandler](src/Translation/Handlers/PrimaryKeyHandler.php)
- **Purpose:** Handles **primary key properties** (IDs).
- **Priority:** 100
- **Dependencies:** `AttributeHelper`.
- **Methods:**
    - `supports()` — Returns true if property is a primary key.
    - `translate()`, `handleSharedAmongstTranslations()`, `handleEmptyOnTranslate()` — Always return `null`.
- **Notes:** Ensures entity identity is immutable, excluded from translation logic.

---

#### [ScalarHandler](src/Translation/Handlers/ScalarHandler.php)
- **Purpose:** Handles **scalar values** and `DateTime`.
- **Priority:** 90
- **Dependencies:** None.
- **Methods:**
  - `supports()` — Returns true if value is scalar or `DateTime`.
  - `translate()` — Returns original value.
  - `handleSharedAmongstTranslations()` — Returns original value.
  - `handleEmptyOnTranslate()` — Returns null for nullable fields, or type-safe defaults for non-nullable fields (string='', int=0, float=0.0, bool=false) via TypeDefaultResolver.
- **Notes:** Leaf handler in the translation pipeline; no delegation required.

---

#### [EmbeddedHandler](src/Translation/Handlers/EmbeddedHandler.php)
- **Purpose:** Handles **Doctrine embeddable objects** (`@Embeddable`).
- **Priority:** 80
- **Dependencies:** `AttributeHelper`.
- **Methods:**
  - `supports()` — Returns true if property is an embeddable.
  - `translate()` — Returns a cloned embeddable.
  - `handleSharedAmongstTranslations()` — Returns original object unchanged.
  - `handleEmptyOnTranslate()` — Returns null for nullable embeddables, or a new empty instance with type-safe property defaults for non-nullable embedded objects.
- **Notes:** Works on value objects embedded in entities, preserves immutability.

---

#### [BidirectionalManyToOneHandler](src/Translation/Handlers/BidirectionalManyToOneHandler.php)
- **Purpose:** Handles translation of **bidirectional ManyToOne associations**.
- **Priority:** 70
- **Dependencies:** `AttributeHelper`, `EntityManagerInterface`, `PropertyAccessorInterface`, `EntityTranslatorInterface`.
- **Methods:**
  - `supports()` — Returns true for `TranslatableInterface` entities with a ManyToOne association having `inversedBy`.
  - `translate()` — Clones parent entity, translates related entity, sets translated entity on clone. Safe fallback to original if translation fails.
  - `handleSharedAmongstTranslations()` — Throws exception if shared; unsupported.
  - `handleEmptyOnTranslate()` — Returns `null`.
- **Notes:** Ensures original objects are never mutated; integrates with `EntityTranslator` for nested translations.

---

#### [BidirectionalOneToManyHandler](src/Translation/Handlers/BidirectionalOneToManyHandler.php)
- **Purpose:** Handles translation of **bidirectional OneToMany associations**.
- **Priority:** 60
- **Dependencies:** `AttributeHelper`, `EntityTranslatorInterface`, `EntityManagerInterface`.
- **Methods:**
    - `supports()` — Returns true for `TranslatableInterface` entities with OneToMany having `mappedBy`.
    - `translate()` — Iterates over child collection, translates each child recursively, sets inverse property to maintain bidirectional consistency, returns translated `ArrayCollection`.
    - `handleSharedAmongstTranslations()` — Throws exception if shared; unsupported.
    - `handleEmptyOnTranslate()` — Returns an empty `ArrayCollection`.
- **Notes:** Maintains bidirectional integrity, ensures clones are used, integrates with `EntityTranslator`.

---

#### [BidirectionalOneToOneHandler](src/Translation/Handlers/BidirectionalOneToOneHandler.php)
- **Purpose:** Handles translation of **bidirectional OneToOne associations**.
- **Priority:** 50
- **Dependencies:** `EntityManagerInterface`, `PropertyAccessor`, `AttributeHelper`.
- **Methods:**
  - `supports()` — Returns true for `TranslatableInterface` entities with OneToOne having `mappedBy` or `inversedBy`.
  - `translate()` — Clones entity, sets target locale, updates inverse property to link to translated parent.
  - `handleSharedAmongstTranslations()` — Throws exception if shared; unsupported.
  - `handleEmptyOnTranslate()` — Returns `null`.
- **Notes:** Ensures bidirectional integrity between parent and child, clones original entities, works with `EntityTranslator`.

---

#### [BidirectionalManyToManyHandler](src/Translation/Handlers/BidirectionalManyToManyHandler.php)
- **Purpose:** Translates **bidirectional ManyToMany Doctrine associations** in `TranslatableInterface` entities.
- **Priority:** 40
- **Dependencies:** `AttributeHelper`, `EntityManagerInterface`, `EntityTranslatorInterface`.
- **Methods:**
    - `supports()` — Returns true for `TranslatableInterface` entities with a ManyToMany association having `mappedBy` or `inversedBy`.
    - `translate()` — Clones and translates the collection of related entities. Ensures inverse collections (`mappedBy`) are updated for translated owners. Avoids duplicate entries.
    - `handleSharedAmongstTranslations()` — Throws exception if `#[SharedAmongstTranslations]` is present; otherwise delegates to `translate()`.
    - `handleEmptyOnTranslate()` — Returns an empty `ArrayCollection`.
- **Notes:** Maintains bidirectional integrity, ensures cloned translations do not affect originals, integrates with `EntityTranslator`.

---

#### [UnidirectionalManyToManyHandler](src/Translation/Handlers/UnidirectionalManyToManyHandler.php)
- **Purpose:** Handles translation of **unidirectional ManyToMany associations** in `TranslatableInterface` entities.
- **Priority:** 30
- **Dependencies:** `AttributeHelper`, `EntityTranslatorInterface`, `EntityManagerInterface`.
- **Methods:**
  - `supports()` — Returns true if the entity implements `TranslatableInterface` and the property is a ManyToMany association **without** `mappedBy` or `inversedBy` (unidirectional).
  - `translate()` — Translates each item in the collection:
    - Copies the original items to avoid modifying the source collection.
    - Clears the target collection.
    - Translates each item for the target locale using `EntityTranslator`.
    - Adds the translated item to the target collection, preventing duplicates.
  - `handleSharedAmongstTranslations()` — Throws a `RuntimeException` if `#[SharedAmongstTranslations]` is applied (unsupported). Otherwise, delegates to `translate()`.
  - `handleEmptyOnTranslate()` — Returns a new empty `ArrayCollection`.
- **Notes:**
  - Ensures safe translation of unidirectional ManyToMany relations without affecting the original collection.
  - Maintains Doctrine collection integrity while cloning translated items.
  - Prevents shared translation attributes from being misused on unidirectional relations.

---

#### [TranslatableEntityHandler](src/Translation/Handlers/TranslatableEntityHandler.php)
- **Purpose:** Handles **entities implementing `TranslatableInterface`**.
- **Priority:** 20
- **Dependencies:** `EntityManagerInterface`, `DoctrineObjectHandler`.
- **Methods:**
    - `supports()` — Returns true if entity implements `TranslatableInterface`.
    - `translate()` — Checks database for existing translation by `tuuid` and target locale; clones and translates via `DoctrineObjectHandler` if not found.
    - `handleSharedAmongstTranslations()` — Delegates to `translate()`.
    - `handleEmptyOnTranslate()` — Returns `null`.
- **Notes:** Integrates entity-level and property-level translation, ensures unique translations per locale.

---

#### [DoctrineObjectHandler](src/Translation/Handlers/DoctrineObjectHandler.php)
- **Purpose:** Handles **basic Doctrine-managed objects**. Entry point for translating full entities.
- **Priority:** 10
- **Dependencies:** `EntityManagerInterface`, `EntityTranslatorInterface`, optional `PropertyAccessorInterface`.
- **Methods:**
    - `supports()` — Returns true if object/class is Doctrine-managed; handles proxies.
    - `translate()` — Clones entity, calls `translateProperties()` for recursive translation.
    - `translateProperties()` — Iterates properties, delegates to `EntityTranslator`, sets translated values via accessor or reflection.
    - `handleSharedAmongstTranslations()` — Returns original entity unchanged.
    - `handleEmptyOnTranslate()` — Returns `null`.
- **Notes:** Core handler for property-level translation, ensures original entities are never mutated.

---

#### Notes for Handlers
- Handlers can be extended or replaced to implement custom translation logic.
- `AttributeHelper` is used throughout to detect Doctrine mapping types (`OneToMany`, `ManyToOne`, `Embedded`, `Id`, `OneToOne`, etc.).
- `TranslationArgs` encapsulates:
    - `dataToBeTranslated`
    - `sourceLocale` / `targetLocale`
    - `translatedParent` (for bidirectional associations)
    - `property` (ReflectionProperty being translated)
- `EntityTranslatorInterface` orchestrates recursive property translation, delegating to appropriate handlers.

---

## Translation Cache Service

### [TranslationCacheInterface](src/Translation/Cache/TranslationCacheInterface.php)

Abstraction for translation caching and circular-reference detection. Replaces the internal `$translationCache` and `$inProgress` arrays from v1.x EntityTranslator.

**Interface methods:**
- `has(string $tuuid, string $locale): bool` -- Check if translation is cached
- `get(string $tuuid, string $locale): TranslatableInterface|null` -- Get cached translation
- `set(string $tuuid, string $locale, TranslatableInterface $entity): void` -- Store translation
- `markInProgress(string $tuuid, string $locale): void` -- Mark translation as in-progress (cycle detection)
- `unmarkInProgress(string $tuuid, string $locale): void` -- Remove in-progress mark
- `isInProgress(string $tuuid, string $locale): bool` -- Check if translation is in-progress

### Default Implementation: InMemoryTranslationCache

Stores translations in PHP arrays, scoped to the current request. Registered as the default implementation.

### PSR-6 Implementation: Psr6TranslationCache

Ships with the bundle for cross-request caching. Uses Symfony's `cache.app` pool. Keys use dot separators with underscore-replaced UUIDs for PSR-6 compliance.

### Custom Implementation

To use a custom cache (e.g., Redis):

```php
use Tmi\TranslationBundle\Translation\Cache\TranslationCacheInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;

class RedisTranslationCache implements TranslationCacheInterface
{
    public function __construct(private RedisClient $redis) {}

    public function has(string $tuuid, string $locale): bool
    {
        return $this->redis->exists("translation.{$tuuid}.{$locale}");
    }

    // ... implement remaining 5 methods
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
  - Create a nested `TranslationArgs` for that property value.  
  - Delegate translation of the property value to the translator.  
  - Set the translated value back on the cloned object.

### TranslationArgs  
- Container class `TranslationArgs` holds:  
  - `dataToBeTranslated` — the object or value being translated.  
  - `sourceLocale`, `targetLocale`.  
  - `translatedParent` (optional) — the parent object in nested translation contexts.  
  - `property` (optional) — the `ReflectionProperty` being processed (for nested translation).  
- Provides context so handlers and translator know how to process nested values (property of object, collection element, etc).

### AttributeHelper  
- Utility service to introspect attributes (PHP 8 attributes like `#[SharedAmongstTranslations]`, `#[EmptyOnTranslate]`, etc).  
- Example usage: in `EmbeddedHandler::supports()`, check if property is embeddable:  
  ```php
  $this->attributeHelper->isEmbedded($args->getProperty())
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
- **TranslatableTrait** provides three essential properties automatically:
  - `$tuuid` — Groups all language variants together (same Tuuid = same product in different languages)
  - `$locale` — Identifies which language this specific entity represents
  - `$translations` — Collection linking to sibling translations

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
When EntityTranslator processes these properties, it checks for `#[SharedAmongstTranslations]` via AttributeHelper. If present, instead of calling `translate()`, it calls `handleSharedAmongstTranslations()`, which returns the original value unchanged. This ensures all language variants share the same price and category reference.

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

**Cause:** Translation filter not enabled in Doctrine configuration

**Fix:** Enable the filter in your Doctrine configuration or manually via EntityManager:

```yaml
# config/packages/doctrine.yaml
doctrine:
    orm:
        filters:
            translation_locale:
                class: Tmi\TranslationBundle\Doctrine\Filter\TranslationFilter
                enabled: true
```

Or enable at runtime:

```php
$entityManager->getFilters()->enable('translation_locale');
```

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

### Translations Not Persisted

**Symptom:** Translation appears to work but translated entity is not in the database

**Diagnosis:** Check if `persist()` and `flush()` were called on the translated entity. The translator creates a NEW entity, not an update to existing.

**Resolution:** Always persist and flush the translated entity returned by the translator:

```php
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

### Embedded Object Shared Unexpectedly

**Symptom:** Changing an embedded value on one locale changes all locales

**Cause:** `#[SharedAmongstTranslations]` on embedded property shares the instance across all translations

**Resolution:** Remove the attribute if per-locale values are needed. Keep it if sharing is intentional (e.g., postal address same across all language variants):

```php
// Shared: All locales reference same Address instance
#[SharedAmongstTranslations]
#[ORM\Embedded(class: Address::class)]
private Address $address;

// Per-locale: Each translation gets cloned Address
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

// OR: Use SharedAmongstTranslations to reuse the same collection
#[SharedAmongstTranslations]
#[ORM\OneToMany(targetEntity: Photo::class, mappedBy: 'product')]
private Collection $photos;
```

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

---

## Tips & Best Practices  

- Always define a clear **shared vs translate** decision at entity design time. Changing this later is error‑prone.  
- Use the `AttributeHelper` to inspect attributes rather than manually checking metadata — this helps keep future changes consistent.  
- For performance: if you have many shared fields across thousands of locale variants, consider updating shared values only once (via batch update) rather than cloning each time.  
- Document inside your code which fields are shared vs per‑locale — this helps for maintenance and for AI assistants to provide accurate answers.  
- When using embeddables, marking the embedded property as `#[SharedAmongstTranslations]` is sufficient; you do *not* need to mark each column inside the embeddable.  
- If your bundle does *not yet* automatically propagate updates to shared fields across existing locale siblings, consider writing a Subscriber or service for that. (Because the handler logic supports the attribute, but may not handle cross‑entity propagation.)

---

## “How can I achieve X?” Quick Answers  

- **"How do I share the address across locales?"**
  Mark the embedded property with `#[SharedAmongstTranslations]`, ensure all locale entities share the same Tuuid, and use the translator to clone/translate the rest.

- **“How do I translate only title and description but keep category and tags shared?”**  
  On the entity: mark category and tags with `#[SharedAmongstTranslations]`, leave title & description un‑marked. On translation, only title/description will be locale‑specific.

- **"How do I propagate a change in a shared field (e.g., latitude) to all language variants after creation?"**
  Ideally your bundle provides a service to iterate sibling entities (same Tuuid) and update the shared field. If not, implement a Doctrine Subscriber on `PostUpdate`, detect changes to a `#[SharedAmongstTranslations]` property, load siblings and update them.

- **“How can I handle OneToMany relations differently for shared vs per‑locale?”**  
  If the relation should be shared: mark property `#[SharedAmongstTranslations]`. If per‑locale: leave un‑marked. Use or extend handler logic if custom merging is needed.

---

## Summary
This bundle gives you a robust way to manage multilingual domain models in Symfony/Doctrine with precise control over shared vs locale‑specific fields. By leveraging the EntityTranslator, the set of handlers, the PropertyAccessor, TranslationArgs, and AttributeHelper, you create a consistent and maintainable translation architecture.

Proper annotation (`#[SharedAmongstTranslations]`), common Tuuids, and correct use of the translator service are the keys to making this work smoothly.

---

## Revision History
- v1.0: Initial methodology documented.
- v2.0: Added cache service, type-safe defaults, fallback control, compile-time validation documentation.
- Next: Add examples for custom handler registration, event subscriber propagation, batch aside.

