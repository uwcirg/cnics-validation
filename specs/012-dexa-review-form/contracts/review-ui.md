# Contract: Review page and instructions document UI

**Feature**: 012-dexa-review-form (rev. 2)

## `/events/review?event_id=<id>` — shell (all studies)

```text
┌──────────────────────────────────────────┐  ┌───────────────────────────┐
│ Review event: SCANS 4821                 │  │ Review Instructions:      │  ← only if the study has
│ Date: 2019-03-02                         │  │ View review instructions ↗│    instructions (FR-021/022)
│                                          │  └───────────────────────────┘
│ Step 1: Review Charts                    │
│ Review the packet for this event:        │
│  • [Download charts for SCANS 4821]      │  ← label changed for all studies (research D11)
│                                          │
│ <form from reviewForms.js[review_form]>  │  ← rendered only after config resolves;
└──────────────────────────────────────────┘    "mci" → existing MI form, unchanged
```

## Scans form (`review_form: "scans"`): states

**S0 — no regions yet**

```text
Step 2: Enter Body Region
┌───────────────────────────────┬──────────────────────────┐
│ Body region                   │ [ Select…            ▾ ] │
└───────────────────────────────┴──────────────────────────┘
(no Submit button)                                            ← FR-012
```

**S1+ — one or more regions** (uses the existing `#reviewForm` table style)

```text
Step 2: Enter Body Region
┌─ Region 1 ────────────────────┬──────────────────────────┐
│ Body region                   │ [ Lumbar spine       ▾ ] │  [Remove]
│ Laterality (optional)         │ [                    ▾ ] │
│ T score                       │ [ -1.1     ]             │
│ Z score                       │ [          ]             │
├─ Region 2 ────────────────────┼──────────────────────────┤
│ Body region                   │ [ Other: please spec ▾ ] │  [Remove]
│ Please specify                │ [ Total body           ] │  ← only when Other (FR-005)
│ Laterality (optional)         │ [                    ▾ ] │
│ T score                       │ [ 2.0      ]  ⚠ message  │  ← per-field error
│ Z score                       │ [          ]             │
└───────────────────────────────┴──────────────────────────┘

Step 3: Is there another body region? If not, click "Submit".
┌───────────────────────────────┬──────────────────────────┐
│ Another body region           │ [ Select…            ▾ ] │  ← choosing appends Region N+1
└───────────────────────────────┴──────────────────────────┘
[ Submit ]
```

| # | Trigger | Result |
|---|---|---|
| U1 | Pick a region in S0, or in the Step 3 dropdown | A new entry is appended. The Step 3 dropdown resets to "Select…". Focus moves to the new entry's Laterality |
| U2 | Change an entry's region | Only the region changes. Laterality and scores are kept. The Other text shows or hides (FR-011) |
| U3 | Remove | That entry is removed and the others keep their values. Removing the last entry returns to S0 |
| U4 | Type a score | Validated on blur and on Submit. An invalid field shows an inline message and `aria-invalid="true"` |
| U5 | Submit with client errors | No request is sent. The first invalid field is focused |
| U6 | Submit, valid | The button is disabled and reads "Submitting…". On 200: "Review submitted. Event status: done." and the form becomes read-only. On 400 with `fields`: the server messages map onto the fields. On 409/403/other: the error text is shown in the existing message area |
| U7 | 8+ entries | One column, no horizontal scroll (SC-003) |

## Instructions document: `/files/instructions/<study>.html`

- It is a self-contained HTML document with its own `<title>` ("DEXA Chart
  Review Instructions") and an inline `<style>`. It has no site banner or
  menu.
- It is opened through `GuidanceLinks` with `target="_blank" rel="noopener"`
  (FR-019), from the review-page box and from the home-page "Review
  Instructions:" box.
- Structure: `<h1>`, the intro paragraph, `<ol>` steps 1–3 with a nested
  `<ol type="a">` for a–d, then "Notes:" followed by `<ol>` notes 1–6. The
  order is the same as in the source document (FR-018).
- Screen: readable max-width of about 48rem, centered.
- Print (`@media print`): full width, about 11pt, black on white, no link
  decoration, `li { page-break-inside: avoid }`. The whole document fits on
  2 pages or fewer (SC-006).

## Home page "Review Instructions:" box

| Study | Box body |
|---|---|
| `scans` | link "View review instructions" → `/files/instructions/scans.html` (new tab) |
| `mci` | header only (`.doc`/`.pdf` links removed, FR-017a) |
