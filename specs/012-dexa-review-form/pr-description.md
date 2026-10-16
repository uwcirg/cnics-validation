# 012: DEXA scan review form and on-site reviewer instructions

Spec: `specs/012-dexa-review-form/` (spec, plan, research, data model, contracts, quickstart, tasks).

## What this does

- **Scans review form.** On a `STUDY_TYPE=scans` deployment, the review page now shows the DEXA form instead of the MI one. The reviewer records each body region in the packet, picked from a fixed list with "Other: please specify" for anything else. Each region has an optional laterality and optional T and Z scores. Regions can be added, changed, removed, and repeated, up to 20. Blank values are stored as "not provided", never as 0. Submitting completes the event.
- **Per-study `reviews` schema.** As in the legacy system, each study has its own `reviews` table:
  - `scans`: `reviews` (one row per review) + `review_regions` (one row per region).
  - `mci`: its existing tables, unchanged.
  - The models package now works: `flask_backend/models.py` became `flask_backend/models/__init__.py`, which makes `flask_backend/models/studies/<study>.py` importable. A resolver keyed on `STUDY_TYPE` picks the study module.
  - The review POST, a new review GET, and the events export all delegate to that module.
- **Per-study DDL in `init/studies/`.** `init/04-study-schema.sh` applies only the deployment's study file, so a database never holds another study's tables. The dormant `02-schema-{vte,afib,cva,hf}.sql` files moved there too, marked `-- DORMANT`.
- **On-site reviewer instructions.** `app/webroot/files/instructions/scans.html` is a print-friendly page that opens in a new tab from the home page and the review page. The MCI reviewer `.doc`/`.pdf` links are removed, and MCI has no on-site content yet.
- **Duplicate-submission guard (FR-024).** A reviewer's second submission for the same event now returns 409 and stores nothing.
- **Constitution v1.5.0 / v1.5.1** (Principle IV): study DDL lives in `init/studies/<study>.sql`; each database holds only its own study's tables; only `reviews` is mandatory per study; the `reviews(id, event_id, reviewer_id)` invariant; the dormancy marker; study components go in `frontend/src/studies/<study>/`.

## Studies affected

- **All studies:**
  - models package move;
  - review POST and events export delegation (MI output byte-identical, pinned by `test_mci_export_golden.py`);
  - 409 guard;
  - Step 1 label is now "Download charts for <event>";
  - review-instructions links;
  - mariadb receives `STUDY_TYPE`.
- **`mci`:** reviewer-instruction `.doc`/`.pdf` links removed. The home page "Review Instructions:" box now shows only its header, and the review page hides its box.
- **`scans`:** new schema, study module, form, and instructions.
- **Dormant studies** (`vte`, `afib`, `cva`, `hf`) and unknown values fall back to `mci` at every layer, with a warning. This is today's behavior.

**Will this break any other study's deployment?** Not for `mci`: its tables, data, export output, and review form are unchanged; the only visible changes are listed above. **Existing `scans` deployments must run the migration below**, or review submission and export return a 503 naming the script.

## Deploying (operators)

See `specs/012-dexa-review-form/quickstart.md`. In short:

1. `docker compose up -d --build`. This recreates mariadb so it picks up `STUDY_TYPE`; the data volume is kept.
2. **Existing scans databases only:**
   ```bash
   docker compose exec -T mariadb bash -s < init/migrations/012-scans-reviews.sh
   docker compose restart backend
   ```
   The script renames the MI-shaped `reviews` and `event_derived_datas` to `*_mi_pre012`. It never drops them, and it is idempotent.
3. Run quickstart manual script §§ A–D.

## Observed behavior recorded before changing it (Principle VI)

- `models.py` shadowed `models/`, so `models/studies/*` could not be imported (research D4).
- Every database received the MI `reviews`/`event_derived_datas` tables, including `scans`. The `Events.reviews` and `Events.derived_data` relationships were unused (D1, D10).
- `POST /api/events/<id>/review` accepted repeat submissions (D5).
- The events export SQL read MI review columns, so it would fail on a non-MI `reviews` table (D9).
- `init/` runs only when a volume is first created (D10).

## Notes

- `GET /files/<path>` (which serves `instructions/scans.html`) has no Flask auth decorator and relies on the Apache basic+ldap gate. That is unchanged; the file contains no PHI.
- `flask_backend/models2.py` is unused and still models the MI review tables as shared. It is flagged in README.md as a removal candidate.
- The untracked repo-root `DEXA.scans.review.instructions.md` is superseded by `instructions/scans.html`. Delete it or keep it as you prefer.
- The DEXA instructions are copied verbatim from the source document, including "specific vertebrate" in note 5 (presumably "vertebra").

## Rev. 4: dev-deployment feedback (spec FR-025 – FR-033)

After the first dev deployment, the reviewer flow, the event page, and role
access were corrected. **No schema change and no migration.**

- **Instructions page was blank.** The Vite dev server proxied only `/api`, so
  `/files/*` fell through to the SPA. `/files` is now proxied too, which also
  fixes the MCI `.doc`/`.pdf` links on dev-server deployments (FR-032, D12).
  **Deployment caveat:** anything serving the frontend without the Vite dev
  server (the Dockerfile's `serve` stage, or another proxy) must route
  `/files` to the backend, as it does `/api`.
- **"Review Events" menu link** (`/events/review` with no `event_id`) showed
  "SCANS null" and a form. It now shows the reviewer's queue, the same
  component as the home page's "Events to Review" (FR-025, D13). Row clicks
  in that table now open the review page; they used to go to the upload page.
- **Already-reviewed events** show "You have already reviewed this event
  (submitted <date>)" instead of a form that could only be refused, for
  every study (FR-029, D15).
- **`/events/edit`** shows admins each submitted review's answers in a
  "Review N" row, rendered by a per-study summary component (FR-027, D17).
- **Styling.** The review page's "Review Instructions:" box is the legacy tan
  box, floated top right. H1 is 1.6em site-wide (was 3.2em) (FR-030, FR-031).

### Behavior changes (all studies)

- The home page's **"Events to Review" now lists only the signed-in reviewer's
  own unreviewed events**. Before, it listed every review-eligible event.
  Admins are unchanged (D14).
- **Review answers are served to admins only.** Reviewers no longer receive
  any review content, including other reviewers' answers on two-reviewer
  studies, which they could fetch before (D16.1).
- **Reviewer-only users lose `/events/edit`**, and can read event details
  only for events they are assigned to review (FR-028, D16).
- **Scrub, screen and `/events/viewAll` are admin-only**, including the
  "View All Events" menu link and the screen and scrubbed-upload APIs. On
  `mci`, **non-admin scrubbers and screeners lose access** (FR-033, D19).
- **`/api/tables/<name>`** had a SQL injection: the table name was
  interpolated into the query unchecked, for any signed-in user. Names must
  now be plain identifiers (else 404), and this study's review tables are
  refused to non-admins (D16.5).
- **Explicit role decorators**: `/api/tables/<name>` and `/api/events/<id>`
  now declare their roles, as the constitution requires. A signed-in user
  with no role gets 403 (D20).

**Will this break any other study's deployment?** On `mci`, non-admin users
lose scrubbing, screening, `/events/viewAll`, and (reviewer-only) the event
page. That is intended (FR-033, FR-028). MI review submission, the MI form,
and the export are unchanged.

### Follow-ups (not changed here)

- `/api/tables/<name>` still serves `users`, `criterias`, `solicitations`,
  and `patients_view` to any role. It needs a full allowlist with per-table
  roles (D16.5).
- `GET /api/events/by_status/<status>` remains open to reviewers and
  uploaders, because `/events/assignThird` (which still allows reviewers)
  reads it (D19).
- `GET /api/events/download/<id>` (packet download) checks sign-in only.
- `need_packets` and `need_reupload` allow reviewers.

### Observed behavior recorded before changing it (Principle VI)

Research D12 – D20: the `/api`-only proxy; `.boxright` with no CSS; the
unscoped `for_review`; row clicks going to `/events/upload`; open event-details
and tables endpoints; review content readable by any assigned reviewer; and
reviewer use of scrub, screen and view-all.

## Verification

- `pytest flask_backend/tests`: 309 passed (rev. 4 adds
  `test_review_access.py`; two existing GET-review tests and one anonymous
  `/api/tables` test were updated for the new access rules). The 209-test
  baseline captured before the refactor still passes in full.
- `cd frontend && npm run build`: OK. `npm run lint`: 31 problems, the same
  count as before this PR. None was introduced; a few pre-existing ones sit
  in touched files (`Home.jsx`'s `statusSummary` and hook warning, which
  moved with `TableWrapper` into `ReviewQueue.jsx`; `MenuBar.jsx`'s
  `third_reviewer`; `EventEdit.jsx`'s `status`).
- Frontend behavior has **not** been exercised. It is covered by quickstart
  §§ A–D, run on a deployment.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
