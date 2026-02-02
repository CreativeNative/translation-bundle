---
phase: "08-advanced-skills"
plan: "01"
name: "translation-debugger-skill"
status: "complete"

subsystem: "ai-skills"
tags: ["skill", "debugging", "diagnostics", "troubleshooting"]

dependency-graph:
  requires:
    - "07-core-implementation-skill"
  provides:
    - "translation-debugger skill with systematic diagnostic workflow"
    - "4-layer diagnostic check procedures"
    - "dependency-ordered issue presentation"
  affects:
    - "08-02 (custom-handler-creator depends on debugger pattern)"
    - "09 (web discovery may link to debugger skill)"

tech-stack:
  added: []
  patterns:
    - "diagnostic-layered-checks"
    - "dependency-ordered-presentation"
    - "offer-to-fix-pattern"

key-files:
  created:
    - ".claude/skills/translation-debugger/SKILL.md"
    - ".claude/skills/translation-debugger/references/diagnostics.md"
  modified: []

decisions:
  - id: "08-01-001"
    decision: "4-layer diagnostic structure (Entity Config, Attributes, Handler Chain, Runtime)"
    context: "Need systematic approach to diagnose translation issues"
    outcome: "Clear dependency order for issue presentation"

metrics:
  duration: "3 min"
  completed: "2026-02-02"
---

# Phase 8 Plan 1: Translation Debugger Skill Summary

**One-liner:** Systematic diagnostic skill with 4-layer checks, dependency-ordered presentation, and offer-to-fix workflow.

## What Was Built

### translation-debugger skill
- **SKILL.md** (114 lines): High-level workflow instructions
  - Broad trigger phrases covering "translation" + problem/issue/wrong/broken/error
  - Auto-runs diagnostics without open-ended questions
  - Presents results in dependency order (BLOCKING -> ERROR -> WARNING)
  - "Want me to fix this?" pattern after each issue
  - References llms.md Troubleshooting for detailed fixes

### references/diagnostics.md
Detailed diagnostic check procedures for 4 layers:

1. **Entity Configuration Layer (BLOCKING)**
   - TranslatableInterface implementation
   - TranslatableTrait usage
   - Tuuid property initialization
   - Locale property presence

2. **Attribute Configuration Layer (ERROR/WARNING)**
   - SharedAmongstTranslations on bidirectional relations (ERROR)
   - EmptyOnTranslate on non-nullable fields (ERROR)
   - Both attributes on same field (WARNING)

3. **Handler Chain Mapping Layer (WARNING)**
   - Field type handler compatibility
   - Handler priority table reference
   - Embedded object sharing behavior

4. **Runtime Configuration Layer (ERROR/WARNING/INFO)**
   - Target locale in configuration
   - Doctrine filter enabled
   - Entity persistence after translation
   - Collection translation duplicates

## Verification Results

| Check | Result |
|-------|--------|
| SKILL.md under 200 lines | 114 lines |
| Frontmatter has name and description | Yes |
| No open-ended questions in activation | Yes |
| Dependency order in presentation | Yes |
| Offer-to-fix pattern present | Yes |
| llms.md Troubleshooting reference | Yes |
| All 4 diagnostic layers documented | Yes |

## Deviations from Plan

None - plan executed exactly as written.

## Task Commits

| Task | Description | Commit |
|------|-------------|--------|
| 1 | Create translation-debugger skill | 6e7b1b1 |
| 2 | Validate skill structure | (validation only) |

## Next Phase Readiness

Ready for:
- **08-02**: custom-handler-creator skill (can follow debugger pattern)
- **08-03**: Any additional advanced skills in Phase 8

No blockers identified.
