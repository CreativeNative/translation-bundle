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
1. **supports()**: What condition identifies this field type?
2. **translate()**: How should the value be cloned/transformed?
3. **handleSharedAmongstTranslations()**: Return same instance or throw exception?
4. **handleEmptyOnTranslate()**: Return null, empty instance, or special value? Use TypeDefaultResolver to return type-safe defaults for non-nullable types (v2.0 pattern)

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
- Implementation for all 4 interface methods
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
        - { name: 'tmi_translation.handler', priority: {priority} }
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
- Custom handlers can inject `TypeDefaultResolver` to resolve type-safe defaults for `handleEmptyOnTranslate()`
- Custom handlers can inject `TranslationCacheInterface` to check/store translations

## Quick Reference: TranslationHandlerInterface

All handlers implement these 4 methods:

```php
public function supports(TranslationArgs $args): bool;
public function handleSharedAmongstTranslations(TranslationArgs $args): mixed;
public function handleEmptyOnTranslate(TranslationArgs $args): mixed;
public function translate(TranslationArgs $args): mixed;
```

See [handler-template.md](references/handler-template.md) for complete implementation template.
