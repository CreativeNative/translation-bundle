# TMI Translation Bundle

## What This Is

A Symfony bundle for managing multilingual entity translations stored in the same database table as the source entity, eliminating expensive joins. Uses a Tuuid (Translation UUID) to group language variants and automatic locale filtering. Now includes comprehensive AI-optimized documentation enabling any LLM assistant to help users implement translations correctly.

## Core Value

Any entity becomes translatable with a single trait and interface, storing all translations efficiently in one table without schema complexity.

## Requirements

### Validated

<!-- Shipped and confirmed valuable. -->

**v1.0 Core Bundle:**
- [x] TranslatableInterface and TranslatableTrait for entity translation — v1.0
- [x] Tuuid (Translation UUID) value object for grouping translations — v1.0
- [x] Handler chain pattern for translating all field types — v1.0
- [x] Automatic locale filtering via Doctrine filter — v1.0
- [x] SharedAmongstTranslations attribute for synced fields — v1.0
- [x] EmptyOnTranslate attribute for cleared fields — v1.0
- [x] Full relationship support (OneToOne, OneToMany, ManyToOne, ManyToMany) — v1.0
- [x] Event system (PRE_TRANSLATE, POST_TRANSLATE) — v1.0
- [x] 100% test coverage — v1.0

**v1.1 AI-Ready Documentation:**
- [x] llms.md handler chain decision tree showing field-to-handler routing — v1.1
- [x] llms.md troubleshooting section with 10 common problems — v1.1
- [x] llms.md minimal working example with Product entity walkthrough — v1.1
- [x] Consistent terminology across all documentation (Tuuid canonical) — v1.1
- [x] entity-translation-setup skill for AI-guided implementation — v1.1
- [x] translation-debugger skill for AI-assisted issue diagnosis — v1.1
- [x] custom-handler-creator skill for handler chain extension — v1.1
- [x] llms.txt for AI crawler discovery — v1.1

### Active

<!-- Current scope. Building toward these. -->

(None — planning next milestone)

### Out of Scope

- Mobile SDK — Symfony bundle only
- Admin UI — Code-level integration only
- Translation memory/TMS integration — Bundle handles storage, not content management
- Video tutorials — Text-based AI docs only
- Interactive playground — Beyond documentation scope
- Multi-language docs (i18n) — English-first

## Context

**Current state (after v1.1):**
- Core bundle: Production-ready with 100% test coverage
- Documentation: ~6,800 lines of AI-optimized content
- AI Skills: 3 Claude Code skills (setup, debug, extend)
- Discovery: llms.txt with 27 navigation links

**Technical environment:**
- PHP 8.4+ with strict types
- Symfony 7.3 or 8.0
- Doctrine ORM 3.5.7+
- PHPStan level 1+ with 100% test coverage

**Target audience:**
- Open source Symfony developers discovering the bundle
- AI assistants helping users implement translations

**Known complexity:**
- Handler chain priority matters for nested relationships
- SharedAmongstTranslations on ManyToMany not yet supported
- Unique constraints need locale-based uniqueness in schema

## Constraints

- **Compatibility**: Must work with PHP 8.4+, Symfony 7.3/8.0
- **Quality**: 100% test coverage, PHPStan level 1 minimum
- **Documentation**: AI-optimized, not traditional prose docs

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Same-table storage | Eliminates joins, simpler queries | ✓ Good |
| Handler chain pattern | Extensible, handles all field types | ✓ Good |
| UUIDv7 for Tuuid | Time-ordered, SEO-friendly | ✓ Good |
| Tuuid as canonical term | Consistent terminology for AI consumption | ✓ Good |
| ASCII decision tree | Visual handler routing for AI assistants | ✓ Good |
| 4-layer diagnostic structure | Systematic issue diagnosis | ✓ Good |
| Examples-first skill guidance | Aligns with user mental model | ✓ Good |
| llms.txt for discovery | Standard AI crawler entry point | ✓ Good |

## Completed Milestones

- **v1.0 Core Bundle** — Production-ready translation bundle (git tag: v1.6.0)
- **v1.1 AI-Ready Documentation** — Comprehensive AI-optimized docs (git tag: v1.7.0)

See `.planning/MILESTONES.md` for full history.

---
*Last updated: 2026-02-02 after v1.1 milestone*
