# Technology Stack: AI Documentation

**Project:** TMI Translation Bundle - AI Documentation Milestone
**Researched:** 2026-02-02
**Confidence:** HIGH

## Executive Summary

AI-optimized documentation uses three complementary formats: **llms.txt** for web discovery, **CLAUDE.md** for project context, and **Claude Code Skills** for executable workflows. All three use Markdown, the de facto standard for LLM-readable content.

**Recommended approach:** Create llms.txt (web discoverability), CLAUDE.md (local context), and a usage skill (interactive guidance).

## Recommended Stack

### Core Documentation Formats

| Format | Purpose | Location | When to Use |
|--------|---------|----------|-------------|
| **llms.txt** | Web discovery & summarization | `/llms.txt` | Web-hosted docs, AI search visibility |
| **CLAUDE.md** | Project context & conventions | Root or `.claude/` | Local development, persistent context |
| **Claude Code Skill** | Interactive workflows | `.claude/skills/` | Complex workflows, bundled scripts |

### Format Details

#### llms.txt

**Version:** Community standard (2024-present, growing adoption)
**Specification:** https://llmstxt.org/
**Status:** Informal standard, adopted by 844,000+ websites (Oct 2025)

**What it is:**
- Markdown file at `/llms.txt` providing LLM-friendly content summary
- Links to detailed markdown files
- Optional `/llms-full.txt` with entire documentation concatenated

**Structure:**
```markdown
# Project Name

> Brief summary with key information for understanding the project

## Optional descriptive sections

Details about the project, how to use it, architecture, etc.

## Section Name (H2 headers delimit file lists)

- [File Name](url): Description
- [Another File](url): Description

## Optional

Files here can be skipped if shorter context is needed
```

**Best practices:**
- Keep main file concise (table of contents style)
- Link to `.md` versions of pages (append `.md` to URLs)
- Only H1 required, all else optional
- Use for web-hosted documentation

**Adoption in 2026:**
- Mintlify: Auto-generates for all hosted docs
- GitBook: Auto-generates llms.txt and llms-full.txt
- Docusaurus: Plugin available
- Used by: Anthropic, Cloudflare, Docker, HubSpot, Cursor, Pinecone

#### CLAUDE.md

**Version:** Claude Code standard (2024-present)
**Documentation:** https://code.claude.com/docs/en/best-practices
**Status:** Official Claude Code feature

**What it is:**
- Markdown file Claude Code reads automatically at session start
- Provides project-specific context, conventions, and rules
- No required format, human-readable preferred

**Locations (in priority order):**
1. Project root: `CLAUDE.md` (team-shared, version controlled)
2. Claude directory: `.claude/CLAUDE.md` (alternative location)
3. User-level: `~/.claude/CLAUDE.md` (personal defaults)
4. Rules directory: `.claude/rules/*.md` (modular, auto-loaded)

**Recommended structure:**
```markdown
# Project Name

## Tech Stack
[Your stack and versions]

## Project Structure
[Architecture overview, key directories]

## Commands
- Run tests: [command]
- Build: [command]
- Lint: [command]

## Code Style
[Syntax preferences, naming conventions]

## Workflow
[Development process, commit conventions]

## Gotchas
[Project-specific warnings]
```

**Best practices (2026 consensus):**
- **Keep concise:** < 300 lines ideal, < 60 lines best
- **Universal applicability:** Info relevant to all sessions
- **No deep specifics:** Avoid "how to structure a new schema" (use skills instead)
- **Progressive disclosure:** General rules here, specific workflows in skills
- **Version control:** Commit to git for team sharing
- **Iterate:** Refine based on observed behavior
- **Emphasis:** Use "IMPORTANT" or "YOU MUST" for critical rules
- **Avoid negatives-only:** Always provide the alternative

**Key insight:** CLAUDE.md has superior instruction adherence compared to user prompts - Claude treats it as immutable system rules.

#### Claude Code Skills

**Version:** Agent Skills open standard + Claude Code extensions
**Documentation:** https://code.claude.com/docs/en/skills
**Standard:** https://agentskills.io/
**Status:** Official format, cross-tool compatible

**What it is:**
- Folder containing `SKILL.md` + optional scripts/resources
- YAML frontmatter tells Claude when to use it
- Markdown body provides instructions
- Can be invoked manually (`/skill-name`) or automatically by Claude

**Structure:**
```
.claude/skills/bundle-usage/
├── SKILL.md              # Main instructions (required)
├── examples/
│   └── sample-usage.md   # Examples
└── templates/
    └── integration.php   # Code templates
```

**SKILL.md format:**
```markdown
---
name: skill-name
description: What this does and when to use it. Include key terms and triggers.
---

# Skill Name

## Quick Start

[Basic usage instructions]

## Advanced

See [examples/sample-usage.md](examples/sample-usage.md) for complete examples.
```

**Frontmatter fields (all optional):**
- `name`: Lowercase, hyphens, max 64 chars (becomes `/slash-command`)
- `description`: Max 1024 chars, third person, include when-to-use triggers
- `disable-model-invocation`: `true` = only user can invoke
- `user-invocable`: `false` = hide from `/` menu
- `allowed-tools`: Tools Claude can use without approval
- `context`: `fork` = run in isolated subagent
- `agent`: Subagent type (`Explore`, `Plan`, etc.)

**Best practices (official Anthropic guidance):**
- **Concise SKILL.md:** < 500 lines, move details to separate files
- **Progressive disclosure:** Main file as table of contents, details in linked files
- **Clear descriptions:** Both what and when (Claude uses for discovery)
- **Gerund naming:** `processing-pdfs` not `pdf-processor`
- **One level deep:** Reference files from SKILL.md, not from other references
- **Bundle scripts:** Include executable helpers for reliability
- **Test all models:** Haiku, Sonnet, Opus have different needs
- **Create evaluations first:** Build test scenarios before extensive docs

**String substitutions:**
- `$ARGUMENTS` - All arguments passed to skill
- `$ARGUMENTS[N]` or `$N` - Specific argument by index
- `${CLAUDE_SESSION_ID}` - Current session ID

**Dynamic context injection:**
- `!`command`` - Run shell command, output replaces placeholder

**Skill locations (priority order):**
1. Enterprise: Managed settings (organization-wide)
2. Personal: `~/.claude/skills/` (all your projects)
3. Project: `.claude/skills/` (this project only)
4. Plugin: `<plugin>/skills/` (where plugin enabled)

**Open source skill bundles (2026):**
- **anthropics/skills:** Official skills (Apache 2.0), docx/pdf/pptx/xlsx
- **VoltAgent/awesome-agent-skills:** 200+ skills, cross-tool compatible
- **travisvn/awesome-claude-skills:** Curated collection
- **alirezarezvani/claude-skills:** Production-ready skill packages
- **claude-code-skill-factory:** Toolkit for building skills at scale

### Supporting Tools

| Tool | Purpose | Notes |
|------|---------|-------|
| **Markdown** | All documentation | De facto LLM format standard |
| **Git** | Version control | CLAUDE.md and skills should be committed |
| **Text editor** | Creation | No special tools required |

## Markdown: The Universal Standard

**Why Markdown for AI documentation:**

1. **Token efficiency:** Lighter than JSON, XML, HTML
2. **Structure preservation:** Headings, lists, code blocks parsed cleanly
3. **Reduced ambiguity:** Clear semantic structure prevents confusion
4. **Human + Machine readable:** Same file works for both
5. **LLM training:** Models trained extensively on Markdown
6. **Wide support:** All documentation platforms support it

**Best practices for LLM-friendly Markdown (2026):**

**Structure:**
- Clear heading hierarchy (H1 → H2 → H3, don't skip levels)
- Short paragraphs (easier to chunk)
- Bullet and numbered lists for enumeration
- Code blocks with language tags
- One concept per page

**Content:**
- Consistent terminology (same word for same concept)
- Concise language (avoid fluff)
- Explicit context (don't assume knowledge)
- Concrete examples (show, don't just tell)
- Direct language (avoid metaphors)

**Avoid:**
- Giant walls of text
- Skipped heading levels
- Inconsistent term usage
- Complex jargon without explanation
- Figurative language

## Installation & Setup

### Creating llms.txt

**Manual approach:**
```bash
# Create llms.txt at web root
cat > llms.txt << 'EOF'
# TMI Translation Bundle

> Symfony bundle for same-table multilingual entity storage with locale-aware queries

## Core Concepts

TranslatableInterface and TranslatableTrait enable entities to store translations
in the same table using JSON columns. Handler chain pattern processes all field types.

## Documentation

- [README](https://github.com/yourorg/translation-bundle/blob/master/README.md): Overview and installation
- [Usage Guide](https://github.com/yourorg/translation-bundle/blob/master/docs/usage.md): Basic usage
- [Handler Guide](https://github.com/yourorg/translation-bundle/blob/master/docs/handlers.md): Field type handlers
EOF
```

**Automated approach (if using doc platform):**
- **Mintlify:** Automatic, no setup required
- **GitBook:** Automatic, no setup required
- **Docusaurus:** Install `docusaurus-plugin-llms-txt`
- **MkDocs:** Install `mkdocs-llmstxt-plugin`

### Creating CLAUDE.md

```bash
# Initialize with Claude Code
claude /init

# Or create manually
cat > CLAUDE.md << 'EOF'
# TMI Translation Bundle

## Tech Stack
- PHP 8.1+
- Symfony 6.4+ / 7.0+
- Doctrine ORM

## Project Structure
- `src/` - Bundle source
- `tests/` - PHPUnit tests
- `docs/` - Documentation

## Commands
- Run tests: `vendor/bin/phpunit`
- Code style: `vendor/bin/phpcs`

## Code Style
- PSR-12 standard
- Type hints required
- Null safety critical for locale handling

## Gotchas
- Always use FilterHelper for locale queries
- Handler priority determines execution order
- JSON columns require doctrine/dbal 3.0+
EOF
```

### Creating Claude Code Skill

```bash
# Create skill directory
mkdir -p .claude/skills/bundle-usage

# Create SKILL.md
cat > .claude/skills/bundle-usage/SKILL.md << 'EOF'
---
name: bundle-usage
description: Guide for using TMI Translation Bundle. Use when implementing translations, setting up entities, or configuring locale handling.
---

# TMI Translation Bundle Usage

## Quick Start

1. Add TranslatableInterface and TranslatableTrait to your entity
2. Mark translatable fields with #[Translatable] attribute
3. Use FilterHelper::createJoinAndWhere() for locale queries

## Examples

See [examples/entity-setup.md](examples/entity-setup.md) for complete entity configuration.

See [examples/query-usage.md](examples/query-usage.md) for locale-aware queries.
EOF

# Create example files
mkdir -p .claude/skills/bundle-usage/examples

cat > .claude/skills/bundle-usage/examples/entity-setup.md << 'EOF'
# Entity Setup Example

[Example entity configuration...]
EOF
```

## Documentation Platform Comparison

| Platform | llms.txt | CLAUDE.md | Skill Support | Best For |
|----------|----------|-----------|---------------|----------|
| **GitHub** | Manual | Manual | Manual | Open source projects |
| **Mintlify** | Auto | N/A | N/A | API documentation |
| **GitBook** | Auto | N/A | MCP server | Product docs |
| **Docusaurus** | Plugin | N/A | N/A | Custom React docs |
| **Local only** | N/A | Auto (Claude Code) | Native | Development-focused |

**Recommendation for TMI Translation Bundle:**
- **GitHub:** Host README.md and docs/
- **llms.txt:** Create manually, link to GitHub markdown files
- **CLAUDE.md:** Project root, version controlled
- **Skill:** `.claude/skills/bundle-usage/` with examples

## Integration Patterns

### Pattern 1: Web + Local (Recommended)

**For open source libraries like TMI Translation Bundle:**

```
/llms.txt                           # Web discovery
/README.md                          # Main documentation
/docs/
  usage.md                          # Usage guide
  handlers.md                       # Handler guide
  architecture.md                   # Architecture
CLAUDE.md                           # Local context
.claude/skills/bundle-usage/        # Interactive guide
  SKILL.md
  examples/
    entity-setup.md
    query-usage.md
```

**Benefits:**
- llms.txt enables AI search/chat discovery
- CLAUDE.md provides local development context
- Skill gives interactive, example-driven guidance
- All markdown, all version controlled

### Pattern 2: Documentation Platform

**If using Mintlify/GitBook:**

```
Mintlify/GitBook docs               # Hosted, auto-generates llms.txt
CLAUDE.md                           # Local context
.claude/skills/bundle-usage/        # Local workflows
```

### Pattern 3: Minimal (Acceptable)

**If time-constrained:**

```
CLAUDE.md                           # Essential project context
.claude/skills/bundle-usage/        # Interactive examples
```

Skip llms.txt initially (can add later for web visibility).

## Verification Checklist

Before publishing:

**llms.txt:**
- [ ] Located at `/llms.txt` (web root)
- [ ] H1 with project name present
- [ ] Links to `.md` files work
- [ ] File under 10KB (keep concise)
- [ ] No time-sensitive information

**CLAUDE.md:**
- [ ] Under 300 lines (ideally < 100)
- [ ] Tech stack documented
- [ ] Commands listed
- [ ] Code style defined
- [ ] Project-specific gotchas included
- [ ] Committed to version control

**Skill:**
- [ ] SKILL.md under 500 lines
- [ ] Description includes when-to-use triggers
- [ ] Name uses lowercase-with-hyphens
- [ ] Examples in separate files
- [ ] Works with `/skill-name` invocation
- [ ] Tested with real queries

## Sources

**llms.txt Specification:**
- [llmstxt.org](https://llmstxt.org/) - Official specification
- [Mintlify: Simplifying docs for AI](https://www.mintlify.com/blog/simplifying-docs-with-llms-txt)
- [What Is llms.txt? The New AI Web Standard](https://www.promarketer.ca/post/what-is-llms-txt)

**CLAUDE.md Best Practices:**
- [Claude Code Best Practices](https://code.claude.com/docs/en/best-practices)
- [Writing a good CLAUDE.md](https://www.humanlayer.dev/blog/writing-a-good-claude-md)
- [The Complete Guide to CLAUDE.md](https://www.builder.io/blog/claude-md-guide)
- [What Great CLAUDE.md Files Have in Common](https://blog.devgenius.io/what-great-claude-md-files-have-in-common-db482172ad2c)

**Claude Code Skills:**
- [Extend Claude with skills](https://code.claude.com/docs/en/skills) - Official documentation
- [Skill authoring best practices](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/best-practices)
- [Inside Claude Code Skills: Structure, prompts, invocation](https://mikhail.io/2025/10/claude-code-skills/)
- [VoltAgent/awesome-agent-skills](https://github.com/VoltAgent/awesome-agent-skills)
- [anthropics/skills](https://github.com/anthropics/skills) - Official repository

**Markdown for LLMs:**
- [Why Markdown is the best format for LLMs](https://medium.com/@wetrocloud/why-markdown-is-the-best-format-for-llms-aa0514a409a7)
- [Boosting AI Performance: The Power of LLM-Friendly Content in Markdown](https://developer.webex.com/blog/boosting-ai-performance-the-power-of-llm-friendly-content-in-markdown)
- [GitBook LLM-ready docs](https://gitbook.com/docs/publishing-documentation/llm-ready-docs)

**Documentation Platforms:**
- [GitBook vs Mintlify](https://www.gitbook.com/blog/gitbook-vs-mintlify)
- [Best Developer Documentation Tools in 2025](https://dev.to/infrasity-learning/best-developer-documentation-tools-in-2025-mintlify-gitbook-readme-docusaurus-10fc)

---

## Next Steps for Implementation

1. **Create CLAUDE.md** (highest priority) - Immediate value for local development
2. **Create usage skill** - Interactive guidance with examples
3. **Create llms.txt** - Web discoverability (can defer if time-limited)
4. **Test with Claude Code** - Verify skill triggers correctly
5. **Iterate** - Refine based on real usage

**Estimated effort:**
- CLAUDE.md: 30-60 minutes (use `/init` then customize)
- Skill: 1-2 hours (main file + examples)
- llms.txt: 15-30 minutes (once other docs are in place)
