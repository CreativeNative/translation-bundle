---
phase: 06-foundation-documentation
plan: 02
subsystem: documentation
tags: [llms.md, troubleshooting, minimal-example, product-entity, ai-assistance]

# Dependency graph
requires:
  - phase: 06-01
    provides: Glossary and decision tree foundation
provides:
  - Troubleshooting section with 10 entries covering setup mistakes and runtime surprises
  - Minimal working example showing Product entity transformation with narrative WHY explanations
  - Complete reference for AI assistants to diagnose common issues
  - Beginner-friendly walkthrough demonstrating interface, trait, and SharedAmongstTranslations
affects: [07-troubleshooting-examples, advanced-skills-documentation, developer-onboarding]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Symptom-cause-fix structure for error-based troubleshooting entries"
    - "Symptom-diagnosis-resolution structure for behavioral troubleshooting entries"
    - "Narrative walkthrough with WHY explanations for minimal examples"

key-files:
  created: []
  modified:
    - llms.md

key-decisions:
  - "10 troubleshooting entries (5 setup mistakes, 5 runtime surprises) for balanced coverage"
  - "Product + Category relationship used in minimal example to demonstrate SharedAmongstTranslations on both scalar and relation"
  - "Narrative structure explains WHY (interface signals to bundle, trait provides properties, attribute controls behavior)"

patterns-established:
  - "Troubleshooting entries include actual error messages where applicable"
  - "Minimal examples show BEFORE and AFTER code with step-by-step transformation"
  - "WHY explanations reference handler chain, priority, and architectural concepts"

# Metrics
duration: 4min
completed: 2026-02-02
---

# Phase 6 Plan 02: Troubleshooting and Minimal Example Summary

**llms.md enhanced with 10 troubleshooting entries (setup + runtime) and Product entity narrative walkthrough demonstrating interface, trait, and SharedAmongstTranslations usage**

## Performance

- **Duration:** 4 min
- **Started:** 2026-02-02T11:40:15Z
- **Completed:** 2026-02-02T11:44:33Z
- **Tasks:** 2
- **Files modified:** 1

## Accomplishments
- Added Troubleshooting section with 10 entries (5 setup mistakes: locale not allowed, EmptyOnTranslate on non-nullable, missing interface/trait/Tuuid, filter not enabled; 5 runtime surprises: bidirectional relation errors, translations not persisted, wrong handler, embedded sharing, collection duplicates)
- Created Minimal Working Example showing Product entity transformation from standard Doctrine entity to translatable with step-by-step narrative
- Each troubleshooting entry follows symptom → cause/diagnosis → fix/resolution structure with actual error messages
- Minimal example explains WHY each change is needed (interface signals to bundle, trait provides properties, Tuuid groups variants, SharedAmongstTranslations controls handler behavior)
- Demonstrated SharedAmongstTranslations on both scalar field (price) and ManyToOne relation (category)

## Task Commits

Each task was committed atomically:

1. **Task 1: Add Troubleshooting Section** - `087e6df` (docs)
2. **Task 2: Add Minimal Working Example** - `8c7c3c1` (docs)

## Files Created/Modified
- `llms.md` - Added Troubleshooting section (after Handler Chain Decision Tree) with 10 entries and Minimal Working Example section (after Troubleshooting) with Product entity narrative walkthrough

## Decisions Made

**Troubleshooting entry split:**
- 5 setup mistakes (configuration and implementation errors)
- 5 runtime surprises (behavioral issues during translation)

**Minimal example scope:**
- Product entity with 4 fields (name, description, price, category)
- ManyToOne relationship to demonstrate SharedAmongstTranslations on relations
- No ManyToMany, embedded objects, or advanced features (deferred to Practical Usage Scenarios)

**Narrative structure:**
- BEFORE code shows standard Doctrine entity
- Step-by-step transformation with WHY explanations
- AFTER code shows complete translatable entity
- Usage example demonstrates Tuuid grouping and shared fields

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## Next Phase Readiness

- Troubleshooting section enables AI assistants to match user-reported errors to diagnostic steps
- Minimal example provides beginner-friendly entry point for first translatable entity
- WHY explanations help users understand rationale, not just mechanical steps
- Glossary terms and decision tree references integrated throughout for consistency
- Ready for advanced skills documentation (wave 3) which can reference this foundation

No blockers.

---
*Phase: 06-foundation-documentation*
*Completed: 2026-02-02*
