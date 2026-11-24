# PR: 014-packet-replace-review-edit

## Baseline

Recorded before any change (T001), on commit `9182d7b` (= `origin/main`).

- **Backend:** `python3 -m pytest flask_backend/tests` → 385 passed, 0 failed.
- **Frontend build:** `npm run build` → succeeds.
- **Frontend lint:** `npm run lint` → 31 problems (25 errors, 6 warnings), all pre-existing, in
  `components/DevAuthTester.jsx`, `components/MenuBar.jsx`, `components/review/ReviewQueue.jsx`,
  `pages/EventDownload.jsx`, `pages/EventEdit.jsx` (1), `pages/EventUpload.jsx` (4),
  `pages/EventViewAll.jsx`, `pages/Home.jsx`, and five files under `studies/vte/`.
  This feature must not add to that count.
