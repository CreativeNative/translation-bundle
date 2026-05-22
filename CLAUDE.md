# TMI Translation Bundle

Symfony bundle for multilingual entity translations stored in the same table — no joins.

## Rules

- Never delete files, badges, annotations, or content without explicit user approval. If something appears unnecessary, ask before removing it.
- GitHub releases and pushes target **origin** (`CreativeNative/translation-bundle`), never `upstream` (`umanit`). Confirm the remote before any push or release.

## Environment

- **PHP runs via Docker** — always use `docker exec php <command>`
- PHP is NOT in the local PATH
- **GitHub remote**: `origin` = `CreativeNative/translation-bundle` (push here), `upstream` = `umanit/translation-bundle` (read-only fork source)

## Commands

| Command | Description |
|---------|-------------|
| `docker exec php composer test` | PHPUnit with coverage |
| `docker exec php composer cs-fix` | Fix coding standards |
| `docker exec php composer stan` | PHPStan level max |
| `docker exec php composer check` | All checks (test + cs + stan) |

## Workflow

- Before starting any larger change, run `docker exec php composer update` first. `composer.lock` is gitignored, so CI always resolves dependencies fresh — a stale local `vendor/` can hide errors (e.g. newer PHPStan rules) that only surface in CI.
- Before every commit: run `docker exec php composer check` (cs-fix + stan + test) and stage any resulting changes. All three must pass: 0 PHPStan errors, 100% code coverage.
- Research: prefer the Context7 MCP server (`resolve-library-id` + `query-docs`) over fetching websites

## Guidelines

- [Code Style & PHP](.claude/code-style.md)
- [Testing](.claude/testing.md)
- [Architecture & Patterns](.claude/architecture.md)
- [Doctrine Integration](.claude/doctrine.md)
