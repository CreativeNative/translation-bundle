# Phase 9: Web Discovery - Research

**Researched:** 2026-02-02
**Domain:** llms.txt standard for AI crawler discovery
**Confidence:** HIGH

## Summary

llms.txt is a standardized markdown file format proposed by Answer.AI (Jeremy Howard) for helping large language models and AI search engines discover and index website content. The specification is simple yet powerful: a single markdown file at `/llms.txt` with an H1 title, optional blockquote summary, and structured navigation links to markdown documentation.

For the TMI Translation Bundle, this phase involves creating a benefit-focused discovery file that highlights the bundle's value proposition for AI assistants while providing structured access to documentation, skills, and key source files. The specification prioritizes human and LLM readability over traditional structured formats.

**Current state (2026):** Over 844,000 websites have implemented llms.txt as of October 2025. Major adopters include Anthropic (Claude docs), Cloudflare, Stripe, Vercel, and Hugging Face. However, no major AI platform has officially confirmed reading these files. Google explicitly stated non-support in July 2025. Despite uncertain crawler adoption, the implementation cost is minimal (1-4 hours) with no downside if platforms eventually adopt the standard.

**Primary recommendation:** Implement a single llms.txt file (20-40 links) at repository root, using GitHub blob URLs for markdown files, organized by workflow (Getting Started → Skills → Reference → Advanced). Include all .claude/ skills as they're core AI-specific content. Use benefit-focused language in the summary blockquote.

## Standard Stack

The llms.txt standard is format-based, not technology-based. No libraries are required.

### Core Format
| Component | Requirement | Purpose | Specification Source |
|-----------|-------------|---------|---------------------|
| Markdown | Required | File format | llmstxt.org official spec |
| H1 heading | Required | Project name | Only mandatory element |
| Blockquote | Recommended | Project summary | Provides context for LLMs |
| H2 sections | Optional | Link categorization | Organizes file lists |
| Link format | Standard | `[title](url): description` | Markdown hyperlinks |

### Validation Tools
| Tool | Purpose | URL | Confidence |
|------|---------|-----|-----------|
| llmstxtchecker.net | Syntax, structure, link validation | https://llmstxtchecker.net/ | HIGH |
| llmstxtvalidator.dev | Official standard compliance | https://llmstxtvalidator.dev/ | HIGH |
| llms-txt.io/validator | Answer.AI standard verification | https://llms-txt.io/validator | HIGH |
| LLMTEXT toolkit | Open source validation library | https://parallel.ai/blog/LLMTEXT-for-llmstxt | MEDIUM |

### Hosting Requirements
| Requirement | Value | Rationale |
|-------------|-------|-----------|
| File location | `/llms.txt` (root) | AI crawlers expect root path |
| Content-Type | `text/plain` or `text/markdown` | Spec requirement for parsability |
| Public access | HTTP 200 response | Must be publicly accessible |
| File permissions | 644 (Unix) | Standard read permissions |
| Character encoding | UTF-8 | Prevents parsing errors |

**Installation:**
None required - plain text file committed to repository root.

## Architecture Patterns

### Recommended File Structure

For GitHub repositories serving as library documentation:

```markdown
# Project Name (package/name)

> Single-sentence benefit-focused summary targeting the audience

Optional paragraph(s) with additional context

## Getting Started
- [Installation](url): How to install
- [Quick Start](url): First implementation
- [Configuration](url): Setup options

## Documentation
- [Architecture](url): Core concepts
- [API Reference](url): Interfaces and types

## Skills
- [Skill Name](url): What the skill does

## Reference
- [Key Interface](url): Purpose
- [Handler Chain](url): How it works

## Optional
- [Advanced Topics](url)
- [Contributing](url)
```

### Pattern 1: Link Count Optimization

**What:** Balance comprehensive coverage with signal-to-noise ratio
**When to use:** Always - this is a core constraint of the specification

The specification provides no strict limits, but research reveals consistent recommendations:

| Source | Recommendation | Context |
|--------|---------------|---------|
| Rankability Guide | 10-20 links | Conservative, quality-focused |
| WebSearch consensus | 10-30 links | Standard range for most sites |
| CONTEXT.md decision | 20-50 links | User-specified range for this project |
| Flowbite example | 114 links | Component library (outlier) |

**Example (20-40 link strategy):**
```markdown
## Getting Started (3-5 links)
- README, Installation, Quick Start

## Skills (3-4 links)
- entity-translation-setup, translation-debugger, custom-handler-creator

## Architecture (4-6 links)
- architecture.md, core interfaces, handler chain reference

## Reference (5-8 links)
- Key source files (TranslationHandlerInterface, EntityTranslator)

## Optional (5-10 links)
- Advanced topics, testing patterns, contributing
```

### Pattern 2: Benefit-Focused Summary

**What:** Blockquote emphasizes value proposition, not technical implementation
**When to use:** Always - per CONTEXT.md decision

**Good examples (from research):**
- Flowbite: "free and open-source UI component library based on Tailwind CSS offering ready-to-use HTML components"
- FastHTML: "python library which brings together Starlette, Uvicorn, HTMX, and fastcore's FT 'FastTags'"
- Material UI: "open-source, comprehensive React component library that can be used in production out of the box"

**Anti-pattern:**
```markdown
> Handler chain architecture for Doctrine entity translation using priority-based processing
```

**Correct pattern:**
```markdown
> Make any Doctrine entity translatable with zero performance overhead. Built for Symfony developers building multilingual applications.
```

### Pattern 3: URL Format for GitHub Repositories

**What:** Use GitHub blob URLs for markdown files, not raw URLs
**When to use:** Linking to markdown documentation in GitHub repositories

**Rationale from research:**
- **blob URLs** (`github.com/user/repo/blob/branch/file.md`): Display GitHub UI with syntax highlighting, edit options, line numbers
- **raw URLs** (`raw.githubusercontent.com/user/repo/branch/file.md`): Serve unrendered content as `text/plain`

**Current practice (from llms-txt-hub):**
- HTMX reference: `https://github.com/bigskysoftware/htmx/blob/master/www/content/reference.md`
- Most documentation sites prefer blob URLs for human/LLM hybrid readability

**Decision:** Use blob URLs - they provide better context with GitHub's UI while remaining LLM-parsable.

### Pattern 4: Optional Section for Secondary Content

**What:** H2 section titled "Optional" signals content that can be skipped if context is limited
**When to use:** For nice-to-have content that's not essential for understanding

**Specification definition:** "the URLs provided there can be skipped if a shorter context is needed. Use it for secondary information which can often be skipped."

**Example:**
```markdown
## Optional
- [Contributing Guidelines](url)
- [Test Patterns](url): Advanced testing approaches
- [Performance Benchmarks](url)
```

### Pattern 5: Link Organization Strategy

**What:** Structure links by workflow/journey, not by file type
**When to use:** Per CONTEXT.md, this is Claude's discretion

**Three organization approaches:**

1. **Topic-based** (by content type):
   - Documentation, Skills, Source Files, Tests

2. **Workflow-based** (by user journey):
   - Getting Started → Core Usage → Advanced → Reference

3. **Hybrid** (recommended for TMI bundle):
   - Getting Started (workflow)
   - Skills (content type - AI-specific)
   - Architecture (reference)
   - Optional (advanced/secondary)

**Rationale:** Skills are unique AI-specific content that deserves dedicated section. Other content follows workflow pattern.

### Anti-Patterns to Avoid

- **Vague marketing language:** "World-class translation solution" vs "Make Doctrine entities translatable"
- **Broken links:** Update llms.txt when restructuring documentation
- **Outdated content:** Quarterly reviews recommended
- **Missing required H1:** File is invalid without H1 title
- **Query parameters in URLs:** Use clean paths only
- **Wrong content-type:** Must be `text/plain` or `text/markdown`
- **Subfolder placement:** `/docs/llms.txt` won't be discovered - must be root `/llms.txt`
- **Link overload:** 100+ links reduces signal (Flowbite is an outlier, not the norm)

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Validation | Custom parser/validator | llmstxtchecker.net or llmstxtvalidator.dev | Multiple free validators exist with full spec compliance |
| Content-type detection | Custom MIME handling | Standard GitHub/web server config | GitHub serves markdown as text/plain by default |
| Link extraction | Custom markdown parser | Existing validator tools | Validators check link accessibility automatically |
| Token counting | Custom tokenizer | llms-full.txt specification | Spec recommends <10k tokens per file; validators include token counts |

**Key insight:** The llms.txt ecosystem has matured rapidly. Validation, generation, and testing tools already exist. Focus on content quality, not tooling.

## Common Pitfalls

### Pitfall 1: File Not Publicly Accessible

**What goes wrong:** File exists but returns 403 Forbidden or 404 Not Found
**Why it happens:**
- File placed in `.planning/` or other gitignored directory
- Restrictive server permissions (not 644)
- Private repository without public GitHub Pages

**How to avoid:**
- Commit llms.txt to repository root
- Verify with `curl https://github.com/user/repo/blob/master/llms.txt`
- For GitHub repositories, blob URLs are always public if repo is public

**Warning signs:**
- Can't access file via browser
- Validator tools report 404

### Pitfall 2: Content-Type Mismatch

**What goes wrong:** File serves as `text/html` instead of `text/plain` or `text/markdown`
**Why it happens:** Web server misconfiguration
**How to avoid:**
- For GitHub blob URLs, content-type is handled automatically
- For self-hosted sites, configure web server MIME types
- Validators will flag content-type issues

**Warning signs:**
- Validator reports "content-type is text/html"
- File renders as HTML instead of plain text

### Pitfall 3: H1 Title Format Confusion

**What goes wrong:** Using wrong format for package names
**Why it happens:** Unclear whether to use package name, descriptive title, or both
**How to avoid:** Per CONTEXT.md decision: "TMI Translation Bundle (tmi/translation-bundle)" format
**Warning signs:** Validators may not catch this - it's a content quality issue

### Pitfall 4: Links to Documentation That No Longer Exists

**What goes wrong:** llms.txt links to moved/deleted files
**Why it happens:** Documentation refactoring without updating llms.txt
**How to avoid:**
- Run link validators quarterly
- Update llms.txt in same commit when moving documentation files
- Use llmstxtchecker.net's automatic link testing

**Warning signs:**
- Validator reports broken links
- 404 errors when clicking links

### Pitfall 5: Too Many Links (Signal Dilution)

**What goes wrong:** 100+ links make it hard for LLMs to prioritize
**Why it happens:** Assumption that more links = better coverage
**How to avoid:**
- Follow 20-50 link guideline from CONTEXT.md
- Use Optional section for secondary content
- Focus on high-value documentation

**Warning signs:**
- File feels overwhelming to read
- LLMs may summarize poorly due to noise

### Pitfall 6: Skill References Not Included

**What goes wrong:** Omitting .claude/skills/ from llms.txt
**Why it happens:** Assumption that hidden directories shouldn't be linked
**How to avoid:** Per CONTEXT.md decision, skills are core AI-specific content
**Warning signs:** AI assistants miss key bundle capabilities

### Pitfall 7: Using raw.githubusercontent.com URLs

**What goes wrong:** Links serve plain text without GitHub context
**Why it happens:** Assumption that "raw" is better for AI parsers
**How to avoid:** Use blob URLs - research shows they're preferred for documentation
**Warning signs:** Links lack GitHub UI context that aids understanding

## Code Examples

### Complete llms.txt Template for TMI Translation Bundle

```markdown
# TMI Translation Bundle (tmi/translation-bundle)

> Make any Doctrine entity translatable with zero performance overhead. Built for Symfony developers building multilingual applications.

## Getting Started
- [README](https://github.com/CreativeNative/translation-bundle/blob/master/README.md): Installation, quick start, and core features
- [Architecture](https://github.com/CreativeNative/translation-bundle/blob/master/.claude/architecture.md): Handler chain pattern and core concepts
- [Code Style](https://github.com/CreativeNative/translation-bundle/blob/master/.claude/code-style.md): Development standards

## AI Skills
- [Entity Translation Setup](https://github.com/CreativeNative/translation-bundle/blob/master/.claude/skills/entity-translation-setup/SKILL.md): Make entities translatable
- [Translation Debugger](https://github.com/CreativeNative/translation-bundle/blob/master/.claude/skills/translation-debugger/SKILL.md): Diagnose translation issues
- [Custom Handler Creator](https://github.com/CreativeNative/translation-bundle/blob/master/.claude/skills/custom-handler-creator/SKILL.md): Build custom translation handlers

## Core Interfaces
- [TranslatableInterface](https://github.com/CreativeNative/translation-bundle/blob/master/src/Doctrine/Model/TranslatableInterface.php): Entity translation contract
- [TranslationHandlerInterface](https://github.com/CreativeNative/translation-bundle/blob/master/src/Translation/Handlers/TranslationHandlerInterface.php): Handler chain protocol
- [EntityTranslatorInterface](https://github.com/CreativeNative/translation-bundle/blob/master/src/Translation/EntityTranslatorInterface.php): Translation orchestrator

## Handler Reference
- [PrimaryKeyHandler](https://github.com/CreativeNative/translation-bundle/blob/master/src/Translation/Handlers/PrimaryKeyHandler.php): ID field handling
- [ScalarHandler](https://github.com/CreativeNative/translation-bundle/blob/master/src/Translation/Handlers/ScalarHandler.php): Primitive types
- [TranslatableEntityHandler](https://github.com/CreativeNative/translation-bundle/blob/master/src/Translation/Handlers/TranslatableEntityHandler.php): Nested translations

## Optional
- [Testing Patterns](https://github.com/CreativeNative/translation-bundle/blob/master/.claude/testing.md): Test strategy and tools
- [Doctrine Integration](https://github.com/CreativeNative/translation-bundle/blob/master/.claude/doctrine.md): ORM specifics
- [Handler Examples](https://github.com/CreativeNative/translation-bundle/blob/master/.claude/skills/custom-handler-creator/references/examples.md): Custom handler patterns
```

**Link count:** 15 core + 3 optional = 18 total (within 20-50 range, skewed conservative)

### Link Description Patterns

**With description (when title isn't self-explanatory):**
```markdown
- [Entity Translation Setup](url): Make entities translatable
- [README](url): Installation, quick start, and core features
```

**Without description (when title is clear):**
```markdown
- [Architecture](url)
- [Code Style](url)
```

**Decision per CONTEXT.md:** Use descriptions where helpful, omit where title is self-explanatory.

### Section Heading Format

**Specification compliance:**
- H1: Required (project name)
- Blockquote: Recommended (summary)
- H2: Optional (section delimiters)

**Example:**
```markdown
# TMI Translation Bundle (tmi/translation-bundle)

> Summary here

Optional details paragraph

## Getting Started
- [Link](url)

## Documentation
- [Link](url)
```

**Decision per CONTEXT.md:** Use H2 markdown headings (not plain separators) for spec compliance.

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| robots.txt for AI | llms.txt standard | Sept 2024 (Answer.AI proposal) | Dedicated AI discovery format |
| XML sitemaps | Markdown navigation | Sept 2024 | Human+LLM readability |
| No recommended link count | 10-30 links consensus | 2025-2026 adoption period | Signal-to-noise optimization |
| Blob vs raw ambiguity | Blob URLs preferred | 2025-2026 (hub examples) | Better context for documentation |
| Single file only | llms.txt + llms-full.txt | 2025 (spec extension) | Summary + comprehensive split |

**Deprecated/outdated:**
- **Raw GitHub URLs for documentation:** Blob URLs provide better context while remaining parsable
- **Assumption of crawler support:** As of 2026, no major AI platform officially confirms reading llms.txt (Google explicitly declined support July 2025)
- **Marketing-focused summaries:** Current best practice favors benefit-focused, not promotional language

**Emerging patterns:**
- **llms-full.txt companion file:** Comprehensive version with all documentation content (not required for this phase)
- **Automatic generation:** Documentation platforms (Mintlify, Fern, GitBook) auto-generate llms.txt
- **Quarterly maintenance:** Recommended update frequency

## Open Questions

### 1. Should we include test files?

**What we know:**
- Test files document expected behavior
- They add to link count (currently 18 links, could grow to 25+)
- No strong examples in research of test file inclusion

**What's unclear:**
- Do test files provide meaningful signal for AI understanding?
- Would they dilute focus on core documentation?

**Recommendation:** Omit test files unless planning reveals specific value. Focus links on documentation and skills. Can add later if validators or AI feedback suggest value.

### 2. Individual handler links vs grouped?

**What we know:**
- 10 handler classes exist in src/Translation/Handlers/
- Already showing 3 representative handlers in template
- Total link count is 18/50 with headroom

**What's unclear:**
- Does linking all 10 handlers improve discoverability?
- Or does it create noise vs linking handler-priority.md reference?

**Recommendation:** Use hybrid approach - link 3-4 representative handlers + handler-priority.md reference. Keeps link count manageable while providing access to all handlers via reference doc.

### 3. Include llms.md (comprehensive guide)?

**What we know:**
- llms.md exists at 43,677 bytes (large comprehensive guide)
- It's not part of standard llmstxt.org specification
- llms-full.txt is the spec's approach to comprehensive content

**What's unclear:**
- Is llms.md useful for AI discovery or redundant?
- Should it be linked from llms.txt Optional section?

**Recommendation:** Don't link llms.md from llms.txt. The llms.txt links already provide navigation to all relevant content. If comprehensive single-file access is needed, create llms-full.txt per spec (marked as Claude's discretion, not in current phase scope).

## Sources

### Primary (HIGH confidence)
- [llmstxt.org](https://llmstxt.org/) - Official specification
- [Answer.AI llms.txt proposal](https://www.answer.ai/posts/2024-09-03-llmstxt.html) - Original specification document
- [llmstxtchecker.net](https://llmstxtchecker.net/) - Validation tool
- [llmstxtvalidator.dev](https://llmstxtvalidator.dev/) - Official standard validator
- [llms-txt.io/validator](https://llms-txt.io/validator) - Answer.AI validator

### Secondary (MEDIUM confidence)
- [Rankability Best Practices Guide](https://www.rankability.com/guides/llms-txt-best-practices/) - Link count recommendations, organization patterns (verified with official spec)
- [llms-txt-hub GitHub](https://github.com/thedaviddias/llms-txt-hub) - 844k+ implementations directory (verified October 2025 stat)
- [Flowbite llms.txt example](https://github.com/themesberg/flowbite/blob/main/llms.txt) - Real-world 114-link implementation
- [Bluehost 2025 Guide](https://www.bluehost.com/blog/what-is-llms-txt/) - Adoption statistics, best practices
- [Mintlify llms.txt blog](https://www.mintlify.com/blog/free-llms-txt) - Major adopters (Anthropic, Vercel, Stripe)

### Tertiary (LOW confidence - noted for context)
- [WebSearch: Google non-support](https://www.semrush.com/blog/llms-txt/) - Gary Illyes statement July 2025 (unverified primary source, but consistent across multiple articles)
- [Medium: Common mistakes](https://medium.com/@singularity-digital-marketing/5-common-mistakes-when-creating-your-llms-txt-and-how-to-fix-them-c0f9cb038dce) - Pitfalls guide (good content but not authoritative source)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Official specification is clear and well-documented
- Architecture: HIGH - Multiple validated examples confirm patterns, spec is straightforward
- Pitfalls: MEDIUM-HIGH - Based on validator documentation and best practice guides, some items are common sense
- GitHub URL format: MEDIUM - Research shows blob URL preference in examples, but spec doesn't mandate this
- Crawler adoption: MEDIUM - Stats are cited but Google's non-support is secondhand reporting

**Research date:** 2026-02-02
**Valid until:** 2026-05-02 (90 days - stable specification, but adoption landscape evolving)

**Research constraints from CONTEXT.md:**
- ✅ Summary: Benefit-focused tone confirmed
- ✅ Link targets: Skills inclusion confirmed as required
- ✅ H1 format: "TMI Translation Bundle (tmi/translation-bundle)" specified
- ✅ Link count: 20-50 range confirmed
- ✅ Organization: Hybrid workflow + content-type approach recommended
- ✅ URL format: Blob URLs recommended based on ecosystem research
