# Phase 0 Research: Interactive reviewer-assignment page

**Feature**: 004-reviewer-assignment
**Date**: 2026-05-21

This document resolves the open questions from the spec — chiefly the spec's
explicit ask: *"confirm whether the endpoint needs any change to assign first
and second reviewers in a single operation, or whether the page issues one
call per slot."*

---

## Decision 1 — Queue source: reuse `GET /api/events/by_status/screened`

**Decision**: The page loads the "To Be Assigned" queue from
`GET /api/events/by_status/screened`, with `limit`, `offset`, and `site`
query parameters.

**Rationale**: `flask_backend/app.py` routes `by_status/screened` to
`table_service.get_to_be_assigned_with_total`, which builds its WHERE clause
from `_ready_to_assign_predicate()`. That predicate is **flag-aware**: it
requires `upload_date IS NOT NULL`, adds `scrub_date IS NOT NULL` only when
`ENABLE_SCRUBBING` is on, adds `screen_date IS NOT NULL` only when
`ENABLE_SCREENING` is on, and always requires `assign_date IS NULL`. For a
scans deployment (scrubbing and screening bypassed) this reduces to
`upload_date IS NOT NULL AND assign_date IS NULL` — i.e. events still in the
`uploaded` status appear in the queue, exactly as the spec requires (FR-002).
This is the same endpoint the View All Events page uses for its "To Be
Assigned" section, so the two views stay consistent.

**Response shape**: `{ data: [ {ID, Date, Created, Uploaded, Scrubbed,
Criteria, Site}, ... ], total: <int> }`. The endpoint supports `site`
filtering and `limit`/`offset` pagination server-side (FR-015, FR-016).

**Alternatives considered**:
- *Generic `get_events_by_status_with_total("screened", …)`* — rejected: it
  filters strictly on `e.status = 'screened'` and is **not** flag-aware, so a
  scans deployment (events in `uploaded`) would show an empty queue.
- *A new dedicated endpoint* — rejected: unnecessary; the flag-aware helper
  already exists and is already in production use.

---

## Decision 2 — Reviewer list: reuse `GET /api/tables/users`

**Decision**: Populate the reviewer selector(s) from
`GET /api/tables/users?limit=2000`, filtering to rows with a truthy
`reviewer_flag` on the client.

**Rationale**: This is the established pattern — `pages/EventAssignThird.jsx`
does exactly this. A "reviewer" is any user with the reviewer role flag set,
which includes admins who also review (FR-006). `users` rows carry `id`,
`username`, `site`, and `reviewer_flag`, which is everything the selector
needs. The endpoint is `@requires_auth`; the page itself is admin-gated, and
the data (usernames/sites) contains no PHI, so no stricter protection is
warranted.

**Alternatives considered**:
- *New `GET /api/users/reviewers` endpoint* — rejected for this feature: adds
  backend surface for no functional gain. Reusing the existing reader keeps
  the single-reviewer MVP (P1) entirely frontend-side.

---

## Decision 3 — Single-reviewer assignment (P1): existing endpoint, no backend change

**Decision**: For `reviewer_count == 1`, the page issues one request:
`POST /api/events/assign_many` with body
`{ event_ids: [...], reviewer_id: <id>, slot: "first" }`.

**Rationale**: The endpoint already exists, is admin-only, and on a
first-reviewer assignment sets `reviewer1_id`, `assigner_id`, `assign_date`
and advances `status` to `assigned` (`table_service.assign_events`). This
fully satisfies the P1 user story and unblocks the scans
upload → assign → review → done lifecycle with **zero backend changes**.

---

## Decision 4 — Two-reviewer assignment (P2): atomic backend extension *(answers the spec's open question)*

**Decision**: The two-reviewer case requires a **backend change**. The page
issues **one** request that carries both reviewers; the backend assigns them
in a **single transaction**. `POST /api/events/assign_many` gains an optional
`reviewer2_id` field, accepted only with `slot: "first"`.

**Why one-call-per-slot does NOT work** — this is the decisive finding:

The "To Be Assigned" queue predicate (`_ready_to_assign_predicate()`) ends
with `e.assign_date IS NULL`. In `assign_events`, **every** slot — `first`,
`second`, and `third` — sets `assign_date = today`. Therefore the *first* of
two sequential per-slot calls already sets `assign_date`, which immediately
removes the event from the To-Be-Assigned queue regardless of which slot was
written first. There is no call ordering that keeps a partially-assigned
event in the queue between the two calls.

That directly violates **FR-012** ("the administrator MUST choose a reviewer
for both slots before confirming, so that an event is fully assigned before
it advances out of the queue") and **FR-014** ("on a successful assignment …
the assigned events MUST leave the queue"). Worse, a partial failure (call 1
succeeds, call 2 fails) leaves an event with `assign_date` set, one reviewer
missing, and `status` possibly still `screened` — it disappears from the
To-Be-Assigned queue but surfaces in "To Be Sent" (whose predicate is
`assign_date IS NOT NULL AND send_date IS NULL`) with a missing reviewer, and
there is no UI to repair it. That is an unrecoverable bad state.

**The change (additive, backward-compatible)**:

- `POST /api/events/assign_many` accepts an **optional** `reviewer2_id`.
  - Existing single-slot bodies (`{event_ids, reviewer_id, slot}` for
    `first`/`second`/`third`) behave exactly as before.
  - When `slot == "first"` **and** `reviewer2_id` is present, both reviewers
    are assigned together.
- `table_service.assign_events` gains a trailing optional parameter
  `reviewer2_id=None`. When `slot == "first"` and `reviewer2_id` is provided,
  it sets `reviewer1_id`, `reviewer2_id`, `assigner_id`, `assign_date`, and
  `status = "assigned"` for every event in **one `session.commit()`** — so
  the assignment is atomic: all events get both reviewers, or none do.
- Validation (returned as `400` so the page can surface it, FR-017):
  - `reviewer2_id` is rejected when `reviewer_count == 1` (consistent with
    the existing rejection of `second`/`third` slots).
  - `reviewer2_id` is rejected unless `slot == "first"`.
  - `reviewer2_id == reviewer_id` is rejected — server-side enforcement of
    FR-011 ("the same person MUST NOT be assigned to more than one slot"),
    in addition to the client-side block.

**Backward compatibility**: `pages/EventAssignThird.jsx` posts
`{event_ids, reviewer_id, slot:"third"}` — unaffected. Backend tests call
`assign_events(event_ids, reviewer_id, slot, assigner_id)` positionally with
no fifth argument — unaffected because `reviewer2_id` defaults to `None`. Per
Constitution Principle VI this is a permitted pre-release change to the app's
own API; `openapi.json` MUST be regenerated in the same change.

**Alternatives considered**:
- *Two sequential per-slot calls* — rejected: cannot satisfy FR-012/FR-014
  and creates an unrecoverable partial state (analysis above).
- *A brand-new `assign_pair` endpoint* — rejected: more surface than needed;
  an optional field on the existing admin-only endpoint is smaller and keeps
  one assignment endpoint.
- *Resurrecting the stale `{reviewer1_id, reviewer2_id}` shape* — rejected:
  that shape drops `slot` entirely and would collide conceptually with the
  third-reviewer flow; keeping `slot` as the discriminator is clearer.

---

## Decision 5 — Spec edge case "partial failure across slots" is superseded

**Decision**: The spec's edge case *"Partial failure across … slots … the
page reports which events/slots were assigned and which were not"* no longer
applies and is replaced by all-or-nothing behavior.

**Rationale (recorded per Constitution Principle VI)**: That edge case was
written anticipating a possible one-call-per-slot implementation. Decision 4
makes the two-reviewer assignment atomic in a single transaction, so a
partial slot outcome is impossible — the request either fully succeeds or
fully fails with nothing written. The remaining, simpler edge case is: the
whole assignment fails (validation, auth, server, or network error), nothing
changes, the error is surfaced (FR-017), and the admin retries. This is a
strictly better outcome than reporting an unrepairable half-assigned state.

---

## Decision 6 — Slot count is driven by `reviewer_count`, never by study name

**Decision**: The page reads `workflow.reviewer_count` (already passed to
`EventAssignMany` as a prop from `App.jsx`, sourced from `GET /api/config`).
`reviewer_count === 1` → render one reviewer selector. `reviewer_count >= 2`
→ render a first and a second reviewer selector. No `STUDY_TYPE` or
study-name check anywhere (Constitution Principle IV; FR-008/009/010).

**Rationale**: `App.jsx` already fetches `/api/config` and passes the
resolved `workflow` object (with a conservative `reviewer_count: 2` default
until config resolves). The component only needs to consume it. Third-reviewer
slots are never rendered here (FR-020) — that is a separate page.

---

## Decision 7 — Page composition and feedback

**Decision**:
- Structure follows `pages/EventAssignThird.jsx`: a selectable event table
  (checkbox column, "select all" affordance), reviewer selector(s), and a
  confirm button. Add a site-filter `<select>` and Previous/Next pagination
  driven by the endpoint's `total`.
- Site-filter options are derived from the `Site` values present in the
  loaded rows (the pattern used by View All Events); changing the filter
  re-fetches with `site=`.
- Success and error feedback use the existing `showToast` helper plus an
  inline status line. On success the page re-fetches the queue so assigned
  events disappear without a manual reload (FR-014).
- The confirm button is disabled until at least one event is selected and
  every visible reviewer slot has a selection (FR-018); in a two-reviewer
  deployment, selecting the same person in both slots blocks confirmation
  with an explanatory message (FR-011).

**Rationale**: Reuses established components (`DataTable` for the read-only
parts is optional; a hand-rolled selectable table matches the existing
assign pages and supports the checkbox column cleanly) and the existing
toast pattern, keeping the change small and consistent with sibling pages.

---

## Summary of backend impact

| Path | P1 (single reviewer) | P2 (two reviewers) |
|------|----------------------|--------------------|
| `frontend/src/pages/EventAssignMany.jsx` | Rewritten | Rewritten (same file) |
| `frontend/src/components/MenuBar.jsx` | Add admin link (FR-003) | — |
| `flask_backend/app.py` | No change | Accept optional `reviewer2_id` |
| `flask_backend/table_service.py` | No change | `assign_events` atomic two-reviewer path |
| `flask_backend/tests/` | (optional) | Add atomic two-reviewer test |
| `openapi.json` | No change | Regenerate |

P1 ships frontend-only. The backend change is scoped entirely to P2.
