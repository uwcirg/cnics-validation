# Quickstart: Study-aware home review sections

## What this feature does

The two boxes at the bottom of the home page — **"Review packets should contain:"** and **"Review Instructions:"** — now show content chosen by the deployment's `STUDY_TYPE`. The headers never change; only the body items and any `.doc`/`.pdf` links do. `mci` keeps today's content; `scans` shows DEXA-specific guidance with no links.

## Files involved

- `frontend/src/components/reviewGuidance.js` — **new** pure module: `resolveReviewGuidance(studyType)` → `{ packets, instructions }`, `mci` fallback. Sibling of `studyTitle.js`.
- `frontend/src/pages/Home.jsx` — **edit**: replace the two hard-coded boxes (lines ~225–262) with a renderer driven by `resolveReviewGuidance(studyType)`; keep the `<h3>` headers literal.
- `frontend/src/App.jsx` — **edit**: pass the already-fetched `studyType` into `<Home>` and gate the boxes until config resolves (no content flash).

No backend, schema, dependency, or `openapi.json` change.

## How content is selected

`STUDY_TYPE` (env) → `/api/config` `data.study_type` → `App.jsx` state → `Home` prop → `resolveReviewGuidance(studyType)`. Unknown/unset study → `mci` content.

## Add guidance for a new study (P2)

Edit `reviewGuidance.js` and add an entry keyed by the study type, e.g.:

```js
vte: {
  packets: {
    items: ['…'],
    linkLabel: 'Full instructions:',
    links: [
      { label: '.doc', href: '/files/CNICS VTE Review packet assembly instructions.doc', download: true },
      { label: '.pdf', href: '/files/CNICS VTE Review packet assembly instructions.pdf', download: false },
    ],
  },
  instructions: { items: ['…'], linkLabel: 'View as:', links: [ /* … */ ] },
}
```

Drop any referenced `.doc`/`.pdf` into the files store served at `/files/<name>` (today `app/webroot/files/`, overridable via `FILES_DIR`). No layout or header edits needed.

## Verify

```bash
cd frontend && npm run build      # must compile clean
```

Then, with the app running (see project `/run`):

- `STUDY_TYPE=mci` (or unset) → both boxes look exactly like today, including the `.doc`/`.pdf` links.
- `STUDY_TYPE=scans` → packets box lists "DEXA scan reports" and "Please redact their PHI (names, birthdate, etc)"; instructions box says "No additional instructions"; **no** links in either box.
- Both cases → the two `h3` headers read "Review packets should contain:" and "Review Instructions:" unchanged, and no MI content flashes before scans content on a scans deployment.
