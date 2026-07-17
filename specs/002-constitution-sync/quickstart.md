# Quickstart: Align Repo Docs & Code With Constitution v1.1.1

**Branch**: `002-constitution-sync`
**Audience**: Reviewer of the PR this feature produces. This walkthrough
is how you verify the change lands the constitution's decisions in the
places a future reader actually looks, without reading the constitution
yourself.

## Prerequisites

- `002-constitution-sync` checked out locally, or the PR diff open in a
  browser.
- `.specify/memory/constitution.md` (v1.1.1) open in another tab for
  cross-reference. Specifically keep the **Security & Data Governance
  → Authentication (first release)** bullet and **Principle VI**
  visible.
- Optionally, `.htaccess` at repo root open to confirm the "basic +
  `AuthBasicProvider ldap` + `require ldap-group`" framing is being
  propagated faithfully, not invented.

## Walkthrough

### Step 1 — Root README, Authentication section

1. Open `README.md` and jump to the **Authentication and Authorization**
   heading.
2. Expected state after the feature lands:
   - The section names two layers as a single contract: (a) HTTP Basic
     Auth at the Apache edge via `AuthBasicProvider ldap` plus a
     `require ldap-group` rule (pointing at `.htaccess` as the source
     of truth), and (b) Apache forwarding the authenticated identity
     to the Flask backend as `X-Remote-User`, which `@requires_auth`
     looks up against `users.login`.
   - The phrasing should be readable to a new contributor who has
     never opened `.htaccess`.
3. Read the **Outstanding next steps** list in the same section.
   Expected state: the former bullet about "permissive dev/Keycloak
   fallback" is no longer present as an open decision. Either the
   bullet is gone or it has been rewritten as a resolved statement
   pointing to the constitution.
4. Grep the root README for `keycloak` (case-insensitive). Expected
   state: every hit is adjacent to an explicit "deferred to a later
   release — not supported for first release" note, not presented
   as a supported or experimental feature.

**If any of the three above fails**: the change has not met FR-001,
FR-002, or FR-003 respectively. Request changes on the PR.

### Step 2 — flask_backend/README.md

1. Open `flask_backend/README.md` and find the paragraph that
   mentions `KEYCLOAK_REALM`, `KEYCLOAK_URL`, `KEYCLOAK_CLIENT_ID`,
   `KEYCLOAK_CLIENT_SECRET`.
2. Expected state: the paragraph explicitly says Keycloak is not
   part of the first release, is default-off, and that enabling
   it in a study deployment requires a prior constitution
   amendment. A pointer to the constitution's authentication
   section is present.

**If missing**: FR-004 not met.

### Step 3 — flask_backend/app.py, two inline comments

1. Open `flask_backend/app.py` at approximately line 161 (the
   Keycloak init block that checks `os.getenv("KEYCLOAK_REALM")`).
2. Expected state: an inline comment immediately above or inside
   the block marking it as deferred to a later release and
   pointing to the constitution's Security & Data Governance
   authentication section.
3. Scroll to the Keycloak fallback branch inside `requires_auth`
   (approximately line 272, the `if 'keycloak_openid' in globals()
   and keycloak_openid:` branch).
4. Expected state: an inline comment on this branch with the same
   "deferred — not first-release path" framing.
5. Run the backend tests (or spot-check them in the diff) to
   confirm SC-005: no test file has been modified. The existing
   `app_mod.keycloak_openid = object()` / `= None` patterns in
   `test_app.py` and `test_auth_header_and_roles.py` are
   preserved unchanged.

**If missing**: FR-005 not met. **If tests changed**: SC-005 not
met and the change must be revisited — no runtime behavior
changes are allowed in this feature.

### Step 4 — flask_backend/requirements.txt

1. Open `flask_backend/requirements.txt`.
2. Expected state: the `python-keycloak` line either carries a
   short inline comment explaining why it is retained despite
   not being used for first-release auth, or the explanation
   lives in `flask_backend/README.md` and is referenced from
   here.

**If missing**: FR-006 not met.

### Step 5 — docs/template-setup-guide.md

1. Open `docs/template-setup-guide.md` and find every mention of
   "Apache/LDAP", "header-based authentication", or the auth
   approach.
2. Expected state: at least one of these mentions has been made
   precise — it now names basic auth with `AuthBasicProvider
   ldap` at the Apache edge plus `X-Remote-User` forwarding, and
   does not present Keycloak as a supported first-release
   option.
3. The edit should not duplicate the constitution verbatim — a
   short pointer to `.specify/memory/constitution.md` (or to the
   root README's Authentication section) is the preferred shape.

**If missing or overwrought**: FR-007 not met.

### Step 6 — docs/technical-architecture.md

1. Open `docs/technical-architecture.md` and find the two
   mermaid nodes that mention LDAP: the "Shared Infrastructure"
   subgraph around line 32 (`AUTH[LDAP Authentication]`) and
   the "Security and Access Control" section around lines
   170–194 (`LDAP → APACHE → ROLES`).
2. Expected state: a one-line caption under each diagram
   names the first-release mechanism (basic+ldap edge →
   `X-Remote-User` → Flask). The diagrams themselves are not
   redrawn — only prose captions are added.

**If missing**: FR-008 is partially unmet. Minor issue; request
clarification rather than a full change-request.

### Step 7 — Consistency grep

From repo root:

```text
grep -rniE 'keycloak|basic.?auth|AuthBasicProvider|X-Remote-User' \
  --include='*.md' --include='*.py' --include='*.txt' \
  README.md flask_backend/ docs/
```

Expected state:
- Every `keycloak` hit outside `node_modules/`, test files, and
  the constitution itself is adjacent to a "deferred — not part
  of first release" note.
- The `basic+ldap` phrasing appears consistently in README,
  `flask_backend/README.md`, and `docs/template-setup-guide.md`.
- `X-Remote-User` appears in README and `docs/template-setup-guide.md`
  alongside the basic+ldap description — not as a standalone
  header contract with no edge context.

**If the grep surfaces a Keycloak mention without an adjacent
marker**: SC-003 is not met.

### Step 8 — Sync Impact addendum

1. Open the PR description (or the top of `plan.md` / an
   appended note). Expected state: a short table or list mapping
   each edited file to the constitution decision it traces
   back to (v1.1.0 Principle VI, v1.1.0 auth bullet, or v1.1.1
   auth clarification).
2. Every file in the diff should appear exactly once in that
   mapping. Any file in the diff that does not trace to a
   listed constitution decision is scope creep and must be
   removed or justified.

**If missing**: FR-011 / SC-006 not met.

### Step 9 — Runtime smoke check (optional, post-merge)

1. With `KEYCLOAK_REALM` unset (the default), start the backend
   via `docker-compose up`. Expected: it starts as before; no
   behavior change.
2. With `KEYCLOAK_REALM=test` set in `.env`, restart. Expected:
   the backend still imports and starts (import may fail
   silently per the existing `try/except` — this is the
   pre-feature behavior and MUST NOT have changed).
3. Run `pytest flask_backend/tests/` (or the compose test
   target). Expected: every test passes unchanged.

**If any of the above diverges from pre-feature behavior**:
FR-009 / SC-005 not met. This is a blocking issue.

## Done criteria

- Steps 1–8 all match their "expected state" descriptions.
- Step 9 (post-merge) confirms no runtime regression.
- The PR's diff touches only the six files listed in
  `research.md`'s revised file-list table. Any extra file
  is scope creep per FR-011.

## What this quickstart deliberately does not check

- The correctness of the constitution itself. That was
  ratified and amended out-of-band; this feature only
  propagates decisions, it does not re-adjudicate them.
- Operational deployment state beyond `.htaccess`. The
  vhost-level `RequestHeader` directive that sets
  `X-Remote-User` from `REMOTE_USER` lives outside this
  repo and is called out as out-of-scope in `spec.md`
  Assumptions.
- Frontend route protection and authorization matrices.
  `docs/WORKFLOW_AUTH.md` and
  `docs/frontend-auth-implementation.md` describe authz,
  not authn, and are deliberately excluded from the edit
  set per `research.md`.
