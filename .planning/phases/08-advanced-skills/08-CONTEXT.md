# Phase 8: Advanced Skills - Context

**Gathered:** 2026-02-02
**Status:** Ready for planning

<domain>
## Phase Boundary

Create two AI skills: a translation-debugger for diagnosing translation issues and a custom-handler-creator for extending the handler chain. Both skills under 200 lines with details in references/ subdirectories.

</domain>

<decisions>
## Implementation Decisions

### Debugger Diagnostic Flow
- Automated detection on activation — run diagnostics immediately, don't ask open-ended questions
- Check entity configuration first (interface, trait, attributes), then handler chain, then runtime state
- Present issues in dependency order (fix prerequisites before downstream issues)
- Offer to fix each issue found — "Want me to fix this?" after diagnosis

### Custom Handler Guidance Style
- Use case first — ask "What field type needs handling?" then generate tailored template
- Interactive priority selection — ask what the handler does, suggest specific priority with reasoning
- Offer tests separately — create handler first, then "Want me to add tests?" as follow-up

### Skill Activation Triggers
- Debugger: broad catch-all — any mention of "translation" + "problem/issue/wrong/broken" triggers suggestion
- Handler creator: Claude's discretion on specific triggers that avoid false positives
- Invocation mode: suggest then invoke — "I can help with that using the debugger skill — want me to run it?"
- Namespace: Claude's discretion on whether skills are independent or share a namespace

### Reference Organization
- Split criteria: Claude's discretion following Phase 7 pattern
- Shared references where sensible — common content (handler priority table) shared, skill-specific separate
- Hybrid linking — link to llms.md for deep details, include summaries inline for quick reference
- File format: Claude's discretion based on AI consumption needs

### Claude's Discretion
- Example use cases for custom handler skill (encrypted fields, computed properties, value objects, etc.)
- Handler creator activation trigger specifics
- Skill namespace organization (independent vs unified)
- SKILL.md vs references/ split approach (following Phase 7 pattern)
- Reference file formats (markdown vs separate templates)

</decisions>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches following Phase 7 patterns.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 08-advanced-skills*
*Context gathered: 2026-02-02*
