# Phase 1 Data Model: Interactive reviewer-assignment page

**Feature**: 004-reviewer-assignment
**Date**: 2026-05-21

**No schema change.** This feature introduces no new tables and no new
columns. It reads existing data and writes existing `events` columns through
the existing `assign_events` service function. This document records the
shapes the page reads and writes so the design is unambiguous.

---

## Entities (all pre-existing)

### Awaiting event (read — the queue)

Source: `GET /api/events/by_status/screened` →
`table_service.get_to_be_assigned_with_total`. Each row:

| Field | Meaning |
|-------|---------|
| `ID` | Event id (used as `event_ids` element on assign) |
| `Date` | Event date |
| `Created` | `events.add_date` |
| `Uploaded` | `events.upload_date` |
| `Scrubbed` | `events.scrub_date` (empty when scrubbing bypassed) |
| `Criteria` | Comma-joined criteria names |
| `Site` | Patient site (drives the site filter) |

Membership rule (flag-aware, `_ready_to_assign_predicate()`): an event is in
this queue when `upload_date IS NOT NULL`, **and** `scrub_date IS NOT NULL`
if scrubbing is enabled, **and** `screen_date IS NOT NULL` if screening is
enabled, **and** `assign_date IS NULL`. The trailing `assign_date IS NULL`
term is why assignment must be atomic (see research Decision 4).

Envelope: `{ "data": [ ...rows ], "total": <int> }`. Supports `limit`,
`offset`, `site` query params.

### Eligible reviewer (read — the selector)

Source: `GET /api/tables/users?limit=2000`, client-filtered to
`reviewer_flag` truthy. Fields used: `id`, `username`, `site`,
`reviewer_flag`. A reviewer may also be an admin.

### Workflow configuration (read — slot count)

Source: the `workflow` prop on `EventAssignMany` (from `GET /api/config`).
Field used: `reviewer_count` (integer, `1` or `2`). `1` → one reviewer slot;
`2` → first + second slots. Never branch on `study_type`.

### Reviewer assignment (write — `events` columns)

Written by `table_service.assign_events`. No new columns.

| `events` column | Set when |
|-----------------|----------|
| `reviewer1_id` | `slot == "first"` — the first reviewer |
| `reviewer2_id` | `slot == "second"`, **or** `slot == "first"` with `reviewer2_id` supplied (new atomic path) |
| `assigner_id` | every assignment — the acting admin (audit) |
| `assign_date` | every assignment — today's date |
| `status` | set to `assigned` **only** on a first-reviewer assignment |

`reviewer3_id` / `assigner3rd_id` / `assign3rd_date` are **not** written by
this page (third-reviewer assignment is out of scope, FR-020).

---

## State transition

Only one event-lifecycle transition is caused by this feature:

```
screened ─┐
          ├──(first-reviewer assignment)──▶ assigned
uploaded ─┘   (uploaded→assigned occurs when scrubbing/screening bypassed)
```

- A **first-reviewer** assignment sets `status = "assigned"` (FR-013). The
  event leaves the To-Be-Assigned queue because both `assign_date` is now set
  and the status advanced.
- A **second-reviewer-only** assignment (`slot == "second"`, used by the
  existing endpoint contract but **not** by this page) sets `reviewer2_id`
  and `assign_date` without advancing `status`.
- This page's two-reviewer path always assigns the first reviewer (plus the
  second, atomically), so it always produces the `→ assigned` transition.

Bypassed states (`scrubbed`, `screened`, `sent`, `reviewer2_done`,
`third_review_*`) remain defined in the schema and shared state machine
(Constitution Principle V); this feature neither removes nor renames any.

---

## Validation rules (enforced at write time)

| Rule | Where enforced | Spec ref |
|------|----------------|----------|
| `event_ids` non-empty list, `reviewer_id` present, `slot` present | endpoint → `400` | FR-018 |
| `slot` ∈ {`first`, `second`, `third`} | `assign_events` → `ValidationError`/`400` | — |
| `second`/`third` slot rejected when `reviewer_count == 1` | endpoint → `400` (existing) | FR-009 |
| `reviewer2_id` rejected when `reviewer_count == 1` | endpoint → `400` (new) | FR-009 |
| `reviewer2_id` accepted only with `slot == "first"` | endpoint → `400` (new) | research D4 |
| `reviewer2_id != reviewer_id` (no person in two slots) | endpoint → `400` (new) + client block | FR-011 |
| Action restricted to admin | `@requires_roles('admin')` (existing) → `401`/`403` | FR-004 |

Client-side, the page also blocks submission with no events selected, no
reviewer chosen, or the same person in both slots (FR-011, FR-018) before any
request is sent.
