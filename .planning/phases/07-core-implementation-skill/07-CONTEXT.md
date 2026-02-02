# Phase 7: Core Implementation Skill - Context

**Gathered:** 2026-02-02
**Status:** Ready for planning

<domain>
## Phase Boundary

A Claude Code skill (`entity-translation-setup`) that guides users through making any Doctrine entity translatable. The skill provides code templates, explains the TranslatableTrait, and guides attribute decisions. Skill file under 200 lines with references for detailed documentation.

</domain>

<decisions>
## Implementation Decisions

### Skill Trigger & Invocation
- Auto-activate on phrases like "make this entity translatable", "add translations to Product"
- Announce activation: "I'll use the entity-translation-setup skill to guide you through this."
- Ask upfront for information (locale field, which fields to translate) before generating code
- Offer quick mode: "Want me to use defaults, or walk through each decision?"

### Information Gathering Flow
- First ask: which fields to translate (after reading the entity)
- Show only translatable field types (hide IDs, timestamps, etc.)
- Automatically detect and ask about relationships: "Product has OneToMany to ProductImage. Should images be translated together?"
- Provide detailed explanation of what TranslatableTrait adds (tuuid, locale, translations fields) before moving to user's fields

### Code Generation Style
- Diff-style presentation: show changes with + markers and contextual lines
- Include inline comments explaining the changes
- Ask before applying: "Apply these changes?" — wait for user confirmation
- Show migration command after changes: `bin/console doctrine:migrations:diff`

### Attribute Decision Guidance
- Examples-first for SharedAmongstTranslations: "Like a product SKU or creation date — same value regardless of language"
- Examples-first for EmptyOnTranslate: "Like a 'slug' or 'seoUrl' — needs new value in each language"
- Smart suggestions for common field names (price → SharedAmongstTranslations, slug → EmptyOnTranslate) with confirmation
- Detailed inline explanation of relationship handler chain behavior (not just link to docs)

### Claude's Discretion
- Exact wording of prompts and explanations
- Order of follow-up questions after initial field selection
- How to handle edge cases (entities with no obvious translatable fields)
- Quick mode defaults selection

</decisions>

<specifics>
## Specific Ideas

- TranslatableTrait already provides tuuid, locale, and translations fields — skill focuses on user's own fields and attributes
- The trait is at `src/Doctrine/Model/TranslatableTrait.php` with tuuid already marked SharedAmongstTranslations
- Skill should feel like a guided setup wizard, not a quiz

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 07-core-implementation-skill*
*Context gathered: 2026-02-02*
