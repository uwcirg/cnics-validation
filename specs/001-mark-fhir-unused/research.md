# Phase 0 Research: Mark FHIR Server References as Not Currently Used

**Feature**: 001-mark-fhir-unused
**Date**: 2026-04-14

## Scope of research

The feature has no open `NEEDS CLARIFICATION` markers and no new
dependencies to evaluate. The only real design decision is, for each of
the seven files that mentions `FHIR_SERVER`, whether to:

- **Annotate**: leave the reference in place and add an in-line comment
  explaining it is not currently used, or
- **Remove**: delete the FHIR block entirely.

This research section records that per-file decision plus supporting
evidence.

## Shared facts (apply to every decision below)

- **Decision**: `FHIR_SERVER` is consumed by zero lines of Python or
  JavaScript in this repository.
  **Rationale**: Verified by two case-insensitive searches:
  1. `flask_backend/` — 0 matches for `fhir`
  2. `frontend/` — 0 matches for `fhir`
  **Alternatives considered**: None; a negative search result is
  dispositive.

- **Decision**: `docker-entrypoint.sh` writes `/var/www/html/env.json`
  containing the FHIR value, but nothing reads that file.
  **Rationale**: Verified by a repo-wide search for `env.json` — the only
  match is `docker-entrypoint.sh` itself. No frontend bundle, backend
  handler, or build step consumes it.
  **Alternatives considered**: Keeping the pass-through "just in case" was
  considered and rejected — it is a maintenance trap because a reader of
  the entrypoint script reasonably assumes the file is consumed.

- **Decision**: A deployment somewhere in the wild may still set
  `FHIR_SERVER` in its real `.env` file.
  **Rationale**: The variable has existed since the CakePHP migration
  baseline; we cannot audit downstream deployments.
  **Alternatives considered**: Requiring downstream deployments to unset
  it was rejected because it would break Principle III (backward
  compatibility). The chosen approach tolerates the variable silently.

## Per-file decisions

### 1. `.env.example` — copied by every new contributor

- **Decision**: Retain the variable, commented out, with a
  "not currently used" note. Keep the example URL value on a commented
  line so it is obvious the entry exists for historical reasons.
- **Rationale**: This file is the primary onboarding touchpoint. Removing
  the entry would erase the context and make a future reader think FHIR
  was never part of this project. Annotating in place directly serves
  User Story 1 (P1).
- **Alternatives considered**: Full removal (rejected — loses context);
  leaving uncommented with a trailing `# unused` comment (rejected — the
  value of a commented entry is unambiguous, whereas a trailing comment
  is easy to overlook).

### 2. `default.env` — documented defaults

- **Decision**: Same treatment as `.env.example`: comment out the entry
  and add a "not currently used" header comment. Adjacent explanatory
  comment is updated to match.
- **Rationale**: `default.env` is the docs-facing companion to
  `.env.example`. Keeping the two in visual lockstep avoids the bug where
  a reader treats one as authoritative over the other.
- **Alternatives considered**: Deleting (rejected — same context loss
  argument).

### 3. `.env.backup` — historical snapshot

- **Decision**: Annotate both the already-commented block (lines 13–14)
  and the currently-active block (lines 51–52) with a "not currently
  used" note. Prefer annotation over removal because this file is a
  snapshot-style backup and deleting lines would mask what that snapshot
  looked like.
- **Rationale**: Backup files are most useful when they faithfully
  represent a point-in-time state. Removing entries defeats that
  purpose; annotation satisfies FR-008 (consistent wording) without
  rewriting history.
- **Alternatives considered**: Deleting the entire `.env.backup` file
  (rejected — out of scope for this feature; if the file is genuinely
  dead it should be removed in a separate cleanup PR).

### 4. `README.md` — the project README's env var list

- **Decision**: Replace the existing one-line description with one that
  explicitly says the variable is not currently used by any runtime
  component, and notes that the entry is retained for backward
  compatibility with deployments that still set it.
- **Rationale**: README is the most-read document in the repo. Principle
  III means we cannot promise to delete the variable, so the README must
  describe the *actual* current state.
- **Alternatives considered**: Deleting the README bullet (rejected —
  readers searching for "FHIR" in the README would find nothing and
  wonder why `.env.example` mentions it).

### 5. `docs/technical-architecture.md` — diagram + edges

- **Decision**: Keep the `FHIR[FHIR Server]` node in the Mermaid diagram
  but rename its label to `FHIR[FHIR Server<br/>reserved — not currently used]`,
  and remove the three `*_BE --> FHIR` edges so the diagram no longer
  depicts an active dependency. Add a one-paragraph note under the
  diagram stating the node is reserved for a potential future
  integration and that no current runtime component calls it.
- **Rationale**: Preserves historical context for future revivers while
  removing the phantom-dependency misread that User Story 2 is written
  against. Dropping the edges is the key signal — a disconnected node
  reads as "not wired up."
- **Alternatives considered**:
  (a) Deleting the node outright — rejected because the revival context
  is useful.
  (b) Keeping the edges but adding a legend — rejected because diagram
  legends are routinely skimmed past; the visual signal (no edges) is
  more reliable.

### 6. `docker-entrypoint.sh` — env.json pass-through

- **Decision**: Remove the FHIR block entirely (lines 4–7). No annotation
  left behind; the commit message and this research doc provide the
  historical record.
- **Rationale**: The block writes a file that nothing reads. Under
  Principle IV (configuration over code forks) and the constitution's
  "Don't add error handling, fallbacks, or validation for scenarios that
  can't happen" ethos, dead code should be deleted rather than
  preserved. The entrypoint script is internal machinery, not a
  user-facing reference, so there is no onboarding-context argument for
  retention.
- **Alternatives considered**: Keeping the block with a comment
  (rejected — the block writes a file that nothing reads; leaving it
  suggests something depends on `env.json`, which is misleading).

### 7. `.github/workflows/tests.yml` — CI env injection

- **Decision**: Remove the `FHIR_SERVER: http://test-server.com` line
  from the `env:` block. Do not annotate in place; the absence after this
  PR is the signal.
- **Rationale**: The variable is not read by any test. A workflow env
  block should reflect the actual inputs a test needs; stale entries
  confuse future maintainers. This directly satisfies SC-004 (CI passes
  without `FHIR_SERVER`).
- **Alternatives considered**: Keeping it with a YAML comment (rejected
  for the same reason as the entrypoint — CI files are internal
  machinery, and a stale env entry is a bigger trap than a clean one).

## Wording standard (for FR-008 consistency)

All retained annotations MUST use the same canonical phrase:

> **"not currently used — retained for backward compatibility; no
> runtime component reads this value."**

Where space is tight (e.g., inside a Mermaid node label), a shortened
form is acceptable:

> **"reserved — not currently used"**

This pair of phrases is the authoritative vocabulary for this feature.

## Open questions

None. All Phase 0 decisions are settled and can be implemented without
further clarification.
