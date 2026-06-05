# UI Contract: Review-guidance module & rendering

This feature exposes no HTTP API. Its contracts are (a) the pure module interface `Home.jsx` depends on, and (b) the rendered-DOM contract reviewers observe. Both are stated here so `/speckit.tasks` and any verification step have testable expectations. No `openapi.json` change (no backend route/shape change).

## Module contract: `frontend/src/components/reviewGuidance.js`

```text
resolveReviewGuidance(studyType: string | null | undefined) -> GuidanceContent
```

- **Input**: a raw study type (e.g. `"scans"`, `"MCI"`, `""`, `null`).
- **Normalization**: trims and lowercases before matching (so `"  Scans "` resolves like `"scans"`).
- **Output**: a `GuidanceContent` object `{ packets: Box, instructions: Box }` (see data-model.md), never `null`.
- **Fallback**: any input with no defined entry — including `""`, `null`, `undefined`, and undefined studies such as `"vte"`/`"cva"` — returns the `mci` content.
- **Purity**: no side effects, no I/O, deterministic for a given input (mirrors `studyTitle.js`).

### Behavioral expectations (verification table)

| Input | `packets.items` | `packets.links` | `instructions.items` | `instructions.links` |
|-------|-----------------|-----------------|----------------------|----------------------|
| `"mci"` | 8 MI checklist lines | 2 (`.doc`,`.pdf` packet-assembly) | `[]` | 2 (`.doc`,`.pdf` reviewer-instructions) |
| `"scans"` | `["DEXA scan reports", "Please redact their PHI (names, birthdate, etc)"]` | `[]` | `["No additional instructions"]` | `[]` |
| `"MCI"` (mixed case) | same as `"mci"` | same as `"mci"` | same as `"mci"` | same as `"mci"` |
| `""` / `null` / `undefined` | same as `"mci"` (fallback) | same as `"mci"` | same as `"mci"` | same as `"mci"` |
| `"vte"` (undefined here) | same as `"mci"` (fallback) | same as `"mci"` | same as `"mci"` | same as `"mci"` |

## Rendered-DOM contract: `Home.jsx` bottom boxes

For the resolved `GuidanceContent`, `Home.jsx` MUST render:

1. Exactly two `.infobox` blocks near the bottom of the page, in order: packets then instructions.
2. The packets box `<h3>` text is exactly `Review packets should contain:` and the instructions box `<h3>` text is exactly `Review Instructions:` — for **every** study type (FR-001, FR-003 headers constant).
3. Each box's `items` rendered as the list lines (packets keeps its current ordered-list style).
4. Each box's `links`:
   - When non-empty: render `linkLabel` followed by each link as an `<a>` with the given `href`; `.doc`-style links carry `download`, `.pdf`-style links open in a new tab — matching today's MI behavior.
   - When empty: render **no** label and **no** link element for that box (FR-004) — no empty "Full instructions:" / "View as:" text.
5. The boxes are rendered only after the study type has resolved, so no other study's content is shown first (FR-010).

### Acceptance mapping

| Spec acceptance | Contract check |
|-----------------|----------------|
| US1 #1 (scans packet items) | `scans` row of the table + DOM check items |
| US1 #2 (scans "No additional instructions") | `scans` instructions items |
| US1 #3 (scans no links) | `scans` links empty ⇒ no link area |
| US1 #4 / US2 (headers constant; mci unchanged) | DOM contract #2 + `mci` row |
| US2 #2/#3 (mci links present & correct targets) | `mci` row link hrefs |
| US3 (new study renders defined content) | module returns the new entry; DOM renders it |
| FR-008 fallback | fallback rows of the table |
| FR-010 no flash | DOM contract #5 |

## Verification method

No frontend unit-test runner is configured (consistent with `studyTitle.js` shipping testless). Verify by:

1. `cd frontend && npm run build` — must compile clean.
2. Manual/`/run` check under `STUDY_TYPE=mci` (boxes identical to current page) and `STUDY_TYPE=scans` (DEXA items + "No additional instructions", no links), per the constitution's "verified under at least one STUDY_TYPE" rule.
