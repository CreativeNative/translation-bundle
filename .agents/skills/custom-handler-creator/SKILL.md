---
name: custom-handler-creator
description: Guide users through creating custom translation handlers for unsupported field types. Use when user asks to "create custom handler", "handle encrypted fields", "handle computed properties", "handle value objects", "extend handler chain", "custom translation handler", or needs to translate field types not supported by built-in handlers.
---

# Custom Handler Creator Skill

## Activation

When triggered, announce: **"I'll use the custom-handler-creator skill to guide you through creating a custom translation handler."**

## Step 1: Identify Use Case

Ask: **"What field type needs custom handling?"**

Show common examples from [examples.md](references/examples.md):
- Encrypted fields (decrypt before clone, re-encrypt)
- Computed properties (recalculate in target locale)
- Value objects without Doctrine metadata
- File paths/URLs (transform for locale)
- Third-party library objects

## Step 2: Determine Handler Behavior

Ask: **"What should happen during translation for this field type?"**

Gather requirements:
1. **supports()**: What condition identifies this field type? (narrow on `EntityTranslationContext`/`PropertyTranslationContext` first, then the field-specific check)
2. **translate()**, `$context->isShared()` branch: Return same instance or throw exception?
3. **translate()**, `$context->isEmpty()` branch: Return null, empty instance, or special value? Use TypeDefaultResolver to return type-safe defaults for non-nullable types (v2.0 pattern)
4. **translate()**, otherwise: How should the value be cloned/transformed?

## Step 3: Interactive Priority Selection

Show priority matrix from [handler-priority.md](references/handler-priority.md).

Ask: **"Where should your handler fit in the chain?"**

Guide selection:
- Must run BEFORE handlers that would incorrectly match the field
- Must run AFTER handlers that should take precedence
- Recommend insertion points: 75, 65, 55, 45, 35, 25, 15

Provide reasoning: **"Priority X recommended because [specific reason]"**

## Step 4: Generate Handler

Use template from [handler-template.md](references/handler-template.md).

Fill in:
- Namespace and class name
- `supports()` condition logic
- Implementation for both interface methods (`translate()`'s `isShared()`/`isEmpty()`/otherwise branches)
- Dependencies (AttributeHelper, EntityManager, etc.)

## Step 5: Service Registration

Show Symfony service configuration:

```yaml
# config/services.yaml
App\Translation\Handler\{HandlerName}:
    arguments:
        $attributeHelper: '@tmi_translation.utils.attribute_helper'
        # Optional v2.0 dependencies:
        # $typeDefaultResolver: '@Tmi\TranslationBundle\Translation\TypeDefaultResolver'
        # $cache: '@Tmi\TranslationBundle\Translation\Cache\TranslationCacheInterface'
    tags:
        - { name: 'tmi_translation.translation_handler', priority: {priority} }
```

## Step 6: Offer Tests

Ask: **"Want me to add PHPUnit tests for this handler?"**

If yes, use template from [test-template.md](references/test-template.md):
- it_supports_[field_type]
- it_does_not_support_other_field_types
- it_translates_[field_type]_correctly
- it_shares_when_marked_shared
- it_returns_null_when_empty

## Handler Chain Reference

For complete handler chain architecture, priority order, and decision tree, see **llms.md "Handler Chain Decision Tree"** section.

### v2.0 Handler Changes

- **EmbeddedHandler** now receives `TypeDefaultResolver` for type-safe empty defaults
- **EntityTranslator** now receives `TranslationCacheInterface` for cache delegation
- Custom handlers can inject `TypeDefaultResolver` to resolve type-safe defaults for the `translate()` `isEmpty()` branch
- Custom handlers can inject `TranslationCacheInterface` to check/store translations

### v4.0: Two Reusable Building Blocks Instead of Hand-Rolling

If the custom handler needs either of these, delegate — don't reimplement:

- **Translating a nested `TranslatableInterface` value.** Inject
  `Tmi\TranslationBundle\Translation\Handlers\TranslatableEntityHandler` and call
  `->translate($context)` on it (an `EntityTranslationContext`), the way the bundle's own
  `BidirectionalManyToOneHandler`/`BidirectionalOneToOneHandler` do. It already does the
  existing-variant lookup (filter-suspended, via `LocaleVariantFinder`), runs the target's own
  property pipeline, and resets generated ids — a plain `clone $value` skips all three and
  duplicates rows on the next translate.
- **Walking a class's properties, including private ones on a parent class.**
  `\ReflectionClass::getProperties()` never lists a private property declared on a parent —
  use `Tmi\TranslationBundle\Utils\ReflectionHelper::getHierarchyProperties($class)` (a full
  walk, memoized per class) or `ReflectionHelper::getProperty($class, $name)` (one named
  property) instead of a hand-rolled loop. Both also resolve a Doctrine proxy to its real
  class first — reflecting the proxy subclass directly finds neither the parent's private
  properties nor any PHP attribute the real class declares.

## Quick Reference: TranslationHandlerInterface

All handlers implement these 2 methods (v4.0), both on a typed `TranslationContext`
(`EntityTranslationContext` or `PropertyTranslationContext`):

```php
public function supports(TranslationContext $context): bool;
public function translate(TranslationContext $context): mixed;
```

`translate()` branches on `$context->isShared()`/`isEmpty()` — pre-resolved by
`EntityTranslator` from the property's attributes before dispatch — for what used to be the
two extra interface methods, `handleSharedAmongstTranslations()`/`handleEmptyOnTranslate()`.
See [UPGRADING.md § 6](../../../UPGRADING.md#6-translationhandlerinterface-is-two-methods-on-typed-contexts)
for the full migration guide, and [handler-template.md](references/handler-template.md) for a
complete implementation template.
