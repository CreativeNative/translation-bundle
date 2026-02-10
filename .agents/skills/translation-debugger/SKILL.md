---
name: translation-debugger
description: Diagnose and fix translation configuration issues when users report problems like "translation not working", "translation error", "why isn't translation working?", "translation is broken", "translation issue", "wrong translation", "translation failed", "can't translate", or any variation of "translation" + problem/issue/wrong/broken/not working/error. Use this skill to systematically identify configuration problems.
---

# Translation Debugger Skill

## Activation

When triggered, announce: **"I'll use the translation-debugger skill to diagnose your translation issues."**

**DO NOT ask open-ended questions.** Run diagnostics immediately.

If the entity is not obvious, ask: **"Which entity is having translation problems?"**

## Diagnostic Workflow

### Step 1: Identify Target Entity

Read the entity file mentioned by the user. If not specified, ask for the entity name only.

### Step 2: Run Diagnostic Checks

Execute all checks from **references/diagnostics.md** in order:

1. **Entity Configuration Layer** - BLOCKING issues first
2. **Attribute Configuration Layer** - ERROR/WARNING issues
3. **Handler Chain Mapping Layer** - Handler compatibility
4. **Runtime Configuration Layer** - Environment setup
5. **Compile-Time Validation Layer** - v2.0 attribute conflicts and unique constraints

### Step 3: Present Results

Group findings by severity in dependency order:

```
DIAGNOSTIC RESULTS
==================

[X] Entity implements TranslatableInterface
[X] Entity uses TranslatableTrait
[X] $tuuid property initialized

BLOCKING ISSUES (must fix first)
--------------------------------
None found

ERRORS (will cause failures)
----------------------------
1. SharedAmongstTranslations on bidirectional relation 'category'
   -> RuntimeException when translating
   -> Affects: Translation will fail completely
   [blocks #2, #3 below]

   Want me to fix this?

WARNINGS (may cause unexpected behavior)
----------------------------------------
2. EmptyOnTranslate on non-nullable field 'slug'
   -> LogicException: cannot use EmptyOnTranslate because it is not nullable

   Want me to fix this?

PASSED CHECKS
-------------
[X] Locale 'fr' in framework.enabled_locales
[X] Doctrine filter configured
[X] No compile-time attribute conflicts
[X] No single-column unique constraints
```

### Step 4: Offer Fixes

After presenting each issue:
- Ask: **"Want me to fix this?"**
- Wait for confirmation before applying fix
- Show diff-style preview before applying
- Reference **llms.md -> Troubleshooting** for detailed fix procedures

## Check Priority Order

Issues are presented in dependency order - fixing earlier issues may resolve later ones:

1. **BLOCKING** - Entity won't be recognized as translatable
2. **ERROR** - Translation will fail with exceptions
3. **WARNING** - Unexpected behavior, silent failures
4. **INFO** - Best practices, optimization suggestions

## Common Issue Patterns

### "Translation not saving"
Run checks: TranslatableInterface, TranslatableTrait, persist/flush sequence

### "Wrong locale returned"
Run checks: Doctrine filter enabled, locale configuration, query filtering

### "RuntimeException during translation"
Run checks: SharedAmongstTranslations on bidirectional relations

### "Field value unexpected after translation"
Run checks: Handler chain mapping, attribute conflicts (Shared vs Empty)

### "Compile-time validation error"
Run checks: AttributeValidationPass errors, class/property attribute conflicts, locale field

### "Unique constraint validation error"
Run checks: Single-column unique: true fields, composite unique constraints

### "LogicException about removed config"
Run checks: v1.x config keys (tmi_translation.locales, tmi_translation.logging), migration guidance

## Quick Commands

For users who know what to check:

- **"Check entity config"** - Run Entity Configuration Layer only
- **"Check attributes"** - Run Attribute Configuration Layer only
- **"Check handlers"** - Run Handler Chain Mapping Layer only
- **"Check runtime"** - Run Runtime Configuration Layer only
- **"Check validation"** - Run Compile-Time Validation Layer only
- **"Full diagnostic"** - Run all layers (default)

## References

- **references/diagnostics.md** - Detailed check procedures for each layer
- **llms.md -> Troubleshooting** - Fix procedures for each issue type
- **llms.md -> Handler Chain Decision Tree** - Handler priority and routing
- **UPGRADING.md** - Migration guide for v1.x to v2.0 breaking changes
