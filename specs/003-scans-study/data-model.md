# Phase 1 Data Model: Implement the `scans` Study Type

**Feature**: 003-scans-study | **Date**: 2026-05-20

This feature introduces **no database schema change**. The model below
documents the conceptual entities, the (unchanged) shared `events.status`
state machine, and the new in-memory *workflow configuration* entity that the
shared configuration layer resolves at startup.

---

## Entity: Workflow configuration (new — in-memory, not persisted)

Resolved once at application startup by the shared configuration layer
(`flask_backend/study_config.py`) and exposed via `get_workflow_config()`.

| Field | Type | Source | Default | Notes |
|---|---|---|---|---|
| `study_type` | string | `STUDY_TYPE` env | `mci` | Selects the default control profile |
| `scrubbing` | bool | `ENABLE_SCRUBBING` env | `true` | `false` ⇒ `scrubbed` state not entered |
| `screening` | bool | `ENABLE_SCREENING` env | `true` | `false` ⇒ `screened` state not entered |
| `sending` | bool | `ENABLE_SENDING` env | `true` | `false` ⇒ `sent` state not entered |
| `reviewer_count` | int | `REVIEWER_COUNT` env | `2` | Must be `1` or `2` |

**Resolution rule**: the `study_type` supplies a default profile; an explicit
env var for any individual control overrides that profile (FR-006).

**Default profiles**

| `study_type` | scrubbing | screening | sending | reviewer_count |
|---|---|---|---|---|
| `scans` | false | false | false | 1 |
| anything else / unset | true | true | true | 2 |

**Validation rules (enforced at startup — FR-005, edge cases)**

- `reviewer_count` ∉ {1, 2} ⇒ raise; app does not start, serves no request.
- a control set to an unrecognized (non-boolean) token ⇒ raise; app does not
  start. Recognized boolean tokens: `true/false/1/0/yes/no` (case-insensitive).

---

## Entity: Event lifecycle / `events.status` (unchanged shared state machine)

The `events.status` enum (`flask_backend/models.py:49`) is **not modified** —
all states are retained (FR-015):

```
created, uploaded, scrubbed, screened, assigned, sent,
reviewer1_done, reviewer2_done, third_review_needed, third_review_assigned,
done, rejected, no_packet_available
```

### Transitions after this feature

| From | To | Trigger | Gating control |
|---|---|---|---|
| `created` | `uploaded` | uploader uploads packet (`upload_raw`) | — |
| `uploaded` | `scrubbed` | scrubbed charts uploaded (`upload_scrubbed`) | only when `scrubbing` |
| `scrubbed` / `uploaded` | `screened` | screener accepts (`screen`) | only when `screening` |
| screened/uploaded/scrubbed | `assigned` | admin assigns first reviewer (`assign_many`) | — (new transition) |
| `assigned` | `sent` | admin sends (`send_many`) | only when `sending` (new transition) |
| `assigned` / `sent` | `reviewer1_done` | reviewer 1 submits review (`/review`) | — (new transition) |
| `reviewer1_done` | `done` | same `/review` call | only when `reviewer_count == 1` (FR-010) |
| `reviewer1_done` | `reviewer2_done` | reviewer 2 submits review (`/review`) | only when `reviewer_count == 2` |
| any | `rejected` | screener rejects | only when `screening` |

**`scans` path** (`scrubbing=false, screening=false, sending=false,
reviewer_count=1`): `created → uploaded → assigned → reviewer1_done → done`
— exactly four transitions (SC-002), one reviewer (SC-003).

**Retained-but-unentered for `scans`**: `scrubbed`, `screened`, `sent`,
`reviewer2_done`, `third_review_needed`, `third_review_assigned`. Defined in the
schema and recognized by cross-study tooling (FR-015, SC-007); simply never
entered by a `scans` deployment.

**Deferred**: `reviewer2_done → (third_review_*) → done` for two-reviewer
studies (reviewer-agreement / escalation) — not built by this feature; not
present today (see research.md Decision 5).

**Per-event history**: an event's history records only states it actually
entered; bypassed states never appear in a `scans` event's history. The
deployment configuration is the authoritative record of which stages are
bypassed (Clarifications 2026-05-20).

---

## Entity: Event (existing `events` table — no column change)

All columns required by the lifecycle already exist: `status`, `assign_date`,
`assigner_id`, `reviewer1_id`, `reviewer2_id`, `send_date`, `sender_id`,
`review1_date`, `review2_date`. The review-submission endpoint writes
`reviewN_date` and `status`; assignment writes `reviewerN_id`/`assign_date`;
sending writes `send_date`/`sender_id`.

## Entity: Review (existing `reviews` table — used as-is)

The shared `reviews` table is reused unchanged. A review submission inserts one
row (`event_id`, `reviewer_id`, plus the existing shared review fields). This
feature does not add study-specific review columns (spec Assumption).

## Entity: Reviewer work queue (derived, not a table)

The set of events a reviewer may pick up. Membership is config-dependent:
- `sending` enabled → events with `status = 'sent'` assigned to the reviewer.
- `sending` disabled (`scans`) → events with `status = 'assigned'` assigned to
  the reviewer (FR-009, FR-011).

## Entity: Roles (existing `users` flags — unchanged)

`admin`, `uploader`, `reviewer`, `third_reviewer` flags on the `users` table
are unchanged (FR-016). A `scans` deployment uses the first three and leaves
`third_reviewer` defined-but-unused.
