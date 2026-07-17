# Quickstart: Interactive reviewer-assignment page

**Feature**: 004-reviewer-assignment
**Date**: 2026-05-21

How to exercise the feature once implemented. The canonical environment is the
Docker Compose stack (`docker compose up`); there is no separate local stack.

---

## Reaching the page

- **From View All Events**: open *View All Events* → expand the **To Be
  Assigned** section → click **assign** on any row. (The button already
  routes to `/events/assignMany`.)
- **From the menu**: an admin-only **Assign Charts** link in the top menu
  (added by this feature, FR-003).
- The `/events/assignMany` route is admin-gated; a non-admin is blocked by
  `ProtectedRoute` before the page renders.

---

## Path 1 — Single-reviewer deployment (P1, e.g. scans)

Deployment has `REVIEWER_COUNT=1` (scrubbing/screening/sending bypassed).

1. Open the assignment page. The **To Be Assigned** queue lists events
   awaiting assignment — for scans these are events in the `uploaded` status
   (flag-aware queue).
2. Confirm exactly **one** reviewer selector is shown — no second/third slot
   (FR-009).
3. Select one or more events with their checkboxes.
4. Choose a reviewer (any user with the reviewer role, admins included).
5. Click **Assign**. Expect a success toast; the assigned events disappear
   from the queue without a manual reload (FR-014).
6. Verify in *View All Events*: the events moved out of **To Be Assigned**;
   their status is `assigned`.

**Lifecycle check (SC-005)**: an event can now go
`uploaded → assigned → reviewer1_done → done` — the stage that was previously
impossible is unblocked.

---

## Path 2 — Two-reviewer deployment (P2)

Deployment has `REVIEWER_COUNT=2`.

1. Open the assignment page. Confirm **two** reviewer selectors — first and
   second (FR-010). No third slot (FR-020).
2. Select one or more events.
3. Choose a first reviewer and a **different** second reviewer.
4. Try selecting the **same** person in both slots → confirmation is blocked
   with an explanatory message (FR-011).
5. With two distinct reviewers, click **Assign**. One atomic request is sent;
   on success both reviewers are recorded on every selected event and the
   events leave the queue (FR-014).
6. Verify in *View All Events*: events show `assigned` status with both
   reviewers populated.

---

## Error handling checks (FR-017, SC-004)

| Trigger | Expected |
|---------|----------|
| Confirm with no events selected, or no reviewer chosen | Submission blocked client-side; page indicates what is missing (FR-018) |
| Session expired / not admin | `401`/`403` surfaced as an authorization message; no success claimed |
| Server/network failure | Failure surfaced; admin can retry; no false success |
| Empty queue | Clear empty-state message, not an error |
| No users hold the reviewer role | Page communicates that no reviewer can be chosen |

---

## Filtering & pagination (P3)

- Apply the **site** filter → only that site's events remain (FR-015).
- With more than 20 events, use **Previous/Next** to page through the queue
  (FR-016).

---

## Regression / verification

- **Backend**: `pytest flask_backend/tests/` — includes a new test for the
  atomic two-reviewer assignment and the new `400` validations. Existing
  `test_scans_workflow.py` / `test_full_workflow_defaults.py` still pass
  (the `assign_events` signature change is additive).
- **API contract**: `openapi.json` regenerated
  (`python -m flask_backend.generate_openapi`) and committed.
- **Frontend**: `npm run lint` in `frontend/` is clean (no test runner is
  configured; verify behavior manually against the running stack).
- **Third-reviewer page** (`/events/assignThird`) still assigns correctly —
  it uses `slot:"third"`, unaffected by the extension.
