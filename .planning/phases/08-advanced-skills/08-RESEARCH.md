# Phase 8: Advanced Skills - Research

**Researched:** 2026-02-02
**Domain:** AI diagnostic and custom handler creation skills
**Confidence:** HIGH

## Summary

This phase creates two advanced Claude Code skills: `translation-debugger` for diagnosing translation configuration issues and `custom-handler-creator` for guiding users through extending the handler chain. Research investigated three key domains: systematic debugging patterns for AI assistants, custom handler extension mechanisms in Symfony, and skill interaction design patterns.

The debugging landscape in 2026 emphasizes automated diagnostic workflows with AI assistants tracing issues backward through execution paths to identify root causes. The translation-debugger skill follows this pattern by checking entity configuration (interface, trait, attributes) first, then handler chain mapping, then runtime state — presenting issues in dependency order. The custom-handler-creator skill leverages interactive template generation, asking about field types first to tailor the handler template, then guiding priority selection with reasoning based on the existing handler chain (priorities 10-100).

Both skills build on Phase 7's established pattern: SKILL.md under 200 lines, references/ subdirectory for supporting content, and linking to llms.md for deep technical details. User decisions from CONTEXT.md lock implementation details: debugger auto-runs diagnostics on activation, handler creator offers tests separately, both use suggest-then-invoke activation mode.

**Primary recommendation:** Create diagnostic-driven debugger that identifies configuration issues systematically (missing interface → wrong attributes → handler mismatches), and use-case-first handler creator that generates tailored templates based on field type being handled. Keep skills workflow-focused, reference llms.md for handler chain internals, share common content (priority table) between skills.

## Standard Stack

### Skill Structure (Established in Phase 7)
| Component | Format | Purpose | Why Standard |
|-----------|--------|---------|--------------|
| SKILL.md | Markdown with YAML frontmatter | Main workflow instructions | Agent Skills standard, Claude Code native |
| references/ directory | Markdown or template files | Supporting documentation | Progressive disclosure, keeps SKILL.md under 200 lines |
| Shared references | Common markdown files | Content used by multiple skills | DRY principle, single source of truth |

### User Decisions (from CONTEXT.md)
| Decision | Locked Choice | Implication |
|----------|---------------|-------------|
| Debugger diagnostic flow | Automated detection on activation | Run diagnostics immediately, no open questions |
| Diagnostic order | Entity config → handler chain → runtime | Check prerequisites before downstream issues |
| Issue presentation | Dependency order | Fix blocking issues first |
| Debugger activation | Broad catch-all | "translation" + "problem/issue/wrong/broken" |
| Handler creator flow | Use case first | Ask field type, generate tailored template |
| Handler priority | Interactive selection | Suggest priority with reasoning |
| Test generation | Offer separately | Create handler first, then ask about tests |
| Invocation mode | Suggest then invoke | "Want me to run the debugger skill?" |
| Reference organization | Claude's discretion | Follow Phase 7 pattern, share where sensible |

### Handler Extension Mechanism
| Component | Purpose | Configuration |
|-----------|---------|---------------|
| TranslationHandlerInterface | Handler contract | 4 methods: supports(), translate(), handleShared(), handleEmpty() |
| Service tag | Handler registration | `tmi_translation.translation_handler` with priority attribute |
| CompilerPass | Handler chain assembly | TranslationHandlerPass collects tagged handlers |
| Priority system | Execution order | Higher numbers checked first (100=highest, 10=lowest) |

### No Installation Required
These are workflow/documentation skills - no packages needed. Skills guide Claude through:
- Reading entity files and configuration
- Analyzing Doctrine metadata
- Generating code templates
- Explaining handler chain behavior
- Writing new handler classes and tests

## Architecture Patterns

### Recommended Skill Structure

```
.claude/skills/
├── translation-debugger/
│   ├── SKILL.md                    # Diagnostic workflow (under 200 lines)
│   └── references/
│       ├── diagnostics.md          # Common diagnostic checks
│       └── handler-priority.md     # Handler chain priority table (shared)
└── custom-handler-creator/
    ├── SKILL.md                    # Handler creation workflow (under 200 lines)
    └── references/
        ├── handler-template.md     # Base handler code template
        ├── test-template.md        # Handler test template
        ├── handler-priority.md     # Handler chain priority table (shared via symlink or copy)
        └── examples.md             # Use case examples (encrypted fields, computed properties, etc.)
```

### Pattern 1: Systematic Diagnostic Workflow (translation-debugger)

**What:** Automated detection that checks configuration layers in dependency order
**When to use:** User mentions translation problem/issue/wrong/broken behavior

**Diagnostic sequence:**
```markdown
1. Entity Configuration Layer
   - [ ] Implements TranslatableInterface?
   - [ ] Uses TranslatableTrait?
   - [ ] Has $tuuid property initialized?
   - [ ] Has $locale property?

2. Attribute Configuration Layer
   - [ ] Bidirectional relations marked SharedAmongstTranslations? (ERROR: throws RuntimeException)
   - [ ] EmptyOnTranslate on non-nullable fields? (ERROR: LogicException)
   - [ ] Conflicting attributes (both shared and empty)?

3. Handler Chain Mapping Layer
   - [ ] Field type matches expected handler?
   - [ ] Handler priority correct for field behavior?
   - [ ] Custom handler properly registered with tag and priority?

4. Runtime Configuration Layer
   - [ ] Target locale in tmi_translation.locales?
   - [ ] Doctrine filter enabled (if queries wrong)?
   - [ ] Entity persisted after translation?

Present issues with: "Found 3 issues. Fix in this order: [blocking issues first]"
```

**Why this works:** User decision specifies automated detection, dependency order presentation, and offer-to-fix approach. Matches 2026 debugging patterns where AI traces backward from symptom to root cause.

**Source:** Based on systematic-debugging skill pattern (95% first-time fix rate) and llms.md troubleshooting section structure.

### Pattern 2: Use-Case-First Template Generation (custom-handler-creator)

**What:** Ask field type first, generate tailored handler template with contextual examples
**When to use:** User wants to create custom handler for unsupported field type

**Interactive flow:**
```markdown
1. Identify Use Case
   Ask: "What field type needs custom handling?"
   Examples shown:
   - Encrypted fields (decrypt before cloning, re-encrypt)
   - Computed properties (recalculate in target locale)
   - Value objects (custom clone logic)
   - Third-party objects (no Doctrine metadata)

2. Determine Handler Behavior
   Ask: "What should happen during translation?"
   - Copy value as-is (like ScalarHandler)
   - Clone and customize (like EmbeddedHandler)
   - Recursively translate (like TranslatableEntityHandler)
   - Custom logic (explain approach)

3. Suggest Priority with Reasoning
   Show current chain: [PrimaryKey=100, Scalar=90, Embedded=80, ... Doctrine=10]
   Ask: "Your handler detects [field type]. Should it run before or after [related handler]?"
   Suggest: "Priority 75 (after Embedded, before relationship handlers) — ensures value objects handled first"

4. Generate Template
   Show diff-style handler class with:
   - supports() method checking field type
   - translate() method with TODO for custom logic
   - handleShared/handleEmpty stubs
   - Inline comments explaining each method

5. Offer Tests Separately
   After handler created: "Want me to add PHPUnit tests?"
```

**Why this works:** User decision specifies use-case-first, interactive priority, and separate test offer. Generates focused templates instead of generic boilerplate.

**Source:** Phase 7 examples-first pattern, GenAI guided workflow patterns (2026), Symfony handler extension mechanisms.

### Pattern 3: Handler Priority Decision Matrix

**What:** Interactive guidance for choosing handler priority based on field type
**When to use:** During custom-handler-creator workflow, after determining handler purpose

**Decision matrix structure:**
```markdown
## Handler Priority Decision Matrix

Your handler processes: [user's field type]

### Priority Ranges

**100-90: Pre-processing (Never Translate)**
- 100: Primary keys (always null)
- 90: Simple values (scalars, DateTime)
Use if: Your field should NEVER be modified during translation

**90-80: Value Objects**
- 80: Embedded objects
Use if: Your field is a value object that needs cloning

**70-30: Relationships**
- 70: Bidirectional ManyToOne
- 60: Bidirectional OneToMany
- 50: Bidirectional OneToOne
- 40: Bidirectional ManyToMany
- 30: Unidirectional ManyToMany
Use if: Your field is a Doctrine relation

**20-10: Fallback Handlers**
- 20: Translatable entities (recursive)
- 10: Doctrine objects (generic)
Use if: Your field is caught by these but needs custom handling

### Conflicts to Avoid

- Don't use priority 100-80 for relations (ScalarHandler would catch first)
- Don't use priority lower than existing handler for same field type (never reached)
- Custom handlers typically use priorities 75, 65, 55, 45, 35, 25, 15 (between standard handlers)

### Recommendation for [user's field type]

Priority: [X] — Reasoning: [why this priority makes sense for their use case]
```

**Why this works:** Provides contextual guidance based on handler chain structure, prevents common priority mistakes, explains reasoning.

**Source:** llms.md handler chain decision tree, Symfony priority-based service tagging patterns.

### Pattern 4: Diagnostic Result Presentation

**What:** Show detected issues in dependency order with fix-blocking relationships
**When to use:** After debugger completes all diagnostic checks

**Presentation format:**
```markdown
## Translation Diagnostics: Product Entity

Found 3 issues (1 blocking, 2 warnings):

### BLOCKING
**1. Missing TranslatableInterface**
   Problem: Product class doesn't implement TranslatableInterface
   Impact: EntityTranslator won't recognize entity as translatable
   Fix: Add `implements TranslatableInterface` and `use TranslatableTrait`

   Want me to fix this? [blocks issues #2, #3]

### WARNINGS
**2. SharedAmongstTranslations on Bidirectional Relation**
   Problem: $category (ManyToOne with inversedBy) marked as shared
   Impact: RuntimeException thrown during translation
   Location: Product::$category property, line 45
   Fix: Remove #[SharedAmongstTranslations] attribute

   Want me to fix this?

**3. EmptyOnTranslate on Non-Nullable Field**
   Problem: $slug (string, not nullable) marked EmptyOnTranslate
   Impact: LogicException during translation
   Location: Product::$slug property, line 67
   Fix: Either make field nullable (?string) or remove attribute

   Want me to fix this?

✓ Handler chain: All fields map to correct handlers
✓ Runtime config: Locale 'fr' is allowed
✓ Doctrine filter: translation_locale is enabled
```

**Why this works:** User decision specifies dependency order, offer-to-fix approach, and present-then-ask pattern. Shows what's working (✓) alongside issues.

**Source:** llms.md troubleshooting section, 2026 AI debugging tool patterns (automated root cause analysis).

### Pattern 5: Activation Trigger Design

**What:** Skill activation descriptions that balance broad coverage with false positive prevention
**When to use:** Frontmatter description field in SKILL.md

**Debugger triggers (broad catch-all per user decision):**
```yaml
description: Diagnose translation configuration issues when translation behavior is wrong, broken, not working, or causing errors. Use when user reports translation problems, unexpected behavior, runtime exceptions, or asks "why isn't translation working?". Systematically checks entity setup, attributes, handler chain, and runtime configuration.
```

**Handler creator triggers (Claude's discretion - specific patterns):**
```yaml
description: Guide users through creating custom translation handlers for unsupported field types. Use when user wants to handle encrypted fields, computed properties, value objects, third-party objects, or other field types not covered by standard handlers. Generates handler template with correct priority and interface implementation.
```

**Why this works:** Debugger uses natural problem language ("wrong", "broken", "not working") that users naturally say. Handler creator uses technical terms and specific use cases to avoid false triggers on general handler questions.

**Source:** Phase 7 trigger reliability research, Claude Code skill activation patterns.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Diagnostic checks | Custom validation | Read llms.md troubleshooting section | All common issues already documented |
| Handler templates | String concatenation | Template markdown with placeholders | Maintainable, shows full context |
| Priority calculation | Complex algorithm | Decision matrix with reasoning | User understands why, can override |
| Test templates | Generate from scratch | Adapt existing handler test pattern | Tests match bundle conventions |
| Entity introspection | Regex parsing | Doctrine ClassMetadata API | Reliable, handles all edge cases |
| Handler registration | Explain YAML syntax | Show complete service definition example | User can copy-paste, less error-prone |

**Key insight:** These skills are workflow orchestrators, not teachers. Reference llms.md for concepts (handler chain, attributes, troubleshooting), focus on step-by-step guidance and code generation.

## Common Pitfalls

### Pitfall 1: Debugger Asks Open-Ended Questions
**What goes wrong:** "What's wrong with your translation?" — user doesn't know, that's why they need debugger
**Why it happens:** Not following user decision for automated detection on activation
**How to avoid:** Run diagnostics immediately, present findings, then offer fixes
**Warning signs:** User responds "I don't know, you tell me"

### Pitfall 2: Handler Template Lacks Context
**What goes wrong:** Generated handler has empty supports() method with TODO
**Why it happens:** Not asking about field type first to tailor template
**How to avoid:** User decision specifies use-case-first — ask field type, generate contextual supports() logic
**Warning signs:** User asks "What do I put in supports()?"

### Pitfall 3: Wrong Handler Priority Suggested
**What goes wrong:** Custom handler never reached because priority too low, or catches wrong fields because priority too high
**Why it happens:** Not considering existing handler chain structure
**How to avoid:** Show decision matrix, explain why priority X makes sense for their field type, let user confirm
**Warning signs:** Handler works in isolation but not in chain

### Pitfall 4: Diagnostics Check Wrong Layer First
**What goes wrong:** Check runtime config before entity configuration, suggest fixing locale issue when real problem is missing interface
**Why it happens:** Not following user decision for dependency-order checks
**How to avoid:** Entity config → attributes → handler chain → runtime. Check prerequisites first.
**Warning signs:** Fix doesn't resolve issue, reveals blocking problem

### Pitfall 5: Shared References Duplicated
**What goes wrong:** Handler priority table exists in both skill directories with different content
**Why it happens:** Not following DRY principle for shared content
**How to avoid:** User decision allows shared references where sensible — priority table is shared, skill-specific logic is separate
**Warning signs:** Updating one file requires remembering to update the other

### Pitfall 6: Test Generation Interrupts Flow
**What goes wrong:** After showing handler template, immediately generate tests without asking
**Why it happens:** Not following user decision for separate test offer
**How to avoid:** Create handler first, confirm it's correct, THEN ask "Want me to add tests?"
**Warning signs:** User says "Wait, I need to modify the handler first"

### Pitfall 7: Trigger Description Too Vague or Too Specific
**What goes wrong:** Debugger doesn't activate when user says "translation broken" (too specific) or activates on "how do handlers work?" (too vague)
**Why it happens:** Not balancing coverage with false positive prevention
**How to avoid:** Debugger uses broad problem language, handler creator uses specific technical terms. User decision specifies debugger=broad, handler=Claude's discretion.
**Warning signs:** Skill never triggers (too specific) or triggers incorrectly (too vague)

## Code Examples

### Debugger Skill Frontmatter

```yaml
---
name: translation-debugger
description: Diagnose translation configuration issues when translation behavior is wrong, broken, not working, or causing errors. Use when user reports translation problems, unexpected behavior, runtime exceptions, or asks "why isn't translation working?". Systematically checks entity setup, attributes, handler chain, and runtime configuration.
---
```

**Why these fields:**
- `name`: Simple, descriptive, becomes `/translation-debugger` command
- `description`: Broad catch-all trigger phrases per user decision, includes natural problem language

### Handler Creator Skill Frontmatter

```yaml
---
name: custom-handler-creator
description: Guide users through creating custom translation handlers for unsupported field types. Use when user wants to handle encrypted fields, computed properties, value objects, third-party objects, or other field types not covered by standard handlers. Generates handler template with correct priority and interface implementation.
---
```

**Why these fields:**
- `name`: Descriptive of purpose, avoids false triggers on general "handler" questions
- `description`: Specific use cases (encrypted, computed, value objects) and technical terms, Claude's discretion for avoiding false positives

### Diagnostic Check Pattern (Debugger)

```markdown
## Diagnostic Workflow

When activated, run checks in this order:

### 1. Entity Configuration
Read entity file, check:
- Implements TranslatableInterface?
  - NO: BLOCKING — "Entity must implement TranslatableInterface"
- Uses TranslatableTrait?
  - NO: BLOCKING — "Entity must use TranslatableTrait"
- Has $tuuid property (if not using trait)?
  - NO: BLOCKING — "Missing $tuuid property for grouping translations"

### 2. Attribute Configuration
For each property in entity:
- Is it bidirectional relation (has inversedBy or mappedBy)?
  - YES + has #[SharedAmongstTranslations]: ERROR — "Bidirectional relations cannot be shared (RuntimeException)"
- Has #[EmptyOnTranslate]?
  - YES + property not nullable: ERROR — "EmptyOnTranslate requires nullable property (LogicException)"
- Has both #[SharedAmongstTranslations] and #[EmptyOnTranslate]?
  - YES: WARNING — "Shared takes precedence, EmptyOnTranslate ignored"

### 3. Handler Chain Mapping
For each translatable field:
- Determine expected handler from field type
- Check if custom handler might intercept (look for tagged services)
- Verify priority order makes sense

### 4. Runtime Configuration
Check bundle config:
- Is target locale in tmi_translation.locales?
  - NO: ERROR — "Locale not allowed (LogicException)"
- Is Doctrine filter enabled (if query behavior wrong)?
  - NO: WARNING — "Filter not enabled, queries return all locales"

### 5. Present Results
Group by severity: BLOCKING → ERROR → WARNING
Show in dependency order (blocking issues first)
Offer to fix each issue: "Want me to fix this?"
```

**Source:** Based on llms.md troubleshooting section (lines 626-775), systematic debugging patterns (2026 research).

### Handler Template Generation Pattern (Handler Creator)

```markdown
## Template Generation

After determining field type and priority, generate:

\`\`\`php
<?php

declare(strict_types=1);

namespace App\Translation\Handler;

use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\Handlers\TranslationHandlerInterface;

/**
 * Handles translation of [user's field type description].
 *
 * Use case: [user's specific use case - e.g., "Decrypts encrypted fields before cloning, re-encrypts for target"]
 */
final class [UserFieldType]Handler implements TranslationHandlerInterface
{
    public function supports(TranslationArgs \$args): bool
    {
        // TODO: Implement detection logic for [user's field type]
        // Example: Check if property has #[Encrypted] attribute
        // Example: Check if value is instance of [specific class]

        \$data = \$args->getDataToBeTranslated();
        \$property = \$args->getProperty();

        // Your detection logic here
        return false; // Replace with actual check
    }

    public function handleSharedAmongstTranslations(TranslationArgs \$args): mixed
    {
        // When field is marked #[SharedAmongstTranslations]
        // Return same instance to share across all translations
        return \$args->getDataToBeTranslated();
    }

    public function handleEmptyOnTranslate(TranslationArgs \$args): mixed
    {
        // When field is marked #[EmptyOnTranslate]
        // Return null (scalars) or new empty instance (objects)
        return null;
    }

    public function translate(TranslationArgs \$args): mixed
    {
        \$data = \$args->getDataToBeTranslated();

        // TODO: Implement translation logic for [user's field type]
        // Common patterns:
        // - Scalars: return \$data; (copy as-is)
        // - Objects: return clone \$data; (shallow clone)
        // - Special: custom logic for your use case

        return \$data; // Replace with actual translation logic
    }
}
\`\`\`

Next, register the handler in services.yaml:

\`\`\`yaml
# config/services.yaml
services:
    App\Translation\Handler\[UserFieldType]Handler:
        tags:
            - { name: tmi_translation.translation_handler, priority: [calculated priority] }
\`\`\`

**Priority reasoning:** [X] places handler [before/after] [related handler] because [explanation for user's use case]

Want me to add PHPUnit tests for this handler?
```

**Why this works:**
- Contextual TODO comments guide implementation
- Shows all 4 required methods with purpose
- Includes service registration (users often forget)
- Explains priority reasoning
- Offers tests separately per user decision

**Source:** Based on ScalarHandler.php, EmbeddedHandler.php structure, services.yaml tagging pattern.

### Handler Priority Table (Shared Reference)

```markdown
# Handler Priority Reference

This table shows the standard handler chain priorities. Custom handlers should fit between these.

| Priority | Handler | Detects | Behavior |
|----------|---------|---------|----------|
| 100 | PrimaryKeyHandler | #[ORM\Id] properties | Always returns null (IDs never translated) |
| 90 | ScalarHandler | Scalars, DateTime | Copies value as-is |
| 80 | EmbeddedHandler | #[ORM\Embedded] | Clones embedded object |
| 70 | BidirectionalManyToOneHandler | ManyToOne with inversedBy | Translates parent, sets relation |
| 60 | BidirectionalOneToManyHandler | OneToMany with mappedBy | Translates collection, maintains inverse |
| 50 | BidirectionalOneToOneHandler | OneToOne with mappedBy/inversedBy | Translates related entity, maintains link |
| 40 | BidirectionalManyToManyHandler | ManyToMany bidirectional | Translates both sides |
| 30 | UnidirectionalManyToManyHandler | ManyToMany unidirectional | Translates one side |
| 20 | TranslatableEntityHandler | TranslatableInterface entities | Recursively translates |
| 10 | DoctrineObjectHandler | Any Doctrine-managed object | Generic cloning and translation |

## Custom Handler Priority Selection

**Insert between standard handlers:** Use priorities like 75, 65, 55, 45, 35, 25, 15

**Examples:**
- Priority 75: Between Embedded (80) and ManyToOne (70) — for special value objects
- Priority 65: Between ManyToOne (70) and OneToMany (60) — for custom relation handling
- Priority 15: Between TranslatableEntity (20) and DoctrineObject (10) — for specific object types

**Rule:** Higher priority = checked earlier. If your handler should catch fields before [handler X], use priority higher than X.

**See also:** llms.md "Handler Chain Decision Tree" for complete routing logic
```

**Usage:** This file is referenced by both debugger (for handler chain diagnostics) and handler creator (for priority selection). Single source of truth.

### Test Template Pattern (Handler Creator)

```markdown
## Handler Test Template

\`\`\`php
<?php

declare(strict_types=1);

namespace App\Tests\Translation\Handler;

use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use App\Translation\Handler\[UserFieldType]Handler;

final class [UserFieldType]HandlerTest extends TestCase
{
    private [UserFieldType]Handler \$handler;

    protected function setUp(): void
    {
        \$this->handler = new [UserFieldType]Handler(/* dependencies */);
    }

    /** @test */
    public function it_supports_[user_field_type](): void
    {
        // Arrange: Create entity with [user's field type]
        \$entity = /* ... */;
        \$args = new TranslationArgs(\$entity);
        \$args->setProperty(/* ... */);

        // Act
        \$result = \$this->handler->supports(\$args);

        // Assert
        self::assertTrue(\$result);
    }

    /** @test */
    public function it_does_not_support_other_field_types(): void
    {
        // Arrange: Create entity with different field type
        \$entity = /* ... */;
        \$args = new TranslationArgs(\$entity);

        // Act
        \$result = \$this->handler->supports(\$args);

        // Assert
        self::assertFalse(\$result);
    }

    /** @test */
    public function it_translates_[user_field_type]_correctly(): void
    {
        // Arrange
        \$entity = /* ... */;
        \$args = new TranslationArgs(\$entity, 'en', 'fr');

        // Act
        \$result = \$this->handler->translate(\$args);

        // Assert
        // Verify translated value matches expectations
        self::assertNotNull(\$result);
        // Add specific assertions for your use case
    }

    /** @test */
    public function it_shares_when_marked_shared_amongst_translations(): void
    {
        // Arrange
        \$entity = /* ... */;
        \$args = new TranslationArgs(\$entity);

        // Act
        \$result = \$this->handler->handleSharedAmongstTranslations(\$args);

        // Assert
        self::assertSame(\$entity->getYourField(), \$result); // Same instance
    }

    /** @test */
    public function it_returns_null_when_marked_empty_on_translate(): void
    {
        // Arrange
        \$entity = /* ... */;
        \$args = new TranslationArgs(\$entity);

        // Act
        \$result = \$this->handler->handleEmptyOnTranslate(\$args);

        // Assert
        self::assertNull(\$result);
    }
}
\`\`\`

This test structure matches the bundle's handler test conventions. Customize the arrange/act/assert sections for your specific field type.
\`\`\`

**Source:** Based on BidirectionalManyToOneHandlerTest.php pattern (lines 19-99), PHPUnit conventions.

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Manual debugging documentation | AI-powered automated diagnostics | 2026 | Systematic workflows with root cause tracing, 95% first-time fix rate |
| Generic handler guides | Use-case-first template generation | 2026 | Tailored code based on field type, reduces boilerplate errors |
| Static skill descriptions | Conversational activation patterns | Claude Code recent | "Want me to run debugger skill?" suggest-then-invoke mode |
| Duplicated reference content | Shared references/ between skills | Phase 7 pattern | DRY principle, single source of truth for priority tables |
| Code examples in documentation | Interactive template generation in skills | 2026 GenAI workflow | Users get copy-paste code, not reference to look up |

**Debugging in 2026:**
Research shows AI debugging tools now emphasize automated diagnostic workflows over manual checking. ChatDBG-style conversational debugging, root cause analysis traced backward through execution paths, and interactive fix suggestions are standard patterns. The translation-debugger skill follows these patterns by automatically running checks on activation and presenting issues in dependency order.

**Handler Extension in 2026:**
Symfony's priority-based service tagging remains the standard mechanism for handler chain extension. Tagged services with priority attribute control execution order. The custom-handler-creator skill leverages this by generating complete service definitions with priority reasoning, not just handler classes.

## Open Questions

1. **Namespace Organization: Independent vs Unified**
   - What we know: User decision gives Claude discretion on namespace
   - What's unclear: Should skills be `.claude/skills/translation-debugger/` and `.claude/skills/custom-handler-creator/` (independent) OR `.claude/skills/translation-bundle/debugger/` and `.claude/skills/translation-bundle/handler-creator/` (unified under bundle namespace)?
   - Recommendation: **Independent namespaces** following Phase 7 pattern. Skills are independently invocable (`/translation-debugger`, `/custom-handler-creator`). Shared references via relative paths (`../shared/handler-priority.md`) or duplication if symlinks unsupported.

2. **Reference Split Criteria**
   - What we know: User decision allows Claude discretion following Phase 7 pattern, references for details, inline summaries for quick reference
   - What's unclear: Exact line count threshold for splitting content to references/
   - Recommendation: Same as Phase 7 — keep workflow in SKILL.md (activation, steps, decision points), move to references/ when skill approaches 200 lines. Priority table, diagnostic checks, and templates go to references/. Link llms.md for deep details (handler chain internals, troubleshooting).

3. **Diagnostic Check Depth**
   - What we know: Check entity config → attributes → handler chain → runtime, present in dependency order
   - What's unclear: How deep to trace handler chain issues (e.g., detect custom handler with wrong priority)
   - Recommendation: **Surface-level detection** for Phase 8. Detect common issues (missing interface, wrong attributes per llms.md troubleshooting). Deep handler chain analysis (tracing why specific field processed by wrong handler) is advanced — defer to user interpretation or future enhancement.

4. **Custom Handler Use Case Examples**
   - What we know: User decision gives Claude discretion for example use cases (encrypted fields, computed properties, value objects mentioned)
   - What's unclear: Full list of compelling examples
   - Recommendation: **5-7 concrete examples** in references/examples.md:
     - Encrypted fields (decrypt before clone, re-encrypt)
     - Computed properties (recalculate in locale)
     - Value objects without Doctrine metadata
     - Third-party library objects
     - Cached/lazy-loaded fields (invalidate cache)
     - File paths/URLs (transform for locale)
     - GeoIP location data (country names)

5. **Test Template Coverage**
   - What we know: Offer tests separately after handler created
   - What's unclear: Test depth (just supports() or full coverage?)
   - Recommendation: **Full TranslationHandlerInterface coverage** — tests for supports(), translate(), handleShared(), handleEmpty(). Matches bundle convention (see BidirectionalManyToOneHandlerTest.php). Templates include all 5 test methods, user customizes arrange/act/assert.

## Sources

### Primary (HIGH confidence)
- D:\www\tmi\translation-bundle\llms.md (lines 33-157: Handler Chain Decision Tree, lines 626-775: Troubleshooting)
- D:\www\tmi\translation-bundle\src\Translation\Handlers\ScalarHandler.php — Simple handler pattern
- D:\www\tmi\translation-bundle\src\Translation\Handlers\EmbeddedHandler.php — Complex handler with dependencies
- D:\www\tmi\translation-bundle\src\Translation\Handlers\TranslationHandlerInterface.php — Handler contract
- D:\www\tmi\translation-bundle\src\Resources\config\services.yaml (lines 32-92) — Handler registration with priority tags
- D:\www\tmi\translation-bundle\src\DependencyInjection\Compiler\TranslationHandlerPass.php — Handler chain assembly
- D:\www\tmi\translation-bundle\tests\Translation\Handlers\BidirectionalManyToOneHandlerTest.php — Handler test pattern
- D:\www\tmi\translation-bundle\.planning\phases\07-core-implementation-skill\07-RESEARCH.md — Phase 7 skill pattern (established structure)
- D:\www\tmi\translation-bundle\.claude\skills\entity-translation-setup\SKILL.md — Phase 7 implementation (193 lines, under 200 requirement)

### Secondary (MEDIUM confidence)
- [Extend Claude with skills - Claude Code Docs](https://code.claude.com/docs/en/skills) — Official skill structure
- [systematic-debugging skill - ChrisWiles/claude-code-showcase](https://github.com/ChrisWiles/claude-code-showcase/blob/main/.claude/skills/systematic-debugging/SKILL.md) — 95% first-time fix rate, four-phase methodology
- [Symfony Messenger: Sync & Queued Message Handling](https://symfony.com/doc/current/messenger.html) — Priority-based handler configuration
- [How to Work with Service Tags](https://symfony.com/doc/current/service_container/tags.html) — Service tag priority attribute
- [Events and Event Listeners](https://symfony.com/doc/current/event_dispatcher.html) — Priority ordering patterns

### Tertiary (LOW confidence)
- [AI-Assisted Debugging 2026: Anomaly Detection in Firmware](https://promwad.com/news/ai-assisted-debugging-2026-anomaly-detection-firmware) — 2026 debugging automation trends
- [AI-powered debugging tools - Graphite](https://graphite.com/guides/ai-powered-debugging-tools) — Conversational debugging patterns
- [My GenAI development workflow: Idea to Code](https://microservices.io/post/architecture/2026/01/29/about-idea-to-code.html) — Guided workflow patterns in 2026
- [Template Method in PHP - Refactoring Guru](https://refactoring.guru/design-patterns/template-method/php/example) — Template generation pattern

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — User decisions lock implementation details, Phase 7 establishes patterns, Symfony service tagging is stable
- Architecture: HIGH — Diagnostic workflow from llms.md troubleshooting + 2026 debugging patterns, handler templates from existing implementations
- Pitfalls: HIGH — Derived from user decisions (automated vs interactive flows), common Symfony priority mistakes, Phase 7 lessons

**Research date:** 2026-02-02
**Valid until:** 60 days (skill patterns stable, Symfony handler mechanism unchanged since 4.1, user decisions locked)
