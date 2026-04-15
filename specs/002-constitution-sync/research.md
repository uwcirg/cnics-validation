# Research: Align Repo Docs & Code With Constitution v1.1.1

**Branch**: `002-constitution-sync`
**Date**: 2026-04-15
**Phase**: 0 (outline & research)

## Purpose

Spec.md (FR-007, FR-008) requires an audit of five `docs/*` files before
making edits, because "some may contradict the constitution, some may
simply lack the new precision, and some may not touch auth at all".
Plan.md's Technical Context estimates "8 files touched", but several of
those were pending audit. This research phase resolves what actually
needs editing and what is directionally-correct-already, so Phase 1
(quickstart) and Phase 2 (tasks) can work from a precise file list
rather than an estimate.

## Audit findings — Keycloak references

Grep for `[Kk]eycloak` across the working tree (excluding `node_modules/`):

| Location | Nature | Action |
|---|---|---|
| `.specify/memory/constitution.md` | Authoritative decision | No edit — this is the source the feature propagates *from*. |
| `README.md:100` | "Outstanding next steps" lists "keep the permissive dev/Keycloak fallback" as an open question. | **EDIT** — the constitution decided this; remove or rewrite the bullet (FR-002). |
| `flask_backend/app.py:161–173` | Keycloak init block, activates iff `KEYCLOAK_REALM` env var set. | **EDIT** — add inline comment marking as deferred (FR-005). |
| `flask_backend/app.py:272–279` | Fallback Bearer-token branch inside `requires_auth`. | **EDIT** — add inline comment marking as deferred (FR-005). |
| `flask_backend/README.md:47–49` | Describes `KEYCLOAK_REALM`, `KEYCLOAK_URL`, etc. as supported config. | **EDIT** — rewrite to name it as deferred (FR-004). |
| `flask_backend/requirements.txt:6` | `python-keycloak` dependency. | **EDIT** — add short comment or a cross-reference note explaining retention (FR-006). |
| `flask_backend/tests/test_app.py` (multiple) | Internal test wiring: `app_mod.keycloak_openid = object()` / `= None`. | **No edit** — out of scope per spec Edge Cases and SC-005. |
| `flask_backend/tests/test_auth_header_and_roles.py:109` | Same — test wiring. | **No edit**. |

**Decision — mark vs remove**: Mark, do not remove. Rationale:
1. Principle VI explicitly offers both options ("marked as unused in
   documentation or removed").
2. The constitution's Security & Data Governance section states
   "Keycloak-based authentication is planned for a later release" —
   removal would force a re-add later with no offsetting benefit.
3. The 001-mark-fhir-unused branch set the precedent that this
   project prefers "mark" for subsystems with a known-future use.
4. Tests already exercise the fallback branch; deleting the code
   would also delete the test coverage.

**Alternatives considered**:
- *Remove entirely*: rejected (see 1–4 above).
- *Move to a separate `flask_backend/deferred/` directory*:
  rejected — adds an import path change and therefore a runtime
  behavior change, violating FR-009/SC-005.
- *Hide behind a stronger non-default flag than `KEYCLOAK_REALM`*:
  rejected — the existing env var already functions as a
  non-default flag per constitution's feature-flag discipline
  section; introducing another flag is scope creep.

## Audit findings — docs/*.md auth references

Grep for `keycloak|basic.?auth|ldap|AuthBasicProvider|X-Remote-User`
(case-insensitive) across `docs/`:

| File | Current content | Stale re: constitution? | Action |
|---|---|---|---|
| `docs/template-setup-guide.md:49,61,72` | "Maintain existing Apache/LDAP authentication approach", "Apache .htaccess & LDAP integration", "Header-based authentication with role management". | Directionally correct but imprecise — does not name `AuthBasicProvider ldap` or the `X-Remote-User` handoff. | **EDIT** — add the precision (FR-007). |
| `docs/technical-architecture.md:32` | Mermaid node `AUTH[LDAP Authentication]` inside "Shared Infrastructure". | Not stale, but does not distinguish the edge (basic+ldap) from the backend (`X-Remote-User`). | **EDIT (minor)** — add a one-line caption under the diagram naming the mechanism; do not redraw the diagram. |
| `docs/technical-architecture.md:170–194` | Mermaid "Security and Access Control" diagram with `LDAP → Apache/LDAP Integration → Role-Based Access Control`. | Not stale; captures the two layers implicitly. | **EDIT (minor)** — same as above, a one-line caption naming basic+ldap and `X-Remote-User`. |
| `docs/architecture-overview.md:285` | Mermaid node `Apache/LDAP` inside "Legacy CakePHP Systems" subgraph. | Historical reference to the legacy stack, not the new one. | **No edit** — per spec Edge Cases, historical notes are preserved. |
| `docs/WORKFLOW_AUTH.md` | Role-based authorization matrix. Never names the authentication mechanism. | Not stale — describes a different concern (authz, not authn). | **No edit** — out of scope; a pointer to README is not required because the file does not make any claim the constitution contradicts. |
| `docs/frontend-auth-implementation.md` | React `ProtectedRoute` and route-protection tables. Never names the auth mechanism. | Not stale. | **No edit** — same reasoning as `WORKFLOW_AUTH.md`. |

**Zero Keycloak references in `docs/`**. This is a material finding: the
pre-existing docs were never out of sync about Keycloak in the first
place — the staleness lives entirely in the root `README.md`, the
`flask_backend/` tree, and the precision level of
`docs/template-setup-guide.md` + `docs/technical-architecture.md`.

**Revised file list** (supersedes the Plan's "8 files touched" estimate):

| File | Edit type | Traces to |
|---|---|---|
| `README.md` | Rewrite auth section; remove stale next-steps bullet; add Keycloak-deferred note. | Constitution v1.1.0 auth bullet, v1.1.1 auth clarification, FR-001/002/003. |
| `flask_backend/README.md` | Rewrite Keycloak paragraph. | v1.1.0 auth bullet, FR-004. |
| `flask_backend/app.py` (2 comments) | Inline markers at init block and fallback branch. | v1.1.0 auth bullet, FR-005. |
| `flask_backend/requirements.txt` | One-line comment. | v1.1.0 "unused subsystem hygiene", FR-006. |
| `docs/template-setup-guide.md` | Precision bump on two bullets. | v1.1.1 auth clarification, FR-007. |
| `docs/technical-architecture.md` | Two one-line captions under two mermaid diagrams. | v1.1.1 auth clarification, FR-008 (partial). |

**Six files, seven edits.** Down from the plan's "8 files (est.)".
`docs/WORKFLOW_AUTH.md`, `docs/frontend-auth-implementation.md`, and
`docs/architecture-overview.md` are dropped from scope because the
audit found they do not contradict the constitution and do not leave
out material that a reader of that file would need.

## Audit findings — `.htaccess` verification

`.htaccess` at repo root contains:

```apache
AuthName "MCI"
AuthType basic
AuthBasicProvider ldap
AuthLDAPURL "ldaps://ldap1.cirg.washington.edu ldap2.cirg.washington.edu/ou=cnics,ou=projects,ou=Clinical Informatics Research Group,dc=cirg,dc=us"
require ldap-group cn=cnicsUpload,ou=groups,ou=cnics,ou=projects,ou="Clinical Informatics Research Group",dc=cirg,dc=us
```

This is the authoritative first-release Apache edge configuration and
matches the constitution's v1.1.1 wording exactly. The README and
docs edits MUST quote or paraphrase this, not contradict it. No
edit to `.htaccess` itself is part of this feature (it is already
correct; the problem is propagation, not the source).

One observation: `.htaccess` does not itself contain a
`RequestHeader set X-Remote-User %{REMOTE_USER}s` directive. That
directive lives in the vhost / reverse-proxy Apache config outside
the repo, which is consistent with the spec's assumption ("if the
operational deployment diverges from `.htaccess` … out of scope").
The docs edits describe the contract the two halves define
*together*, not the syntax of either half in isolation.

## Marker pattern — consistency with 001-mark-fhir-unused

Inspected `README.md:50` (the FHIR marker from 001-mark-fhir-unused):

> `FHIR_SERVER` – **not currently used.** Retained for backward
> compatibility with deployments that still set it; no runtime
> component reads this value. Safe to omit.

And `docs/technical-architecture.md:39`:

> `FHIR[FHIR Server<br/>reserved — not currently used]`

**Pattern extracted**: name the subsystem, bold the status
("not currently used"), give a one-line why ("retained for backward
compatibility" / "reserved"), and optionally note operator guidance
("safe to omit"). The Keycloak markers in this feature will follow
the same shape with "deferred — not part of first release" as the
status and "planned for a later release; enabling requires a prior
constitution amendment" as the why + operator guidance. This
satisfies FR-010 without inventing a new convention.

## Open questions resolved

The spec had **zero** `[NEEDS CLARIFICATION]` markers, so there
were no spec-level ambiguities to resolve here. The research phase
instead narrowed the plan's file-list estimate into a concrete
seven-edit list and recorded the mark-vs-remove decision with
rationale.

## Output for downstream phases

- **quickstart.md (Phase 1)**: should walk a reviewer through the
  seven edits above and show how each traces to the constitution.
- **tasks.md (Phase 2, via `/speckit.tasks`)**: each edit becomes
  its own task. The six-file list in the table above is the
  authoritative starting point.
- **No data-model.md, no contracts/**: no entities, no interface
  changes.
