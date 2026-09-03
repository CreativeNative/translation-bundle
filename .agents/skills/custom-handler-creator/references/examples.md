# Custom Handler Examples

Real-world use cases for custom translation handlers.

Every `supports()` below narrows on `PropertyTranslationContext` first — none of these seven
field types is ever the entity itself, only a property's value — and every `translate()` checks
`$context->isShared()` (and, where relevant, `$context->isEmpty()`) before falling through to the
ordinary case, replacing what used to be separate `handleSharedAmongstTranslations()`/
`handleEmptyOnTranslate()` methods.

## 1. Encrypted Fields

**Field Type**: Strings encrypted at rest (e.g., personal data, API keys)

**Why Custom Handler Needed**: ScalarHandler would copy the encrypted value unchanged, but you may need to decrypt before cloning and re-encrypt with different salt, or handle encryption key per locale.

**Suggested Priority**: 85 (before ScalarHandler at 90)

**Key Implementation Notes**:
```php
public function supports(TranslationContext $context): bool
{
    return $context instanceof PropertyTranslationContext
        && $context->getProperty()?->getAttributes(Encrypted::class) !== [];
}

public function translate(TranslationContext $context): mixed
{
    \assert($context instanceof PropertyTranslationContext);
    $encrypted = $context->getValue();
    $plain = $this->encryptor->decrypt($encrypted);
    return $this->encryptor->encrypt($plain); // New salt/IV
}
```

## 2. Computed Properties

**Field Type**: Values calculated from other fields (e.g., slug, searchIndex, fullName)

**Why Custom Handler Needed**: Computed values must be recalculated for the target locale, not copied. A French slug for "Blue Widget" should be "widget-bleu", not "blue-widget".

**Suggested Priority**: 85 (before ScalarHandler at 90)

**Key Implementation Notes**:
```php
public function supports(TranslationContext $context): bool
{
    return $context instanceof PropertyTranslationContext
        && $context->getProperty()?->getAttributes(Computed::class) !== [];
}

public function translate(TranslationContext $context): mixed
{
    // Return null to force recalculation after translation -- isEmpty() wants the
    // same result, so there is no separate branch for it.
    return null;
}
```

## 3. Value Objects Without Doctrine Metadata

**Field Type**: Custom value objects not mapped as Doctrine embeddables (e.g., Money, Coordinate, PhoneNumber)

**Why Custom Handler Needed**: EmbeddedHandler only processes `#[ORM\Embedded]` objects. Non-Doctrine value objects need explicit handling to ensure proper cloning.

**Suggested Priority**: 75 (after EmbeddedHandler at 80)

**Key Implementation Notes**:
```php
public function supports(TranslationContext $context): bool
{
    if (!$context instanceof PropertyTranslationContext) {
        return false;
    }

    $value = $context->getValue();

    return $value instanceof Money
        || $value instanceof Coordinate
        || $value instanceof PhoneNumber;
}

public function translate(TranslationContext $context): mixed
{
    \assert($context instanceof PropertyTranslationContext);

    if ($context->isShared()) {
        // Share the same immutable instance
        return $context->getValue();
    }

    // Value objects should be cloned
    return clone $context->getValue();
}
```

## 4. Third-Party Library Objects

**Field Type**: Objects from external libraries (e.g., Carbon dates, Ramsey UUIDs, Brick Math numbers)

**Why Custom Handler Needed**: External library objects may need special cloning or serialization. ScalarHandler won't match them, and DoctrineObjectHandler may not handle them correctly.

**Suggested Priority**: 75 (before relationship handlers)

**Key Implementation Notes**:
```php
public function supports(TranslationContext $context): bool
{
    if (!$context instanceof PropertyTranslationContext) {
        return false;
    }

    $value = $context->getValue();

    return $value instanceof \Brick\Math\BigDecimal
        || $value instanceof \Ramsey\Uuid\UuidInterface;
}

public function translate(TranslationContext $context): mixed
{
    \assert($context instanceof PropertyTranslationContext);
    $value = $context->getValue();

    // BigDecimal is immutable, safe to share
    if ($value instanceof \Brick\Math\BigDecimal) {
        return $value;
    }

    // UUID should be preserved (same identity across locales)
    if ($value instanceof \Ramsey\Uuid\UuidInterface) {
        return $value;
    }

    return clone $value;
}
```

## 5. Cached/Lazy-Loaded Fields

**Field Type**: Properties with cached computations or lazy proxies (e.g., translationCache, computedMetrics)

**Why Custom Handler Needed**: Cached values must be invalidated when translating. Copying a cache from source locale would return stale data.

**Suggested Priority**: 85 (before ScalarHandler)

**Key Implementation Notes**:
```php
public function supports(TranslationContext $context): bool
{
    if (!$context instanceof PropertyTranslationContext) {
        return false;
    }

    $name = $context->getProperty()?->getName() ?? '';

    return str_contains($name, 'Cache') || str_contains($name, 'Cached');
}

public function translate(TranslationContext $context): mixed
{
    if ($context->isShared()) {
        // Caches should NOT be shared across locales
        throw new \RuntimeException('Cached properties cannot be shared across translations');
    }

    // Invalidate cache by returning null -- isEmpty() and the ordinary (neither
    // shared nor empty) case both want the same result here.
    return null;
}
```

## 6. File Paths and URLs

**Field Type**: Locale-specific file paths or URLs (e.g., imagePath, documentUrl, pdfPath)

**Why Custom Handler Needed**: File paths often include locale segments that need transformation. "/uploads/en/manual.pdf" should become "/uploads/fr/manual.pdf".

**Suggested Priority**: 85 (before ScalarHandler)

**Key Implementation Notes**:
```php
public function supports(TranslationContext $context): bool
{
    if (!$context instanceof PropertyTranslationContext) {
        return false;
    }

    $name = $context->getProperty()?->getName() ?? '';

    return str_ends_with($name, 'Path')
        || str_ends_with($name, 'Url')
        || str_ends_with($name, 'Uri');
}

public function translate(TranslationContext $context): mixed
{
    \assert($context instanceof PropertyTranslationContext);
    $path = $context->getValue();
    $source = $context->getSourceLocale();
    $target = $context->getTargetLocale();

    // Transform locale segment in path
    return str_replace("/{$source}/", "/{$target}/", $path);
}
```

## 7. Money Value Objects (Currency Handling)

**Field Type**: Money objects with amount and currency (e.g., Price, Cost, Total)

**Why Custom Handler Needed**: Money may need currency conversion for different locales, or at minimum proper cloning to avoid shared state.

**Suggested Priority**: 75 (after EmbeddedHandler)

**Key Implementation Notes**:
```php
public function supports(TranslationContext $context): bool
{
    return $context instanceof PropertyTranslationContext && $context->getValue() instanceof Money;
}

public function translate(TranslationContext $context): mixed
{
    \assert($context instanceof PropertyTranslationContext);
    $money = $context->getValue();

    if ($context->isShared()) {
        // Money is typically shared (same price across locales)
        return $money;
    }

    // Clone money object (immutable, but good practice)
    return new Money($money->getAmount(), $money->getCurrency());
}
```

## Summary Table

| Use Case | Priority | supports() Check | translate() Behavior |
|----------|----------|------------------|----------------------|
| Encrypted fields | 85 | `#[Encrypted]` attribute | Decrypt + re-encrypt |
| Computed properties | 85 | `#[Computed]` attribute | Return null |
| Value objects | 75 | `instanceof` check | `isShared()`: share instance; otherwise: clone |
| Third-party objects | 75 | `instanceof` check | Share (immutable) or clone, per type |
| Cached fields | 85 | Name contains "Cache" | `isShared()`: throw; otherwise: return null |
| File paths/URLs | 85 | Name ends with "Path"/"Url" | Transform locale segment |
| Money objects | 75 | `instanceof Money` | `isShared()`: share instance; otherwise: clone |
