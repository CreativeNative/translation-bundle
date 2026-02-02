# Feature Landscape: AI Documentation for Technical Libraries

**Domain:** AI-friendly documentation for Symfony translation bundle
**Researched:** 2026-02-02
**Overall Confidence:** HIGH

## Executive Summary

Effective AI documentation in 2026 follows two standards: **llms.txt** for discovery/indexing and **SKILL.md** (Agent Skills) for executable capabilities. This research identifies table-stakes features, differentiators, and anti-patterns based on successful implementations from Anthropic, Stripe, Zapier, and emerging 2026 standards.

**Key insight:** AI documentation succeeds through **progressive disclosure** (load metadata first, details on-demand) and **structured markdown** (parseable by both LLMs and humans). Context management beats content volume.

---

## Table Stakes

Features AI assistants expect. Missing these = incomplete or frustrating experience.

| Feature | Why Expected | Complexity | Implementation Notes |
|---------|--------------|------------|----------------------|
| **H1 Project Name + Summary** | LLMs need immediate context about what they're reading | Low | Required first element in llms.txt format |
| **Structured Navigation Links** | Progressive disclosure - metadata loads first, content on-demand | Low | H2 sections with markdown links `[name](url): description` |
| **Minimal Working Example** | LLMs need concrete patterns, not abstract descriptions | Medium | "Hello World" equivalent showing core usage |
| **Code Blocks with Context** | LLMs look for exact matches in dedicated blocks | Low | Always include import statements and type hints |
| **Consistent Terminology** | LLMs get confused by synonym variation | Low | Pick one term per concept, use everywhere |
| **Installation Instructions** | Step 1 for any library usage | Low | Copy-paste-ready commands |
| **API Reference Structure** | LLMs need function signatures with arguments and returns | Medium | Separate code blocks for each method/class |
| **Markdown Format** | HTML wastes tokens without adding semantic value | Low | Serve `.md` versions of all documentation |
| **Version Information** | LLMs need to know compatibility | Low | State PHP version, Symfony version, library version upfront |
| **Common Use Cases** | Concrete scenarios > abstract capabilities | Medium | 3-5 real-world scenarios with code |

**Source confidence:** HIGH - Verified through [llmstxt.org specification](https://llmstxt.org/), [Anthropic best practices](https://www.anthropic.com/engineering/claude-code-best-practices), and [Mintlify llms.txt guide](https://www.mintlify.com/blog/simplifying-docs-with-llms-txt)

---

## Differentiators

Features that elevate documentation from functional to exceptional.

| Feature | Value Proposition | Complexity | Implementation Notes |
|---------|-------------------|------------|----------------------|
| **Progressive Disclosure Architecture** | 70-90% token savings while maintaining quality | High | Three-layer: (1) Index/metadata (2) Details on-demand (3) Deep reference |
| **SKILL.md for Common Tasks** | Executable capabilities vs passive documentation | Medium | YAML frontmatter + task instructions following [Agent Skills standard](https://github.com/anthropics/skills) |
| **Anti-Pattern Documentation** | Prevents common mistakes proactively | Medium | "Don't do X because Y, do Z instead" sections |
| **Tested Code Examples** | Examples stay current as library evolves | High | Integrate snippets into CI/CD for validation |
| **Context-Aware Examples** | Shows before/after state, not just isolated snippets | Medium | Include entity definitions, not just translation calls |
| **Troubleshooting Decision Trees** | Guides debugging without back-and-forth | Medium | "If X happens, check Y, then Z" flowcharts |
| **Migration Guides** | Addresses "how do I move from X to this" | Medium | Comparative examples with old/new approaches |
| **llms-full.txt Companion** | Single-file complete context for complex queries | Low | Generated automatically from llms.txt links |
| **Explicit Assumptions** | States prerequisites and constraints upfront | Low | "Assumes Doctrine ORM configured, entities annotated" |
| **Cross-Reference Links** | Connects related concepts explicitly | Medium | "See also: [SharedAmongstTranslations](#shared)" |

**Value analysis:**
- **Progressive Disclosure**: Anthropic's Agent Skills framework reports [70-90% token reduction](https://alexop.dev/posts/stop-bloating-your-claude-md-progressive-disclosure-ai-coding-tools/) while maintaining response quality
- **SKILL.md**: [Portable across GitHub Copilot, Claude Code, OpenAI agents](https://code.claude.com/docs/en/skills) as of Dec 2025 open standard
- **Tested Examples**: [Document360 and Codacy best practices](https://document360.com/blog/code-documentation/) cite CI integration as #1 for accuracy

**Source confidence:** HIGH - Verified through Anthropic documentation, Agent Skills GitHub repo, industry best practices

---

## Anti-Features

Documentation patterns to explicitly AVOID. Common mistakes in this domain.

| Anti-Feature | Why Avoid | What to Do Instead |
|--------------|-----------|-------------------|
| **Context Stuffing** | More text = more distractors, causes hedging and fuzzy answers | Small labeled chunks (200-400 tokens, 10-15% overlap) |
| **Repetitive Information** | Wastes context budget without adding value | DRY principle - link to canonical definition |
| **Missing Negative Examples** | LLMs reproduce patterns from training data | Explicitly document "Don't use X for Y" |
| **Obscure Without Explanation** | LLMs fail on post-training or rare topics | Provide extra context for domain-specific concepts |
| **Skipped Heading Levels** | Breaks LLM mental mapping (H1 → H3 without H2) | Use strict hierarchical structure |
| **Bloated System Instructions** | 1,000+ token guides consume context needlessly | Keep role/safety/tone under 200 tokens |
| **Examples Without Imports** | LLMs don't infer missing dependencies | Always show full working code including use statements |
| **Ambiguous Terminology** | Using "translate", "localize", "convert" interchangeably | Define terms once, use consistently |
| **HTML for AI Consumption** | Markup tokens add no semantic value | Serve markdown to AI agents |
| **Unverified Claims** | Stating capabilities without code proof | Every feature claim needs working example |
| **Missing Attribution** | LLMs can't cite sources without grounding | Include source URLs in documentation |
| **Dense Prose Paragraphs** | LLMs scan for structure, not read linearly | Use bullet points, tables, code blocks |

**Source confidence:** HIGH - Documented in [LLM anti-patterns research](https://medium.com/marvelous-mlops/patterns-and-anti-patterns-for-building-with-llms-42ea9c2ddc90), [RAG context stuffing issues](https://medium.com/@2nick2patel2/llm-rag-anti-patterns-stop-stuffing-context-c79c11a2529d), [ballooning context analysis](https://www.coderabbit.ai/blog/handling-ballooning-context-in-the-mcp-era-context-engineering-on-steroids)

---

## Feature Dependencies

```
Foundation Layer (Required for all others):
├── Markdown Format
├── H1 + Summary
├── Consistent Terminology
└── Version Information

Discovery Layer (Enables AI to find documentation):
├── llms.txt (depends on: Markdown Format, Structured Links)
└── Structured Navigation (depends on: Heading Hierarchy)

Usage Layer (Enables AI to generate correct code):
├── Minimal Working Example (depends on: Installation Instructions)
├── API Reference (depends on: Code Blocks with Context)
└── Common Use Cases (depends on: Minimal Working Example)

Excellence Layer (Elevates from functional to exceptional):
├── Progressive Disclosure (depends on: Structured Navigation)
├── SKILL.md (depends on: Common Use Cases, API Reference)
├── Anti-Patterns (depends on: Common Use Cases)
└── Tested Examples (depends on: CI/CD integration)
```

**Critical path for MVP AI documentation:**
1. Foundation Layer → 2. Discovery Layer → 3. Minimal Working Example → 4. Common Use Cases

---

## Feature Specifications

### 1. llms.txt Format (Table Stakes)

**Specification:**
```markdown
# TMI Translation Bundle

> Symfony bundle for making Doctrine entities translatable with shared and locale-specific fields.

This bundle provides entity translation with fine-grained control over field behavior.

## Documentation

- [Quick Start](docs/quick-start.md): Get up and running in 5 minutes
- [Core Concepts](docs/core-concepts.md): Translation vs shared vs empty fields
- [API Reference](docs/api-reference.md): Handler chain and EntityTranslator
- [Common Use Cases](docs/use-cases.md): Real-world scenarios with code

## Optional

- [Advanced Topics](docs/advanced.md): Custom handlers and event subscribers
- [Architecture](docs/architecture.md): Design decisions and internals
```

**Requirements:**
- H1 with project name (required)
- Blockquote with summary (required)
- H2 sections for organization
- Markdown links with format: `[name](url): optional description`
- "Optional" section for secondary content

**Source:** [llms.txt specification](https://llmstxt.org/)

---

### 2. SKILL.md Format (Differentiator)

**Specification:**
```markdown
---
name: symfony-translation-bundle
description: Add entity translation to Symfony/Doctrine projects with shared and locale-specific field control
---

# Symfony Translation Bundle Integration

Use this skill when the user wants to make Doctrine entities translatable in Symfony.

## Prerequisites

- Symfony 7.x project
- Doctrine ORM configured
- PHP 8.4+

## Steps

1. Install bundle: `composer require creative-native/translation-bundle`
2. Add TranslatableInterface to entity
3. Add locale and tuuid properties
4. Mark shared fields with #[SharedAmongstTranslations]
5. Inject EntityTranslatorInterface
6. Call translate() method

## Common Patterns

### Pattern 1: Basic Translation
[example code]

### Pattern 2: Shared Embedded Object
[example code]

## Gotchas

- Primary keys are never translated
- Bidirectional relations need special handlers
- Shared fields override EmptyOnTranslate
```

**Requirements:**
- YAML frontmatter with `name` and `description` (required)
- Clear task description
- Prerequisite checklist
- Step-by-step instructions
- Common patterns with code
- Known pitfalls

**Source:** [Anthropic Agent Skills specification](https://github.com/anthropics/skills)

---

### 3. Progressive Disclosure Structure (Differentiator)

**Pattern:**

```
Layer 1: Metadata (Always loaded, ~50-100 tokens)
- File: llms.txt
- Contains: Project name, summary, link structure
- Purpose: Discovery and routing

Layer 2: Topic Content (Loaded on-demand, ~500-1500 tokens)
- Files: Individual markdown pages
- Contains: Concept explanation, minimal examples
- Purpose: Understanding and basic implementation

Layer 3: Reference (Loaded when needed, ~2000+ tokens)
- Files: API reference, advanced guides
- Contains: Complete specifications, edge cases
- Purpose: Deep implementation and debugging
```

**Implementation approach:**
1. Never put full content in llms.txt (links only)
2. Keep topic pages focused (one concept per page)
3. Link to reference docs for completeness
4. Structure allows AI to "drill down" as needed

**Token budget:** Aim for 70-90% savings vs monolithic documentation

**Source:** [Progressive disclosure for AI agents](https://alexop.dev/posts/stop-bloating-your-claude-md-progressive-disclosure-ai-coding-tools/)

---

### 4. Minimal Working Example (Table Stakes)

**Pattern:**
```php
// Install
composer require creative-native/translation-bundle

// 1. Entity setup
use CreativeNative\TranslationBundle\Contract\TranslatableInterface;
use CreativeNative\TranslationBundle\Attribute\SharedAmongstTranslations;

#[ORM\Entity]
class Article implements TranslatableInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 5)]
    private string $locale;

    #[ORM\Column(type: 'tuuid')]
    private Tuuid $tuuid;

    #[ORM\Column(length: 255)]
    private string $title; // Translatable

    #[ORM\Column(length: 255)]
    #[SharedAmongstTranslations]
    private string $category; // Shared across locales
}

// 2. Translation service
class ArticleService
{
    public function __construct(
        private EntityTranslatorInterface $translator,
        private EntityManagerInterface $em
    ) {}

    public function translateArticle(Article $source, string $targetLocale): Article
    {
        $translated = $this->translator->translate($source, $targetLocale);
        $this->em->persist($translated);
        $this->em->flush();
        return $translated;
    }
}

// 3. Usage
$germanArticle = $articleService->translateArticle($englishArticle, 'de');
// Result: New entity with same category, empty title (ready for translation)
```

**Requirements:**
- Copy-paste executable without modifications
- Shows complete flow (setup → service → usage)
- Includes all imports and type hints
- Comments explain what's happening
- Demonstrates core value proposition

**Source:** [Code documentation best practices 2026](https://www.qodo.ai/blog/code-documentation-best-practices-2026/)

---

### 5. Anti-Pattern Documentation (Differentiator)

**Pattern:**
```markdown
## Common Mistakes

### ❌ DON'T: Mark bidirectional relations as shared
```php
#[ORM\OneToMany(mappedBy: 'article')]
#[SharedAmongstTranslations] // Will throw RuntimeException
private Collection $comments;
```

**Why:** Bidirectional relations have ownership semantics that conflict with sharing.

### ✅ DO: Let the handler manage bidirectional translations
```php
#[ORM\OneToMany(mappedBy: 'article')]
private Collection $comments; // Handler translates collection correctly
```

### ❌ DON'T: Forget to set tuuid on new entities
```php
$article = new Article();
$article->setLocale('en');
// Missing: $article->setTuuid(Tuuid::generate());
$translated = $translator->translate($article, 'de'); // No grouping!
```

### ✅ DO: Always initialize tuuid for translatables
```php
$article = new Article();
$article->setLocale('en');
$article->setTuuid(Tuuid::generate()); // Links all translations
```
```

**Requirements:**
- Side-by-side wrong/right examples
- Explains WHY the anti-pattern fails
- Shows correct alternative
- Uses clear visual markers (❌/✅)

**Source:** [Security anti-patterns for LLMs](https://github.com/Arcanum-Sec/sec-context), [AI coding anti-patterns](https://dev.to/lingodotdev/ai-coding-anti-patterns-6-things-to-avoid-for-better-ai-coding-f3e)

---

## MVP Feature Prioritization

For initial AI documentation milestone, prioritize:

### Phase 1: Foundation (Week 1)
1. **llms.txt** - Discovery mechanism
2. **Quick Start markdown** - Minimal working example
3. **Core Concepts markdown** - Translation/shared/empty explained
4. **Consistent terminology audit** - Fix synonym confusion

### Phase 2: Usability (Week 2)
5. **Common Use Cases** - 5 real-world scenarios with code
6. **API Reference** - Handler chain documentation
7. **Anti-patterns guide** - Top 5 mistakes to avoid

### Phase 3: Excellence (Week 3+)
8. **SKILL.md** - Executable AI capability
9. **llms-full.txt** - Generated from structure
10. **Tested examples** - CI integration for code validation

**Defer to post-MVP:**
- **Advanced topics** (custom handlers) - Complex, low initial demand
- **Architecture deep-dive** - Useful for contributors, not users
- **Migration guides** - No prior versions yet (new milestone)
- **Troubleshooting flowcharts** - Build from user feedback
- **Video walkthroughs** - High production cost, text works for AI

**Rationale:** Foundation + Usability gets AI assistants functional. Excellence phase adds polish based on usage patterns.

---

## Success Metrics

How to measure if AI documentation is effective:

| Metric | Target | Measurement Method |
|--------|--------|-------------------|
| **Time to first working code** | < 5 minutes | User testing with AI assistant |
| **Code correctness rate** | > 90% | AI-generated code passes tests |
| **Context token efficiency** | 70-90% reduction vs monolithic | Token usage analysis |
| **Query resolution rate** | > 85% | Questions answered without human intervention |
| **Anti-pattern avoidance** | < 10% | Code review of AI-generated implementations |

**Validation approach:**
1. Test with Claude Code, GitHub Copilot, ChatGPT
2. Give fresh task: "Make this entity translatable"
3. Measure time, correctness, tokens used
4. Iterate based on failures

---

## Technology Compatibility

| AI Platform | llms.txt | SKILL.md | Progressive Disclosure | Notes |
|-------------|----------|----------|----------------------|-------|
| Claude Code | ✅ | ✅ | ✅ | Native support for all patterns |
| GitHub Copilot | ✅ | ✅ | ✅ | Agent Skills supported since Dec 2025 |
| ChatGPT | ✅ | ⚠️ | ✅ | Skills spec adopted, implementation varies |
| Cursor | ✅ | ✅ | ✅ | Full support as of Q1 2026 |
| OpenAI Codex CLI | ✅ | ✅ | ✅ | Skills standard compatible |

**Note:** llms.txt is universally supported. SKILL.md follows open [Agent Skills standard](https://agentskills.io) announced Dec 2025.

---

## Implementation Recommendations

### For TMI Translation Bundle Specifically:

**Strengths to highlight:**
- Attribute-based configuration (clear and scannable)
- Handler chain architecture (extensible pattern)
- Doctrine integration (fits existing Symfony workflows)

**Documentation structure:**
```
/llms.txt (root, discovery index)
/docs/
  /quick-start.md (minimal working example)
  /core-concepts.md (translation vs shared vs empty)
  /api-reference.md (handler chain specifications)
  /use-cases.md (5 common scenarios)
  /anti-patterns.md (top mistakes to avoid)
/.skills/
  /symfony-translation-bundle/
    /SKILL.md (executable capability)
```

**Content strategy:**
1. Audit existing llms.md for terminology consistency
2. Extract anti-patterns from tests and issue discussions
3. Create minimal example from simplest test case
4. Structure API reference by handler priority order
5. Generate llms-full.txt automatically from structure

**Quality gates:**
- [ ] All code examples execute without modification
- [ ] No terminology synonyms (one term per concept)
- [ ] Every handler has example in use-cases.md
- [ ] Anti-patterns extracted from real failures
- [ ] llms.txt validates against specification

---

## Sources

### High Confidence (Official Specifications & Documentation)

- [llms.txt specification](https://llmstxt.org/) - Official format specification
- [Anthropic Agent Skills GitHub](https://github.com/anthropics/skills) - SKILL.md format specification
- [Claude Code Best Practices](https://www.anthropic.com/engineering/claude-code-best-practices) - Anthropic official guidance
- [Agent Skills Standard](https://code.claude.com/docs/en/skills) - Open standard documentation
- [Mintlify llms.txt Guide](https://www.mintlify.com/blog/simplifying-docs-with-llms-txt) - Implementation guide from doc platform

### Medium Confidence (Industry Best Practices, Multiple Sources)

- [LLM Documentation Optimization](https://redocly.com/blog/optimizations-to-make-to-your-docs-for-llms) - Best practices guide
- [Progressive Disclosure for AI](https://alexop.dev/posts/stop-bloating-your-claude-md-progressive-disclosure-ai-coding-tools/) - Context management patterns
- [Code Documentation Best Practices 2026](https://www.qodo.ai/blog/code-documentation-best-practices-2026/) - Industry standards
- [LLM Anti-Patterns](https://medium.com/marvelous-mlops/patterns-and-anti-patterns-for-building-with-llms-42ea9c2ddc90) - Research compilation
- [Technical Documentation Best Practices](https://document360.com/blog/code-documentation/) - Documentation platform insights

### Supporting Research (Trend Analysis)

- [Best llms.txt Platforms](https://buildwithfern.com/post/best-llms-txt-implementation-platforms-ai-discoverable-apis) - Platform comparison
- [llms.txt Examples](https://llms-txt.io/blog/companies-using-llms-txt-examples) - Real-world implementations
- [Agent Skills Framework](https://www.digitalapplied.com/blog/claude-agent-skills-framework-guide) - Framework guide
- [Context Stuffing Anti-Pattern](https://medium.com/@2nick2patel2/llm-rag-anti-patterns-stop-stuffing-context-c79c11a2529d) - RAG pitfalls
- [Progressive Disclosure in AI](https://www.honra.ai/articles/progressive-disclosure-for-ai-agents) - Context management research

---

## Confidence Assessment

| Feature Category | Confidence Level | Evidence Basis |
|-----------------|------------------|----------------|
| llms.txt Format | HIGH | Official specification, widespread adoption (844K+ sites) |
| SKILL.md Format | HIGH | Open standard with multi-platform support (Anthropic, GitHub, OpenAI) |
| Progressive Disclosure | HIGH | Multiple authoritative sources, measured benefits (70-90% token savings) |
| Anti-Patterns | MEDIUM-HIGH | Industry research, documented in multiple sources, some anecdotal |
| Success Metrics | MEDIUM | Industry practice, not standardized benchmarks |
| Code Example Patterns | HIGH | Established best practices, verified across multiple style guides |

**Overall assessment:** Research is comprehensive and actionable. All table-stakes features have HIGH confidence. Differentiators are well-supported. Anti-patterns need validation through implementation but are documented patterns from credible sources.

---

## Open Questions & Future Research

**Resolved for this milestone:**
- ✅ What format standards exist? (llms.txt + SKILL.md)
- ✅ What features are table stakes? (Documented above)
- ✅ What makes documentation exceptional? (Progressive disclosure, anti-patterns, tested examples)
- ✅ What should be avoided? (Context stuffing, inconsistent terminology, HTML)

**Future investigation (post-MVP):**
- How do different AI platforms parse nested markdown links? (Test empirically)
- What's the optimal chunk size for code examples? (Currently 200-400 tokens recommended)
- Should we generate llms-full.txt dynamically or statically? (Platform support varies)
- How to handle versioned documentation with llms.txt? (Specification unclear)
- Can SKILL.md include executable scripts? (Specification allows, adoption unclear)

**Recommendation:** Proceed with features documented above. High confidence in core requirements. Edge cases can be refined based on user feedback during implementation.
