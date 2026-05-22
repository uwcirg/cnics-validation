# UI Contract: Events Summary section visibility

**Feature**: 006-study-aware-event-actions | **Date**: 2026-05-22

This feature adds **no API, route, or schema**. Its only external interface is
what the `/events/viewAll` page renders. This document is that contract: the
observable mapping from the resolved workflow configuration to the set of
visible page sections. It is the reference for verification (`quickstart.md`)
and for `/speckit.tasks`.

## Consumed contract (existing — not modified)

`GET /api/config` → `200`:

```json
{
  "data": {
    "study_type": "scans",
    "workflow": {
      "scrubbing": false,
      "screening": false,
      "sending": false,
      "reviewer_count": 1
    }
  }
}
```

`App.jsx` fetches this once, stores `data.workflow` in its `workflow` state
(initialised to the full-workflow default `{scrubbing:true, screening:true,
sending:true, reviewer_count:2}`, retained on fetch failure), and passes that
object to `<EventViewAll>` as the `workflow` prop.

## Rendering contract

**Input**: the `workflow` prop — `{scrubbing, screening, sending,
reviewer_count}` — or an absent/partial object.

**Output**: the set and order of `<TableSection>`s rendered on `/events/viewAll`.

### Rules

| ID | Given | Then |
|----|-------|------|
| R1 | `workflow.scrubbing === false` | "To Be Scrubbed" is **not** rendered |
| R2 | `workflow.screening === false` | "To Be Screened" is **not** rendered |
| R3 | `workflow.sending === false` | "To Be Sent" is **not** rendered |
| R4 | `Number(workflow.reviewer_count) === 1` | neither "Third Review Needed" nor "Third Reviewer Assigned" is rendered |
| R5 | a gated control is `true` / `> 1` / missing / malformed | its section(s) **are** rendered (conservative default) |
| R6 | any configuration | "To Be Uploaded", "Not Yet Reviewed", "To Be Assigned", "All Done", "No Packet Available", "Rejected" are rendered |
| R7 | any configuration | visible sections appear in the fixed order 1→11 (gaps closed, order preserved) |
| R8 | a section is not rendered | its heading, Show/Hide toggle, queue, and all `renderActions` buttons are absent with it |
| R9 | any configuration | the "Event Status Summary" count table renders unchanged, gated by nothing |

### Worked examples

**Full-workflow deployment** — `{scrubbing:true, screening:true, sending:true,
reviewer_count:2}` (also: config not yet resolved / fetch failed):

```
Event Status Summary  (count table)
1. To Be Uploaded            6. Not Yet Reviewed
2. To Be Scrubbed            7. Third Review Needed
3. To Be Screened            8. Third Reviewer Assigned
4. To Be Assigned            9. All Done
5. To Be Sent               10. No Packet Available
                            11. Rejected
```
→ **11 sections** (SC-002). Order matches the canonical event lifecycle
(`uploaded → scrubbed → screened → assigned → sent → reviewer*_done →
(third_review_*) → done`).

**Scans deployment** — `{scrubbing:false, screening:false, sending:false,
reviewer_count:1}`:

```
Event Status Summary  (count table)
1. To Be Uploaded            (To Be Sent          — hidden, R3)
(To Be Scrubbed — hidden, R1) 6. Not Yet Reviewed
(To Be Screened — hidden, R2) (Third Review Needed  — hidden, R4)
4. To Be Assigned            (Third Reviewer Assigned — hidden, R4)
                             9. All Done
                            10. No Packet Available
                            11. Rejected
```
→ **6 sections** in order: To Be Uploaded, To Be Assigned, Not Yet Reviewed,
All Done, No Packet Available, Rejected (SC-001). The lifecycle collapses to
Uploaded → Assigned → Not Yet Reviewed → Done with the bypassed stages
removed.

**Screening-only deployment** — `{scrubbing:false, screening:true,
sending:false, reviewer_count:2}` (illustrates FR-007 — no study name is
consulted; any flag combination is valid):

→ **9 sections**: the 6 always-on, plus "To Be Screened", "Third Review
Needed", "Third Reviewer Assigned".

## Non-goals (contract explicitly does NOT change)

- Section content, columns, queue endpoints, `renderActions` targets, or the
  Show/Hide behavior of any section that *is* rendered.
- The "Event Status Summary" count table (FR-011).
- Any backend response shape, route, or the `events.status` enum.
- The legacy `/vte/viewAll` page.
