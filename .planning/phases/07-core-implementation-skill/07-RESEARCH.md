# Phase 7: Core Implementation Skill - Research

**Researched:** 2026-02-02
**Domain:** Claude Code skills for guided entity translation setup
**Confidence:** HIGH

## Summary

This phase creates a Claude Code skill (`entity-translation-setup`) that guides users through making Doctrine entities translatable. The research investigated three key domains: Claude Code skill structure and best practices, guided workflow patterns for information gathering, and code template presentation techniques.

Claude Code skills follow the Agent Skills open standard with a simple structure: YAML frontmatter for metadata and Markdown content for instructions. Skills under 200 lines (user requirement) are achievable by focusing on workflow guidance and referencing existing documentation rather than duplicating content. The user has already decided on key implementation details through CONTEXT.md: auto-activation triggers, information gathering flow, diff-style code generation, and examples-first attribute guidance.

**Primary recommendation:** Create a workflow-driven skill with conditional logic (quick mode vs guided mode), code template generation with inline explanations, and smart field detection. Reference llms.md for detailed handler chain behavior rather than duplicating. Keep skill focused on orchestrating the setup process, not teaching the underlying concepts.

## Standard Stack

### Skill Structure
| Component | Format | Purpose | Why Standard |
|-----------|--------|---------|--------------|
| SKILL.md | Markdown with YAML frontmatter | Main skill instructions | Agent Skills standard, Claude Code native |
| Frontmatter | YAML between --- markers | Metadata for triggering | Required by Claude Code |
| Supporting files | Optional references/ directory | Detailed docs loaded as needed | Progressive disclosure pattern |

### User Decisions (from CONTEXT.md)
| Decision | Locked Choice | Implication |
|----------|---------------|-------------|
| Trigger phrases | "make this entity translatable", "add translations to" | Must include in description field |
| Quick mode | Offer defaults vs walkthrough | Conditional workflow needed |
| Code presentation | Diff-style with + markers | Template with context lines |
| Attribute guidance | Examples-first | Show use cases before asking |
| Relationship detection | Auto-detect and prompt | Requires entity introspection |

### No Installation Required
This is a documentation/workflow skill - no packages needed. The skill guides Claude through:
- Reading entity files
- Generating code diffs
- Explaining TranslatableTrait
- Writing changes via Edit tool

## Architecture Patterns

### Recommended Skill Structure

```
.claude/skills/entity-translation-setup/
├── SKILL.md                    # Main workflow (under 200 lines per requirement)
└── references/                 # Optional if needed for examples
    └── examples.md             # Code templates (if SKILL.md exceeds limit)
```

### Pattern 1: Conditional Workflow (Quick vs Guided Mode)

**What:** Offer two paths based on user preference at start
**When to use:** User decision specifies "Want me to use defaults, or walk through each decision?"

**Structure:**
```markdown
## Activation

When user requests translatable entity setup:

1. Announce: "I'll use the entity-translation-setup skill to guide you through this."
2. Ask mode preference: "Want me to use defaults (quick mode), or walk through each decision?"

## Quick Mode Workflow

[Steps with defaults]

## Guided Mode Workflow

[Steps with questions]
```

**Why this works:** Matches user's locked decision, avoids overwhelming users who want speed

### Pattern 2: Information Gathering with Smart Detection

**What:** Read entity first, then show only translatable fields
**When to use:** After reading entity file, before asking user questions

**Flow:**
1. Read entity file
2. Parse field types via reflection or code analysis
3. Hide non-translatable (IDs, timestamps, generated values)
4. Show fields grouped by type (scalars, relations, embedded)
5. Auto-detect relationships with inversedBy/mappedBy
6. Prompt: "Product has OneToMany to ProductImage. Should images be translated together?"

**Why this works:** User decision specifies auto-detection and smart prompting

### Pattern 3: Diff-Style Code Presentation

**What:** Show changes with context lines and + markers, not full file replacement
**When to use:** Before applying changes to entity

**Template format:**
```php
class Product implements TranslatableInterface
{
+   use TranslatableTrait;    // Adds tuuid, locale, translations fields

    #[ORM\Column(length: 255)]
    private string $name;       // Will be translated per locale

+   #[SharedAmongstTranslations]
    #[ORM\Column(type: Types::DECIMAL)]
    private string $price;      // Same across all locales (product price doesn't change by language)
```

**Why this works:** User decision specifies diff-style, inline comments explain WHY

### Pattern 4: Examples-First Attribute Decision

**What:** Show concrete use cases before asking user to decide
**When to use:** When determining SharedAmongstTranslations vs EmptyOnTranslate

**Guidance structure:**
```markdown
SharedAmongstTranslations examples:
- Product SKU: "LAPTOP-123" is same in all languages
- Creation date: Created timestamp doesn't change per locale
- Price: $999.00 is the price regardless of language
- Category: Product belongs to same category in all languages

EmptyOnTranslate examples:
- Slug: "laptop-computer" (English) needs new value "ordinateur-portable" (French)
- SEO URL: URL path must be regenerated per locale
- Search keywords: Must be re-entered in target language
```

**Why this works:** User decision specifies examples-first approach, helps users make correct decisions

### Pattern 5: TranslatableTrait Explanation Before User Fields

**What:** Explain what trait provides before moving to user's own fields
**When to use:** Right after adding trait, before attribute decisions

**Content:**
```markdown
TranslatableTrait adds three fields automatically:
1. $tuuid (Translation UUID): Groups all language variants together
   - Marked #[SharedAmongstTranslations] automatically
   - Same tuuid = same product in different languages
2. $locale: Identifies which language this entity represents
3. $translations: Collection linking to sibling translations

You DON'T need to add these - they're already in the trait.
Now let's configure YOUR fields...
```

**Why this works:** User decision specifies detailed explanation, prevents confusion

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Code templates | String concatenation | Template with placeholders | Maintainable, readable |
| Field type detection | Regex parsing | Read entity + analyze Doctrine attributes | Reliable, handles edge cases |
| Handler chain explanation | Copy from llms.md | Reference llms.md sections | Avoids duplication, stays current |
| Entity validation | Custom checks | Trust Doctrine metadata | Doctrine already validates |

**Key insight:** The skill is a workflow orchestrator, not a teacher. Reference existing documentation (llms.md) for concepts. Focus on the setup process.

## Common Pitfalls

### Pitfall 1: Skill Description Too Vague
**What goes wrong:** Claude doesn't trigger skill when user asks to make entity translatable
**Why it happens:** Description doesn't include natural trigger phrases
**How to avoid:** Include exact phrases from user decisions: "make this entity translatable", "add translations to"
**Warning signs:** Skill appears in `/` menu but never auto-triggers

### Pitfall 2: Duplicating llms.md Content
**What goes wrong:** Skill exceeds 200 lines, violates user requirement
**Why it happens:** Trying to teach handler chain instead of referencing
**How to avoid:** Reference llms.md sections, don't copy content. Example: "See llms.md Handler Chain Decision Tree for how fields are processed"
**Warning signs:** SKILL.md contains handler priority tables or decision trees

### Pitfall 3: Asking Questions Without Context
**What goes wrong:** User confused by questions about fields they don't recognize
**Why it happens:** Asking about attributes before showing current entity state
**How to avoid:** Read entity first, show current fields, then ask
**Warning signs:** User says "What field are you talking about?"

### Pitfall 4: Showing Full File Instead of Diff
**What goes wrong:** User loses track of what changed
**Why it happens:** Generating complete file replacement
**How to avoid:** Show context lines (unchanged) and + markers (added). User decision locked this approach
**Warning signs:** Code blocks show entire class definition

### Pitfall 5: Technical Explanation Before Examples
**What goes wrong:** User makes wrong attribute decisions
**Why it happens:** Explaining SharedAmongstTranslations concept before showing use cases
**How to avoid:** Examples first (SKU, price, date), then ask "Which of YOUR fields are like this?"
**Warning signs:** User asks "I don't understand, can you give an example?"

### Pitfall 6: Not Waiting for Confirmation
**What goes wrong:** Changes applied before user reviews
**Why it happens:** Skipping "Apply these changes?" step
**How to avoid:** User decision specifies "Ask before applying: wait for user confirmation"
**Warning signs:** User surprised by changes, asks to undo

### Pitfall 7: Forgetting Migration Reminder
**What goes wrong:** User's database out of sync with entity changes
**Why it happens:** Not showing migration command after changes
**How to avoid:** User decision specifies show `bin/console doctrine:migrations:diff` after changes
**Warning signs:** User asks "How do I update database?"

## Code Examples

### Skill Frontmatter (Based on User Decisions)

```yaml
---
name: entity-translation-setup
description: Guide users through making Doctrine entities translatable with TranslatableInterface, TranslatableTrait, and attribute configuration. Use when user asks to "make this entity translatable", "add translations to [Entity]", or "translate [Entity] fields".
allowed-tools: Read, Edit, Grep
---
```

**Why these fields:**
- `name`: Simple, descriptive, becomes `/entity-translation-setup` command
- `description`: Includes natural trigger phrases from user decisions
- `allowed-tools`: Read entities, make changes, search for relationships
- No `disable-model-invocation`: User wants auto-activation
- No `user-invocable: false`: User should be able to invoke manually too

### Workflow Structure (Condensed Example)

```markdown
## When to Use This Skill

Use when user wants to make a Doctrine entity translatable.

## Activation

Announce: "I'll use the entity-translation-setup skill to guide you through this."

## Step 1: Read Entity

Read the entity file user specified.

## Step 2: Choose Mode

Ask: "Want me to use defaults (quick mode), or walk through each decision?"

## Quick Mode

[Default choices: translate scalars, share relations, skip EmptyOnTranslate]

## Guided Mode

### 2.1: Explain TranslatableTrait

"TranslatableTrait adds three fields:
- $tuuid: Groups translations (already marked SharedAmongstTranslations)
- $locale: Which language
- $translations: Collection of siblings

You don't add these - the trait provides them."

### 2.2: Identify Fields to Translate

Show fields grouped:
- Scalars: [list detected scalar fields]
- Relations: [list detected relations]
- Embedded: [list detected embedded objects]

Ask: "Which fields should be translated per locale?"
[User selects, defaults to all scalars]

### 2.3: Identify SharedAmongstTranslations

"SharedAmongstTranslations marks fields that are SAME in all languages.

Examples:
- Product SKU: 'LAPTOP-123' same in English and French
- Price: $999.00 doesn't change by language
- Creation date: Timestamp same across locales

Which fields should be shared?"
[User selects from relations and specific scalars]

### 2.4: Identify EmptyOnTranslate (Optional)

"EmptyOnTranslate clears field value when translating.

Examples:
- Slug: 'laptop-computer' (EN) → needs new value in French
- SEO URL: Must be regenerated per locale

Which fields should be empty on translate?"
[User selects, usually none or just slug fields]

## Step 3: Auto-Detect Relationships

For each OneToMany/ManyToMany detected:
Ask: "[Entity] has OneToMany to [Related]. Should [related] be translated together?"

## Step 4: Generate Diff

Show changes with + markers and inline comments:

[Code example from Pattern 3 above]

Ask: "Apply these changes?"

## Step 5: Apply Changes

Use Edit tool to apply changes.

Show: "Run migration: `bin/console doctrine:migrations:diff`"

## Reference

For handler chain details, see llms.md "Handler Chain Decision Tree" section.
```

### Smart Field Suggestions (Pattern)

```php
// Skill logic for smart suggestions
Common field patterns:
- price, cost, amount → Suggest SharedAmongstTranslations (money same across locales)
- slug, seoUrl, permalink → Suggest EmptyOnTranslate (needs new value per locale)
- createdAt, updatedAt → Suggest SharedAmongstTranslations (timestamps don't translate)
- name, title, description → Default translated (content differs per locale)
- category, author, owner → Suggest SharedAmongstTranslations (relationships same across locales)

Ask with suggestion: "Price field detected. Usually prices are shared across locales (same $999 whether page is English or French). Mark as SharedAmongstTranslations?"
```

### Diff Template Format

```php
// Template structure for showing changes
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;
+ use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;  // For shared fields
+ use Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;           // For cleared fields (if needed)

#[ORM\Entity]
- class Product
+ class Product implements TranslatableInterface
{
+   use TranslatableTrait;  // Adds: tuuid (groups translations), locale (which language), translations (siblings)

    #[ORM\Id]
    private ?int $id = null;  // Never translated - IDs are database-generated

    #[ORM\Column(length: 255)]
    private string $name;     // Translated per locale: "Laptop" (EN) → "Ordinateur portable" (FR)

+   #[SharedAmongstTranslations]
    #[ORM\Column(type: Types::DECIMAL)]
    private string $price;    // Same across locales: $999.00 regardless of language

+   #[SharedAmongstTranslations]
    #[ORM\ManyToOne(targetEntity: Category::class)]
    private ?Category $category;  // Same category for all locales
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| .claude/commands/*.md | .claude/skills/*/SKILL.md | Claude Code recent versions | Skills support bundled resources, progressive disclosure |
| Static instructions | Conditional workflows (quick vs guided) | Current best practice | Better UX, accommodates user preferences |
| Full file replacement | Diff-style presentation | User decision for this phase | Clearer what changed |
| Concept-first teaching | Examples-first guidance | User decision for this phase | Better decision-making |

**Skill auto-triggering reality:**
Research found that skill auto-activation is unreliable (described as "coin flip" in sources). However:
- Description field is still primary mechanism - must include trigger phrases
- User can always invoke manually with `/entity-translation-setup`
- Announcing activation ("I'll use the entity-translation-setup skill") per user decision helps confirm skill is active

## Open Questions

1. **Relationship Handler Chain Details in Skill**
   - What we know: User wants "detailed inline explanation of relationship handler chain behavior"
   - What's unclear: How much to include vs reference llms.md (under 200 line constraint)
   - Recommendation: Include ONE concrete example in skill (e.g., "OneToMany to ProductImage: BidirectionalOneToManyHandler translates collection, maintains inverse property"), reference llms.md for complete chain

2. **Quick Mode Defaults**
   - What we know: User specified quick mode exists, Claude's discretion for "quick mode defaults selection"
   - What's unclear: Exact defaults (which fields shared, which translated)
   - Recommendation: Safe defaults: translate all scalars except price/cost/amount, share all relations, skip EmptyOnTranslate unless "slug" in name

3. **Entity with No Translatable Fields**
   - What we know: User specified Claude's discretion for "edge cases (entities with no obvious translatable fields)"
   - What's unclear: Should skill proceed or warn?
   - Recommendation: Warn user: "This entity has only ID and timestamps - typically not translated. Are you sure you want to proceed?"

4. **Supporting Files Needed**
   - What we know: Skill should be under 200 lines
   - What's unclear: Whether code examples should be in references/examples.md or inline
   - Recommendation: Keep templates inline (compact with placeholders), move only if skill exceeds 200 lines

## Sources

### Primary (HIGH confidence)
- D:\www\tmi\translation-bundle\llms.md - Handler chain, TranslatableTrait documentation
- D:\www\tmi\translation-bundle\src\Doctrine\Model\TranslatableTrait.php - Trait implementation
- D:\www\tmi\translation-bundle\src\Doctrine\Model\TranslatableInterface.php - Interface contract
- D:\www\tmi\translation-bundle\src\Doctrine\Attribute\SharedAmongstTranslations.php - Attribute definition
- D:\www\tmi\translation-bundle\src\Doctrine\Attribute\EmptyOnTranslate.php - Attribute definition
- D:\www\tmi\translation-bundle\.agents\skills\skill-creator\SKILL.md - Skill creation best practices
- D:\www\tmi\translation-bundle\.planning\phases\06-foundation-documentation\06-RESEARCH.md - Foundation docs research
- [Claude Code Skills Documentation](https://code.claude.com/docs/en/skills) - Official skill structure, frontmatter, patterns

### Secondary (MEDIUM confidence)
- [Skill Authoring Best Practices](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/best-practices) - Progressive disclosure, workflow patterns
- [How to Make Claude Code Skills Activate Reliably](https://scottspence.com/posts/how-to-make-claude-code-skills-activate-reliably) - Trigger reliability patterns

### Tertiary (LOW confidence)
- WebSearch: "Claude Code skill auto-trigger description patterns" - Community experiences with skill triggering (marked unreliable)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - User decisions lock all major choices, official docs confirm structure
- Architecture: HIGH - Skill structure from official docs, workflow patterns from user decisions
- Pitfalls: HIGH - Derived from user decisions, skill best practices, and domain knowledge

**Research date:** 2026-02-02
**Valid until:** 60 days (Claude Code skills stable, user decisions locked)
