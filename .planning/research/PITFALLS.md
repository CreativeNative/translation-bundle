# Domain Pitfalls: AI Documentation for Technical Libraries

**Domain:** AI-friendly documentation (llms.md/llms.txt, Claude Skills) for Symfony bundle
**Researched:** 2026-02-02
**Overall confidence:** HIGH (verified with official sources and 2025-2026 practices)

## Critical Pitfalls

Mistakes that cause AI tools to misuse your library or fail to discover documentation.

### Pitfall 1: Content Type Misconfiguration

**What goes wrong:** The llms.txt file and linked resources don't serve as `text/markdown` or `text/plain`, causing AI crawlers to reject the file entirely.

**Why it happens:** Web servers default to application/octet-stream for .txt files without proper MIME configuration. GitHub Pages and many hosting services don't automatically configure markdown MIME types.

**Consequences:**
- AI assistants cannot parse your documentation
- Zero visibility in AI-powered tools despite having documentation
- Silent failure (no error messages, just ignored)

**Prevention:**
```yaml
# .htaccess or server config
<Files "llms.txt">
    ForceType text/plain
</Files>
<FilesMatch "\.md$">
    ForceType text/markdown
</FilesMatch>
```

**Detection:**
- Use `curl -I https://your-site.com/llms.txt` to check Content-Type header
- Should return `Content-Type: text/plain` or `text/markdown`
- Warning sign: AI assistants never reference your documentation

**Phase impact:** Phase 1 (Setup). Must be addressed during initial deployment or documentation is invisible.

**Source confidence:** HIGH - [Official llms.txt specification](https://llmstxt.org/), verified in [multiple 2025 guides](https://medium.com/@singularity-digital-marketing/5-common-mistakes-when-creating-your-llms-txt-and-how-to-fix-them-c0f9cb038dce)

---

### Pitfall 2: Information Overload (Link Dumping)

**What goes wrong:** Including 100+ links in llms.txt or massive walls of text in SKILL.md, overwhelming AI models and reducing signal-to-noise ratio.

**Why it happens:**
- Mistaking "comprehensive" for "complete exhaustiveness"
- Fear of leaving something out
- Not understanding token economics and context window constraints

**Consequences:**
- AI models skip your documentation due to poor signal quality
- Critical information buried in noise
- Increased latency and cost when documentation is loaded
- Reduced effectiveness compared to curated documentation

**Prevention:**
- **llms.txt:** Keep to 20-50 high-quality URLs maximum
- **SKILL.md:** Body should stay under 500 lines
- Use progressive disclosure pattern: overview in main file, details in referenced files
- Ask: "Would this page make sense quoted out of context?"
- Prioritize core concepts, common use cases, and gotchas

**Detection:**
- Warning sign: Your llms.txt is longer than your README
- Warning sign: SKILL.md contains complete API reference
- Warning sign: You're linking to every example file

**Phase impact:** Phase 2 (Content Creation). Address during initial writing and content review cycles.

**Source confidence:** HIGH - [Anthropic Skills Best Practices](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/best-practices), [llms.txt guidance](https://www.rankability.com/guides/llms-txt-best-practices/)

---

### Pitfall 3: Broken or Outdated Code Examples

**What goes wrong:** Code examples in documentation don't work with current library version, causing AI assistants to generate broken code that frustrates users.

**Why it happens:**
- Examples written for older versions and never updated
- No automated testing of documentation code
- Copy-paste from other sources without verification
- Documentation update lag behind code changes

**Consequences:**
- Trust erosion: developers blame AI, but root cause is documentation
- Support burden increases (repeated questions about broken examples)
- Negative reputation in developer community
- AI models learn incorrect patterns and propagate them

**Prevention:**
- **Automated validation:** Extract and run code examples in CI/CD
- **Version pinning:** Show which version examples target
```php
// TMI Translation Bundle 3.0+
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
```
- **Test matrix:** Verify examples against supported PHP/Symfony versions
- **Docs-as-tests pattern:** If examples don't run, build fails
- **Regular review:** Schedule quarterly documentation audit

**Detection:**
- Run code examples as unit tests
- Monitor GitHub issues for "documentation doesn't work"
- Check AI-generated code against your examples
- Warning sign: Release notes show breaking changes but docs unchanged

**Phase impact:** Phase 3 (Quality Assurance) and ongoing maintenance. Set up CI validation before documentation release.

**Source confidence:** HIGH - [API Documentation Best Practices 2025](https://www.theneo.io/blog/api-documentation-best-practices-guide-2025), [Developer Experience Research](https://getdx.com/blog/developer-documentation/)

---

### Pitfall 4: Assuming AI Knowledge Without Verification

**What goes wrong:** Documentation omits critical context assuming "AI already knows this," but AI models hallucinate or use outdated patterns specific to your library.

**Why it happens:**
- Overestimating AI training data coverage of niche libraries
- Confusing general concepts (AI knows) with library-specific implementation (AI doesn't)
- Not understanding AI knowledge cutoff dates

**Consequences:**
- AI generates plausible-looking but incorrect code
- Library-specific gotchas not communicated (e.g., "ManyToMany not supported with SharedAmongstTranslations")
- Users hit edge cases that seem like bugs but are documented limitations
- Complex features like handler priorities never discovered

**Prevention:**
- **Document the specific, not the general:**
  - ❌ "Translations allow you to localize content" (AI knows)
  - ✅ "This bundle stores translations in the same table using locale field" (library-specific)
- **Call out non-obvious behaviors:**
```markdown
## Critical: Handler Priority System

Handlers execute in priority order (higher = earlier).
DEFAULT priority is 0, but AutoPopulateTranslationHandler MUST run before
TranslatableOriginHandler, so it uses priority 10.

⚠️ If you create custom handlers, use priorities > 100 to run first.
```
- **Include "gotchas" section:**
```markdown
## Common Mistakes

### ManyToMany with SharedAmongstTranslations
❌ NOT SUPPORTED - Will cause runtime errors
✅ Use ManyToOne or OneToMany instead
```

**Detection:**
- Test AI-generated code from your docs with fresh AI session
- Monitor support questions: repetitive questions = documentation gap
- Warning sign: "I thought it would work like X" (AI assumption)

**Phase impact:** Phase 2 (Content Creation). Requires domain expertise to identify what's obvious vs. what's library-specific.

**Source confidence:** MEDIUM-HIGH - Derived from [Skills authoring principles](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/best-practices) and AI documentation trends

---

### Pitfall 5: Third-Person Voice Violation in Descriptions

**What goes wrong:** Using first-person ("I can help you") or second-person ("You can use this") in SKILL.md description field, causing AI discovery problems.

**Why it happens:**
- Natural to write conversationally
- Not understanding that description is injected into system prompt
- Copying patterns from chatbot interfaces

**Consequences:**
- Confusing point-of-view in AI's system prompt
- Reduced skill discovery effectiveness
- AI may not recognize when to activate the skill
- Inconsistent with how AI interprets other system instructions

**Prevention:**
```yaml
# ❌ BAD - First person
description: I can help you translate Symfony entities and handle relationships

# ❌ BAD - Second person
description: You can use this to translate entities in Symfony projects

# ✅ GOOD - Third person
description: Translates Symfony Doctrine entities with same-table storage. Use when working with multilingual entities, translation attributes, or entity localization in Symfony projects.
```

**Detection:**
- Automated check: grep for "I can" or "you can" in description fields
- During review: Read description as if it's part of system instructions
- Warning sign: Skills not triggering when expected

**Phase impact:** Phase 2 (Content Creation). Enforce during initial skill creation with linting.

**Source confidence:** HIGH - [Official Anthropic Skills documentation](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/best-practices)

---

### Pitfall 6: Deeply Nested Reference Files

**What goes wrong:** Creating reference chains like SKILL.md → advanced.md → details.md → examples.md, causing AI to use partial reads (head -100) and miss critical information.

**Why it happens:**
- Natural document organization instinct
- Not understanding AI's progressive loading behavior
- Organizing for human reading patterns instead of AI access patterns

**Consequences:**
- AI previews files with head command, sees only first 100 lines
- Critical information in deep files never discovered
- Incomplete information leads to incorrect implementations
- Users report "AI ignored the documentation"

**Prevention:**
- **Keep references one level deep from SKILL.md:**
```markdown
# SKILL.md

## Quick Start
[Basic instructions inline]

## Advanced Features
- **Attribute interactions:** See [ATTRIBUTES.md](ATTRIBUTES.md)
- **Handler priorities:** See [HANDLERS.md](HANDLERS.md)
- **Relationship handling:** See [RELATIONSHIPS.md](RELATIONSHIPS.md)

# ✅ All references direct from SKILL.md
# ❌ Don't make ATTRIBUTES.md reference DEEP_DETAILS.md
```

- **Use table of contents for long files:**
```markdown
# HANDLERS.md (250 lines)

## Contents
- Handler execution order
- Built-in handlers (AutoPopulate, TranslatableOrigin, EmptyOnTranslate)
- Priority system (0-100 scale)
- Custom handler creation
- Troubleshooting handler conflicts

[Then full content...]
```

**Detection:**
- Audit reference chains: no file should reference another file
- Check file length: files > 100 lines need table of contents
- Test: Ask AI to find information 2+ levels deep, see if it succeeds

**Phase impact:** Phase 2 (Content Creation) and Phase 4 (Refinement). Restructure during architecture planning.

**Source confidence:** HIGH - [Anthropic Skills best practices](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/best-practices#avoid-deeply-nested-references)

---

## Moderate Pitfalls

Mistakes that cause delays, confusion, or technical debt.

### Pitfall 7: Time-Sensitive Information in Documentation

**What goes wrong:** Including dates and version cutoffs that quickly become outdated ("If you're doing this before August 2025..."), making documentation stale.

**Why it happens:**
- Natural to reference current state during migrations
- Helpful context during transition periods
- Not thinking about documentation lifecycle

**Consequences:**
- Documentation becomes misleading after cutoff dates
- AI might give wrong advice based on stale temporal markers
- Creates maintenance burden (manual updates needed)
- Confusing for users who don't know "when" documentation was written

**Prevention:**
- **Use "old patterns" sections instead:**
```markdown
## Current Method (v3.0+)

Use `TranslatableInterface` and `TranslatableTrait`:

```php
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

class Product implements TranslatableInterface {
    use TranslatableTrait;
}
```

<details>
<summary>Legacy Pattern (v2.x - deprecated)</summary>

The v2.x API used different interfaces:

```php
// DON'T USE - This is for historical reference only
use Umanit\TranslationBundle\Model\TranslatableModelInterface;
```

This approach is no longer supported.
</details>
```

**Detection:**
- Grep for date patterns: `\b(before|after|until|since)\s+(Jan|Feb|Mar|...|202\d)`
- Warning sign: Issue reports saying "documentation says to do X but that doesn't work"
- Scheduled review: quarterly check for temporal references

**Phase impact:** Phase 2 (Content Creation). Prevent during initial writing, but also ongoing maintenance concern.

**Source confidence:** HIGH - [Anthropic Skills best practices](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/best-practices#avoid-time-sensitive-information)

---

### Pitfall 8: Inconsistent Terminology

**What goes wrong:** Mixing terms like "entity translation" / "entity localization" / "translatable entities" / "multilingual models" interchangeably, confusing AI pattern matching.

**Why it happens:**
- Multiple valid terms for same concept
- Different team members writing different sections
- Trying to improve SEO with keyword variations
- Not establishing style guide early

**Consequences:**
- AI fails to recognize that sections discuss same concept
- Reduced semantic understanding
- Users confused about whether different terms mean different things
- Search/grep becomes unreliable

**Prevention:**
- **Establish terminology dictionary early:**
```markdown
# Terminology

This documentation uses these terms consistently:

- **Translatable entity**: An entity implementing TranslatableInterface
- **Translation**: A locale-specific version of an entity (NOT "localization")
- **Shared attribute**: Field marked with SharedAmongstTranslations (NOT "synchronized field")
- **Handler**: Event subscriber processing translation logic (NOT "listener" or "processor")
```

- **Automated enforcement:** Add to CI
```bash
# Check for banned terms
grep -r "localization\|synchronized field\|listener" docs/ && exit 1
```

**Detection:**
- Create list of synonyms to search for
- Review with team: "What do we call X?"
- Warning sign: Same concept explained differently in different files

**Phase impact:** Phase 2 (Content Creation). Establish style guide before bulk writing begins.

**Source confidence:** HIGH - [Anthropic Skills best practices](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/best-practices#use-consistent-terminology)

---

### Pitfall 9: Missing UTF-8 Encoding Declaration

**What goes wrong:** llms.txt or markdown files use different encoding, causing AI models to misinterpret special characters or reject the file.

**Why it happens:**
- Windows default encoding is not UTF-8
- Legacy systems or editors with wrong defaults
- Copy-paste from sources with different encoding
- Not explicitly setting encoding in version control

**Consequences:**
- Character corruption in examples (quotes, dashes, accents)
- AI rejects file as unparseable
- Symfony-specific characters (€, ä, ñ) broken in examples
- Silent failures or garbled content

**Prevention:**
```gitattributes
# .gitattributes
*.md text eol=lf encoding=utf-8
*.txt text eol=lf encoding=utf-8
llms.txt text eol=lf encoding=utf-8
```

```editorconfig
# .editorconfig
[*.{md,txt}]
charset = utf-8
end_of_line = lf
```

**Detection:**
- Automated: `file -bi llms.txt` should show `charset=utf-8`
- Visual check: Look for � or strange characters
- Test: Open in multiple editors/browsers

**Phase impact:** Phase 1 (Setup). Configure once during project initialization.

**Source confidence:** HIGH - [llms.txt specification](https://llmstxt.org/) and web standards

---

### Pitfall 10: No Validation of llms.txt Format

**What goes wrong:** llms.txt has structural errors (missing H1, wrong markdown syntax, malformed links) that break parsing.

**Why it happens:**
- Hand-editing without validation
- No tooling to check format compliance
- Specification is simple but strict
- Copy-paste errors during updates

**Consequences:**
- AI crawlers skip malformed file
- Silent failure: no error message to developer
- Links don't work as expected
- Wasted effort creating documentation that's never used

**Prevention:**
- **Create validation script:**
```bash
#!/bin/bash
# validate_llms_txt.sh

if ! head -1 llms.txt | grep -q "^# "; then
    echo "ERROR: First line must be H1 (# Project Name)"
    exit 1
fi

if ! head -5 llms.txt | grep -q "^> "; then
    echo "WARNING: Consider adding blockquote summary"
fi

# Check for malformed links
if grep -qE '\[.*\]\([^)]*\s[^)]*\)' llms.txt; then
    echo "ERROR: Links contain spaces in URLs"
    exit 1
fi

echo "llms.txt validation passed"
```

- **Add to CI pipeline:**
```yaml
# .github/workflows/docs.yml
- name: Validate llms.txt
  run: ./scripts/validate_llms_txt.sh
```

**Detection:**
- Run validation on every commit
- Manual check: Does file start with `# Project Name`?
- Test with llms.txt parsers if available

**Phase impact:** Phase 1 (Setup) for CI integration, Phase 3 (Quality Assurance) for validation.

**Source confidence:** MEDIUM - Derived from [llms.txt specification](https://llmstxt.org/) and common validation practices

---

### Pitfall 11: Offering Too Many Alternatives Without Guidance

**What goes wrong:** Listing multiple approaches without recommendation ("you can use X, or Y, or Z..."), forcing AI to guess and users to research.

**Why it happens:**
- Trying to be comprehensive
- Avoiding appearing opinionated
- Different valid approaches exist
- Fear of being wrong

**Consequences:**
- AI picks randomly or defaults to training data (which may be outdated)
- Inconsistent code generation across sessions
- Users paralyzed by choice
- Lost opportunity to guide toward best practices

**Prevention:**
- **Provide default with escape hatch:**
```markdown
## Handling Nullable Fields

Use the EmptyOnTranslate attribute for fields that should be empty in new translations:

```php
#[ORM\Column(type: 'string', nullable: true)]
#[EmptyOnTranslate]
private ?string $title = null;
```

**For fields that must maintain values across translations**, use SharedAmongstTranslations instead.
```

- **Decision matrix for valid alternatives:**
```markdown
## Unique Field Handling

**Composite unique constraint** (RECOMMENDED for most cases):
```php
#[ORM\UniqueConstraint(name: "uniq_slug_locale", columns: ["slug", "locale"])]
```
Use when: Slug should be unique per locale

**Global unique with UUID** (for multi-tenant systems):
```php
#[ORM\Column(type: 'tuuid', unique: true)]
```
Use when: Entity needs globally unique identifier across all locales
```

**Detection:**
- Search for phrases: "you can", "alternatively", "or"
- Review: Does each option explain WHEN to use it?
- Warning sign: Users ask "which one should I use?"

**Phase impact:** Phase 2 (Content Creation) and Phase 4 (Refinement based on feedback).

**Source confidence:** HIGH - [Anthropic Skills best practices](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/best-practices#avoid-offering-too-many-options)

---

### Pitfall 12: Missing Version Requirements

**What goes wrong:** Documentation doesn't specify which versions it applies to, causing AI to generate code incompatible with user's version.

**Why it happens:**
- Assuming everyone uses latest version
- Not thinking about backward compatibility
- Documentation written before version strategy established
- Rapid development cycle outpacing docs

**Consequences:**
- Generated code uses features not available in user's version
- Breaking changes not communicated
- Support burden: "This doesn't work in my version"
- User frustration and abandonment

**Prevention:**
- **Badge in documentation:**
```markdown
# TMI Translation Bundle Documentation

![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-8892BF.svg)
![Symfony 7.3+](https://img.shields.io/badge/Symfony-7.3%2B-000000.svg)
![Doctrine ORM 3.5+](https://img.shields.io/badge/Doctrine-ORM%203.5%2B-FF6D00.svg)
```

- **Version-specific examples:**
```markdown
## Installation

### Current version (3.0+, requires PHP 8.4)

```bash
composer require tmi/translation-bundle ^3.0
```

### Legacy version (2.x, supports PHP 7.4+)

See [v2 documentation](https://github.com/owner/repo/tree/2.x/docs)
```

- **Deprecation notices with timelines:**
```markdown
## Breaking Changes in 3.0

- `TranslatableModelInterface` → `TranslatableInterface`
- `LocaleFilter` moved to `Doctrine\Filter\LocaleFilter`

Migration guide: [UPGRADE-3.0.md](UPGRADE-3.0.md)
```

**Detection:**
- Check if version requirements are prominent
- Test: Can user determine if doc applies to their version?
- Warning sign: Version-related issues in bug tracker

**Phase impact:** Phase 2 (Content Creation), critical for Phase 5 (Versioning Strategy).

**Source confidence:** HIGH - [API versioning best practices](https://www.theneo.io/blog/api-documentation-best-practices-guide-2025)

---

## Minor Pitfalls

Mistakes that cause annoyance but are easily fixable.

### Pitfall 13: Windows-Style Path Separators

**What goes wrong:** Using backslashes in file paths (`scripts\helper.py`) breaks documentation on Unix systems.

**Why it happens:**
- Developing on Windows
- Not testing on multiple platforms
- Auto-generated paths from IDE

**Consequences:**
- AI-generated code fails on macOS/Linux
- Cross-platform users hit errors
- Professional appearance degraded

**Prevention:**
- Always use forward slashes: `scripts/helper.py`
- Add to style guide and linting
- Works on all platforms (Windows handles forward slashes correctly)

**Detection:**
```bash
# CI check
grep -r '\\[a-zA-Z]' docs/ *.md && exit 1
```

**Phase impact:** Phase 2 (Content Creation). Easy to fix, enforce early.

**Source confidence:** HIGH - [Anthropic Skills anti-patterns](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/best-practices#avoid-windows-style-paths)

---

### Pitfall 14: Vague Skill Naming

**What goes wrong:** Using generic names like "helper", "utils", "tools" that don't indicate what skill does.

**Why it happens:**
- Placeholder names that stick
- Lack of naming conventions
- Not understanding discovery importance

**Consequences:**
- AI can't determine when to use skill
- Developers can't find relevant skills
- Poor organization in skill library

**Prevention:**
- Use gerund form (verb + -ing): `translating-entities`, `handling-relationships`
- Or noun phrases: `entity-translation`, `relationship-handling`
- Include domain: `symfony-translation` not just `translation`

**Detection:**
- Review skill names: would you understand what it does?
- Warning sign: Generic words like "helper", "util", "manager"

**Phase impact:** Phase 1 (Setup). Name correctly from the start.

**Source confidence:** HIGH - [Anthropic Skills naming conventions](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/best-practices#naming-conventions)

---

### Pitfall 15: Missing Table of Contents for Long Files

**What goes wrong:** Reference files over 100 lines without TOC, causing AI to use head preview and miss relevant sections.

**Why it happens:**
- Not understanding AI's partial read behavior
- Natural to write top-to-bottom without navigation aids
- TOC seems like unnecessary overhead

**Consequences:**
- AI sees only first 100 lines when previewing
- Relevant information later in file never discovered
- Incomplete implementations

**Prevention:**
```markdown
# Attribute Reference (350 lines)

## Contents
- SharedAmongstTranslations: Synchronize field across translations
- EmptyOnTranslate: Clear field in new translations
- EmptyOnTranslateIfNotCollection: Conditional clearing
- Priority system for handler execution
- Combining multiple attributes

---

## SharedAmongstTranslations

[Full documentation...]
```

**Detection:**
- Check file length: `wc -l *.md`
- Files > 100 lines should have TOC
- Test: Can AI find information at line 200+?

**Phase impact:** Phase 2 (Content Creation), add during initial writing for long files.

**Source confidence:** HIGH - [Anthropic Skills structure guidelines](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/best-practices#structure-longer-reference-files-with-table-of-contents)

---

## Phase-Specific Warnings

| Phase Topic | Likely Pitfall | Mitigation | Priority |
|-------------|---------------|------------|----------|
| Initial Setup | Content type misconfiguration (#1) | Verify MIME types before deployment | CRITICAL |
| Initial Setup | Missing UTF-8 encoding (#9) | Configure .gitattributes and .editorconfig | HIGH |
| Content Creation | Information overload (#2) | Progressive disclosure, 20-50 links max | CRITICAL |
| Content Creation | Broken code examples (#3) | Extract examples to tests, run in CI | CRITICAL |
| Content Creation | Third-person voice violation (#5) | Review descriptions, add linting | HIGH |
| Content Creation | Deeply nested references (#6) | Flat structure, one level from SKILL.md | HIGH |
| Content Creation | Time-sensitive information (#7) | Use "old patterns" sections | MEDIUM |
| Content Creation | Inconsistent terminology (#8) | Establish style guide early | MEDIUM |
| Content Creation | Too many alternatives (#11) | Provide defaults with guidance | MEDIUM |
| Content Creation | Windows path separators (#13) | Use forward slashes everywhere | LOW |
| Quality Assurance | No validation (#10) | Create validation script, add to CI | MEDIUM |
| Quality Assurance | Missing version requirements (#12) | Add version badges and notices | HIGH |
| Ongoing Maintenance | Outdated examples (#3) | Quarterly documentation audit | HIGH |
| Ongoing Maintenance | Stale temporal references (#7) | Annual review for date mentions | LOW |

---

## Symfony Bundle-Specific Warnings

### Complex Feature Documentation

**Challenge:** TMI Translation Bundle has non-obvious interactions:
- Handler priorities affect execution order
- Attribute combinations have edge cases (e.g., ManyToMany unsupported with SharedAmongstTranslations)
- Relationship handling varies by relationship type

**Mitigation:**
- Create dedicated file for each complex feature: HANDLERS.md, ATTRIBUTES.md, RELATIONSHIPS.md
- Include decision trees: "If ManyToOne → use SharedAmongstTranslations; If ManyToMany → NOT SUPPORTED"
- Document the "why": "AutoPopulateTranslationHandler runs before TranslatableOriginHandler because..."

---

### Table Stakes vs. Advanced Features

**Challenge:** Balance between quick start (80% use case) and comprehensive reference (edge cases).

**Mitigation:**
- SKILL.md: Quick start with most common patterns
- Referenced files: Edge cases and advanced scenarios
- Clear signposting: "For basic usage, see below. For custom handlers, see HANDLERS.md"

---

### Known Limitations Visibility

**Challenge:** TMI bundle has known limitations (ManyToMany, unique fields without special handling). These must be prominent.

**Mitigation:**
```markdown
# TMI Translation Bundle

## ⚠️ Known Limitations

Before using this bundle, be aware:

- **ManyToMany not supported** with SharedAmongstTranslations
- **Unique fields** require composite unique constraint (see UNIQUE_FIELDS.md)
- **Requires PHP 8.4+, Symfony 7.3+, Doctrine ORM 3.5+**

---

[Rest of documentation...]
```

Place limitations prominently so AI assistants warn users early.

---

## Meta-Documentation Pitfall

### Documentation of the Pitfall

**Pitfall:** Creating PITFALLS.md that's too abstract or generic to be actionable.

**Prevention for this file:**
- ✅ Each pitfall includes specific detection method
- ✅ Phase mapping shows when to address
- ✅ Code examples show concrete prevention
- ✅ Confidence levels indicate source quality
- ✅ Priority levels guide triage

**Self-assessment of this document:**
- Comprehensive: Covers llms.txt, SKILL.md, code examples, versioning
- Actionable: Each pitfall has prevention strategy and detection method
- Prioritized: Critical/Moderate/Minor categories guide effort allocation
- Verified: Sources cited for HIGH confidence claims

---

## Sources

### llms.txt Specification and Best Practices
- [Official llms.txt specification](https://llmstxt.org/)
- [LLMS.txt Best Practices & Implementation Guide](https://www.rankability.com/guides/llms-txt-best-practices/)
- [5 Common Mistakes When Creating Your llms.txt](https://medium.com/@singularity-digital-marketing/5-common-mistakes-when-creating-your-llms-txt-and-how-to-fix-them-c0f9cb038dce)
- [What Is llms.txt? How the New AI Standard Works (2025 Guide)](https://www.bluehost.com/blog/what-is-llms-txt/)

### Claude Skills Best Practices
- [Anthropic: Skill authoring best practices](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/best-practices)
- [Anthropic: Agent Skills Overview](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/overview)
- [Claude Agent Skills: A First Principles Deep Dive](https://leehanchung.github.io/blogs/2025/10/26/claude-skills-deep-dive/)

### Technical Documentation Best Practices
- [The Ultimate Guide to API Documentation Best Practices (2025 Edition)](https://www.theneo.io/blog/api-documentation-best-practices-guide-2025)
- [Developer Documentation: How to measure impact and drive engineering productivity](https://getdx.com/blog/developer-documentation/)
- [API Documentation Best Practices for 2025](https://www.midday.io/blog/api-documentation-best-practices-for-2025)
- [Code examples on MDN - MDN Web Docs](https://developer.mozilla.org/en-US/docs/MDN/Writing_guidelines/Page_structures/Code_examples)

### API Versioning and Breaking Changes
- [API Versioning Best Practices](https://redocly.com/blog/api-versioning-best-practices)
- [API versioning best practices 2025](https://www.gravitee.io/blog/api-versioning-best-practices)

### Symfony Bundle Documentation
- [Symfony: Best Practices for Reusable Bundles](https://symfony.com/doc/current/bundles/best_practices.html)
- [Symfony: The Bundle System](https://symfony.com/doc/current/bundles.html)

### AI Documentation Trends
- [Major AI Documentation Trends for 2026](https://document360.com/blog/ai-documentation-trends/)
- [Making your content AI friendly in 2026](https://dev.to/coolasspuppy/making-your-content-ai-friendly-in-2026-58h)
- [AI can write your docs, but should it?](https://www.mintlify.com/blog/ai-can-write-your-docs-but-should-it)

---

## Confidence Assessment

| Category | Confidence | Basis |
|----------|-----------|-------|
| llms.txt pitfalls | HIGH | Official specification + multiple 2025 guides + WebSearch verified |
| Claude Skills pitfalls | HIGH | Official Anthropic documentation + hands-on examples |
| Code examples quality | HIGH | Multiple authoritative 2025 sources + MDN guidelines |
| Versioning best practices | HIGH | Industry-standard API documentation sources |
| Symfony-specific concerns | MEDIUM-HIGH | Official Symfony docs + existing bundle understanding |

**Overall assessment:** HIGH confidence in core pitfalls, actionable prevention strategies, and phase mapping.

**Gaps acknowledged:**
- Specific validation tooling for llms.txt (ecosystem still emerging in 2026)
- TMI bundle-specific edge cases (will emerge during documentation creation)
- AI crawler behavior variations across different AI platforms (limited public data)
