# Phase 6: Foundation Documentation - Context

**Gathered:** 2026-02-02
**Status:** Ready for planning

<domain>
## Phase Boundary

Enhanced llms.md documentation that enables AI assistants to understand the bundle's handler chain architecture and guide users through common scenarios. Includes handler decision tree, troubleshooting section, and minimal working example. Skills and web discovery are separate phases.

</domain>

<decisions>
## Implementation Decisions

### Handler decision tree format
- ASCII art format (works everywhere, no rendering dependencies)
- Start with field type routing ("What kind of field?"), then show which handler catches it
- Include handler priorities with each handler shown
- Separate explanation section after tree explaining WHY the priority order matters
- Claude's discretion: whether to include "no handler matches" fallback path

### Troubleshooting section
- 8-10 entries covering both setup mistakes and runtime surprises equally
- Claude's discretion: structure each entry based on problem type (Symptom→Cause→Fix vs diagnostic steps)
- Include code snippets only when code is the actual fix
- Skip code for config-only or conceptual fixes

### Minimal example walkthrough
- Use Product entity (name, description, price) — e-commerce domain
- Include one simple ManyToOne relationship (Category) to demonstrate relationship handling
- Narrative flow structure: "You have a Product entity. To make it translatable, first..."
- Explain the "why" behind each change — rationale for interface, trait, Tuuid, attributes

### Terminology consistency
- "Tuuid" wins (not "Translation UUID" or "translation ID") — matches code
- "Translatable entity" for entities implementing TranslatableInterface
- Both glossary section at top AND inline definitions on first use
- Claude's discretion: whether to explicitly call out "don't use X" for confusing synonyms

### Claude's Discretion
- Decision tree: whether to show fallback/default handler path
- Troubleshooting entry structure per problem type
- Whether to add explicit "don't use X, use Y" terminology warnings

</decisions>

<specifics>
## Specific Ideas

- Product with Categories demonstrates SharedAmongstTranslations naturally (price shared, Category relation)
- Narrative should feel like teaching, not reference documentation
- Glossary serves as quick lookup, inline definitions serve reading flow

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 06-foundation-documentation*
*Context gathered: 2026-02-02*
