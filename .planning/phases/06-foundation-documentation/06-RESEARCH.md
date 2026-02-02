# Phase 6: Foundation Documentation - Research

**Researched:** 2026-02-02
**Domain:** Technical documentation for AI assistants (llms.md enhancement)
**Confidence:** HIGH

## Summary

This phase focuses on enhancing the existing llms.md documentation to enable AI assistants to understand the bundle's handler chain architecture and guide users through common scenarios. The research investigated three key areas locked by user decisions: ASCII art decision trees for handler visualization, troubleshooting documentation best practices, and narrative-style minimal examples.

The existing llms.md provides solid foundational documentation but lacks the visual handler chain flow, structured troubleshooting section, and beginner-friendly walkthrough that would help AI assistants quickly orient users. The bundle's architecture is well-documented in code and internal docs (.claude/architecture.md), providing authoritative source material.

**Primary recommendation:** Enhance llms.md with three distinct sections: (1) ASCII decision tree showing handler priority-based routing, (2) 8-10 troubleshooting entries mixing symptom-cause-fix with diagnostic patterns, (3) Product entity narrative walkthrough demonstrating interface, trait, and attribute usage.

## Standard Stack

This phase is documentation-only - no libraries required.

### Documentation Format
| Format | Purpose | Why Standard |
|--------|---------|--------------|
| Markdown | llms.md content | LLM-readable, universal rendering |
| ASCII art | Decision tree visualization | No rendering dependencies, works in all contexts |
| Inline code blocks | Examples | Standard markdown, syntax highlighting |

### Source Material
| Source | Location | Purpose |
|--------|----------|---------|
| Architecture docs | `.claude/architecture.md` | Handler chain priority table |
| Existing llms.md | `llms.md` | Current documentation structure |
| Test fixtures | `tests/Fixtures/Entity/` | Working entity examples |
| Source handlers | `src/Translation/Handlers/` | Authoritative handler behavior |

## Architecture Patterns

### Handler Chain Decision Tree Structure

Based on analysis of `EntityTranslator.php` and handler implementations, the decision flow is:

```
Field Processing Order:
1. EntityTranslator receives TranslationArgs
2. Checks TranslatableInterface cache
3. Iterates handlers by priority (highest first)
4. First handler.supports() = true wins
5. Attribute check: SharedAmongstTranslations -> handleSharedAmongstTranslations()
6. Attribute check: EmptyOnTranslate -> handleEmptyOnTranslate()
7. Embedded check: special handling for embedded properties
8. Default: handler.translate()
```

**ASCII Tree Recommendation:**

The decision tree should start with "What kind of field?" routing because:
- Users think in terms of field types (scalar, relation, embedded)
- Handler priority IS field-type-based routing
- Priority 100 (PrimaryKeyHandler) catches IDs first, then 90 (ScalarHandler), etc.

Structure pattern:
```
What kind of field?
    |
    +-- Is it a primary key (ID)?
    |       |
    |       +-- YES --> PrimaryKeyHandler (100): Returns null
    |
    +-- Is it scalar/DateTime?
    |       |
    |       +-- YES --> ScalarHandler (90): Copies value
    |
    ... [continue through handlers]
```

### Troubleshooting Entry Patterns

Based on research from documentation best practices, two patterns apply:

**Pattern A: Symptom-Cause-Fix** (for clear-cut issues)
- Best for: Configuration errors, missing implementations
- Structure: What you see -> Why it happens -> How to fix

**Pattern B: Diagnostic Steps** (for runtime mysteries)
- Best for: "Translation not working" type issues
- Structure: Check X -> If problem, do Y -> Still broken? Check Z

### Minimal Example Narrative Pattern

Based on user decisions, the walkthrough should follow teaching narrative:
```
1. "You have a Product entity..."
2. "To make it translatable, first implement TranslatableInterface..."
3. "This interface requires..." (explain WHY)
4. "Next, use TranslatableTrait to get..." (explain what trait provides)
5. "The Tuuid groups all translations..." (explain purpose)
6. "For price, which shouldn't change per locale, add..." (SharedAmongstTranslations)
```

## Don't Hand-Roll

This is a documentation phase - applies to documentation patterns, not code.

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Decision tree format | Custom diagram tool output | ASCII art with box characters | Works everywhere, no dependencies |
| Troubleshooting structure | Ad-hoc Q&A | Structured symptom/cause/fix | Scannable, consistent |
| Example entity | Abstract examples | Product with Category (e-commerce) | Relatable, demonstrates relationships |

## Common Pitfalls

### Pitfall 1: Decision Tree Too Deep
**What goes wrong:** Tree becomes unreadable when showing all conditional branches
**Why it happens:** Trying to capture every edge case in the visual
**How to avoid:** Tree shows ROUTING only; separate explanation section for WHY
**Warning signs:** Tree wider than 80 characters or deeper than 10 levels

### Pitfall 2: Troubleshooting Without Error Messages
**What goes wrong:** User can't find matching entry because symptom descriptions don't match actual errors
**Why it happens:** Writing causes instead of symptoms
**How to avoid:** Include actual error messages or exact behavioral symptoms
**Warning signs:** Entries start with "When you..." instead of observable symptoms

### Pitfall 3: Example Too Complex for First Encounter
**What goes wrong:** Minimal example includes every feature, overwhelming users
**Why it happens:** Trying to demonstrate all capabilities at once
**How to avoid:** Product + Category relationship is scope ceiling; defer ManyToMany, embedded
**Warning signs:** Example entity has more than 6-7 properties

### Pitfall 4: Terminology Inconsistency
**What goes wrong:** Same concept called different names in different sections
**Why it happens:** Writing sections at different times without cross-reference
**How to avoid:** Glossary at top, inline definitions on first use, "Tuuid" not "Translation UUID"
**Warning signs:** Multiple terms for same concept appearing in text

### Pitfall 5: Missing Handler Priority Rationale
**What goes wrong:** Users confused why PrimaryKeyHandler runs before ScalarHandler
**Why it happens:** Showing WHAT without explaining WHY
**How to avoid:** Dedicated section explaining priority order matters for correctness
**Warning signs:** Decision tree without accompanying "Why This Order" explanation

## Code Examples

### Product Entity (Minimal Example Starting Point)

```php
// BEFORE: Standard Doctrine entity
#[ORM\Entity]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $price;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'products')]
    private ?Category $category = null;

    // getters/setters...
}
```

### Product Entity After Translation Setup

```php
// AFTER: Translatable entity
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
class Product implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;           // Translated per locale

    #[ORM\Column(type: Types::TEXT)]
    private string $description;    // Translated per locale

    #[SharedAmongstTranslations]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $price;          // Same across all locales

    #[SharedAmongstTranslations]
    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'products')]
    private ?Category $category = null;  // Same category for all locales

    // getters/setters...
}
```

### Handler Priority Table (Source: .claude/architecture.md)

| Priority | Handler | Catches |
|----------|---------|---------|
| 100 | PrimaryKeyHandler | `#[ORM\Id]` properties |
| 90 | ScalarHandler | Scalars, DateTime |
| 80 | EmbeddedHandler | `#[ORM\Embedded]` |
| 70 | BidirectionalManyToOneHandler | ManyToOne with inversedBy |
| 60 | BidirectionalOneToManyHandler | OneToMany with mappedBy |
| 50 | BidirectionalOneToOneHandler | OneToOne with mappedBy/inversedBy |
| 40 | BidirectionalManyToManyHandler | ManyToMany bidirectional |
| 30 | UnidirectionalManyToManyHandler | ManyToMany without mappedBy/inversedBy |
| 20 | TranslatableEntityHandler | TranslatableInterface entities |
| 10 | DoctrineObjectHandler | Any Doctrine-managed object (fallback) |

### Common Troubleshooting Scenarios (From Test Analysis)

1. **Locale not allowed error**
   - Symptom: `LogicException: Locale "xx" is not allowed`
   - Cause: Target locale not in `tmi_translation.locales` config
   - Fix: Add locale to config

2. **EmptyOnTranslate on non-nullable**
   - Symptom: `LogicException: cannot use EmptyOnTranslate because it is not nullable`
   - Cause: Using `#[EmptyOnTranslate]` on non-nullable property
   - Fix: Make property nullable or remove attribute

3. **SharedAmongstTranslations on bidirectional relations**
   - Symptom: `RuntimeException` when translating
   - Cause: Bidirectional relation handlers throw when shared
   - Fix: Don't use SharedAmongstTranslations on bidirectional relations

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Reference-style docs | Narrative walkthrough | Current best practice | Better for teaching |
| List-based handler docs | Decision tree | User decision | Visual routing |
| Q&A troubleshooting | Symptom-cause-fix | Documentation research | Scannable, actionable |

**Current llms.md state:**
- Good: Handler reference, attribute explanations, usage scenarios
- Missing: Visual handler flow, troubleshooting section, minimal walkthrough
- Terminology: Uses "tuuid" consistently (matches decision)

## Open Questions

1. **Fallback handler path in decision tree**
   - What we know: DoctrineObjectHandler (priority 10) catches anything Doctrine-managed
   - What's unclear: Whether to show explicit "no handler matches" path
   - Recommendation: Show fallback to DoctrineObjectHandler; skip "no handler" path since Doctrine-managed objects always match

2. **Terminology warnings**
   - What we know: "Tuuid" is correct term, user wants consistency
   - What's unclear: Whether to add "Don't use: Translation UUID, translation ID"
   - Recommendation: Add brief "Terminology Note" with preferred vs avoided terms for clarity

3. **Troubleshooting entry count**
   - What we know: User specified 8-10 entries, equal setup/runtime split
   - What's unclear: Exact split (4+4 or 5+5)
   - Recommendation: Start with 4 setup + 4 runtime, can expand if needed

## Sources

### Primary (HIGH confidence)
- `src/Translation/EntityTranslator.php` - Handler chain execution logic
- `src/Translation/Handlers/*.php` - Individual handler implementations
- `.claude/architecture.md` - Handler priority table
- `tests/Translation/EntityTranslatorTest.php` - Error scenarios and edge cases
- `src/Doctrine/Model/TranslatableInterface.php` - Interface contract
- `src/Doctrine/Model/TranslatableTrait.php` - Trait implementation

### Secondary (MEDIUM confidence)
- [llmstxt.org](https://llmstxt.org/) - llms.txt specification format guidance
- [Developer Troubleshooting Docs Best Practices](https://daily.dev/blog/developer-troubleshooting-docs-best-practices) - Troubleshooting structure patterns

### Tertiary (LOW confidence)
- General web search on ASCII decision tree formatting - validated by user decision to use ASCII art

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - no libraries needed, format decisions locked
- Architecture: HIGH - sourced from bundle code and existing docs
- Pitfalls: HIGH - derived from code analysis and test scenarios

**Research date:** 2026-02-02
**Valid until:** 90 days (documentation patterns are stable)
