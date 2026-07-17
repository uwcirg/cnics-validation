# Quickstart: Legacy-style Top Banner

**Feature**: 005-banner-restyle | **Date**: 2026-05-22

How to build and verify the banner. The frontend has no test runner, so
verification is manual (Constitution Principle VI) against the legacy reference
screenshot.

## Scope at a glance

Frontend-only. Files touched:

- `frontend/src/components/studyTitle.js` — **new** — `formatStudyTitle` helper
- `frontend/src/components/BaseLayout.jsx` — render logo + title + login strip
- `frontend/src/components/BaseLayout.css` — legacy-faithful banner styling
- `frontend/src/App.jsx` — pass the already-fetched `study_type` into `BaseLayout`

No backend, schema, or API change.

## Reference screenshots

- Target (legacy): `CVA.Screenshot 2026-05-21 103208.jpg` (repo root)
- Before (current): `Screenshot 2026-05-21 105013.events.viewAll.ScansMode.jpg`

## Build / lint

```bash
cd frontend
npm install        # if not already installed
npm run lint       # MUST be clean
npm run build      # MUST succeed
npm run dev        # local preview (needs the backend running for /api/config)
```

## Verifying the study title across studies

The title comes from `STUDY_TYPE`, surfaced by `GET /api/config`. To see each
case, run the backend with that env var set (Docker Compose `.env`, or the
process environment for a local Flask run):

| `STUDY_TYPE` | Expected banner title |
|--------------|-----------------------|
| `cva`        | `CVA Project`         |
| `mci`        | `MCI Project`         |
| `scans`      | `Scans Project`       |
| (unset)      | `MCI Project` (backend defaults `STUDY_TYPE` to `mci`) |

For a pure-frontend check of the rule without a backend, exercise
`formatStudyTitle` directly (Node REPL or browser console):

```js
import { formatStudyTitle } from './src/components/studyTitle.js'
formatStudyTitle('cva')    // 'CVA Project'
formatStudyTitle('scans')  // 'Scans Project'
formatStudyTitle('  mci ') // 'MCI Project'
formatStudyTitle('')       // null
formatStudyTitle(undefined)// null
```

## Acceptance walkthrough

Run against the contract checks in `contracts/banner-ui.md` (C1–C10):

1. **Study title (C1–C3, C5)** — start the app for `cva`, `mci`, `scans`, and
   one unrecognized code; on each, confirm the banner title matches the table
   above and the casing rule.
2. **Config not resolved (C4)** — throttle or block `/api/config` in browser
   devtools and load a page; confirm the logo shows, the page is usable, and
   **no** `undefined` / placeholder title appears.
3. **Logo prominence (C10)** — open any page and compare side by side with
   `CVA.Screenshot 2026-05-21 103208.jpg`: logo prominent at top-left, study
   title the largest text beside it, logged-in line a subtle strip.
4. **Consistency (C6)** — navigate across several routes (Home, View All
   Events, Admin Tools, an upload page); confirm the identical banner on each.
5. **Logo load failure (C7)** — in devtools, block `/cnics_logo.png`; confirm
   the title and Log Out control stay visible and usable.
6. **Auth states (C8, C9)** — confirm the logged-in strip shows the user +
   Log Out when authenticated, and shows neither when not; logo + title show
   in both cases.

## Done when

- `npm run lint` and `npm run build` pass.
- All contract checks C1–C10 pass.
- Spec Success Criteria SC-001…SC-006 are satisfied.
- The banner is judged a faithful match to the legacy reference screenshot.
</content>
