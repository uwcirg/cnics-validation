# Phase 1 Data Model: Show only study-relevant actions on Events Summary

**Feature**: 006-study-aware-event-actions | **Date**: 2026-05-22

This feature introduces **no persisted data and no schema change**. It consumes
configuration that already exists. The "entities" below are the runtime
structures the gating logic reads and acts on; they are documented here so the
section-visibility rules have a precise vocabulary.

## Entity 1 — Resolved Workflow Configuration (consumed, not defined here)

The active deployment's workflow controls. Resolved by
`flask_backend/study_config.py`, exposed by `GET /api/config` as
`data.workflow`, fetched once by `App.jsx`, and held in its `workflow` state.

| Field | Type | Meaning | Conservative default |
|---|---|---|---|
| `scrubbing` | boolean | Is the `scrubbed` stage entered? | `true` |
| `screening` | boolean | Is the `screened` stage entered? | `true` |
| `sending` | boolean | Is the `sent` stage entered? | `true` |
| `reviewer_count` | integer (`1` or `2`) | Reviewers required to fully review an event | `2` |

This feature **reads** these fields; it never writes or resolves them. The
defaults shown are the values `App.jsx` initialises the `workflow` state to and
retains on a failed `/api/config` fetch (FR-010).

## Entity 2 — Workflow-Stage Section

A collapsible area of the Events Summary page representing one lifecycle
status. In code, one `<TableSection>` element in `EventViewAll.jsx`. Each has a
heading, a Show/Hide control, a queue (`endpoint`), and `renderActions`
buttons. There are eleven, in fixed render order.

### Section catalog and visibility rule

| Order | Section title | Visibility rule | Requirement |
|---|---|---|---|
| 1 | To Be Uploaded | Always visible | FR-006 |
| 2 | To Be Scrubbed | Visible unless `scrubbing === false` | FR-002 |
| 3 | To Be Screened | Visible unless `screening === false` | FR-003 |
| 4 | To Be Assigned | Always visible | FR-006 |
| 5 | To Be Sent | Visible unless `sending === false` | FR-004 |
| 6 | Not Yet Reviewed | Always visible | FR-006 |
| 7 | Third Review Needed | Visible unless `reviewer_count === 1` | FR-005 |
| 8 | Third Reviewer Assigned | Visible unless `reviewer_count === 1` | FR-005 |
| 9 | All Done | Always visible | FR-006 |
| 10 | No Packet Available | Always visible | FR-006 |
| 11 | Rejected | Always visible | FR-006 |

**Order note**: The render order matches the canonical event lifecycle from the constitution — `uploaded → scrubbed → screened → assigned → sent → reviewer*_done → (third_review_*) → done` — followed by the terminal queues (No Packet Available, Rejected). In the Scans configuration with all stage gates off, the visible six collapse to Uploaded → Assigned → Not Yet Reviewed → All Done → No Packet Available → Rejected — the same lifecycle, with the bypassed stages removed.

**Render-order invariant**: visible sections keep this relative order; gating
is purely subtractive — a hidden section leaves no gap, no placeholder, and
never reorders the survivors (FR-008).

**Atomicity invariant**: a section is rendered whole or not at all. When
hidden, its heading, Show/Hide toggle, queue, and every `renderActions` button
are absent with it (FR-009) — automatic, because they are descendants of the
`TableSection` element.

### State transitions

A section has no lifecycle of its own. Its visibility is a pure function of the
Resolved Workflow Configuration, recomputed on every render:

```text
visible(section) =
    section is in {1,4,6,9,10,11}                      → true   (always)
    section is "To Be Scrubbed"                        → workflow.scrubbing !== false
    section is "To Be Screened"                        → workflow.screening !== false
    section is "To Be Sent"                            → workflow.sending  !== false
    section is "Third Review Needed" / "…Assigned"     → Number(workflow.reviewer_count) !== 1
```

The `!== false` / `!== 1` form means an unresolved, missing, or malformed
control resolves to *visible* — the conservative default (FR-010).

## Entity 3 — Event Status (context only — unchanged)

The lifecycle state of an event (`created`, `uploaded`, `scrubbed`, `screened`,
`assigned`, `sent`, `reviewer1_done`, …, `done`, `rejected`,
`no_packet_available`, etc.). Each gated section corresponds to one status
queue. This feature does **not** change the status enum, the state machine, or
any status value — bypassed-stage statuses remain defined; the page simply
stops surfacing the queues a given deployment can never populate (Constitution
Principle V — "not entered" ≠ "deleted").

## Out of scope (explicitly unchanged)

- **Event Status Summary count table** — data-driven from
  `GET /api/events/status_summary`; reports actual stored counts and is **not**
  gated by workflow configuration (FR-011).
- **`GET /api/config` and `study_config.py`** — consumed as-is; no field added,
  removed, or renamed.
- **`studies/vte/EventViewAll.jsx`** — the legacy VTE-forked page is untouched.
