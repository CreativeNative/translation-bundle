# TMI Translation Bundle

## What This Is

A Symfony bundle for managing multilingual entity translations stored in the same database table as the source entity, eliminating expensive joins. Uses a Tuuid (Translation UUID) to group language variants and automatic locale filtering.

## Core Value

Any entity becomes translatable with a single trait and interface, storing all translations efficiently in one table without schema complexity.

## Requirements

### Validated

<!-- Shipped and confirmed valuable. -->

- [x] TranslatableInterface and TranslatableTrait for entity translation
- [x] Tuuid (Translation UUID) value object for grouping translations
- [x] Handler chain pattern for translating all field types
- [x] Automatic locale filtering via Doctrine filter
- [x] SharedAmongstTranslations attribute for synced fields
- [x] EmptyOnTranslate attribute for cleared fields
- [x] Full relationship support (OneToOne, OneToMany, ManyToOne, ManyToMany)
- [x] Event system (PRE_TRANSLATE, POST_TRANSLATE)
- [x] 100% test coverage

### Active

<!-- Current scope. Building toward these. -->

- [ ] Claude Code skill for AI-assisted implementation
- [ ] llms.md with integration examples for AI consumption

### Out of Scope

- Mobile SDK — Symfony bundle only
- Admin UI — Code-level integration only
- Translation memory/TMS integration — Bundle handles storage, not content management

## Context

**Technical environment:**
- PHP 8.4+ with strict types
- Symfony 7.3 or 8.0
- Doctrine ORM 3.5.7+
- PHPStan level 1+ with 100% test coverage

**Target audience for AI documentation:**
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

## Current Milestone: v1.1 AI-Ready Documentation

**Goal:** Make the bundle AI-friendly so any LLM assistant can help open source users implement translations correctly, understand architectural advantages, and handle edge cases.

**Target features:**
- Claude Code skill with complete implementation guide
- llms.md with real-world integration examples
- Coverage of setup, usage patterns, debugging, and pitfalls

---
*Last updated: 2026-02-02 after milestone v1.1 start*
