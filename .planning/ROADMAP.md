# Roadmap: TMI Translation Bundle

## Milestones

- ✅ **v1.0 Core Bundle** - Phases 1-5 (shipped, existing codebase)
- 🚧 **v1.1 AI-Ready Documentation** - Phases 6-9 (in progress)

## Overview

Transform the TMI Translation Bundle into an AI-friendly open source project by creating comprehensive documentation that enables any LLM assistant to help users implement translations correctly. Starting with enhanced foundation documentation (llms.md improvements), then building task-specific Claude Code Skills for the three core workflows (setup, debugging, extensibility), and finishing with web discovery via llms.txt. Every requirement delivers observable value for AI-assisted development.

## Phases

<details>
<summary>✅ v1.0 Core Bundle (Phases 1-5) - SHIPPED</summary>

The v1.0 milestone delivered the core translation bundle with 100% test coverage:

- TranslatableInterface and TranslatableTrait for entity translation
- Tuuid (Translation UUID) value object for grouping translations
- Handler chain pattern for translating all field types
- Automatic locale filtering via Doctrine filter
- SharedAmongstTranslations and EmptyOnTranslate attributes
- Full relationship support (OneToOne, OneToMany, ManyToOne, ManyToMany)
- Event system (PRE_TRANSLATE, POST_TRANSLATE)

Phases 1-5 (implied structure from existing codebase):
1. Foundation (Tuuid, interfaces, traits)
2. Handler chain architecture
3. Doctrine integration (filter, events)
4. Relationship support
5. Testing and polish

</details>

### 🚧 v1.1 AI-Ready Documentation (In Progress)

**Milestone Goal:** Make bundle AI-friendly so any LLM assistant can help open source users implement translations correctly, understand architectural advantages, and handle edge cases.

**Phase Numbering:**
- Integer phases (6, 7, 8, 9): Planned milestone work
- Decimal phases (6.1, 6.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [ ] **Phase 6: Foundation Documentation** - Enhanced llms.md with handler decision tree and troubleshooting
- [ ] **Phase 7: Core Implementation Skill** - entity-translation-setup skill with references
- [ ] **Phase 8: Advanced Skills** - Debugging and custom handler creation workflows
- [ ] **Phase 9: Web Discovery** - llms.txt for AI crawler discovery

## Phase Details

### Phase 6: Foundation Documentation

**Goal**: AI assistants understand the bundle's handler chain architecture and can guide users through common troubleshooting scenarios

**Depends on**: Nothing (first phase of v1.1)

**Requirements**: FOUND-01, FOUND-02, FOUND-03, FOUND-04

**Success Criteria** (what must be TRUE):
1. llms.md includes visual handler chain decision tree showing which handler processes each field type and priority rationale
2. llms.md includes troubleshooting section with 5+ common problems and diagnostic steps
3. llms.md includes minimal working example walking through entity-to-translatable transformation
4. All terminology is consistent across llms.md (single term per concept, no confusing synonyms)

**Plans**: TBD

Plans:
- [ ] 06-01: [Description pending plan-phase]

### Phase 7: Core Implementation Skill

**Goal**: AI assistants can guide users through making any entity translatable with correct interface implementation, trait usage, and attribute configuration

**Depends on**: Phase 6 (foundation documentation must exist)

**Requirements**: SKILL-01

**Success Criteria** (what must be TRUE):
1. entity-translation-setup skill exists with SKILL.md under 200 lines
2. Skill includes working code templates for TranslatableInterface implementation
3. Skill guides through SharedAmongstTranslations and EmptyOnTranslate attribute decisions
4. AI can invoke skill automatically when user asks to make entity translatable
5. Skill references point to handler documentation for field-specific details

**Plans**: TBD

Plans:
- [ ] 07-01: [Description pending plan-phase]

### Phase 8: Advanced Skills

**Goal**: AI assistants can diagnose translation issues and guide users through extending the handler chain with custom handlers

**Depends on**: Phase 7 (core skill establishes pattern)

**Requirements**: SKILL-02, SKILL-03

**Success Criteria** (what must be TRUE):
1. translation-debugger skill exists with diagnostic workflow for common failures
2. Debugger skill can identify handler chain priority issues automatically
3. custom-handler-creator skill exists with handler + test templates
4. Custom handler skill guides priority selection with decision matrix
5. Both skills under 200 lines with details in references/ subdirectories

**Plans**: TBD

Plans:
- [ ] 08-01: [Description pending plan-phase]

### Phase 9: Web Discovery

**Goal**: AI crawlers and search interfaces can discover the bundle's documentation and index it for AI-assisted searches

**Depends on**: Phase 6 (foundation documentation must be stable)

**Requirements**: WEB-01

**Success Criteria** (what must be TRUE):
1. llms.txt exists at repository root with H1 project name and summary blockquote
2. llms.txt includes structured navigation links (20-50 links maximum)
3. llms.txt links point to GitHub markdown files (not HTML)
4. llms.txt is served as text/plain content type
5. llms.txt validates against llmstxt.org specification

**Plans**: TBD

Plans:
- [ ] 09-01: [Description pending plan-phase]

## Progress

**Execution Order:**
Phases execute in numeric order: 6 → 7 → 8 → 9

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 6. Foundation Documentation | 0/TBD | Not started | - |
| 7. Core Implementation Skill | 0/TBD | Not started | - |
| 8. Advanced Skills | 0/TBD | Not started | - |
| 9. Web Discovery | 0/TBD | Not started | - |

---
*Roadmap created: 2026-02-02*
*Last updated: 2026-02-02*
