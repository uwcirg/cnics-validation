# Phase 1 Data Model: Study-aware home review sections

No persisted data, no schema change. The "data model" here is the in-memory shape of the per-study guidance content held by the new `reviewGuidance.js` module. It is static configuration, not stored state.

## Entity: GuidanceContent (per study type)

The content for one study's two home-page boxes.

| Field | Type | Description |
|-------|------|-------------|
| `packets` | `Box` | Content for the "Review packets should contain:" box. |
| `instructions` | `Box` | Content for the "Review Instructions:" box. |

The two `h3` headers are NOT part of this entity — they are constant in `Home.jsx` and identical across all studies (FR-001). Only the body content varies.

## Entity: Box

The body content of a single guidance box.

| Field | Type | Description |
|-------|------|-------------|
| `items` | `string[]` | Ordered display lines for the box (e.g. the eight MI checklist items, or the two scans lines). May be empty. |
| `links` | `Link[]` | Optional downloadable/viewable files for the box. Empty array ⇒ the box renders no link area at all (FR-004). |
| `linkLabel` | `string` (optional) | The lead-in label shown before the links (e.g. "Full instructions:" for packets, "View as:" for instructions). Omitted/ignored when `links` is empty. |

## Entity: Link

One file link within a box.

| Field | Type | Description |
|-------|------|-------------|
| `label` | `string` | Visible link text (e.g. ".doc", ".pdf"). |
| `href` | `string` | Target path under the existing files route (e.g. `/files/CNICS MI reviewer instructions.pdf`). |
| `download` | `boolean` | `true` ⇒ download attribute (the `.doc` behavior today); `false` ⇒ open in new tab (the `.pdf` behavior today). |

## Resolution rule

`resolveReviewGuidance(studyType) -> GuidanceContent`:

1. Normalize `studyType` (trim, lowercase).
2. If a defined entry exists for it (`mci`, `scans`), return that entry.
3. Otherwise return the `mci` entry (fallback — FR-008).

This mirrors the fallback philosophy of `studyTitle.js`.

## Defined instances

### `mci` (also the fallback)

- **packets**:
  - `items`: the existing eight lines — physician's notes closest to potential Event date; outpatient cardiology consultations; in-patient cardiology notes or consults; baseline ECG; first 2 ECGs after admission or in-hospital event; related procedure and diagnostic test results; related laboratory evidence; please redact the personal identifiers including name, birthday, and hospital number.
  - `linkLabel`: "Full instructions:"
  - `links`:
    - `.doc` → `/files/CNICS MI Review packet assembly instructions.doc` (download: true)
    - `.pdf` → `/files/CNICS MI Review packet assembly instructions.pdf` (download: false)
- **instructions**:
  - `items`: [] (the box currently shows only the links)
  - `linkLabel`: "View as:"
  - `links`:
    - `.doc` → `/files/CNICS MI reviewer instructions.doc` (download: true)
    - `.pdf` → `/files/CNICS MI reviewer instructions.pdf` (download: false)

### `scans`

- **packets**:
  - `items`: ["DEXA scan reports", "Please redact their PHI (names, birthdate, etc)"]
  - `links`: [] (no link area rendered)
- **instructions**:
  - `items`: ["No additional instructions"]
  - `links`: [] (no link area rendered)

## Validation rules (from requirements)

- Headers are constant and not data-driven (FR-001) — enforced by keeping them literal in `Home.jsx`.
- Empty `links` ⇒ no link area, no orphaned label (FR-004).
- Unknown study ⇒ `mci` content (FR-008).
- Content is selected by study type only (FR-002); no other input affects it.
