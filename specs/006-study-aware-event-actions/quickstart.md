# Quickstart: Show only study-relevant actions on Events Summary

**Feature**: 006-study-aware-event-actions | **Date**: 2026-05-22

How to implement and verify this frontend-only change. No backend, schema, or
dependency change is involved.

## What changes

Two files in `frontend/src/`:

1. **`App.jsx`** — pass the existing `workflow` state to `EventViewAll`:

   ```jsx
   // before
   <EventViewAll />
   // after
   <EventViewAll workflow={workflow} />
   ```

   `App.jsx` already holds `workflow` (fetched from `/api/config`, defaulted to
   the full workflow) and already passes it to `<EventAssignMany>`. This is the
   same one-prop pass-through.

2. **`pages/EventViewAll.jsx`** — accept the prop and gate five sections:

   ```jsx
   function EventViewAll({ workflow }) {
     const wf = workflow || {}
     const showScrubbing = wf.scrubbing !== false
     const showScreening = wf.screening !== false
     const showSending   = wf.sending   !== false
     const showThirdReviewer = Number(wf.reviewer_count) !== 1
     // ...
   }
   ```

   Then wrap the gated `<TableSection>`s:

   - `{showScrubbing && (<TableSection title="To Be Scrubbed" … />)}`
   - `{showScreening && (<TableSection title="To Be Screened" … />)}`
   - `{showSending && (<TableSection title="To Be Sent" … />)}`
   - `{showThirdReviewer && (<TableSection title="Third Review Needed" … />)}`
   - `{showThirdReviewer && (<TableSection title="Third Reviewer Assigned" … />)}`

   Leave the other six `TableSection`s and the Event Status Summary table
   exactly as they are.

The `!== false` / `!== 1` form is deliberate — a missing or unresolved control
yields a *visible* section (FR-010, conservative default).

## Lint gate

The frontend has no test runner. ESLint MUST stay clean:

```bash
cd frontend
npm run lint
```

## Manual verification

No local stack is available to this developer — verification runs on the
deployment server (or any environment where `/api/config` can be set per
study). Drive the workflow by editing `.env` and restarting the backend, then
load `/events/viewAll`.

### Scenario A — Scans configuration (User Story 1 + 2, SC-001)

`.env`: `STUDY_TYPE=scans` (or `ENABLE_SCRUBBING=false`,
`ENABLE_SCREENING=false`, `ENABLE_SENDING=false`, `REVIEWER_COUNT=1`).

Expect on `/events/viewAll` — **exactly 6 sections, in this order**:
To Be Uploaded · To Be Assigned · Not Yet Reviewed · All Done ·
No Packet Available · Rejected. (Order follows the canonical event lifecycle;
in Scans it collapses to Uploaded → Assigned → Not Yet Reviewed → Done with
the bypassed stages removed.)

Confirm **absent**: To Be Scrubbed, To Be Screened, To Be Sent,
Third Review Needed, Third Reviewer Assigned — each gone in its entirety
(heading, Show/Hide, queue, action buttons).

### Scenario B — Full-workflow configuration (User Story 3, SC-002)

`.env`: a full-workflow study (e.g. `STUDY_TYPE=mci`, controls unset).

Expect on `/events/viewAll` — **all 11 sections** in the original order, every
action present. Nothing removed relative to today.

### Scenario C — A single bypassed stage (User Story 1, per-stage)

`.env`: full workflow except `ENABLE_SCREENING=false`.

Expect — 10 sections: "To Be Screened" absent; all others (including
"To Be Scrubbed", "To Be Sent", both third-reviewer sections) present.
Confirms gating keys on the individual control, not a study name (FR-007).

### Scenario D — Config unavailable / not yet resolved (Acceptance 3.2, FR-010)

With the backend `/api/config` unreachable, load `/events/viewAll`.

Expect — **all 11 sections** show (the full-workflow fallback). No section is
hidden on missing config.

### Cross-check — count table unchanged (FR-011)

In every scenario, the "Event Status Summary" table at the top still lists its
status rows with real counts — it is never gated.

## Done when

- `npm run lint` is clean.
- Scenarios A–D all observed as described.
- `/vte/viewAll` is unchanged (not in scope).
