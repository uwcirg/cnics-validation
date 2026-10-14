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
