# Phase 0 Research: Show only study-relevant actions on Events Summary

**Feature**: 006-study-aware-event-actions | **Date**: 2026-05-22

The spec carried no `[NEEDS CLARIFICATION]` markers. Research here records the
current observed behavior (Constitution Principle VI) and resolves the small
implementation unknowns so Phase 1 can proceed.

## Current observed behavior (recorded before change, per Principle VI)

`frontend/src/pages/EventViewAll.jsx` renders the Events Summary page. It is a
function component, `EventViewAll()`, taking **no props**. It renders, in order:

1. An "Event Status Summary" count table (fed by `GET /api/events/status_summary`).
2. Eleven `TableSection` components — one per lifecycle status — rendered
   **unconditionally**, regardless of the deployment's workflow configuration:

   | # | Section title | `endpoint` (status queue) |
   |---|---|---|
   | 1 | To Be Uploaded | `/api/events/need_packets` |
   | 2 | Not Yet Reviewed | `/api/events/by_status/sent` (+ `reviewer1_done`, `reviewer2_done`) |
   | 3 | To Be Scrubbed | `/api/events/by_status/uploaded` |
   | 4 | To Be Screened | `/api/events/by_status/scrubbed` |
   | 5 | To Be Assigned | `/api/events/by_status/screened` |
   | 6 | To Be Sent | `/api/events/by_status/assigned` |
   | 7 | Third Review Needed | `/api/events/by_status/third_review_needed` |
   | 8 | Third Reviewer Assigned | `/api/events/by_status/third_review_assigned` |
   | 9 | All Done | `/api/events/by_status/done` |
   | 10 | No Packet Available | `/api/events/by_status/no_packet_available` |
   | 11 | Rejected | `/api/events/by_status/rejected` |

Each `TableSection` owns its heading, a Show/Hide toggle, its queue, and a
`renderActions` callback supplying per-row action buttons. In the Scans
deployment, sections 3, 4, 6, 7, and 8 are permanently empty because their
stages are bypassed — this is the noise shown in
`Screenshot 2026-05-22 124501.events.viewAll.TooManyActions.jpg`.

`EventViewAll` is routed in `App.jsx` at `/events/viewAll` as
`<EventViewAll />` — currently with no props passed.

## Existing infrastructure this feature reuses

The bypassed-stage gating mechanism already exists; this feature is the last
consumer to adopt it.

- `flask_backend/study_config.py` resolves the four workflow controls
  (`scrubbing`, `screening`, `sending`, `reviewer_count`) into an immutable
  `WorkflowConfig`, defaulting to the conservative full workflow.
- `GET /api/config` (`flask_backend/app.py`) exposes them as
  `data.workflow = {scrubbing, screening, sending, reviewer_count}`.
- `frontend/src/App.jsx` already fetches `/api/config` on mount and stores the
  result in a `workflow` state object, **initialised to the conservative
  full-workflow default** (`{scrubbing: true, screening: true, sending: true,
  reviewer_count: 2}`) and left at that default if the fetch fails.
- `App.jsx` already **gates the bypassed-stage *routes*** on this state — e.g.
  `{workflow.sending && <Route path="/events/sendMany" .../>}`,
  `{workflow.screening && <Route path="/events/screen" .../>}`,
  `{workflow.scrubbing && <Route path="/events/scrub" .../>}`,
  `{workflow.reviewer_count > 1 && <Route path="/events/assignThird" .../>}`.
- `App.jsx` already **passes `workflow` as a prop** to `EventAssignMany`
  (`<EventAssignMany workflow={workflow} />`).

So `EventViewAll` is the one page that shows bypassed-stage UI without
consulting the config the rest of the app already honors.

## Decision 1 — Source of the workflow configuration

**Decision**: Pass `App.jsx`'s existing `workflow` state object into
`EventViewAll` as a prop — `<EventViewAll workflow={workflow} />` — exactly as
`EventAssignMany` already receives it. `EventViewAll` does **not** fetch
`/api/config` itself.

**Rationale**: `App.jsx` is already the single fetch site for `/api/config` and
already owns the conservative default and the failure fallback. Re-fetching
inside `EventViewAll` would duplicate that logic, add a redundant request, and
risk a second, inconsistent default. Threading the prop matches the
established `EventAssignMany` pattern and keeps one source of truth.

**Alternatives considered**:
- *`EventViewAll` fetches `/api/config` itself* — rejected: duplicates the
  fetch, the default, and the error handling already in `App.jsx`.
- *A React context for workflow config* — rejected: only two consumers
  (`EventAssignMany`, now `EventViewAll`); a context is unjustified ceremony.
  Can be revisited if a third consumer appears.

## Decision 2 — Section-to-control mapping

**Decision**: Gate exactly five of the eleven sections; the other six always
render.

| Section | Gate condition | Requirement |
|---|---|---|
| To Be Scrubbed | `workflow.scrubbing` is on | FR-002 |
| To Be Screened | `workflow.screening` is on | FR-003 |
| To Be Sent | `workflow.sending` is on | FR-004 |
| Third Review Needed | `workflow.reviewer_count > 1` | FR-005 |
| Third Reviewer Assigned | `workflow.reviewer_count > 1` | FR-005 |
| To Be Uploaded, Not Yet Reviewed, To Be Assigned, All Done, No Packet Available, Rejected | *(always rendered)* | FR-006 |

**Rationale**: This is a direct reading of the constitution's selective-bypass
controls (Principle V) and the spec's FR-002…FR-006. The two third-reviewer
sections share one gate (`reviewer_count`) because they both belong to the
multi-reviewer escalation path. "Not Yet Reviewed" stays visible in every
configuration because review always happens (spec Assumptions); only the
*third*-reviewer sections are reviewer-count-gated. "To Be Assigned" stays
visible because assignment happens in every workflow — and its backend
`by_status` endpoint is already flag-aware (the in-file comment at the "To Be
Assigned" section notes it surfaces `uploaded` events when scrubbing/screening
are bypassed), so the section is correct in every configuration without
gating.

**Alternatives considered**:
- *Gate "To Be Assigned" too* — rejected: assignment is never bypassed; the
  section is always relevant.
- *Drive visibility from a status→stage table or the backend* — rejected as
  over-engineering for five render-time booleans; no backend change is in
  scope (spec Assumptions).

## Decision 3 — Conservative default when the config has not resolved

**Decision**: Treat a section as **visible unless its control is explicitly
off**. Concretely, gate on `workflow.scrubbing !== false` (etc.) and on
`Number(workflow?.reviewer_count) !== 1` for the third-reviewer pair, with
`workflow` itself defaulting to an empty object if the prop is absent. Any
unresolved / missing / malformed control therefore yields a *visible* section.

**Rationale**: FR-010 and Acceptance Scenario 3.2 require the full section set
to show until the config resolves or if it cannot be retrieved. In practice
`App.jsx` already initialises `workflow` to the full-workflow default and
keeps it there on fetch failure, so the prop is well-formed in every path —
but gating on `!== false` rather than truthiness makes `EventViewAll`
independently safe (e.g. a partial object) and self-documents the conservative
intent. Hiding a section requires a *positive* "off" signal.

**Alternatives considered**:
- *Gate on truthiness (`workflow.scrubbing && …`)* — rejected: an absent prop
  or `undefined` control would hide the section, the wrong default direction.

## Decision 4 — Scope boundary: the legacy VTE page

**Decision**: Modify only `frontend/src/pages/EventViewAll.jsx`. Leave
`frontend/src/studies/vte/EventViewAll.jsx` (route `/vte/viewAll`) untouched.

**Rationale**: The spec's Assumptions place the VTE-forked page out of scope as
a separate, deprecated code path. The project memory note "Don't propagate the
VTE fork pattern" reinforces that new work targets `/events/*` only and must
not extend the VTE branch.

## Decision 5 — Action buttons inside gated sections

**Decision**: No separate work. FR-009 (omit a hidden section's Show/Hide
toggle and per-event action buttons) is satisfied automatically: the toggle
and `renderActions` buttons are JSX *children/props of the `TableSection`*, so
not rendering the `TableSection` removes them with it.

**Rationale**: There is no action UI for these stages outside their owning
`TableSection`. The corresponding destination *pages* (e.g. `/events/scrub`,
`/events/screen`, `/events/sendMany`) are already route-gated in `App.jsx`, so
even a stale deep link is handled. No extra change is needed.

## Testing approach

The frontend has no automated test runner (only ESLint), consistent with
features 003–005. Verification is manual against the three user-story
acceptance scenarios, recorded in `quickstart.md`. ESLint MUST stay clean
(`npm run lint`). This matches Constitution Principle VI's manual-verification
posture for the pre-release frontend.
