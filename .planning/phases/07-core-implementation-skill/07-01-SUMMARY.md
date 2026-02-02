---
phase: 07-core-implementation-skill
plan: 01
subsystem: documentation
tags: [claude-code, skills, doctrine, translation, ai-optimization]

# Dependency graph
requires:
  - phase: 06-foundation-documentation
    provides: llms.md with handler chain decision tree reference
provides:
  - Claude Code skill for guided entity translation setup
  - Examples-first attribute decision guidance
  - Quick mode (defaults) and guided mode (walkthrough) workflows
  - Inline relationship handler behavior summary with llms.md reference
affects: [08-advanced-skills, web-discovery]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Examples-first guidance pattern for AI-assisted setup
    - Quick mode vs guided mode workflow pattern for skills
    - Diff-style code generation with inline comments

key-files:
  created:
    - .claude/skills/entity-translation-setup/SKILL.md
  modified: []

key-decisions:
  - "Skill auto-activates on trigger phrases: 'make entity translatable', 'add translations to [Entity]'"
  - "Quick mode uses smart defaults (translate scalars except price/cost, share relations, EmptyOnTranslate only for slug)"
  - "Guided mode presents examples-first (SKU/price/date for SharedAmongstTranslations, slug/SEO for EmptyOnTranslate)"
  - "Diff-style code output with + markers and inline comments for clarity"
  - "Inline relationship handler summary (3-4 lines) references llms.md for complete details (balances 200-line constraint)"

patterns-established:
  - "Examples-first attribute guidance: Show concrete use cases before asking user to classify their fields"
  - "Smart field suggestions: Auto-suggest attributes based on field names (price→Shared, slug→Empty) with confirmation"
  - "Diff-style presentation: Show changes with + markers and inline comments, wait for user confirmation before applying"

# Metrics
duration: 3min
completed: 2026-02-02
---

# Phase 7 Plan 01: Entity Translation Setup Skill Summary

**Claude Code skill for guided entity translation setup with examples-first attribute decisions, quick/guided modes, and inline relationship handler reference**

## Performance

- **Duration:** 3 min
- **Started:** 2026-02-02T15:25:00Z
- **Completed:** 2026-02-02T15:27:53Z
- **Tasks:** 2
- **Files modified:** 1

## Accomplishments
- Created entity-translation-setup skill under 200 lines (192 lines)
- Implemented quick mode (defaults) and guided mode (walkthrough) workflows
- Examples-first guidance for SharedAmongstTranslations (SKU, price, date, category) and EmptyOnTranslate (slug, SEO URL)
- Inline relationship handler behavior summary (OneToMany, ManyToOne, ManyToMany) with llms.md reference
- Diff-style code template with + markers and inline comments
- User confirmation step before applying changes
- Migration command reminder after changes applied

## Task Commits

Each task was committed atomically:

1. **Task 1: Create entity-translation-setup skill** - `66788dd` (feat)

Task 2 was validation only (no code changes).

**Plan metadata:** (to be added)

## Files Created/Modified
- `.claude/skills/entity-translation-setup/SKILL.md` - Claude Code skill guiding users through making Doctrine entities translatable with TranslatableInterface, TranslatableTrait, and attribute configuration

## Decisions Made
- **Skill trigger phrases in description:** "make this entity translatable", "add translations to [Entity]", "translate [Entity] fields" enable auto-activation
- **Quick mode defaults:** Translate all scalars except price/cost/amount (SharedAmongstTranslations), share all relations, apply EmptyOnTranslate only for slug/seo field names
- **Examples-first approach:** Show concrete examples (Product SKU, price, slug) before asking users to classify their own fields
- **Inline relationship handler summary:** Brief 3-4 line summary with reference to llms.md for complete details (balances 200-line constraint with comprehensive guidance)
- **Diff-style code output:** Show changes with + markers, contextual lines, and inline comments explaining each modification

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None - straightforward skill creation following established patterns.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Core implementation skill complete and ready for use
- Ready for Phase 7 Plan 02 (advanced skills) and Phase 8 (web discovery)
- Skill can be immediately tested with real entity files
- llms.md reference properly linked for comprehensive handler chain documentation

---
*Phase: 07-core-implementation-skill*
*Completed: 2026-02-02*
