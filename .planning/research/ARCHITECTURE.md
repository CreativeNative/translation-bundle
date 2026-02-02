# Architecture: AI Documentation for Symfony Bundle with Handler Chain

**Project:** TMI Translation Bundle
**Focus:** Skills and llms.md structure for AI-friendly documentation
**Researched:** 2026-02-02
**Confidence:** HIGH

## Executive Summary

AI-friendly documentation for a Symfony bundle with handler chain architecture requires a dual-layer approach: comprehensive llms.md for general understanding, and focused skills for specific tasks. The handler chain pattern maps naturally to sequential documentation that emphasizes decision flows and context propagation.

### Key Recommendations

1. **llms.md remains the comprehensive reference** - Already excellent at 343 lines, this serves as the "always loaded" context
2. **Skills provide task-specific workflows** - Create 3-5 targeted skills for common operations (adding handlers, entity setup, debugging translations)
3. **Handler chain gets visual documentation** - Decision tree diagrams help AI understand priority-based execution
4. **Progressive disclosure through references/** - Move detailed handler implementations to separate reference files

## Architecture Pattern Analysis

### Handler Chain Pattern in TMI Bundle

The bundle implements a **priority-based chain of responsibility** pattern:

```
EntityTranslator (orchestrator)
  ├─> handlers[] (sorted by priority 100→10)
  │   └─> Each handler.supports(args) → first match wins
  └─> TranslationArgs (context object flowing through chain)
```

**Key characteristics for AI documentation:**
- Sequential evaluation (priority order matters)
- First-match-wins (no handler fallthrough)
- Context object (TranslationArgs) carries state through chain
- Recursive translation (handlers can invoke EntityTranslator)
- Three modes per handler: translate(), handleShared(), handleEmpty()

### Why This Matters for AI Documentation

Chain-of-responsibility patterns are well-understood by LLMs when documented with:
1. **Decision flow diagrams** - Visual representation of "which handler handles what"
2. **Input/output contracts** - What goes into TranslationArgs, what comes out
3. **Priority rationale** - Why PrimaryKey is 100 and DoctrineObject is 10
4. **Extension points** - How to add custom handlers at specific priorities

**Source:** [Multi-Step LLM Chains: Best Practices for Complex Workflows](https://www.deepchecks.com/orchestrating-multi-step-llm-chains-best-practices/) documents that handler chains benefit from explicit documentation of intermediate reasoning stages.

## Recommended Documentation Structure

### Layer 1: llms.md (Comprehensive Reference)

**Current state:** Excellent foundation at 343 lines covering:
- Core concepts (translation vs shared vs empty)
- Handler descriptions with priorities
- Practical usage scenarios
- Step-by-step integration

**Recommendations:**
1. **Add visual handler decision tree** (section after "Translation Handlers")
2. **Consolidate handler details** - Move implementation specifics to references/
3. **Add troubleshooting section** - Common mistakes and how to debug
4. **Link to skills** - Reference specific skills for common tasks

**Ideal structure:**

```markdown
# TMI Translation Bundle - Developer & AI Guide

## Quick Navigation
- [Core Concepts](#core-concepts) - Read this first
- [Handler Chain](#handler-chain) - How translation works
- [Common Tasks](#common-tasks) - Links to skills
- [Troubleshooting](#troubleshooting) - Debug guide

## Core Concepts
[Keep existing content - it's excellent]

## Handler Chain Overview
[Add decision tree diagram]
[Simplified handler descriptions - move implementation to references/]

## Common Tasks
- Adding entity translation → See skill: entity-translation-setup
- Creating custom handler → See skill: custom-handler-creator
- Debugging translation issues → See skill: translation-debugger

## Handler Implementation Details
See references/handlers/ for detailed implementation notes

## Troubleshooting
### Translation not working
1. Check LocaleFilter is enabled
2. Verify tuuid is set correctly
3. Check handler priorities
→ Run skill: translation-debugger for automated diagnosis

[Rest of existing content]
```

**Keep under 500 lines** per Claude Skills best practices (currently 343 - room to grow).

**Source:** [Skill authoring best practices](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/best-practices) recommends keeping documentation concise and using progressive disclosure.

### Layer 2: Skills (Task-Specific Workflows)

Create focused skills for common operations. Each skill should be 50-200 lines.

#### Recommended Skills

**1. entity-translation-setup**
```
Purpose: Guide through making an entity translatable
When: User wants to add translation to existing entity
Flow:
  1. Add TranslatableInterface + TranslatableTrait
  2. Configure tuuid and locale fields
  3. Decide on shared vs translatable fields
  4. Add migration
  5. Test translation
```

**2. custom-handler-creator**
```
Purpose: Create custom translation handler at correct priority
When: User needs special translation logic
Flow:
  1. Analyze what the handler should support
  2. Determine correct priority (between existing handlers)
  3. Generate handler class with all 4 methods
  4. Register with AsTaggedItem
  5. Write tests
```

**3. translation-debugger**
```
Purpose: Diagnose translation problems
When: Translation not working as expected
Flow:
  1. Check configuration (locales, filter)
  2. Inspect entity (tuuid, locale, interface)
  3. Trace handler chain execution
  4. Verify attribute placement
  5. Report findings with fix suggestions
```

**4. shared-field-propagator** (future)
```
Purpose: Propagate shared field updates across translations
When: User updates SharedAmongstTranslations field
Flow:
  1. Detect tuuid of modified entity
  2. Load all sibling translations
  3. Update shared field value
  4. Flush changes
Related: GitHub Issue #4 (shared field propagation)
```

**Structure per skill:**
```
.claude/skills/entity-translation-setup/
├── SKILL.md                    # 50-200 lines
├── references/
│   ├── translatable-trait.md   # Deep dive on trait
│   └── field-decisions.md      # Shared vs translatable logic
└── assets/
    └── entity-template.php     # Template code
```

**Source:** [Extend Claude with skills](https://code.claude.com/docs/en/skills) and [Claude Agent Skills Deep Dive](https://leehanchung.github.io/blogs/2025/10/26/claude-skills-deep-dive/) demonstrate skills with references/ and assets/ subdirectories.

### Layer 3: .claude/ Reference Files

Move detailed implementation notes out of llms.md into focused reference files.

**Recommended structure:**

```
.claude/
├── architecture.md          # Existing - keep as-is
├── code-style.md           # Existing - keep as-is
├── doctrine.md             # Existing - keep as-is
├── testing.md              # Existing - keep as-is
├── handlers.md             # NEW - handler chain deep dive
├── attributes.md           # NEW - attribute system explained
└── troubleshooting.md      # NEW - common issues and fixes

.claude/references/
├── handlers/
│   ├── primary-key.md
│   ├── scalar.md
│   ├── embedded.md
│   ├── bidirectional-many-to-one.md
│   ├── bidirectional-one-to-many.md
│   ├── bidirectional-one-to-one.md
│   ├── bidirectional-many-to-many.md
│   ├── unidirectional-many-to-many.md
│   ├── translatable-entity.md
│   └── doctrine-object.md
├── architecture/
│   ├── handler-chain-pattern.md
│   ├── translation-args-context.md
│   └── cache-and-cycles.md
└── examples/
    ├── basic-entity-translation.md
    ├── embedded-with-shared.md
    └── complex-relations.md
```

**Purpose:**
- Keep llms.md under 500 lines
- Provide "deep dive" content when needed
- Skills can reference specific files
- AI loads only relevant context

**Source:** [Claude Skills SKILL.md structure](https://github.com/anthropics/skills/blob/main/skills/skill-creator/SKILL.md) shows how references/ subdirectories enable progressive disclosure.

## Handler Chain Documentation Best Practices

### 1. Visual Decision Tree

Add to llms.md after "Translation Handlers" intro:

```markdown
## Handler Chain Decision Flow

When translating a property, handlers evaluate in priority order:

┌─────────────────────────────────────────┐
│ TranslationArgs (property + value)      │
└────────────────┬────────────────────────┘
                 │
    ┌────────────▼───────────┐
    │ PrimaryKeyHandler (100)│ → Is property @Id?
    └────────────┬───────────┘    YES: return null
                 │ NO              NO: continue
    ┌────────────▼───────────┐
    │ ScalarHandler (90)     │ → Is value scalar/DateTime?
    └────────────┬───────────┘    YES: handle scalar
                 │ NO              NO: continue
    ┌────────────▼───────────┐
    │ EmbeddedHandler (80)   │ → Is property @Embedded?
    └────────────┬───────────┘    YES: handle embedded
                 │ NO              NO: continue
    [... continue through all handlers ...]
    ┌────────────▼───────────┐
    │ DoctrineObjectHandler  │ → Is Doctrine-managed?
    │         (10)           │    YES: clone and recurse
    └────────────────────────┘    NO: return unchanged
```

**Why:** LLMs excel at understanding control flow with visual representations. AWS Prescriptive Guidance on agentic patterns emphasizes decision flow visualization.

**Source:** [Agentic AI patterns and workflows on AWS](https://docs.aws.amazon.com/pdfs/prescriptive-guidance/latest/agentic-ai-patterns/agentic-ai-patterns.pdf) documents router chains with visual decision trees.

### 2. Handler Interface Contract

Document the contract clearly:

```markdown
## Handler Interface Contract

Every handler implements 4 methods:

| Method | Purpose | Return | When Called |
|--------|---------|--------|-------------|
| `supports(TranslationArgs): bool` | "Can I handle this?" | true/false | Every handler, every property |
| `translate(TranslationArgs): mixed` | Default translation | Translated value | When supports() = true, no attributes |
| `handleSharedAmongstTranslations()` | Shared field logic | Shared reference | Property has #[SharedAmongstTranslations] |
| `handleEmptyOnTranslate()` | Empty field logic | null or empty | Property has #[EmptyOnTranslate] |

**Critical:** First handler where `supports() = true` wins. Order matters!
```

### 3. Priority Rationale

Document WHY each handler has its priority:

```markdown
## Handler Priority Rationale

| Priority | Handler | Why This Order |
|----------|---------|----------------|
| 100 | PrimaryKey | Must check first - IDs never translate |
| 90 | Scalar | Simple values before complex objects |
| 80 | Embedded | Value objects before entity relations |
| 70 | ManyToOne | Parent relations before children |
| 60 | OneToMany | Child collections after parents |
| 50 | OneToOne | Peer relations after parent/child |
| 40 | ManyToMany (bi) | Complex relations after simpler ones |
| 30 | ManyToMany (uni) | Unidirectional after bidirectional |
| 20 | TranslatableEntity | Check if entity translatable before generic |
| 10 | DoctrineObject | Fallback - handles anything Doctrine-managed |

**Adding custom handlers:**
- Priority 95: Between Scalar and Embedded (special value objects)
- Priority 75: Between Embedded and Relations (custom object types)
- Priority 55: Between relation types (special relation handling)
```

**Why:** Explicit ordering rationale helps AI suggest correct priorities for custom handlers.

### 4. Context Object Flow

Document how TranslationArgs carries context:

```markdown
## TranslationArgs: Context Through the Chain

TranslationArgs is the "request object" flowing through handlers:

```php
TranslationArgs {
  dataToBeTranslated: mixed     // The value being translated
  sourceLocale: string          // 'en_US'
  targetLocale: string          // 'de_DE'
  translatedParent: mixed|null  // For bidirectional relations
  property: ReflectionProperty|null // Metadata about the property
}
```

**Handler responsibilities:**
1. Read context via getters
2. Make decision (supports?)
3. Return transformed value
4. DO NOT mutate args (immutable pattern)

**Recursive translation:**
Handlers call `EntityTranslator->processTranslation()` with NEW TranslationArgs:
- DoctrineObjectHandler: Creates args for each property
- Relation handlers: Creates args for related entities
- This enables deep object graph translation
```

**Source:** [Composable Effect Handling for Programming LLM-integrated Scripts](https://arxiv.org/pdf/2507.22048) discusses handler stack configuration where context flows through handlers.

## Integration with Existing Documentation

### Current Documentation Inventory

**Root level:**
- `llms.md` (343 lines) - Comprehensive guide for developers and AI
- `README.md` (240 lines) - User-facing installation and quick start
- `AGENTS.md` (26 lines) - Agent instructions
- `CLAUDE.md` (6 lines) - Compatibility redirect

**.claude/ directory:**
- `architecture.md` - Handler chain basics, attributes, events
- `code-style.md` - PHP 8.4 conventions
- `doctrine.md` - ORM integration patterns
- `testing.md` - Test writing guidelines

**.claude/skills/ directory:**
- Symlinks to .agents/skills/ (php-pro, agent-md-refactor, skill-creator)

### Integration Strategy

**Phase 1: Enhance llms.md**
1. Add handler decision tree diagram
2. Add troubleshooting section
3. Link to future skills
4. Move detailed implementation notes to references/ (prepare structure)

**Phase 2: Extract References**
1. Create .claude/references/handlers/ with one file per handler
2. Create .claude/references/architecture/ for pattern documentation
3. Create .claude/references/examples/ for common scenarios
4. Update llms.md to reference these files

**Phase 3: Create Core Skills**
1. entity-translation-setup skill (highest priority - most common task)
2. custom-handler-creator skill (for extensibility)
3. translation-debugger skill (for troubleshooting)

**Phase 4: Advanced Skills** (post-MVP)
1. shared-field-propagator (addresses GitHub Issue #4)
2. performance-optimizer (batch translation, cache tuning)
3. migration-generator (database schema updates)

### Avoiding Duplication

**Principle:** Single source of truth with layered access

| Information Type | Primary Location | Referenced By |
|------------------|------------------|---------------|
| Core concepts | llms.md | Skills, README |
| Handler basics | llms.md | References (deep dive) |
| Handler implementation | references/handlers/ | llms.md (summary), skills |
| Configuration | README.md | llms.md, skills |
| Code style | .claude/code-style.md | Skills (code generation) |
| Common tasks | Skills | llms.md (links) |
| API reference | Generated from code | All documentation |

**Strategy:**
- llms.md: 30,000 foot view + quickstart
- references/: Technical deep dives
- skills/: Step-by-step workflows
- .claude/*.md: Conventions and guidelines

**Source:** [Optimizing technical documentation for LLMs](https://dev.to/johtom/optimizing-technical-documentations-for-llms-4bcd) emphasizes single source of truth with clear hierarchy.

## File Organization Recommendations

### Recommended Structure (Full)

```
translation-bundle/
├── llms.md                          # Main AI reference (keep ~500 lines)
├── README.md                        # User guide (existing)
├── AGENTS.md                        # Agent instructions (existing)
├── CLAUDE.md                        # Compatibility (existing)
│
├── .claude/
│   ├── architecture.md              # Existing - enhance with handler details
│   ├── code-style.md               # Existing - keep as-is
│   ├── doctrine.md                 # Existing - keep as-is
│   ├── testing.md                  # Existing - keep as-is
│   ├── handlers.md                 # NEW - handler chain master doc
│   ├── attributes.md               # NEW - attribute system deep dive
│   ├── troubleshooting.md          # NEW - common issues
│   │
│   ├── references/
│   │   ├── handlers/
│   │   │   ├── README.md           # Handler overview with decision tree
│   │   │   ├── primary-key.md      # PrimaryKeyHandler implementation
│   │   │   ├── scalar.md           # ScalarHandler implementation
│   │   │   ├── embedded.md         # EmbeddedHandler implementation
│   │   │   ├── many-to-one.md      # BidirectionalManyToOneHandler
│   │   │   ├── one-to-many.md      # BidirectionalOneToManyHandler
│   │   │   ├── one-to-one.md       # BidirectionalOneToOneHandler
│   │   │   ├── many-to-many-bi.md  # Bidirectional M2M
│   │   │   ├── many-to-many-uni.md # Unidirectional M2M
│   │   │   ├── translatable.md     # TranslatableEntityHandler
│   │   │   └── doctrine-object.md  # DoctrineObjectHandler
│   │   │
│   │   ├── architecture/
│   │   │   ├── handler-chain.md    # Chain of responsibility pattern
│   │   │   ├── translation-args.md # Context object pattern
│   │   │   ├── recursion.md        # Recursive translation
│   │   │   ├── caching.md          # Translation cache strategy
│   │   │   └── cycles.md           # Cycle detection
│   │   │
│   │   ├── examples/
│   │   │   ├── basic-entity.md     # Simple entity translation
│   │   │   ├── embedded-shared.md  # Embedded with shared fields
│   │   │   ├── relations.md        # Translating relations
│   │   │   └── custom-handler.md   # Creating custom handlers
│   │   │
│   │   └── api/
│   │       ├── entity-translator.md    # EntityTranslator API
│   │       ├── translation-args.md     # TranslationArgs API
│   │       └── attribute-helper.md     # AttributeHelper API
│   │
│   └── skills/
│       ├── entity-translation-setup/
│       │   ├── SKILL.md
│       │   ├── references/
│       │   │   ├── translatable-trait.md
│       │   │   └── field-decisions.md
│       │   └── assets/
│       │       ├── entity-template.php
│       │       └── migration-template.php
│       │
│       ├── custom-handler-creator/
│       │   ├── SKILL.md
│       │   ├── references/
│       │   │   ├── handler-interface.md
│       │   │   └── priority-selection.md
│       │   └── assets/
│       │       ├── handler-template.php
│       │       └── test-template.php
│       │
│       ├── translation-debugger/
│       │   ├── SKILL.md
│       │   ├── scripts/
│       │   │   └── diagnose.py
│       │   └── references/
│       │       └── diagnostic-checklist.md
│       │
│       └── shared-field-propagator/  # Future
│           ├── SKILL.md
│           └── references/
│               └── sibling-loading.md
│
├── src/
│   ├── Translation/
│   │   ├── EntityTranslator.php
│   │   ├── Args/
│   │   │   └── TranslationArgs.php
│   │   └── Handlers/
│   │       ├── TranslationHandlerInterface.php
│   │       └── [10 handler implementations]
│   └── [other source directories]
│
└── tests/
    └── [test files]
```

### File Size Guidelines

Based on Claude Skills best practices:

| File Type | Max Size | Rationale |
|-----------|----------|-----------|
| llms.md | 500 lines | Loaded in most contexts, must stay concise |
| SKILL.md | 200 lines | Loaded when skill triggered, keep focused |
| references/*.md | 1000 lines | Loaded on demand, can be comprehensive |
| .claude/*.md | 300 lines | Conventions should be scannable |

**Current llms.md: 343 lines** - Excellent! Room to add decision tree and troubleshooting.

**Source:** [Skill authoring best practices](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/best-practices) recommends SKILL.md under 500 lines.

## Handler Chain Documentation Template

For each handler in references/handlers/, use this template:

```markdown
# [Handler Name] (Priority: XX)

**Purpose:** One-line description
**Priority:** XX
**Supports:** What types of data/properties

## When This Handler Triggers

Precise conditions for `supports()` returning true:
- Condition 1
- Condition 2

## Translation Modes

### translate()
Default behavior when no attributes present:
- Input: [describe]
- Output: [describe]
- Side effects: [describe]

### handleSharedAmongstTranslations()
Behavior for #[SharedAmongstTranslations]:
- Input: [describe]
- Output: [describe]
- Important: [special notes]

### handleEmptyOnTranslate()
Behavior for #[EmptyOnTranslate]:
- Input: [describe]
- Output: [describe]
- Validation: [nullable check, etc]

## Dependencies

Services injected:
- ServiceName: Used for [purpose]

## Edge Cases

1. Edge case description
   - How handled
   - Why this approach

## Examples

### Basic Usage
```php
// Code example
```

### With Shared Attribute
```php
// Code example
```

### With Empty Attribute
```php
// Code example
```

## Testing Notes

Key test cases:
- Test 1: [description]
- Test 2: [description]

See: tests/Translation/Handlers/[Handler]Test.php

## Related

- Depends on: [other handlers/services]
- Called by: EntityTranslator->processTranslation()
- Calls: [other handlers/services]

## Implementation

Location: `src/Translation/Handlers/[Handler].php`
Lines: [approximate line count]
Complexity: [Low/Medium/High]

## Known Issues

GitHub Issues:
- #X: [description]
```

## Skill Creation Priority

### Phase 1 (MVP - Week 1)

**1. entity-translation-setup** (Priority: CRITICAL)
- Most common task for bundle users
- Guides through TranslatableInterface implementation
- Reduces onboarding friction
- Estimated: 150 lines SKILL.md + 2 reference files + 2 templates

**2. translation-debugger** (Priority: HIGH)
- Second most common need (troubleshooting)
- Automated diagnosis saves support time
- Can generate actionable recommendations
- Estimated: 100 lines SKILL.md + 1 script + 1 reference

### Phase 2 (Extended - Week 2)

**3. custom-handler-creator** (Priority: MEDIUM)
- Advanced use case (extensibility)
- Ensures handlers integrate correctly
- Reinforces priority system understanding
- Estimated: 180 lines SKILL.md + 2 references + 2 templates

### Phase 3 (Future - Post-MVP)

**4. shared-field-propagator** (Priority: LOW)
- Addresses GitHub Issue #4
- Requires deeper Doctrine knowledge
- Less common use case
- Estimated: 120 lines SKILL.md + 1 reference

**5. performance-optimizer** (Priority: LOW)
- Advanced optimization
- Batch operations, cache tuning
- For high-traffic applications
- Estimated: 140 lines SKILL.md + 1 script + 2 references

## Quality Checklist for AI Documentation

Based on 2026 best practices:

### llms.md Quality

- [ ] Under 500 lines
- [ ] Visual decision tree for handler chain
- [ ] Links to skills for common tasks
- [ ] Troubleshooting section
- [ ] Examples use current syntax (PHP 8.4, Symfony 7.3)
- [ ] Clear hierarchy (H1, H2, H3 proper nesting)
- [ ] Code blocks have language tags
- [ ] Cross-references use consistent format

### Skill Quality

- [ ] SKILL.md has YAML frontmatter with name, description, version
- [ ] Description includes "what" and "when to use"
- [ ] Body under 200 lines
- [ ] Uses progressive disclosure (references/)
- [ ] Includes templates in assets/
- [ ] Clear step-by-step instructions
- [ ] Links back to llms.md for concepts
- [ ] Examples use actual bundle code

### Reference Quality

- [ ] Each file covers one topic deeply
- [ ] Under 1000 lines per file
- [ ] Includes code examples
- [ ] Links to actual source files
- [ ] Describes edge cases
- [ ] Notes related GitHub issues
- [ ] Provides testing guidance

### Overall Structure Quality

- [ ] No duplication between files
- [ ] Clear hierarchy: llms.md → skills → references
- [ ] Consistent terminology throughout
- [ ] All cross-references work
- [ ] Follows Symfony bundle conventions
- [ ] Compatible with Claude Skills open standard

**Sources:**
- [Best Practices for Reusable Bundles](https://symfony.com/doc/current/bundles/best_practices.html) - Symfony documentation standards
- [Skill authoring best practices](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/best-practices) - Claude Skills guidelines
- [Optimizing technical documentation for LLMs](https://dev.to/johtom/optimizing-technical-documentations-for-llms-4bcd) - LLM-friendly docs

## Implementation Roadmap

### Week 1: Foundation

**Day 1-2: Enhance llms.md**
- Add handler decision tree diagram (ASCII art + mermaid)
- Add troubleshooting section with common issues
- Add "Common Tasks" section with skill links (prepare for future)
- Consolidate handler descriptions (move details to notes)

**Day 3-4: Create References Structure**
- Create .claude/references/handlers/ directory
- Write handler template (use for consistency)
- Document 2-3 critical handlers (PrimaryKey, Scalar, DoctrineObject)

**Day 5: First Skill**
- Create entity-translation-setup skill
- Write SKILL.md with frontmatter
- Add 2 reference files (trait, field decisions)
- Add 2 templates (entity, migration)

### Week 2: Expansion

**Day 1-2: Complete Handler References**
- Document remaining 7 handlers
- Add architecture references (chain pattern, recursion, caching)
- Add example references (basic, embedded, relations)

**Day 3-4: Second Skill**
- Create translation-debugger skill
- Write diagnostic script
- Add reference with checklist

**Day 5: Third Skill**
- Create custom-handler-creator skill
- Add handler and test templates
- Write priority selection guide

### Week 3+: Polish & Advanced

- Refine based on usage patterns
- Add performance-optimizer skill if needed
- Consider shared-field-propagator skill
- Gather feedback from AI interactions
- Iterate on structure

## Success Metrics

How to measure if this architecture is working:

### Quantitative
- AI can answer "How do I make my entity translatable?" without asking clarifying questions (skill triggers correctly)
- AI can suggest correct priority for custom handlers (decision tree is clear)
- AI can debug translation issues systematically (troubleshooting guide is effective)
- Documentation searches complete in <2 seconds (file sizes appropriate)

### Qualitative
- New contributors understand handler chain after reading llms.md
- AI generates correct handler implementations
- Support questions decrease (better documentation)
- Contributors can extend bundle without asking maintainers

### Technical
- llms.md stays under 500 lines
- Skills stay under 200 lines each
- No duplication between documentation layers
- All cross-references remain valid
- Works with Claude Code, VS Code Copilot, other AI tools (open standard)

## References & Sources

### Claude Skills & AI Documentation (2026)

- [Claude Skills and CLAUDE.md: A Practical 2026 Guide for Teams](https://www.gend.co/blog/claude-skills-claude-md-guide) - Current best practices for skills
- [Extend Claude with skills - Claude Code Docs](https://code.claude.com/docs/en/skills) - Official skill documentation
- [Claude Agent Skills: A First Principles Deep Dive](https://leehanchung.github.io/blogs/2025/10/26/claude-skills-deep-dive/) - Deep dive into skill structure
- [Skill authoring best practices](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/best-practices) - Official best practices
- [Inside Claude Code Skills: Structure, prompts, invocation](https://mikhail.io/2025/10/claude-code-skills/) - Technical deep dive

### LLM-Friendly Documentation

- [My LLM coding workflow going into 2026](https://addyosmani.com/blog/ai-coding-workflow/) - Addy Osmani's workflow
- [Optimizing technical documentation for LLMs](https://dev.to/johtom/optimizing-technical-documentations-for-llms-4bcd) - Documentation optimization
- [LLM-ready docs - GitBook](https://gitbook.com/docs/publishing-documentation/llm-ready-docs) - Publishing standards

### Handler Chain Patterns

- [Multi-Step LLM Chains: Best Practices for Complex Workflows](https://www.deepchecks.com/orchestrating-multi-step-llm-chains-best-practices/) - Chain patterns
- [Chain of Responsibility Design Pattern](https://www.geeksforgeeks.org/system-design/chain-responsibility-design-pattern/) - Design pattern fundamentals
- [Agentic AI patterns and workflows on AWS](https://docs.aws.amazon.com/pdfs/prescriptive-guidance/latest/agentic-ai-patterns/agentic-ai-patterns.pdf) - AWS patterns guide
- [Composable Effect Handling for Programming LLM-integrated Scripts](https://arxiv.org/pdf/2507.22048) - Academic research on handler patterns

### Symfony Bundle Standards

- [Best Practices for Reusable Bundles - Symfony Docs](https://symfony.com/doc/current/bundles/best_practices.html) - Official Symfony standards
- [The Bundle System - Symfony Docs](https://symfony.com/doc/current/bundles.html) - Bundle architecture

## Appendix: SKILL.md Template

For creating new skills, use this template:

```markdown
---
name: skill-name-here
description: Brief description of what this skill does and when to use it (include specific triggers)
allowed-tools: "Bash, Read, Write, Glob, Grep"
version: 1.0.0
---

# Skill Name

**Purpose:** One-line description of what this skill accomplishes

**When to use:**
- Trigger condition 1
- Trigger condition 2
- Trigger condition 3

## Prerequisites

What must be true before this skill can run:
- Prerequisite 1
- Prerequisite 2

## Steps

### Step 1: [Action Name]

Description of what happens in this step.

```php
// Example code
```

**Expected output:**
- What user should see

### Step 2: [Action Name]

Description of what happens in this step.

**Decision point:**
- If X, do Y
- If A, do B

### Step 3: [Action Name]

Final step description.

## Verification

How to verify the skill succeeded:
1. Check X
2. Verify Y
3. Test Z

## Troubleshooting

### Issue: [Common Problem]
**Cause:** Why this happens
**Solution:** How to fix

## Related Resources

- See llms.md: [Section Name]
- See reference: [File Name]
- GitHub Issue: #X

## Examples

### Example 1: [Scenario]
```php
// Complete example
```

### Example 2: [Scenario]
```php
// Complete example
```

## Notes

- Important note 1
- Important note 2
```

---

**End of Architecture Research Document**

**Confidence Assessment:**
- Skill structure: HIGH (based on official Claude Skills docs)
- llms.md recommendations: HIGH (based on current best practices)
- Handler chain documentation: HIGH (based on pattern literature)
- Integration strategy: MEDIUM (specific to this bundle's needs)

**Next Steps:**
1. Review this architecture with team
2. Implement Week 1 roadmap (enhance llms.md, create first skill)
3. Gather feedback from AI interactions
4. Iterate based on usage patterns
