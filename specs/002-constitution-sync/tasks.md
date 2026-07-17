---

description: "Task list for feature 002-constitution-sync"
---

# Tasks: Align Repo Docs & Code With Constitution v1.1.1

**Input**: Design documents from `/specs/002-constitution-sync/`
**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md (audit), quickstart.md (reviewer walkthrough)

**Tests**: No test tasks are generated. This feature explicitly forbids
runtime behavior changes (spec FR-009 / SC-005). Existing
`flask_backend/tests/*` MUST pass unchanged and any task that touches
test code is out of scope.

**Organization**: Tasks are grouped by user story to enable independent
implementation and testing. US1, US2, US3, and US4 each touch a
disjoint set of files — they are fully parallelizable once the Setup
phase is complete.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1, US2, US3, US4)
- Include exact file paths in descriptions

## Path Conventions

- **Web app**: existing layout — `flask_backend/`, `frontend/`,
  `docs/`, and repo-root files (`README.md`, `.htaccess`).
- **This feature is docs-and-markers only**. No new directories,
  no new source files, no new dependencies.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Capture a baseline so later tasks and the reviewer can
compare against it. No project initialization is needed — the
branch and design artifacts already exist.

- [X] T001 Capture baseline: from repo root, run `grep -rniE 'keycloak|basic.?auth|AuthBasicProvider|X-Remote-User' --include='*.md' --include='*.py' --include='*.txt' README.md flask_backend/ docs/` and save the output to `specs/002-constitution-sync/baseline-grep.txt` so Phase 7's T012 can diff against it. Do not commit `baseline-grep.txt` — it is an implementation scratch file and belongs in `.gitignore` or is simply deleted before the PR lands.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: None. All four user stories touch disjoint file sets
and have no shared blocking work. This phase is intentionally empty.

**⚠️ CRITICAL**: This is a docs feature. There is no schema, no
migration, no shared model, and no shared service to stand up. Any
"foundational" task would be invented work. Skip directly to Phase 3.

**Checkpoint**: (vacuous) — user story implementation can begin
immediately after T001.

---

## Phase 3: User Story 1 - New contributor learns first-release auth without reading the Apache config (Priority: P1) 🎯 MVP

**Goal**: After this phase, a reader of the root `README.md` can
describe the first-release auth contract (basic+ldap edge →
`X-Remote-User` → Flask `@requires_auth` → `users.login` lookup) in
one sentence, and sees no lingering "dev/Keycloak fallback" open
question. The README's "Outstanding next steps" list reflects the
constitution's decisions rather than predating them.

**Independent Test**: Open `README.md`, jump to Authentication and
Authorization, and confirm: (a) the two-layer contract is described
as a single production path with both halves named, (b) the
"Outstanding next steps" list has no unresolved Keycloak/dev-fallback
bullet, (c) every `keycloak` mention is adjacent to a "deferred to a
later release — not supported for first release" note. Matches
quickstart.md Step 1. Traces to spec FR-001, FR-002, FR-003.

### Implementation for User Story 1

- [X] T002 [US1] In `/home/debadmin/cnics-validation/README.md`, rewrite the "Authentication and Authorization" section (currently approximately lines 79–93) to describe the first-release auth mechanism as a single two-layer contract: (layer 1) HTTP Basic Auth at the Apache edge using `AuthType basic` + `AuthBasicProvider ldap` + `AuthLDAPURL` + `require ldap-group` (per `/home/debadmin/cnics-validation/.htaccess`); (layer 2) Apache forwarding the authenticated identity to the Flask backend as the `X-Remote-User` header, which `@requires_auth` then resolves against the `users.login` column. Present both halves as load-bearing — not alternatives — and point at `.htaccess` and `.specify/memory/constitution.md` (Security & Data Governance → Authentication) as the authoritative sources. Do not duplicate the constitution verbatim; paraphrase with pointers. (FR-001)
- [X] T003 [US1] In `/home/debadmin/cnics-validation/README.md`, edit the "Outstanding next steps" bullet list (currently approximately lines 95–100) so that the bullet reading "Decide whether to require header auth in all environments or keep the permissive dev/Keycloak fallback" is no longer presented as an open decision. Preferred form: delete the bullet entirely, and — if useful — add a one-line affirmative note immediately above the remaining bullets pointing at the constitution's authentication section as the place where that decision now lives. (FR-002)
- [X] T004 [US1] In `/home/debadmin/cnics-validation/README.md`, add a one-sentence note near the Authentication and Authorization section (either as the closing sentence of the section or as a dedicated bullet) stating that Keycloak is deferred to a later release and is not supported in first-release deployments, and point to `flask_backend/README.md` for the `KEYCLOAK_*` environment variables' current status. Keep the note short — a pointer, not a reiteration. (FR-003)

**Checkpoint**: After T002–T004, the root README satisfies FR-001,
FR-002, and FR-003, and quickstart.md Step 1 passes on its own.
User Story 1 is the MVP and is independently deliverable.

---

## Phase 4: User Story 2 - Keycloak code in the tree carries an explicit "not for first release" marker (Priority: P2)

**Goal**: Every user-facing Keycloak reference in `flask_backend/`
carries an unambiguous "deferred to a later release — not supported
for first release" marker that names the constitution as the
authority. No runtime behavior changes; the `KEYCLOAK_REALM`-gated
code paths continue to activate exactly as they did before.

**Independent Test**: From repo root, `grep -rn keycloak
flask_backend/ --include='*.md' --include='*.py' --include='*.txt'`
and confirm every hit outside `flask_backend/tests/` is immediately
adjacent to a deferred-marker comment that points to the
constitution. Then run `pytest flask_backend/tests/` and confirm
every test passes unchanged. Matches quickstart.md Steps 2–4 and
Step 9. Traces to spec FR-004, FR-005, FR-006, FR-009, SC-005.

### Implementation for User Story 2

- [X] T005 [P] [US2] In `/home/debadmin/cnics-validation/flask_backend/README.md`, rewrite the paragraph (currently approximately lines 47–49) that describes `KEYCLOAK_REALM`, `KEYCLOAK_URL`, `KEYCLOAK_CLIENT_ID`, and `KEYCLOAK_CLIENT_SECRET` as if they were a supported mode. New paragraph MUST state: (1) Keycloak integration is not part of the first release; (2) the code paths are gated by `KEYCLOAK_REALM` being set and default to off; (3) the environment variables are retained so local experimentation continues to work but MUST NOT be set in any first-release study deployment; (4) enabling Keycloak in a study deployment requires a prior amendment to `.specify/memory/constitution.md` per the Security & Data Governance section. Keep the language parallel to the existing `FHIR_SERVER` "not currently used" note in `/home/debadmin/cnics-validation/README.md` around line 50 so the pattern is recognizable. (FR-004)
- [X] T006 [P] [US2] In `/home/debadmin/cnics-validation/flask_backend/app.py`, add two inline comments tagging the Keycloak code as deferred. (1) Immediately above the `# Optional Keycloak configuration` block at approximately line 161 (the `keycloak_openid = None` / `if os.getenv("KEYCLOAK_REALM"):` block), add a 2–3 line comment: "DEFERRED: not part of first release. Gated off by default (KEYCLOAK_REALM unset). See .specify/memory/constitution.md → Security & Data Governance → Authentication (future releases) before re-enabling." (2) Immediately above the Keycloak fallback branch inside `requires_auth` at approximately line 272 (the `# Fallback to Keycloak if configured: require a valid Bearer token` comment and the `if 'keycloak_openid' in globals() and keycloak_openid:` branch), replace the existing one-line `# Fallback to Keycloak if configured...` comment with the same "DEFERRED: not part of first release" framing, preserving the pointer to the constitution. Do NOT change any code inside either block — only the comments around them change. Tests must continue to pass unchanged. (FR-005, FR-009)
- [X] T007 [P] [US2] In `/home/debadmin/cnics-validation/flask_backend/requirements.txt`, add a one-line comment immediately above the `python-keycloak` line (currently approximately line 6) that reads roughly: "# Retained for a later release; Keycloak is not part of first-release auth — see flask_backend/README.md and .specify/memory/constitution.md". Confirm the package name and version pin (if any) are unchanged. (FR-006)

**Checkpoint**: After T005–T007, a grep for "keycloak" across
`flask_backend/` outside `tests/` returns only hits that are
adjacent to a deferred-marker. User Story 2 is complete and
independently testable by running quickstart.md Steps 2–4 and
Step 9.

---

## Phase 5: User Story 3 - Auth-adjacent docs under `docs/` agree with each other and with the constitution (Priority: P2)

**Goal**: The subset of `docs/*.md` files that reference auth
mechanisms (`template-setup-guide.md` and `technical-architecture.md`,
per the research.md audit) name the basic+ldap edge and the
`X-Remote-User` handoff precisely, and do not present Keycloak as a
supported first-release option. Files that do not contradict the
constitution (`WORKFLOW_AUTH.md`, `frontend-auth-implementation.md`,
`architecture-overview.md`) are out of scope per research.md and
MUST NOT be edited.

**Independent Test**: Read `docs/template-setup-guide.md` and
`docs/technical-architecture.md` without reading `README.md` or the
constitution, and confirm the first-release auth story they tell is
consistent with each other and with the constitution's v1.1.1
Authentication (first release) bullet. Matches quickstart.md Steps
5 and 6. Traces to spec FR-007, FR-008.

### Implementation for User Story 3

- [X] T008 [P] [US3] In `/home/debadmin/cnics-validation/docs/template-setup-guide.md`, make the three existing auth bullets precise. (1) Line 49 (currently "**Authentication**: Maintain existing Apache/LDAP authentication approach"): rewrite to name `AuthBasicProvider ldap` plus `require ldap-group` at the Apache edge and `X-Remote-User` forwarding to the Flask backend. (2) Line 61 (currently "**Authentication**: Apache .htaccess & LDAP integration"): rewrite to the same precision, pointing at `/home/debadmin/cnics-validation/.htaccess` as the authoritative source. (3) Line 72 (currently "**Authentication**: Header-based authentication with role management"): expand to include the Apache edge half, so the file no longer presents the contract as header-only. After the edits, grep the file for "keycloak" — expected: zero hits (this file is not stale about Keycloak). Keep edits small and surgical — do not restructure surrounding sections. (FR-007)
- [X] T009 [P] [US3] In `/home/debadmin/cnics-validation/docs/technical-architecture.md`, add two short prose captions (one line each) describing the first-release auth mechanism near the existing mermaid diagrams. (1) Immediately after the "Shared Infrastructure" subgraph around line 32 (the one containing `AUTH[LDAP Authentication]`), add a caption line stating that `LDAP Authentication` in this diagram means HTTP Basic Auth at the Apache edge with `AuthBasicProvider ldap`, with the authenticated identity forwarded to the Flask backend as `X-Remote-User`. (2) Immediately after the "Security and Access Control" diagram around lines 170–194 (`LDAP → APACHE → ROLES`), add a caption line explaining that the `LDAP → Apache/LDAP Integration` edge is `AuthType basic` + `AuthBasicProvider ldap` (per `.htaccess`) and that `Role-Based Access Control` downstream is enforced by the Flask backend via `@requires_auth` reading `X-Remote-User`. Do NOT redraw either mermaid diagram — only add prose captions. (FR-008 partial)

**Checkpoint**: After T008–T009, the two in-scope `docs/` files
describe the first-release auth contract consistently with the
constitution and with each other. User Story 3 is complete. Files
dropped from scope per research.md remain untouched.

---

## Phase 6: User Story 4 - "Unused subsystem" hygiene is applied consistently (Priority: P3)

**Goal**: The Keycloak deferred-markers introduced in US2/US3 follow
the same shape as the existing `FHIR_SERVER` "not currently used"
markers introduced in 001-mark-fhir-unused, so the repo has a single
recognizable pattern for any future unused or deferred subsystem to
follow.

**Independent Test**: Compare the `FHIR_SERVER` marker at
`/home/debadmin/cnics-validation/README.md` (around line 50) and the
`FHIR Server` node caption in
`/home/debadmin/cnics-validation/docs/technical-architecture.md`
(around line 39) against the Keycloak markers added by T004, T005,
T006, T007, T008, and T009. All should: name the subsystem, state
status in bold or equivalent emphasis, give a one-line why, and
optionally give operator guidance. Traces to spec FR-010.

### Implementation for User Story 4

- [X] T010 [US4] Re-read the FHIR markers at `/home/debadmin/cnics-validation/README.md` line ~50 and `/home/debadmin/cnics-validation/docs/technical-architecture.md` line ~39, then re-read the Keycloak markers written by T004 (README Keycloak-deferred note), T005 (flask_backend README paragraph), T006 (inline app.py comments), T007 (requirements.txt comment), T008 (template-setup-guide bullets), and T009 (technical-architecture captions). For any Keycloak marker whose shape diverges from the FHIR precedent — e.g., missing the bolded status, missing the one-line why, or missing the pointer to the authoritative doc — open the specific file and make a minimal edit to bring it into line. The goal is pattern consistency, not rewording for its own sake; if a marker already matches the FHIR shape, leave it alone. This task depends on T004–T009 being complete. (FR-010)

**Checkpoint**: After T010, the repo has two parallel instances of
the "unused/deferred subsystem" marker pattern (FHIR and Keycloak)
and a future contributor can use either as a template.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: The constitution-traceability deliverable (FR-011, SC-006),
the no-regression confirmation (SC-005), and the final consistency
grep (SC-002, SC-003) are cross-cutting — they validate the feature
as a whole, not any one user story.

- [X] T011 [P] Run the quickstart.md Step 7 consistency grep from repo root: `grep -rniE 'keycloak|basic.?auth|AuthBasicProvider|X-Remote-User' --include='*.md' --include='*.py' --include='*.txt' README.md flask_backend/ docs/`. Confirm that every `keycloak` hit outside `flask_backend/tests/`, `node_modules/`, and `.specify/memory/constitution.md` is adjacent to a deferred-marker; that the `basic+ldap` phrasing appears consistently in `README.md`, `flask_backend/README.md`, and `docs/template-setup-guide.md`; and that `X-Remote-User` appears alongside the basic+ldap description (not as a standalone header contract) in `README.md` and `docs/template-setup-guide.md`. If any hit fails the adjacency test, loop back to the owning task (T004 for root README, T005–T007 for `flask_backend/`, T008–T009 for `docs/`) and fix it. (SC-002, SC-003)
- [X] T012 [P] Run `pytest /home/debadmin/cnics-validation/flask_backend/tests/` (or the equivalent compose test target if pytest is not available on the host). Confirm every existing test passes unchanged, including `test_app.py` and `test_auth_header_and_roles.py` (which manipulate `app_mod.keycloak_openid`). If any test fails, the implementation violated FR-009 — a comment-only change must not move any runtime behavior — and the offending task (most likely T006) must be revisited. Then, with `KEYCLOAK_REALM` unset in `.env`, `docker-compose up backend` and confirm the backend starts cleanly; then with `KEYCLOAK_REALM=test` set, restart and confirm the backend still starts (import may fail silently per the existing `try/except`, which is the pre-feature behavior). (SC-005, FR-009)
- [X] T013 Draft a "Sync Impact addendum" block at the top of the PR description (or append it as a short section to `/home/debadmin/cnics-validation/specs/002-constitution-sync/plan.md`) that maps every edited file in the diff to the constitution decision it traces back to. Use a table with three columns: `File | Edit summary | Traces to (constitution section + FR)`. Expected row count matches research.md's revised six-file table: `README.md` (3 rows for T002/T003/T004), `flask_backend/README.md`, `flask_backend/app.py`, `flask_backend/requirements.txt`, `docs/template-setup-guide.md`, `docs/technical-architecture.md`. Any file in the diff not appearing in this table is scope creep and MUST be removed before the PR is opened. (FR-011, SC-006)
- [X] T014 Walk through `/home/debadmin/cnics-validation/specs/002-constitution-sync/quickstart.md` Steps 1 through 8 against the current diff (self-review before requesting reviewers). Confirm each step's "Expected state" clause is satisfied. Any step that does not pass identifies the task to revisit. Step 9 is a post-merge smoke check and is deferred until after the PR lands. Then delete `specs/002-constitution-sync/baseline-grep.txt` (created by T001) so it does not accidentally get committed.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — T001 can start immediately.
- **Foundational (Phase 2)**: Empty by design. There is no blocking
  work between Setup and the user stories for this feature.
- **User Stories (Phase 3–6)**: Depend only on T001.
  - US1, US2, and US3 are fully independent of each other (disjoint
    file sets) and can proceed in parallel.
  - US4 (T010) depends on US1 (T004), US2 (T005–T007), and US3
    (T008–T009) all being complete, because it is a pattern-
    consistency pass over their outputs.
- **Polish (Phase 7)**: T011, T012, T013, T014 all depend on every
  user-story task being done (T002–T010).

### User Story Dependencies

- **US1 (P1 — MVP)**: Depends on T001. Independent of US2, US3, US4.
- **US2 (P2)**: Depends on T001. Independent of US1, US3.
- **US3 (P2)**: Depends on T001. Independent of US1, US2.
- **US4 (P3)**: Depends on US1, US2, US3 all being complete
  (consistency-check pass, not a new edit).

### Within Each User Story

- US1 (T002, T003, T004): all touch `README.md`. MUST be sequential
  in that file (no `[P]`). Order within the file is not
  significant; T002 first is natural because it is the largest
  rewrite.
- US2 (T005, T006, T007): touch three distinct files
  (`flask_backend/README.md`, `flask_backend/app.py`,
  `flask_backend/requirements.txt`). All marked `[P]`.
- US3 (T008, T009): touch two distinct files
  (`docs/template-setup-guide.md`,
  `docs/technical-architecture.md`). Both marked `[P]`.
- US4 (T010): single task, no internal parallelism.

### Parallel Opportunities

- After T001, US1, US2, and US3 can be staffed concurrently by
  three separate workers, because their file sets are disjoint.
- Within US2: T005, T006, T007 are all `[P]` — three parallel
  file edits in one worker's turn.
- Within US3: T008 and T009 are `[P]` — two parallel file edits.
- Within US1: T002, T003, T004 are sequential (same file).
- In Polish: T011 and T012 are `[P]` (grep vs. tests). T013 and
  T014 are sequential (diff must be stable first) and sit after
  T011/T012.

---

## Parallel Example: User Story 2

```bash
# After T001 completes, launch all three US2 file edits together:
Task: "Rewrite Keycloak paragraph in flask_backend/README.md (T005)"
Task: "Add deferred comments at both Keycloak sites in flask_backend/app.py (T006)"
Task: "Add retention comment next to python-keycloak in flask_backend/requirements.txt (T007)"
```

## Parallel Example: Cross-Story (after T001)

```bash
# Three parallel tracks, one per story, all starting from the same baseline:
Track A (US1, one worker): T002 → T003 → T004  (sequential, all touch README.md)
Track B (US2, one worker): T005, T006, T007   (all [P], three files)
Track C (US3, one worker): T008, T009         (both [P], two files)
# Then converge on US4:
T010  (after A, B, and C are complete)
# Then polish:
T011, T012 in parallel → T013 → T014
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1 (T001).
2. Complete Phase 3 (T002, T003, T004) — MVP.
3. **STOP and VALIDATE**: Walk through quickstart.md Step 1
   against the root `README.md` changes. If it passes, the MVP
   delivers enough value to merge on its own: a new contributor
   reading the root README gets the correct first-release auth
   story with no lingering open Keycloak question. US2–US4 can
   follow in a second PR if desired.

### Incremental Delivery

1. T001 → baseline captured.
2. US1 (T002–T004) → merge as MVP → README is
   constitution-aligned.
3. US2 (T005–T007) → merge → `flask_backend/` carries
   deferred markers.
4. US3 (T008–T009) → merge → `docs/` precise on basic+ldap.
5. US4 (T010) → merge → marker pattern consistent with FHIR.
6. Polish (T011–T014) → final consistency grep, tests pass,
   PR addendum written, self-walkthrough clean.

For a single-PR delivery (the likely path for this small
docs-and-markers feature), collapse steps 2–6 into one branch,
run Polish last, and open the PR with the T013 addendum
prepared.

### Parallel Team Strategy

Three workers after T001 completes:

- Worker A: Track US1 (sequential T002 → T003 → T004).
- Worker B: Track US2 (parallel T005, T006, T007).
- Worker C: Track US3 (parallel T008, T009).

All three tracks converge at T010 (US4), then Polish. Because
every task has an exact file path and the file sets are disjoint
within this feature, there are no merge conflicts by
construction.

---

## Notes

- `[P]` tasks = different files, no dependencies on incomplete
  tasks in the same phase.
- `[Story]` label maps each task to its spec.md user story so a
  reviewer can check coverage per story.
- This feature deliberately produces **no new source files, no
  new dependencies, no schema changes, no API shape changes, and
  no test changes**. Any task tempted to create such artifacts
  has drifted out of scope — stop and re-read `spec.md`
  Assumptions.
- Commit per task or per user story. Do not bundle US1 and US2
  into a single commit if delivering incrementally, since that
  would erase the MVP boundary described in the Implementation
  Strategy above.
- If at any point a task's file path does not match the line
  numbers in this document, trust the file contents — the tasks
  were generated against a snapshot and ordinary edits may have
  shifted line numbers. The described edits remain valid; only
  the line-number hints are approximate.
