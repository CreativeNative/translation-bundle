---
phase: "08"
plan: "02"
title: "Custom Handler Creator Skill"
subsystem: ai-skills
tags: [skill, handler, chain-extension, templates]
status: complete

dependency-graph:
  requires:
    - "Phase 6: Foundation documentation (llms.md, handler chain)"
    - "Phase 7: Core skill patterns"
  provides:
    - "custom-handler-creator skill for extending handler chain"
    - "Handler template with TranslationHandlerInterface implementation"
    - "Test template following bundle conventions"
    - "Priority decision matrix for handler ordering"
  affects:
    - "Phase 8-03: Translation debugger skill (may reference handler patterns)"

tech-stack:
  patterns:
    - use-case-first-workflow
    - interactive-priority-selection
    - separate-test-offer

file-tracking:
  key-files:
    created:
      - ".claude/skills/custom-handler-creator/SKILL.md"
      - ".claude/skills/custom-handler-creator/references/handler-template.md"
      - ".claude/skills/custom-handler-creator/references/test-template.md"
      - ".claude/skills/custom-handler-creator/references/handler-priority.md"
      - ".claude/skills/custom-handler-creator/references/examples.md"
    modified: []

decisions:
  - id: use-case-first
    choice: "Ask 'What field type needs custom handling?' before generating template"
    why: "Aligns with user's mental model - they know the problem, not the solution structure"

  - id: priority-reasoning
    choice: "Include reasoning template for priority selection"
    why: "Priority selection requires understanding handler chain order and conflicts"

  - id: separate-test-offer
    choice: "Offer tests after handler generation, not bundled"
    why: "Users may want handler first, then optionally add tests"

  - id: insertion-points
    choice: "Define custom priorities at 5-unit intervals (75, 65, 55, etc.)"
    why: "Provides clear insertion points between standard handlers"

metrics:
  duration: "4 min"
  completed: "2026-02-02"
---

# Phase 08 Plan 02: Custom Handler Creator Skill Summary

**One-liner:** Use-case-first skill for creating custom translation handlers with interactive priority selection and 7 concrete examples.

## What Was Built

### SKILL.md (93 lines)
- Frontmatter with specific trigger phrases avoiding false positives
- 6-step workflow: identify use case, determine behavior, select priority, generate handler, register service, offer tests
- Reference to llms.md Handler Chain Decision Tree for architecture context

### references/handler-template.md
Complete handler class template with:
- All 4 TranslationHandlerInterface methods: `supports()`, `translate()`, `handleSharedAmongstTranslations()`, `handleEmptyOnTranslate()`
- Contextual TODO comments explaining each method's purpose
- Common patterns for each method (return null, throw exception, clone, share)
- TranslationArgs reference showing available context

### references/test-template.md
PHPUnit test template with:
- 5 standard test methods following bundle naming convention
- Arrange/Act/Assert pattern for each test
- Mock setup for AttributeHelper dependency
- Helper method for creating TranslationArgs

### references/handler-priority.md
Priority decision matrix including:
- All 10 standard handler priorities (100-10)
- 10 custom insertion points (95, 85, 75, 65, 55, 45, 35, 25, 15, 5)
- Conflict avoidance guide
- Decision flowchart for priority selection
- Reasoning template: "Priority X because [reason]"

### references/examples.md
7 concrete use case examples:
1. Encrypted fields (priority 85)
2. Computed properties (priority 85)
3. Value objects without Doctrine metadata (priority 75)
4. Third-party library objects (priority 75)
5. Cached/lazy-loaded fields (priority 85)
6. File paths and URLs (priority 85)
7. Money value objects (priority 75)

Each example includes: field type, why custom handler needed, suggested priority, key implementation notes.

## Key Patterns Applied

1. **Use-case-first workflow**: Skill asks "What field type?" before showing templates
2. **Interactive priority selection**: User guided through priority matrix with reasoning
3. **Separate test offer**: "Want me to add tests?" comes after handler generation
4. **Examples-first approach**: Show concrete examples to help user identify their use case

## Deviations from Plan

None - plan executed exactly as written.

## Verification Results

| Check | Result |
|-------|--------|
| SKILL.md under 200 lines | 93 lines |
| Frontmatter present | name + description |
| Use-case-first workflow | "What field type needs custom handling?" |
| All 4 interface methods in template | supports, translate, handleShared, handleEmpty |
| 5 test methods in test template | Verified |
| Priority matrix complete | 10 standard + 10 insertion points |
| 5+ examples | 7 examples |
| Test offer separate | Line 69: "Want me to add PHPUnit tests?" |
| llms.md reference | Line 80 |

## Commits

| Hash | Message |
|------|---------|
| 8701a20 | feat(08-02): create custom-handler-creator skill |

## Next Phase Readiness

Ready for Phase 8-03 (Translation Debugger Skill). No blockers.
