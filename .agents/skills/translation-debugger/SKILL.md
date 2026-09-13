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
5. **Compile-Time Validation Layer** - attribute conflicts, unique constraints, `strict_discovery`
6. **Tuuid Linkage Integrity Layer** - broken linkage (run `tmi:translation:doctor`: orphan, duplicate and null-tuuid fail; untranslated and incomplete are informational unless `--strict`), Removal Semantics, and — for an application declaring translation roots — the root invariant (`tmi:translation:adopt-root --check`, Check 6.5)

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
1. SharedAmongstTranslations on association 'category' (target is translatable)
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
Run checks: SharedAmongstTranslations on an association to a translatable entity

### "Field value unexpected after translation"
Run checks: Handler chain mapping, attribute conflicts (Shared vs Empty)

### "Compile-time validation error"
Run checks: AttributeValidationPass errors, class/property attribute conflicts, locale field

### "Unique constraint validation error"
Run checks: Single-column unique: true fields, composite unique constraints

### "Entity only resolves in one locale" / "hreflang or shared media missing on translations"
Run `tmi:translation:doctor` (Layer 6) — likely a standalone Tuuid created by bypassing
`EntityTranslator::translate()`. See diagnostics Check 6.1.

### "Shared field differs between locales"
Run `tmi:translation:sync-shared --dry-run`, then without `--dry-run`. Drift on a fresh
database means `propagate_shared_on_flush` is off (it is on by default) — or the field is one
the application varies per locale on purpose and should not carry the attribute at all. See
diagnostics Check 6.2. A `listing`-style path listed as **not writable** with a warning naming
`tmi:translation:adopt-root --check` is a translation root reference the siblings disagree on
— `sync-shared` never re-points it; see Check 6.5.

### "adopt-root --check fails" / "TranslationRootContractException at cache:clear"
Run `tmi:translation:adopt-root --dry-run`: `new`/`partial` groups are repaired by the write
mode, `drift`/`ambiguous`/`mismatched` groups need a manual decision (the report names tuuid,
locales and detail). A contract exception at compile time names the declaration and carries a
`Solution:` line — most often a missing `tmi_translation.root_adopter` tag (`class` attribute)
or a non-nullable root reference without a constructor that requires the root. See diagnostics
Check 6.5.

### "OrphanTranslationException on flush"
An entity is being flushed in a non-default locale without a shared Tuuid — no other
locale variant links to it, not even one created in the same flush. Create translations
via `EntityTranslator::translate()`, or adjust `strict_orphan_check`.

### "Deleted entity still served in another locale"
A plain `$em->remove($entity)` only removes the one row passed to it — nothing links its
sibling locale variants for Doctrine to cascade through. Fix: `TranslatableRemover::
removeAllLocaleVariants($entity)`, or `cascade_remove_locale_variants: true`. See diagnostics
Check 6.4.

### "Duplicate variant / new row on every cross-locale lookup"
A hand-rolled "does this variant exist?" query through the entity's own repository combines
with an active locale filter into a contradiction that can never match, so the existing
variant is invisible and the caller creates a second row. Use `LocaleVariantFinder`
(filter-suspended); the bundle's own lookups already do. See diagnostics Check 6.4.

### "Detached entity duplicated during an import"
A translation-cache hit surviving `$em->clear()` must not be reused — `UnitOfWork` no longer
tracks it and `persist()` would re-insert it as a new row. `EntityTranslator` checks every hit
against the real `UnitOfWork` state and treats a detached one as a miss, so nothing needs
changing in application code; a **custom** cache must uphold the same contract and reload
through the `EntityManager`. See diagnostics Check 6.4.

### "null-tuuid rows reported by tmi:translation:doctor"
The `tuuid` column is mapped `NOT NULL`, so this anomaly can only come from a write that
bypassed the entity layer (a raw INSERT, or a row imported from another system). No automatic
fix — repair or delete the row at the database level. See diagnostics Check 6.4.

### "LogicException: ...strict_discovery... is enabled..." at compile time
`strict_discovery: true` turned a `0 translatable entities discovered` result from a logged
warning into a hard failure. Either the project genuinely has no translatable entities yet
(set `strict_discovery: false`), or doctrine-bundle's `attribute_metadata_driver` service
shape changed and compile-time discovery is silently finding nothing — investigate before
disabling the check. See diagnostics Check 5.3.

## Quick Commands

For users who know what to check:

- **"Check entity config"** - Run Entity Configuration Layer only
- **"Check attributes"** - Run Attribute Configuration Layer only
- **"Check handlers"** - Run Handler Chain Mapping Layer only
- **"Check runtime"** - Run Runtime Configuration Layer only
- **"Check validation"** - Run Compile-Time Validation Layer only
- **"Check linkage"** - Run Tuuid Linkage Integrity Layer only (`tmi:translation:doctor`)
- **"Check roots"** - Run the root invariant only (`tmi:translation:adopt-root --check`, Check 6.5)
- **"Full diagnostic"** - Run all layers (default)

## References

- **references/diagnostics.md** - Detailed check procedures for each layer
- **llms.md -> Troubleshooting** - Fix procedures for each issue type
- **llms.md -> Handler Chain Decision Tree** - Handler priority and routing
- **llms.md -> Removal Semantics** - `TranslatableRemover`, `cascade_remove_locale_variants`
- **llms.md -> Translation Roots** - root contract, `adopt-root` classification, extension points
- **llms.md -> Performance** - `preload()`, reflection caches, query budgets
- **UPGRADING.md** - Migration guide per upgrade path, newest first
