# Phase 9: Web Discovery - Context

**Gathered:** 2026-02-02
**Status:** Ready for planning

<domain>
## Phase Boundary

Create llms.txt file at repository root for AI crawler discovery and indexing. File follows llmstxt.org specification with H1 project name, summary blockquote, and 20-50 structured navigation links to documentation.

</domain>

<decisions>
## Implementation Decisions

### Content summary
- Benefit-focused tone, not technical-precise ("Make any Doctrine entity translatable..." not "Handler chain architecture...")
- Explicitly mention target audience (Symfony developers building multilingual applications)
- H1 title uses both formats: "TMI Translation Bundle (tmi/translation-bundle)"

### Link targets
- Include skills from .claude/ directory — they're AI-specific documentation
- Skills are core content for AI discovery

### Claude's Discretion
- Summary length (1 sentence vs 2-3 sentences) — balance brevity with completeness
- Link organization structure (topic-based, workflow-based, or hybrid)
- Section heading format (H2 markdown vs plain separators) — based on spec compliance
- Total link count within 20-50 range — balance coverage with signal-to-noise
- Link descriptions — use where helpful, omit where title is self-explanatory
- Source file inclusion — include key interfaces if they help AI understanding
- URL format — raw vs blob GitHub URLs based on crawler preferences
- Test file inclusion — evaluate if they add meaningful signal
- Link granularity — section anchors where they significantly improve navigation
- Skill depth — main SKILL.md vs including references/ files
- Split files — llms.txt only vs llms.txt + llms-full.txt based on content volume
- Handler linking — individual vs grouped based on link count limits

</decisions>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches following llmstxt.org specification

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 09-web-discovery*
*Context gathered: 2026-02-02*
