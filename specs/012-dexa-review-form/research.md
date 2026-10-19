# Research: DEXA scan review form and on-site reviewer instructions

**Feature**: 012-dexa-review-form | **Date**: 2026-10-14 (rev. 3: per-study `reviews` schema, HTML instructions, all study DDL in `init/studies/`, constitution v1.5.0)

No `NEEDS CLARIFICATION` remained in the Technical Context. Each decision below
records what the code does today first, as Principle VI requires.

**Revision 2 inputs (user, 2026-10-14):**
1. Every study has a table named `reviews`, and its schema differs by study,
   following the legacy CakePHP approach. Study models belong in
   `flask_backend/models/studies/` (`scans.py`, and `mci.py`).
2. Instructions are kept as HTML, not Markdown.

**Naming rule (user, 2026-10-14)**: files and identifiers specific to the `scans`
study use `scans`, never `dexa` (e.g. `ScansReviewForm.jsx`,
`test_scans_review.py`, `review_form: "scans"`). "DEXA" stays only in
user-facing text.

---

## D1 — Per-study `reviews` table (replaces rev. 1's shared `review_regions`)

**Observed today**:
- `init/02-schema.sql` creates the MI-shaped `reviews` table for *every*
  deployment: `mci enum NOT NULL`, `cardiac_cath NOT NULL`, and so on. A
  `scans` database therefore has an MI `reviews` table.
- Only three places in shared code touch `reviews`: the `Reviews` model in
  `flask_backend/models.py`, `POST /api/events/<id>/review` (`app.py`
  ~L2501), and the MI export SQL in `table_service.get_events_export_rows()`
  (~L716, `LEFT JOIN reviews rv1/rv2/rv3` reading MI columns). `models2.py`
  has a copy, but nothing imports it unless `SQLA_MODELS=models2`.
- `Events.reviews` / `Reviews.event` ORM relationships are declared, but
  nothing reads them anywhere in the code.

**Decision**: Each study's database has its own `reviews` table, defined by
that study. One shared invariant applies to every study: **`reviews` has
`id`, `event_id`, and `reviewer_id`, with one row per reviewer per event.**
Shared code may rely only on those three columns. Everything else is
study-defined.

- `mci`: `reviews` stays exactly as it is today (legacy-compatible).
- `scans`: `reviews` holds `id`, `event_id`, `reviewer_id`, and `created`,
  one row per completed DEXA review. A child table, `review_regions`
  (`review_id` → `reviews.id`), holds one row per body-region entry.

**Rationale**: This is the user's chosen model, and Principle II's
one-database-per-deployment rule allows it. Keeping "one `reviews` row per
reviewer per event" in the `scans` schema keeps that meaning the same in
every study, so future shared checks such as "has this reviewer reviewed this
event?" or review counts work without change. The variable number of regions
needs a child table, since one `reviews` row cannot hold 1–N regions in fixed
columns.

**Alternatives rejected**:
- (a) `scans` `reviews` with one row per region: this would change the
  meaning of `reviews` for one study, so shared "one row per reviewer per
  event" logic would miscount.
- (b) rev. 1's shared additive `review_regions` with no `reviews` row: the
  user rejected it, and it leaves a misleading MI `reviews` table in `scans`
  databases.

## D2 — Column types

**Decision** (scans): `review_regions.region` is `VARCHAR(40) NOT NULL`,
holding the dropdown label, with the allowed list checked in code.
`region_other` is `VARCHAR(100) NULL`. `laterality` is
`ENUM('Left','Right','Both','Unspecified') NULL`. `t_score` and `z_score`
are `DECIMAL(4,2) NULL`. `position` is `TINYINT UNSIGNED`. `NULL` means
"not provided" (FR-006).

**Rationale**: The instructions say frequently used "Other" regions will be
added to the list. With `VARCHAR` plus a code list, each addition is a code
change, where a region `ENUM` would need an `ALTER` on every deployment.
`DECIMAL` stores `-1.1` exactly, which `FLOAT` cannot.

## D3 — Score input rules

The decision is unchanged from rev. 1.

**Decision**: A score is optional. If present (after trimming), it must match
`^[+-]?(\d{1,2}(\.\d{0,2})?|\.\d{1,2})$` and fall within −10 ≤ x ≤ 10. The
frontend uses a text input with `inputMode="decimal"`, not `type="number"`,
and sends the trimmed string. The backend parses it with `Decimal` and checks
the same rules again (FR-014).

**Rationale**: With `type="number"`, browsers silently report invalid text as
empty, so a typo would look like a blank, which violates FR-006/FR-007.
Sending strings means no float conversion happens in transit. A third
decimal place is rejected rather than rounded.

## D4 — Bringing `flask_backend/models/studies/` online

**Observed today** (recorded in `docs/template-setup-guide.md` and in
project memory):
- `flask_backend/models.py` *shadows* the `flask_backend/models/` directory.
  That directory has no `__init__.py`, so it is only a namespace package, and
  a plain module wins over it. As a result,
  `import flask_backend.models.studies.vte` fails.
- `models/studies/{vte,afib,cva,hf}.py` are wrapped in triple-quoted strings,
  so they do nothing. They import `from ..base import Base`, which does not
  exist.
- `flask_backend/__init__.py` can switch `models` to `models2` through
  `SQLA_MODELS`. That option is unused, according to the README.

**Decision**:
1. **Convert `models.py` into a package.** Move it, with history kept
   (`git mv`), to `flask_backend/models/__init__.py`, and remove `Reviews`
   from it. A regular package wins over a same-named module, so every
   existing `from flask_backend import models` / `models.get_session` /
   `models.Events` import keeps working unchanged. Drop the unused
   `Events.reviews` relationship. It is the only thing tying shared models to
   a study's `Reviews` class.
2. **Add `models/studies/__init__.py`** with
   `get_study_module(study_type=None)`. It imports
   `flask_backend.models.studies.<study_type>` **at call time**, reading
   `study_config.get_study_type()`. If that module has no `REVIEW_FORM`
   attribute, which is the case for the dormant `vte`/`afib`/`cva`/`hf` files
   and unknown studies, it falls back to `mci` and logs one WARNING. This
   matches today's behavior, since every deployment currently uses the MI
   `reviews` table.
3. **Add `models/studies/mci.py`**. It holds the MI `Reviews` and
   `EventDerivedDatas` models, moved verbatim from `models.py` (D10), together with the MI review-form handler,
   meaning the body parsing currently in `app.py` (`_REVIEW_FLAG_FIELDS`,
   `_REVIEW_TEXT_FIELDS`), and the MI export (D9).
4. **Add `models/studies/scans.py`**. It holds the scans `Reviews` and
   `ReviewRegions` models plus the scans handler.
5. **Each study module declares its own `DeclarativeBase`.** It does not
   share `models.Base`. Both modules define a `reviews` table. With separate
   metadata they can be imported in the same process, as the test suite
   does when it switches `STUDY_TYPE` with `monkeypatch`, without a "Table
   'reviews' is already defined" error. Study models therefore declare
   `event_id` and `reviewer_id` as plain integer columns with no ORM
   relationships to `Events`/`Users`. Joins are written explicitly. Database
   foreign keys are not possible anyway, because `events` and `users` are
   MyISAM.
6. **Study module interface** (duck-typed; documented in the module
   docstring of `models/studies/__init__.py`):

   | Name | Purpose |
   |---|---|
   | `Reviews` | ORM class for this study's `reviews` table (must have `id`, `event_id`, `reviewer_id`) |
   | `REVIEW_FORM` | short id of the form the frontend should render (`'mci'`, `'scans'`) |
   | `parse_review(data) -> dict` | validate a POST body; raises `ReviewValidationError(message, fields)` |
   | `add_review(session, event_id, reviewer_id, parsed)` | add this study's rows to the session (no commit) |
   | `get_reviews(session, event_id) -> list[dict]` | serialize stored reviews for `GET .../review` |
   | `export_csv(session, writer)` | write the study's export headings + rows |
   | `check_schema(session) -> str \| None` | detect a database that hasn't been migrated for this study (D10); the review POST and the export return 503 with the returned hint |

**Rationale**: This is the layout the constitution prescribes (Principle IV
item 3: "model files under `flask_backend/models/<study>.py`, loaded by a
factory keyed on `STUDY_TYPE`"). It is what the user expected, and it is the
consolidation that project memory already identified as the prescribed
direction. Resolving the module at call time follows `get_workflow_config()`
and keeps tests simple.

**Not in scope**: The dormant `vte`/`afib`/`cva`/`hf` *model* files are not
converted. They stay dormant, and the loader's fallback deals with them.
`models2.py` is left as is (unused). Its MI `Reviews` copy is noted in the
plan as a follow-up for removal.

**Alternatives rejected**:
- (a) A shared `Base` for study modules: importing both `mci` and `scans`
  then fails, which breaks the test suite.
- (b) Defining the class in a factory function: harder to read and debug.
- (c) Keeping `models.py` and adding `models/studies/` under a different
  package name: leaves the shadowing trap in place and departs from the
  constitution's path.

## D5 — Duplicate submissions

Unchanged from rev. 1.

**Observed today**: The review endpoint does not check whether the
submitter's slot has already been reviewed, so a second POST inserts a second
row.

**Decision**: Add a shared guard. If the submitter's slot already has
`review1_date` (or `review2_date`) set, return **409** and write nothing. In
`scans`, `UNIQUE (event_id, reviewer_id)` on `reviews` adds a
database-level backstop. The scans form disables Submit while a request is in
flight and after a success.

**Rationale**: A duplicate would double the region rows, which violates
SC-002. This also fixes the same latent bug for MI. It is a behavior change in
shared code, recorded here.

## D6 — How the frontend picks the form

**Observed today**: `EventReview.jsx` holds the page shell and the whole MI
form inline. Study-varying presentation comes from maps keyed on study type
(`reviewGuidance.js`).

**Decision**: `frontend/src/components/review/reviewForms.js` maps a study
type to a form component: `scans` → `ScansReviewForm`, default →
`MciReviewForm`. The MI form moves verbatim out of `EventReview.jsx` into
`frontend/src/studies/mci/MciReviewForm.jsx`, and the new form is
`frontend/src/studies/scans/ScansReviewForm.jsx`. These locations are
constitution Principle IV mechanism 3. `EventReview.jsx` keeps the shell and
renders the resolved form once `configResolved` is true.

**Why this is not the retired VTE fork pattern**: `studies/vte/` copies whole
*pages* and adds parallel `/vte/*` routes plus `isVtePage` branching. Here,
`studies/<study>/` holds only the one component that genuinely differs per
study. There is a single page and a single route, and a registry picks the
component.

**Rationale**: It reuses the `reviewGuidance.js` pattern, with no study
branching in shared modules. The backend module's `REVIEW_FORM` is reported
on `GET /api/config` as `review_form`, and the frontend registry is keyed on
that value. As a result the backend's choice, including its fall back to
`mci` for dormant studies, and the form the frontend renders cannot disagree.

## D7 — Instructions as static HTML (replaces rev. 1's Markdown + `marked` + `/instructions` route)

**Decision**: Each study's instructions are a **self-contained HTML
document**, `app/webroot/files/instructions/<study>.html`. It carries its own
`<title>`, an inline `<style>` (including `@media print`), and the content. It
is served by the existing `GET /files/<path>` route from read-only
`FILES_DIR`, and links open it in a new tab. `scans.html` is converted from
the "DEXA CHART REVIEW INSTRUCTIONS" section of the 2026-09-22 Word
document: intro paragraph, steps 1–3 with sub-items a–d as a nested
`<ol type="a">`, and notes 1–6. The "PROGRAMMING INSTRUCTIONS" section is
excluded. `mci` has no file yet.

**Rationale**:
- No new dependency: `marked` is dropped.
- No new frontend route.
- No need to hide the site banner and menu with print CSS, because the
  document has none.
- Print-friendliness is built into the file: about 11pt serif, max-width only
  on screen, no link decoration, and `page-break-inside: avoid` on list
  items.
- An operator can edit the wording on the server without rebuilding
  (FR-023).
- `FILES_DIR` is only ever read, which meets the security rule.

**Alternatives rejected**:
- An HTML fragment injected into a React page: needs
  `dangerouslySetInnerHTML` plus print CSS for `BaseLayout`, for no gain.
- Markdown: the user does not want it.

## D8 — Instruction links

**Observed today**: `GuidanceLinks` already opens non-download links in a new
tab (`target="_blank"`), prefixed with `API_BASE`, which is correct for
`/files/*`. The `scans` box shows "No additional instructions". The `mci` box
links the reviewer `.doc`/`.pdf` files.

**Decision**:
- `reviewGuidance.js`: `scans.instructions` =
  `{ items: [], links: [{ label: 'View review instructions', href: '/files/instructions/scans.html', download: false }] }`.
- `mci.instructions` = `{ items: [], links: [] }`, so the reviewer
  `.doc`/`.pdf` links are removed (FR-017a).
- `packets` and `scrubbing` are unchanged.
- `GuidanceLinks` adds `rel="noopener"` to new-tab links. No other change.

**Effect**: The home page's `mci` "Review Instructions:" box shows only its
header, as spec 007 FR-001 requires. The review page's box is hidden by the
existing `showInstructionDocs` gate. When `mci` content exists, the change is
to add `mci.html` and one link entry. FR-022 holds: there is no dead link.

## D9 — Export and retrieval are study-specific

**Observed today**: `GET /api/events/export` (`app.py` ~L1027) writes MI
review headings, and `table_service.get_events_export_rows()` selects MI
`reviews` columns. On a `scans` database with the new schema, that SQL would
**fail** because the columns don't exist.

**Decision**:
- Move the MI-specific part (the review joins and columns plus the
  headings/row writer) into `models/studies/mci.py:export_csv`, with no
  output change. `events_export` calls
  `get_study_module().export_csv(session, writer)`.
- `scans.export_csv` writes **one row per region**: event columns (Event ID,
  Patient ID, Site, Site Patient ID, Event Date, Status), then Reviewer,
  Review Date, Position, Region, Region (other), Laterality, T score,
  Z score. Events with no review get a single row with the review columns
  blank.
- New route `GET /api/events/<id>/review` (admin, or a reviewer assigned to
  that event) returns `get_study_module().get_reviews(...)`.

**Rationale**: The existing Export page then works for both studies. Without
this change it would break on `scans`. No separate regions export is needed.

## D10 — Schema delivery: per-study init, dormant files, and migration (rev. 3)

**Observed today**:
- `init/` is mounted at `/docker-entrypoint-initdb.d`. The MariaDB image runs
  **every top-level** `*.sql`/`*.sh` file there, in alphabetical order, the
  first time a database volume is created. It does this regardless of
  `STUDY_TYPE`, and it does not descend into subdirectories.
- `02-schema.sql` holds the MI `reviews` **and** the MI
  `event_derived_datas` DDL.
- `02-schema-{vte,afib,cva,hf}.sql` sit at the top level, so they already
  run in *every* deployment. They have no effect only because their bodies
  are wrapped in `/* … */`. Each one defines that study's own `reviews` and
  `event_derived_datas` (CVA also defines `questionnaires`). Uncommenting any
  of them would create those tables in every study's database.
- `event_derived_datas` is read only by the MI export SQL. The
  `Events.derived_data` relationship is declared but never used.
- The mariadb service does not receive `STUDY_TYPE`.

**Decision**: There is one approach for every study, now written into
constitution v1.5.0, Principle IV:
- All study-specific DDL lives in `init/studies/<study>.sql`. Nothing
  study-specific stays at the top level of `init/`.
  - `init/studies/mci.sql`: the MI `reviews` and `event_derived_datas` DDL,
    moved verbatim out of `02-schema.sql`.
  - `init/studies/scans.sql`: the scans `reviews` and `review_regions`.
    `scans` has no `event_derived_datas`, since it has one reviewer and
    nothing to derive.
  - `init/studies/{vte,afib,cva,hf}.sql`: `git mv` from
    `init/02-schema-<study>.sql`, keeping their bodies commented. Each gets
    a first line of `-- DORMANT`.
- `init/04-study-schema.sh` applies `studies/${STUDY_TYPE:-mci}.sql`. If
  that file is missing or begins with `-- DORMANT`, it applies `mci.sql`
  instead and logs a warning. That matches the backend resolver (D4.2) and
  today's behavior, where every non-scans study effectively runs MI tables.
- Add `STUDY_TYPE: ${STUDY_TYPE:-mci}` to the mariadb service's environment
  in `docker-compose.yaml`.
- `EventDerivedDatas` moves from the shared models into
  `models/studies/mci.py`, and the unused `Events.derived_data`
  relationship is dropped.

**Existing deployments**:
- `mci`: nothing to do. Its tables are identical.
- `scans`: run `init/migrations/012-scans-reviews.sh` once. For each of
  `reviews` and `event_derived_datas` that still has its MI shape
  (`reviews.mci` and `event_derived_datas.outcome` columns, found through
  `information_schema`), the script renames the table to `<name>_mi_pre012`.
  The tables are renamed, not dropped, so nothing entered through the old MI
  form is lost. It then applies `studies/scans.sql`
  (`CREATE TABLE IF NOT EXISTS`). Re-running it is a no-op.

**Unmigrated databases**: if an operator upgrades an existing `scans`
server without running the migration, the backend would use the scans module
against the old MI-shaped `reviews` table. Every review submission would then
fail with an unexplained 500. `scans.check_schema()` detects this case once
per process. The review POST and the export then log at ERROR level and
return 503, naming `012-scans-reviews.sh`.

**DDL details**: the scans tables are InnoDB/utf8mb4, with
`review_regions.review_id → reviews.id ON DELETE CASCADE`.

**Rationale**: A database holds only its own study's tables, which is what
the user requires. There is one place and one mechanism for all study DDL,
with dormancy marked explicitly. This also removes the existing hazard of
top-level commented study files. `01`, `03`, `05` and `06` are untouched.
`03-data.sql` and `05-add-indexes.sql` don't reference `reviews` or
`event_derived_datas` (verified).

## D11 — Step 1 download label

Unchanged from rev. 1.

The shared Step 1 label becomes `Download charts for <heading identifier>`
for all studies, matching the legacy MCI screen (FR-002). Only the shell
changes, so the MI decision form itself is unchanged (SC-004).

---

# Revision 4 (2026-10-16): post-deployment feedback

**Inputs**: spec Clarifications, Session 2026-10-16 (FR-025 – FR-032), from
the user's dev-deployment review (`Review.Screenshot.2026-10-16.113230.jpg`,
`Review.Instructions.Screenshot.2026-10-16.113334.jpg`) and the legacy MCI
screenshots (`mci.review.Screenshot.1.2026-10-14.111240.jpg`,
`mci.event.edit.Screenshot.1/2.2026-10-14.jpg`). Each decision records what
the code does today first (Principle VI).

## D12 — Why `/files/instructions/scans.html` renders blank

**Observed**: The `web` service runs the Vite dev server
(`docker-compose.yaml`: `target: development_build`).
`frontend/vite.config.js` proxies only `/api`. Any other path falls through
to Vite's SPA history fallback, which returns `index.html`. React Router has
no `/files/*` route, so it renders the shell with an empty body. The
screenshot shows exactly that: a menu bar and a "Home page" link. The
backend's `/files/<path>` route (`flask_backend/app.py:2569`) and the file
`app/webroot/files/instructions/scans.html` are both correct. The same fault
already breaks every `/files/*.doc`/`.pdf` link (the MCI packet-assembly
instructions and the scrubbing protocol) on dev-server deployments.

**Decision**: Add a `/files` entry to `server.proxy` in `vite.config.js`. It
uses the same `apiProxyTarget` as `/api`, with default timeouts.

**Rationale**: It is a one-line change that fixes every `/files` link at
once, and the URL stays the same, so `reviewGuidance.js` is unchanged.

**Alternatives rejected**:
- Moving the route to `/api/files/...`: it changes every existing link, and
  `.doc` links that someone saved would break.
- Copying the HTML into `frontend/public/`: it would split `FILES_DIR`
  content across two places, against FR-023's single source.

**Residual**: The `serve` production stage of `frontend/Dockerfile`
(`serve -s dist --single`) has no proxy either. Compose doesn't use it today.
It is noted in quickstart as a deployment caveat and is not changed.

## D13 — Review page with no `event_id` (FR-025)

**Observed**: `MenuBar.jsx` links "Review Events" to `/events/review`.
`EventReview.jsx` reads a null `event_id` and renders the heading
`headingSuffix(label, null)`, which gives "SCANS null". It also renders the
whole form, a download link to `/api/events/download/null`, and fetches
`/api/events/null`.

**Decision**: When `event_id` is absent, `EventReview.jsx` returns a new
`ReviewQueue` component (`frontend/src/components/review/ReviewQueue.jsx`)
instead of the shell. `ReviewQueue` renders "Events to Review" with the same
endpoint (`/api/events/for_review`), columns, and actions as the home page.
To make that possible, `Home.jsx`'s `TableWrapper` and its "Events to Review"
section are extracted into that shared component, and `Home.jsx` uses it
too. That keeps one source and avoids drift. The empty state reads "No
events are awaiting review." The wording is neutral because admins see every
event (analyze U3).

**Also observed**: `TableWrapper.handleClick` sends **every** home-page
table's row click to `/events/upload?...`, including "Events to Review" rows.
Only the "review" button reaches the review page. `ReviewQueue`'s row click
and its button both open `/events/review?event_id=N` (FR-025). The other
home tables keep their current row behavior.

**Rationale**: The legacy MCI menu item listed the reviewer's queue, and the
user chose this (Q1). Sharing the component satisfies "same rows, from the
same source".

**Alternative rejected**: A redirect to `/` loses the "Review Events" menu
meaning.

## D14 — Scoping "Events to Review" to the signed-in reviewer

**Observed**: `/api/events/for_review` → `table_service.get_events_for_review`
returns **every** event with status `sent`/`assigned`, no matter who is
signed in. A reviewer therefore sees, and can open, events assigned to other
reviewers. The POST then refuses them with 403 ("not an assigned reviewer").
The home page has always behaved this way.

**Decision**: Non-admin callers get only events where they are
`reviewer1_id` and `review1_date IS NULL`, or `reviewer2_id` and
`review2_date IS NULL`. This is the same slot rule as the 409 guard, so an
event leaves the list the moment the server would refuse a resubmission.
Admin callers keep today's unscoped list. The filter is an optional
`reviewer_id` argument threaded into the existing status query. It contains
no study name.

**Rationale**: FR-025 says "the signed-in reviewer's events". Both list
locations share this endpoint, so both become correct together.

**Behavior change (all studies)**: the home page table narrows for
non-admin reviewers. This is recorded for the PR description.

## D15 — Already-reviewed state on the review page (FR-029)

**Observed**: The page always renders the form. Duplicates are refused only
on POST: `app.py` checks `review1_date`/`review2_date` for the caller's slot
and returns 409.

**Decision**: Extend `GET /api/events/{id}/review` with `mine`, the caller's
own status, computed in the shared endpoint from the event's slot columns.
This is the same rule as the 409 guard, so the two cannot disagree:
`mine = {slot, review_date}` if the caller's slot has a date; `null` if the
caller is assigned but hasn't reviewed. `EventReview.jsx` fetches it and
renders one of three states:
- Loading: no form yet.
- `mine` set: "You have already reviewed this event (submitted
  &lt;date&gt;)". No form and no Submit. Step 1's download link stays, since
  the packet is still the reviewer's to consult.
- Otherwise: the form, as today.

This applies to every study, because the shell owns it, not the form.

**Alternative rejected**: Inferring the state from `GET /api/events/{id}`'s
`review1_date`/`review2_date`. That response has no reviewer ids, so the
frontend can't tell which slot is the caller's.

## D16 — Review content on `/events/edit` (FR-027) and who receives it (FR-028)

**Observed**:
- `EventEdit.jsx` shows "Review N Date" rows only.
- `GET /api/events/{id}/review` returns all reviews, with content, to any
  admin or assigned reviewer. In a two-reviewer study, reviewer 1 could read
  reviewer 2's answers.
- Route `/events/edit` allows `reviewer`.
- `GET /api/events/{id}` (event details, including PHI `site_patient_id`)
  checks only that the caller is signed in.
- `GET /api/tables/<name>` runs `SELECT * FROM {name}` for any signed-in user,
  with the name interpolated straight into the SQL. Any user can read
  `reviews`/`review_regions` (and `users`), and the name is injectable.

**Decisions**:
1. `GET /api/events/{id}/review`: admins get `reviews` with full content,
   each item gaining `slot` (1/2/3, matched from `reviewer{1,2,3}_id`; `null`
   if unmatched). Non-admin assigned reviewers get `reviews: []` plus `mine`
   (D15). They never receive answers, their own included. Everyone else gets
   403, as today.
2. `EventEdit.jsx` fetches it **only when `auth.admin`**. Under each "Review N
   Date" row it adds a "Review N" row, rendered by the study's summary
   component from the frontend registry (D17). Non-admins (e.g. uploaders)
   see dates and reviewer names only.
3. Route `/events/edit`: `requiredRoles={['uploader', 'admin']}`. The
   `Home.jsx` "edit" buttons are already restricted to uploader/admin.
   `EventViewAll.jsx` shows "edit" buttons to everyone, but under D19 that
   page becomes admin-only, so no per-button gate is needed.
4. `GET /api/events/{id}`: callers with neither `admin` nor `uploader` must be
   one of the event's `reviewer{1,2,3}_id`, or they get 403. The review page
   still loads the event date for an assigned reviewer.
5. `GET /api/tables/<name>`: the name must match `^[A-Za-z_][A-Za-z0-9_]*$`
   (else 404), which closes the injection. The study module's review tables
   (a new module constant, `REVIEW_TABLES`: `mci` →
   `('reviews', 'event_derived_datas')`, `scans` →
   `('reviews', 'review_regions')`) return 403 to non-admins. A full
   allowlist with per-table roles is a **follow-up** (Principle VI): the
   frontend reads `users`, `criterias`, `solicitations`, and `patients_view`
   through it today, and tightening those needs its own discovery.

**Rationale**: The backend is the boundary. The frontend changes only remove
dead ends.

## D17 — Study review summaries in the frontend registry

**Decision**: `components/review/reviewForms.js` gains a second map and a
`resolveReviewSummary(reviewForm)` beside `resolveReviewForm`, with the same
key normalization and the same mci fallback.
`studies/scans/ScansReviewSummary.jsx` renders one line per region, in
position order: `Region[: other] — Laterality, T: x, Z: y`. Omitted parts are
"not provided" and never 0.
`studies/mci/MciReviewSummary.jsx` renders the legacy labels for non-null MI
columns, as in `mci.event.edit.Screenshot.1` ("MI: Probable", "Criteria:
…", "Primary/Secondary: …", "Cardiac Cath: No"). The label text comes from
`MciReviewForm.jsx`'s questions so the two stay together. An unknown id falls
back to mci, matching the form fallback.

**Rationale**: The labels already live with each study's form component
(Principle IV, mechanism 3). Backend-formatted text would duplicate them in
Python.

## D18 — Instructions box placement and H1 size (FR-030, FR-031)

**Observed**:
- `EventReview.jsx` gives the box `className="boxright"`, but no stylesheet
  defines `.boxright`, so it renders as plain flow content above the H1.
- `index.css` sets `h1 { font-size: 3.2em }`, which is the Vite scaffold
  default.
- `Home.css`'s `.infobox` already has the legacy tan style (`#f2e8c2` fill,
  `#f1d676` border, 12px radius).

**Decision**:
- Define `.boxright` in `index.css`: float right, `width: 300px`, the same
  colors, border, and radius as `.infobox`, `margin: 0 0 1em 1.5em`. Under
  `@media (max-width: 640px)`, `float: none; width: auto;`, so there is no
  horizontal scroll.
- Drop the inline `width`/`fontSize` from `EventReview.jsx`, keeping its
  `h3`.
- Change `h1` to `font-size: 1.6em; line-height: 1.2` site-wide.
  `Admin.css`'s `.admin-container h1` override stays.

**Rationale**: It reuses existing tokens and adds no new palette, which
matches the legacy screenshot.

## D19 — Scrub, screen, and all-events pages become admin-only (FR-033)

**Trigger**: `/speckit.analyze` finding U1. D16.4 restricts
`GET /api/events/{id}` for callers who are neither admin nor uploader to
events they are assigned to review. Three reviewer-reachable pages call that
endpoint for events the caller isn't assigned to:
- `EventScreen.jsx`;
- `EventScrub.jsx`;
- the "Not Yet Reviewed" table in `EventViewAll.jsx` (`augmentRows`, ~L320).

**Observed roles today**:

| Surface | Roles |
|---|---|
| Route `/events/screen` (`App.jsx` ~L322) | reviewer, admin |
| Route `/events/scrub` (~L346) | reviewer, uploader, admin |
| Route `/events/viewAll` (~L367) | reviewer, uploader, admin |
| MenuBar "View All Events" | reviewer, uploader, admin |
| `POST /api/events/<id>/screen` | `@requires_any_role('reviewer', 'admin')` |
| `POST /api/events/<id>/upload_scrubbed` | `@requires_any_role('reviewer', 'uploader', 'admin')` |

The pages' only entry points are buttons on `/events/viewAll`. Nothing on
the home page or menu links to scrub or screen.

**Decision (user, 2026-10-16)**: All of the above become admin-only, for
every study:
- routes: `requiredRoles={['admin']}`;
- MenuBar link: `admin &&`;
- endpoints: `@requires_roles('admin')`.

This makes D16.4 safe. The remaining callers of `GET /api/events/{id}` are:
- `EventUpload.jsx` (uploader/admin);
- `EventEdit.jsx` (uploader/admin);
- `EventSendMany.jsx` (admin);
- `EventReview.jsx` (an assigned reviewer).

Studies with scrubbing and screening disabled (`scans`) never reach these
stages, but the rule is the same for every study, so there is no study
branching (Principle IV).

**Constitution**: Principle V forbids redefining, removing, or renaming
roles or states. This changes neither; it changes which existing roles may
perform two stages, uniformly across studies. Principle III is unaffected,
since no data changes.

**Not changed (follow-ups, Principle VI)**:
- `GET /api/events/by_status/<status>` stays open to reviewer, uploader,
  and admin. `EventAssignThird.jsx` (route allows reviewer) reads
  `by_status/third_review_needed`.
- `/events/assignThird` itself still allows reviewers.
- `GET /api/events/download/<id>` (packet download) has only
  `@requires_auth`.
- `GET /api/events/need_packets` and `need_reupload` allow reviewers.

**Behavior change (all studies)**: non-admin reviewers and uploaders on
`mci` (scrubbing and screening on) can no longer scrub or screen. The PR
description must say so.

## D20 — `/speckit.analyze` follow-ups (C1, I1, I2, U3)

- **C1 (constitution, "Authorization")**: Endpoints must carry explicit role
  decorators. `GET /api/events/{id}` and `GET /api/tables/{name}` had only
  `@requires_auth`. Both get
  `@requires_any_role('admin', 'uploader', 'reviewer', 'third_reviewer')`,
  the set `/api/config` uses. No current frontend caller lacks one of these
  roles, so nothing changes for real users. A signed-in user with no role
  now gets 403.
- **I1**: `_reviewer_slot()` can return 3, but POST review keeps accepting
  only slots 1 and 2 and returns the existing 403 for anything else. Third
  review submission is not part of this feature.
- **I2**: `mine` reports slots 1 and 2 only, matching POST and the GET's
  unchanged non-admin access check (reviewer1/2). Admin `reviews` items
  still label slot 3 when one exists, e.g. `mci` third reviews on
  `/events/edit`.
- **U3**: The empty-queue text is "No events are awaiting review."
