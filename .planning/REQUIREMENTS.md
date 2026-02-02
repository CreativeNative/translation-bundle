# Requirements: TMI Translation Bundle v1.1

**Defined:** 2026-02-02
**Core Value:** Any AI assistant can help open source users implement translations correctly on first try

## v1.1 Requirements

Requirements for AI-Ready Documentation milestone.

### Foundation

- [x] **FOUND-01**: llms.md includes handler chain decision tree showing which handler processes each field type
- [x] **FOUND-02**: llms.md includes troubleshooting section with common problems and solutions
- [x] **FOUND-03**: llms.md includes minimal working example (entity to translatable walkthrough)
- [x] **FOUND-04**: Terminology is consistent across all documentation files

### Skills

- [x] **SKILL-01**: entity-translation-setup skill guides AI through making any entity translatable
- [ ] **SKILL-02**: translation-debugger skill helps AI diagnose and fix translation issues
- [ ] **SKILL-03**: custom-handler-creator skill guides AI through extending the handler chain

### Web Discovery

- [ ] **WEB-01**: llms.txt exists at repository root for AI crawler discovery

## Future Requirements

Deferred to later milestones.

### Advanced Skills

- **SKILL-04**: Migration skill for converting from other translation bundles
- **SKILL-05**: Performance optimization skill for large datasets

### Automation

- **AUTO-01**: CI validation of code examples in documentation
- **AUTO-02**: Automated llms.txt generation from documentation

## Out of Scope

| Feature | Reason |
|---------|--------|
| Video tutorials | Text-based AI docs only |
| Interactive playground | Beyond documentation scope |
| Translation management UI | Bundle is code-level only |
| Multi-language docs (i18n) | English-first for v1.1 |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| FOUND-01 | Phase 6 | Complete |
| FOUND-02 | Phase 6 | Complete |
| FOUND-03 | Phase 6 | Complete |
| FOUND-04 | Phase 6 | Complete |
| SKILL-01 | Phase 7 | Complete |
| SKILL-02 | Phase 8 | Pending |
| SKILL-03 | Phase 8 | Pending |
| WEB-01 | Phase 9 | Pending |

**Coverage:**
- v1.1 requirements: 8 total
- Mapped to phases: 8
- Unmapped: 0 (100% coverage)

---
*Requirements defined: 2026-02-02*
*Last updated: 2026-02-02 after Phase 7 completion*
