# Phase 0 Research: Implement the `scans` Study Type

**Feature**: 003-scans-study | **Date**: 2026-05-20

This document resolves the unknowns behind the implementation plan and — per
Constitution Principle VI — records the **observed current behavior** of the
event-lifecycle code *before* it is changed.

---

## Decision 1 — Observed current behavior of the event lifecycle (Principle VI)

**Decision**: Treat this feature as *completing* the shared event lifecycle
(assign → review → done) for the first time, gated by the four workflow-stage
controls — not as bypassing an already-working full pipeline.

**Observed behavior (verified by reading the code on branch `003-scans-study`):**

- `events.status` is an enum with all 13 states
  (`created, uploaded, scrubbed, screened, assigned, sent, reviewer1_done,
  reviewer2_done, third_review_needed, third_review_assigned, done, rejected,
  no_packet_available`) — `flask_backend/models.py:49`.
- Status transitions that **are** wired in `flask_backend/app.py`:
  `created → uploaded` (`events_upload_raw`, line 1232),
  `uploaded → scrubbed` (`events_upload_scrubbed`, line 1160),
  `scrubbed → screened` (`events_screen` accept, line 1087),
  plus `→ rejected` and rescrub `→ uploaded`.
- Transitions that are **NOT** wired:
  - `assign_events` (`table_service.py:714`) sets `reviewerN_id`, `assign_date`,
    `assigner_id` — it never sets `status = 'assigned'`.
  - `send_events` (`table_service.py:762`) sets `sender_id`, `send_date` and
    sends assignment emails — it never sets `status = 'sent'`.
  - There is **no review-submission endpoint**. Nothing in the backend ever
    sets `reviewer1_done`, `reviewer2_done`, `done`, or `third_review_*`. The
    frontend review form's submit handler is a placeholder —
    `frontend/src/pages/EventReview.jsx:120` is `alert('Review submitted
    (placeholder).')`.
- The workflow list endpoint `/api/events/by_status/<status>` is **date-driven**:
  it maps a status name to a date-column predicate
  (`get_to_be_scrubbed/screened/assigned/sent/reviewed_with_total`,
  `table_service.py:439-511`), keyed on `upload_date`/`scrub_date`/`screen_date`/
  `assign_date`/`send_date` — not on the `status` column.
- The reviewer queue `get_events_awaiting_review` (`table_service.py:660`) keys
  on `status IN ('sent', 'reviewer2_done')` etc. — but since `status` is never
  set to `sent`, this queue is presently non-functional for slots 1 and 2.
- **No runtime code reads `STUDY_TYPE` or any feature flag.**
  `flask_backend/study_config.py` carries a `SCAFFOLDING ONLY` banner and is
  imported by nothing. Configuration is read via scattered `os.getenv` calls.
- The frontend has **no study selector**: `App.jsx` hard-codes a `/events/*`
  (MCI) route tree and a parallel `/vte/*` tree; `MenuBar` switches on the URL
  prefix `/vte`. No `STUDY_TYPE` / `VITE_STUDY_TYPE` dispatch exists.

**Rationale**: The constitution v1.4.0 Sync Impact Report itself cites
`table_service.py`'s `status IN ('sent', …)` logic and assumes a full pipeline.
The pipeline's back half is in fact unbuilt. Recording this here satisfies
Principle VI ("record the current observed behavior before modifying it") and
makes clear that wiring `assigned`/`sent`/`reviewer1_done`/`done` is **new**
behavior, not a regression of existing behavior — so FR-017 ("MUST NOT alter
the workflow behavior of existing studies") and SC-006 ("pre-existing workflow
tests pass without modification") are satisfied: no existing study runs the
back half today, and no pre-existing test exercises it.

**Alternatives considered**: Narrowing this feature to "config + flags + docs"
and splitting the assign→review→done build into a separate feature — rejected:
User Story 1 (priority P1, "the feature") cannot be demonstrated without the
back half, and the spec's Independent Test for Story 1 requires an event to
reach `done`.

---

## Decision 2 — The shared configuration layer

**Decision**: Repurpose `flask_backend/study_config.py` as **the** shared
configuration layer (Constitution Principle IV / FR-002). It gains: resolution
of `STUDY_TYPE` plus the four workflow-stage controls, the per-study default
profile, and startup validation. It is imported by `app.py` and
`table_service.py`. Its `SCAFFOLDING ONLY` banner is replaced.

`get_workflow_config()` returns a single resolved, immutable object:
`{ study_type, scrubbing: bool, screening: bool, sending: bool, reviewer_count: int }`.

**Resolution semantics (FR-006)**: the selected `STUDY_TYPE` supplies a
*default profile*; an explicit environment variable overrides any individual
control.
- `scans` profile → `scrubbing=false, screening=false, sending=false, reviewer_count=1`.
- every other / unset study → `scrubbing=true, screening=true, sending=true, reviewer_count=2` (FR-004, the conservative full-workflow default).
- If `ENABLE_SCRUBBING` / `ENABLE_SCREENING` / `ENABLE_SENDING` / `REVIEWER_COUNT`
  are set in `.env`, the explicit value wins over the profile (edge case
  "operator overrides a study-type default"; "non-`scans` study using bypass
  controls").

**Rationale**: One module, one resolved object — eliminates scattered
`os.getenv` reads (FR-002) and removes the need for any `STUDY_TYPE` branch in
pipeline code (FR-003). Reusing `study_config.py` rather than adding a new
module also discharges its Principle VI "unused subsystem" debt.

**Alternatives considered**: A brand-new `workflow_config.py` — rejected as a
redundant second config home; `study_config.py` already exists for exactly
this role. Per-study `VITE_` frontend env vars as a second source of truth —
rejected (see Decision 7).

---

## Decision 3 — Startup validation (fail-fast)

**Decision**: `get_workflow_config()` validates at first call during app
initialization (module import / app construction in `app.py`). On any invalid
value it raises, so the Flask app fails to construct and **serves no request**
(FR-005, SC-004).

- `REVIEWER_COUNT` must parse to an integer in `{1, 2}`. `3` or any other value
  → startup error ("Unsupported reviewer count" edge case).
- `ENABLE_SCRUBBING` / `ENABLE_SCREENING` / `ENABLE_SENDING` must parse to a
  boolean from a recognized token set (`true/false/1/0/yes/no`,
  case-insensitive). Any unrecognized token → startup error ("Malformed
  control value" edge case) — never silently coerced.

**Rationale**: "Refuse to start, serve no request" is only guaranteed if the
error is raised before the WSGI app object exists. Importing the config layer
at the top of `app.py` achieves that.

**Alternatives considered**: Validating lazily on first request — rejected;
SC-004 requires 100% of bad-config starts to be refused *before any request is
served*.

---

## Decision 4 — Wiring the assign / send status transitions

**Decision**:
- `assign_events` additionally sets `status = 'assigned'` for first-reviewer
  assignment. This is new shared behavior, applied to every study.
- `send_events` additionally sets `status = 'sent'`. The `send_many` route /
  send queue is only surfaced when `sending` is enabled; when `sending` is
  disabled the send step is skipped entirely (FR-009) and an `assigned` event
  is directly reviewer-eligible.
- `assign_many` rejects `slot` values of `second`/`third` when
  `reviewer_count == 1` (FR-013).

**Rationale**: `assigned` and `sent` are already defined enum values
(FR-015 — nothing is added or removed); the feature simply starts *entering*
them. Skipping `sent` when sending is off is the "selective bypass" itself.

**Alternatives considered**: Driving the queues purely off date columns and
leaving `status` alone — rejected: the reviewer queue and cross-study tooling
(FR-007, SC-007) read `status`; a coherent lifecycle needs `status` to advance.

---

## Decision 5 — The review-submission endpoint and completion logic

**Decision**: Add a shared `POST /api/events/<int:event_id>/review`
(`@requires_auth`, `@requires_any_role('reviewer','admin')`). It records a
`reviews` row using the existing shared `reviews` table, sets the submitting
reviewer's `reviewN_date`, and advances `status`:

- The submitter's slot is determined from `events.reviewer1_id` /
  `reviewer2_id` (not from study name — FR-003).
- After reviewer 1 submits → `status = 'reviewer1_done'`.
- If `reviewer_count == 1`, the same call immediately advances
  `status = 'done'` — no separate admin completion step (FR-010), and no
  `reviewer2_done` / third-review state is entered, and no reviewer-disagreement
  comparison is performed (FR-014).
- If `reviewer_count == 2`, reviewer 2's submission sets `status =
  'reviewer2_done'`.

**Scope boundary**: the `reviewer2_done → done` path for two-reviewer studies
(reviewer-agreement comparison / third-review escalation) is **deferred** —
it does not exist today and is out of scope for this feature, which targets the
`scans` (single-reviewer) lifecycle. The `reviewer2_done` and `third_review_*`
states remain defined in the schema and enum (FR-015); `assign_events` already
supports a `third` slot and `emailer.send_third_reviewer_emails_*` already
exists, so the escalation feature can be added later without touching this work.

**Review content**: this feature implements the review-submission *lifecycle*
(record + transition). The review *form and `reviews` columns* are the existing
shared artifact, used unchanged — per spec Assumption, `scans` carries no
study-specific review fields. `frontend/src/pages/EventReview.jsx` is updated
so its submit handler POSTs to the new endpoint instead of `alert()`-ing.

**Rationale**: A single `REVIEWER_COUNT`-parameterized endpoint satisfies
FR-010, FR-012, FR-013, FR-014 with no study-name branching. Deferring the
two-reviewer completion keeps the feature scoped to `scans` and avoids
inventing undocumented clinical-disagreement logic.

**Alternatives considered**: A `scans`-specific completion path — rejected,
violates FR-003. Building the full two-reviewer agreement/escalation logic
now — rejected as scope creep with no spec coverage.

---

## Decision 6 — Flag-aware eligibility and queue queries

**Decision**: Make the phase/queue helpers in `table_service.py` consult
`get_workflow_config()` instead of assuming every stage runs:

- "Ready to assign" predicate = *all enabled* pre-assignment stages complete
  AND `assign_date IS NULL`. With scrubbing+screening disabled (`scans`):
  `upload_date IS NOT NULL AND assign_date IS NULL` (FR-007, FR-008).
- `get_events_awaiting_review` / `get_events_for_review`: when `sending` is
  disabled, include `status = 'assigned'` events for the assigned reviewer;
  when enabled, keep `status = 'sent'` (FR-009, FR-011).
- `/api/events/by_status/<status>` maps only to phases that the active config
  actually uses; bypassed phases return empty / are not surfaced.

**Rationale**: Eligibility currently keys on intermediate date columns that a
`scans` event never receives (`scrub_date`, `screen_date`, `send_date`); a
bypass deployment would otherwise have an event stuck forever. Centralizing the
predicates against the resolved config keeps Principle IV satisfied (no
`STUDY_TYPE` branch in `table_service.py`).

**Alternatives considered**: Stamping bypassed date columns (e.g. setting
`scrub_date` on a `scans` upload even though no scrubbing happened) — rejected:
it would record stages that never occurred, contradicting the clarification
"per-event history omits bypassed states entirely."

---

## Decision 7 — Delivering the resolved config to the frontend

**Decision**: Add `GET /api/config` (`@requires_auth`) returning
`{ study_type, workflow: { scrubbing, screening, sending, reviewer_count } }`.
`App.jsx` fetches it once at load (alongside `/api/auth/me`) and supplies it to
the component tree; UI components hide bypassed-stage elements by reading these
flags (FR-018–FR-021).

**Rationale**: The backend `.env` is the single source of truth (Constitution
Principle IV — one canonical `.env`). Exposing the *resolved* config over the
API avoids a second, desyncable copy in `frontend/default.env` and keeps the
frontend's hide/show decisions driven by the same flags the backend obeys
(FR-021 — driven by controls, not a hard-coded study name).

**Alternatives considered**: A `VITE_STUDY_TYPE` / `VITE_ENABLE_*` build-time
env set — rejected: it splits the config across two files an operator must keep
in sync, working against the single-`.env` rule.

---

## Decision 8 — Frontend: hide bypassed-stage UI

**Decision**: `scans` adds **no** `frontend/src/studies/scans/` directory
(spec Assumption — `scans` only removes stages). The shared pages are made
config-aware:

- `MenuBar.jsx` — hide scrub/screen entry points when those stages are off.
- The admin dashboard / `EventAssignMany` — hide scrubbing/screening/sending
  queues and second-/third-reviewer assignment controls when bypassed
  (FR-018, FR-019); show upload, assignment, single review, completion
  (FR-020).
- All hide/show logic reads the `GET /api/config` flags, never `study_type`
  directly (FR-021).

**Rationale**: `scans` is "the shared interface with bypassed-stage elements
hidden" (spec Assumption); a study-specific component tree is only warranted
when a study *extends* the workflow (as VTE does).

---

## Decision 9 — No database schema change

**Decision**: This feature ships **no** schema migration and **no**
`init/02-schema-scans.sql`.

**Rationale**: The `events` table already has every needed column
(`assign_date`, `send_date`, `review1_date`, `review2_date`, `scrubber_id`,
`reviewer1_id`, …) and the full `status` enum; the shared `reviews` table
already exists. `scans` reuses the shared schema (spec Assumption). Constitution
Principle III is satisfied trivially — no legacy-data compatibility concern.
Bypassed states are retained, not removed (FR-015).

**Alternatives considered**: A `scans` schema file — rejected as unnecessary;
would also collide on the `reviews` table per the setup guide's wiring note.

---

## Decision 10 — Documentation and contract artifacts

**Decision**:
- `default.env` — add `STUDY_TYPE` and the four controls with conservative
  defaults and one-line descriptions (FR-022).
- `README.md` — list the four controls in the Environment Variables section
  (FR-023).
- `docs/template-setup-guide.md` — add a `scans` worked example, parallel to
  the existing VTE alternative-study example (FR-024).
- `openapi.json` — regenerate via `python -m flask_backend.generate_openapi`
  in the same change set, since `/api/events/<id>/review` and `/api/config` are
  new routes and `assign_many`/`send_many` change behavior (Constitution
  "API contracts" gate; Principle VI).

**Rationale**: These are the documentation deliverables the v1.4.0 Sync Impact
Report flagged as ⚠ pending; FR-022–FR-024 require these exact files.

---

## Resolved unknowns summary

| Unknown | Resolution |
|---|---|
| Where do the four controls live? | `study_config.py`, repurposed as the shared config layer (Decision 2) |
| How is the `scans` profile applied? | Study-type default profile + per-control `.env` override (Decision 2) |
| How does a bad config "refuse to start"? | Validation raises at app-init import (Decision 3) |
| Is there an existing review/done path to bypass? | No — it must be built (Decisions 1, 5) |
| Schema change / migration needed? | No (Decision 9) |
| How does the frontend learn the config? | `GET /api/config` (Decision 7) |
| Does `scans` need a study-specific component dir? | No (Decision 8) |
| Two-reviewer `→ done` escalation? | Deferred, out of scope; states retained (Decision 5) |
