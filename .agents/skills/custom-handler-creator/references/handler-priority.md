# Handler Priority Decision Matrix

## Standard Handler Priorities

| Priority | Handler | Matches |
|----------|---------|---------|
| 100 | PrimaryKeyHandler | `#[ORM\Id]` properties |
| 90 | ScalarHandler | Scalars and DateTime |
| 80 | EmbeddedHandler | `#[ORM\Embedded]` objects |
| 70 | BidirectionalManyToOneHandler | ManyToOne with `inversedBy` |
| 60 | BidirectionalOneToManyHandler | OneToMany with `mappedBy` |
| 50 | BidirectionalOneToOneHandler | OneToOne with `mappedBy`/`inversedBy` |
| 40 | BidirectionalManyToManyHandler | ManyToMany with `mappedBy`/`inversedBy` |
| 30 | UnidirectionalManyToManyHandler | ManyToMany without `mappedBy`/`inversedBy` |
| 20 | TranslatableEntityHandler | `TranslatableInterface` entities |
| 10 | DoctrineObjectHandler | Any Doctrine-managed object (fallback) |

## Custom Handler Insertion Points

| Priority | Position | Use When |
|----------|----------|----------|
| **95** | After PrimaryKeyHandler | Custom ID handling (composite keys, ULIDs) |
| **85** | After ScalarHandler | Special scalar types (encrypted, computed) |
| **75** | After EmbeddedHandler | Custom embeddable-like objects |
| **65** | After ManyToOne | Custom ManyToOne variants |
| **55** | After OneToMany | Custom collection handling |
| **45** | After OneToOne | Custom singular relation handling |
| **35** | After ManyToMany | Custom collection relation handling |
| **25** | After Unidirectional | Custom relation handling |
| **15** | After TranslatableEntity | Custom entity handling |
| **5** | Before DoctrineObjectHandler | Catch-all before fallback |

## Priority Selection Guide

### Step 1: Identify What Your Handler Catches

What field type does your handler process?
- Specific value object class? Use priority 85 (after ScalarHandler)
- Custom embeddable? Use priority 75 (after EmbeddedHandler)
- Custom relation type? Use 65-35 range based on relation type
- Entity subtype? Use priority 15 (after TranslatableEntityHandler)

### Step 2: Check for Conflicts

Your handler MUST run BEFORE handlers that would incorrectly match:
- If DoctrineObjectHandler (10) would catch it, use priority > 10
- If ScalarHandler (90) would catch it, use priority > 90
- If a relationship handler would catch it, use higher priority

### Step 3: Recommend with Reasoning

Template: **"Priority [X] recommended because [specific reason]"**

Examples:
- "Priority 85 recommended because encrypted strings should be processed before ScalarHandler copies them unchanged"
- "Priority 75 recommended because Money objects need special handling before DoctrineObjectHandler tries to clone them"
- "Priority 15 recommended because this entity subtype needs processing after TranslatableEntityHandler checks for interface"

## Common Custom Handler Scenarios

### Encrypted Fields (Priority 85)

**Reasoning**: Must run AFTER PrimaryKeyHandler (100) to skip IDs, but BEFORE ScalarHandler (90) which would copy encrypted values unchanged.

```php
public function supports(TranslationArgs $args): bool
{
    // Match properties with #[Encrypted] attribute
    return $args->getProperty()?->getAttributes(Encrypted::class) !== [];
}
```

### Value Objects (Priority 75)

**Reasoning**: Must run AFTER EmbeddedHandler (80) to not conflict with Doctrine embeddables, but BEFORE relationship handlers.

```php
public function supports(TranslationArgs $args): bool
{
    return $args->getDataToBeTranslated() instanceof Money;
}
```

### File/URL Paths (Priority 85)

**Reasoning**: String fields that need transformation, must run BEFORE ScalarHandler (90).

```php
public function supports(TranslationArgs $args): bool
{
    $property = $args->getProperty();
    return $property?->getName() === 'filePath'
        || str_ends_with($property?->getName() ?? '', 'Url');
}
```

### Computed Properties (Priority 85)

**Reasoning**: Fields that must be recalculated, not copied. Must run BEFORE ScalarHandler (90).

```php
public function supports(TranslationArgs $args): bool
{
    return $args->getProperty()?->getAttributes(Computed::class) !== [];
}
```

## Priority Conflicts to Avoid

| Conflict | Problem | Solution |
|----------|---------|----------|
| Priority = 100 | Conflicts with PrimaryKeyHandler | Use 95 or lower |
| Priority = 90 | Conflicts with ScalarHandler | Use 85 or higher/lower |
| Priority = 80 | Conflicts with EmbeddedHandler | Use 75 or 85 |
| Priority = existing | Conflicts with same-priority handler | Adjust by +/- 5 |

## Decision Flowchart

```
Is your field a scalar type (string, int, bool, DateTime)?
    YES -> Priority 85-95 (before ScalarHandler)
    NO  -> Continue

Is your field an embedded/value object?
    YES -> Priority 75 (after EmbeddedHandler)
    NO  -> Continue

Is your field a relation?
    YES -> Priority 35-65 (in relation handler range)
    NO  -> Continue

Is your field an entity?
    YES -> Priority 15 (after TranslatableEntityHandler)
    NO  -> Priority 5 (before DoctrineObjectHandler fallback)
```
