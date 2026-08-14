# Contract: Upload page UI states

**Feature**: `008-upload-event-identifiers`
**Surface**: `/events/upload` — `frontend/src/pages/EventUpload.jsx`

The page has one existing behavior (no `event_id` → browse a list) and, for a
named event, four mutually exclusive states. Today only one exists, and it
renders whatever the address bar happened to carry.

## State machine

```text
                    ┌─────────────────────────────────────┐
   no event_id ────▶│ BROWSE — events-needing-packets list │  (unchanged)
                    └─────────────────────────────────────┘

   event_id present
        │
        ▼
   ┌──────────┐  fetch ok + 3 required present   ┌──────────┐
   │ LOADING  │──────────────────────────────────▶│ VERIFIED │  upload enabled
   └──────────┘                                   └──────────┘
        │
        ├── 404 ─────────────▶ ┌───────────┐
        ├── 403 ─────────────▶ │ NOT       │  upload withheld
        │                      │ VERIFIED  │
        ├── required field ───▶ └───────────┘
        │   missing/null
        │
        └── network / 500 ───▶ ┌───────────┐
                               │ UNAVAIL.  │  upload withheld + retry
                               └───────────┘
```

## States

### BROWSE — no `event_id` in the address

Unchanged (FR-013). The events-needing-packets table renders exactly as today.
Row and action-button links are simplified per D4 but target the same page.

### LOADING — request in flight

No identifying values shown, and **no upload control**. The form must not
appear before the values it exists to be checked against.

### VERIFIED — details retrieved, all three required identifiers present

The only state that accepts a packet.

| Field | Source | Empty rendering |
|---|---|---|
| Patient ID | `data.patient_id` | n/a — guaranteed present |
| Site Patient ID | `data.site_patient_id` | n/a — guaranteed present |
| Date | `data.event_date` (ISO) | n/a — guaranteed present |
| Criteria | `data.criteria[]` as `name: value` pairs | `—` when `[]` (FR-006a) |

Requirements met: FR-001, FR-002, FR-004, FR-005, FR-007, FR-008, FR-009,
FR-012.

- Patient ID and Site Patient ID carry **distinct labels** (FR-005). The page
  must not collapse them into one line the way `EventScrub.jsx:78` does.
- Criteria render as name-and-value pairs (FR-012), in response order, which
  the backend has already sorted (FR-008). The component must not re-sort.
- A criterion with an empty `value` shows its name with `—` in place of the
  value.
- Empty criteria never disable the upload control (FR-006b).

### NOT VERIFIED — 404, 403, or a required identifier missing/null

Message stating the event could not be verified. **No identifying fields
rendered as empty** (FR-010) and **no upload control** (FR-011). A route back
to the events list is offered.

The three trigger conditions are deliberately collapsed into one user-facing
state: all three mean the same thing to an uploader — the event on screen is
not one they can check a packet against. The 403 wording follows however the
rest of the app reports insufficient access.

### UNAVAILABLE — network error or 500

Message that details are temporarily unavailable, **no upload control**, plus a
retry that re-issues the request in place. Success transitions to VERIFIED
without the uploader leaving the page (FR-011a).

## Invariants

1. The upload control exists **only** in VERIFIED. No other state renders a
   file input or submit button. (FR-011, SC-003a)
2. `event_id` is the only query parameter read. `patient_id`, `date`, and
   `criteria` are ignored if present in an old bookmark. (FR-003)
3. No identifying field is ever rendered blank, absent, or as the string
   `"undefined"` for an event that exists. (FR-006, SC-003)
4. The rendered values depend only on `event_id`, never on the navigation
   route taken. (FR-004, SC-002)

## Link changes (D4)

`patient_id`, `date`, and `criteria` are dropped from the constructed links;
`event_id` remains.

| Location | Current | After |
|---|---|---|
| `EventUpload.jsx:78` (row click) | `?event_id=…&patient_id=undefined&date=…&criteria=…` | `?event_id=…` |
| `EventUpload.jsx:201` (action button) | `?event_id=…` | unchanged |
| `EventReupload.jsx:68` (row click) | `?event_id=…&patient_id=undefined&date=…&criteria=…` | `?event_id=…` |

All three converge on one URL shape, which is what makes SC-002 testable.
