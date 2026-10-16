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

---

# Rev. 4 (2026-10-16): post-deployment feedback

## `/events/review` with no `event_id`: review queue (FR-025; research D13)

```text
Events to Review
Events with packets ready for review.
[Search this table]            [site filter]
┌────┬────────────┬────────────┬──────────┬──────┬─────────┐
│ ID │ Patient ID │ Date       │ Criteria │ Site │         │
│5848│ …          │ 2026-05-01 │ …        │ …    │[review] │  ← row click or button →
└────┴────────────┴────────────┴──────────┴──────┴─────────┘     /events/review?event_id=5848
(empty → "No events are awaiting review.")
```

- No "Review event:" heading, no Step 1, no form, no instructions box.
- The same component, `components/review/ReviewQueue.jsx`, renders the home
  page's "Events to Review" section.
- Rows: `/api/events/for_review`, scoped to the caller (contract
  review-api, rev. 4).

## `/events/review?event_id=<id>` — shell states (FR-029; research D15)

| `GET …/review` result | Below Step 1 |
|---|---|
| pending | nothing (no form flash) |
| `mine` is set | "You have already reviewed this event (submitted 2026-10-16)." No form, no Submit |
| `mine` is `null` | the study's form, as before |
| 403 / error | the existing error message, no form |

This applies to all studies, since the shell owns it.

## Shell layout (FR-030, FR-031; research D18)

```text
┌──────────────────────────────────────────┐ ╭───────────────────────────╮
│ Review event: SCANS 5848   (h1 ≈ 1.6em)  │ │ Review Instructions:      │  .boxright: float right,
│ Date: 2026-05-01                         │ │ View review instructions ↗│  300px, tan #f2e8c2,
│ Step 1: Review Charts …                  │ ╰───────────────────────────╯  border #f1d676, radius 12px
```

At ≤ 640px the box stops floating and stacks above the heading at full
width. The `h1` size applies site-wide.

## `/events/edit?event_id=<id>` (FR-027, FR-028; research D16, D17)

- Route roles: `uploader`, `admin`. A user with only `reviewer` gets the
  standard Access Denied.
- For **admins**, each "Review N Date" row is followed by:

```text
│ Review 1 Date │ 2026-10-16                                            │
│ Review 1      │ Lumbar spine — T: -1.10, Z: -0.40                      │  ← ScansReviewSummary
│               │ Hip — Left, T: -0.90                                   │
│               │ Other: Calcaneus — Right                               │
```

  `mci` uses the legacy lines ("MI: Probable", "Criteria: …", "Cardiac Cath:
  No") from `MciReviewSummary`. "Not provided" parts are omitted, never shown
  as 0.
- **Non-admins** (uploaders): unchanged. Dates and reviewer names, no
  "Review N" content rows, and no request for review content is made.
- Nothing on the page edits a review (FR-026).

## Admin-only pages (FR-033; research D19)

| Route | Roles (before → after) |
|---|---|
| `/events/scrub` | reviewer, uploader, admin → **admin** |
| `/events/screen` | reviewer, admin → **admin** |
| `/events/viewAll` | reviewer, uploader, admin → **admin** |
| `/events/edit` | reviewer, uploader, admin → **uploader, admin** (FR-028) |

The MenuBar shows "View All Events" to admins only. Non-admins who follow an
old link get the standard Access Denied page.
