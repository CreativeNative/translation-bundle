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
- Scalar values are set to null (no predefined defaults see [GitHub Issue #2](https://github.com/CreativeNative/translation-bundle/issues/2)).
- Embedded objects are replaced with a new, empty instance.
- Shared fields override this rule: if a field has both #[SharedAmongstTranslations] and #[EmptyOnTranslate], the shared behavior takes precedence and the value is not cleared.

#### 4. Priority of Rules
1. #[SharedAmongstTranslations] → always overrides others.
2. #[EmptyOnTranslate] → only applies if not shared.
3. Otherwise → default translation cloning behavior.

---

### Workflow
1. A source entity (locale A) is passed to EntityTranslator to produce a target translation entity (locale B).
2. Handlers inspect each property of the source:
  - If the property is marked `#[SharedAmongstTranslations]`, the same value is reused/propagated across siblings.
  - If the property is marked `#[EmptyOnTranslate]`, the target value will be set to null or a new empty instance, regardless of the source.
  - Otherwise, a clone or new value may be created for the target locale, depending on other attributes and the property type.
3. PropertyAccessor is used to read source values and write to the target.
4. The result is a consistent set of entities: one per locale, sharing or translating fields as configured.

---

## Key Components  

### [EntityTranslator](src/Translation/EntityTranslator.php)
- Class/interface: [`EntityTranslatorInterface`](src/Translation/EntityTranslatorInterface.php) (provided by the bundle).  
- Responsible for initiating translation: taking a source object + sourceLocale + targetLocale, and returning the translated object.  
- Internally delegates to appropriate Handler(s) depending on object type (entity vs embeddable vs collection).  
- Ensures metadata (locale property, translation‑group id) is set correctly.

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
  - `handleEmptyOnTranslate()` — Returns `null`.
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
  - `handleEmptyOnTranslate()` — Returns `null`.
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
2. For any entity you wish to translate:  
   - Add a locale field (e.g., `$locale`, or use your own strategy).  
   - Add a translation‑group field (e.g., `$tuuid`) so you can link all variants.  
   - Implement or tag the entity as “translatable” (depending on bundle setup).  
3. On properties that should be shared across locale versions, add the `#[SharedAmongstTranslations]` attribute.  
4. In your code when creating a translation:  
   ```php
   $translated = $entityTranslator->translate($sourceEntity, $targetLocale);
   $entityManager->persist($translated);
   $entityManager->flush();
   ```  
   This will clone and handle all fields using handlers.  
5. For relations and embeddables, verify if they should be shared or translatable — use attributes accordingly.  
6. If you require custom behaviour (e.g., clearing a field on translation, propagating changes across siblings when shared fields are updated), you may:  
   - Configure custom handler by implementing `TranslationHandlerInterface`.  
   - Write a Doctrine Event Subscriber to post‑update shared fields across sibling entities (if your bundle does *not* yet automatically propagate).  
7. Make sure your repository/finder logic considers translation‑group and locale filters so you fetch the correct variant for current locale or fallback.

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

- **“How do I share the address across locales?”**  
  Mark the embedded property with `#[SharedAmongstTranslations]`, ensure all locale entities share the same translation‑group ID, and use the translator to clone/translate the rest.

- **“How do I translate only title and description but keep category and tags shared?”**  
  On the entity: mark category and tags with `#[SharedAmongstTranslations]`, leave title & description un‑marked. On translation, only title/description will be locale‑specific.

- **“How do I propagate a change in a shared field (e.g., latitude) to all language variants after creation?”**  
  Ideally your bundle provides a service to iterate sibling entities (same translation‑group ID) and update the shared field. If not, implement a Doctrine Subscriber on `PostUpdate`, detect changes to a `#[SharedAmongstTranslations]` property, load siblings and update them.

- **“How can I handle OneToMany relations differently for shared vs per‑locale?”**  
  If the relation should be shared: mark property `#[SharedAmongstTranslations]`. If per‑locale: leave un‑marked. Use or extend handler logic if custom merging is needed.

---

## Summary  
This bundle gives you a robust way to manage multilingual domain models in Symfony/Doctrine with precise control over shared vs locale‑specific fields. By leveraging the EntityTranslator, the set of Handlers, the PropertyAccessor, TranslationArgs, and AttributeHelper, you create a consistent and maintainable translation architecture.

Proper annotation (`#[SharedAmongstTranslations]`), common translation‑group IDs, and correct use of the translator service are the keys to making this work smoothly.

---

## Revision History  
- v1.0: Initial methodology documented.  
- Next: Add examples for custom handler registration, event subscriber propagation, batch aside.

