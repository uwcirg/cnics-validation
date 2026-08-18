# Implementation Plan: Archive Bulk-Import CSV Files and Report Import Outcomes Honestly

**Branch**: `009-save-import-csv` | **Date**: 2026-08-13, amended 2026-08-17 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/009-save-import-csv/spec.md`

## Summary

`POST /api/events/bulk` reads the submitted CSV into memory, parses it, creates
events, and lets the bytes fall out of scope. Non-persistence is not an
oversight — a test, `test_bulk_csv_upload_does_not_persist_file`, asserts it
today. This feature reverses that decision: the submitted file is written to
disk before it is parsed, and the outcome of parsing is written beside it.

Each submission produces two files in a new `imports/` subdirectory of the
existing writable uploads volume: `<id>.csv`, the verbatim bytes, and
`<id>.json`, a manifest holding submitter, timestamp, original file name,
counts, and the skipped-row reasons that today live only in a toast. The id is
a sortable UTC timestamp plus a random suffix, so concurrent submissions of
identically named files cannot collide, and the stored name carries no patient
identifier. Three admin-only read endpoints expose the list, one record, and
the archived file; one new admin page renders them.

Archiving happens before parsing and is fail-closed: if the file cannot be
written, the request is refused and no events are created (research D3).
Because the write precedes the decode, every submission the system tries to
process is retained — including ones that are not valid UTF-8, parse to
nothing, or blow up mid-import. The single exception is a submission over the
size cap, whose contents are refused to keep storage bounded; that attempt
still gets a manifest with `outcome: refused`, so it is recorded rather than
silently dropped (research D5). The
manifest is written after the import, and a `.csv` without a `.json` is
rendered as an incomplete record rather than treated as an error, because the
underlying tables are MyISAM and cannot be rolled back to match (research D8).

No schema change, no migration, no new dependency. The archive lives in the
volume study deployments already mount, so no deployment change beyond two
new optional environment variables.

## Technical Context

**Language/Version**: Python 3.11 (Flask backend); JavaScript / JSX, React 19 (frontend)
**Primary Dependencies**: Flask, SQLAlchemy, mysql-connector-python; React 19, react-router-dom 6, Vite 7 — **no new dependency**; the archive uses `csv`, `json`, `os`, `uuid`, and `datetime` from the standard library
**Storage**: Filesystem only — `<DOWNLOADS_DIR>/imports/`, on the existing `cnics-downloads` named volume. **No schema change, no migration**; the import record is a JSON sidecar, not a table (research D2)
**Testing**: pytest (`flask_backend/tests/`, 11 modules). The `admin_client` fixture in `conftest.py` covers admin paths; the `FakeUser` pattern in `test_auth_header_and_roles.py` covers the non-admin refusal cases. **No frontend test infrastructure exists** — the new page is verified manually per quickstart.md
**Target Platform**: Linux server, Docker Compose stack
**Project Type**: Web application — Flask backend + React SPA frontend
**Performance Goals**: Two small file writes per bulk import (a few KB each); import wall-clock unchanged within noise (SC-005 allows 10%). The list endpoint scans one directory and reads N manifests — at the SC-007 target of 500 imports this is ~500 stat + read calls of a few hundred bytes each, served from page cache
**Constraints**: Archiving is fail-closed, so a full or read-only uploads volume refuses bulk imports (spec Assumptions, flagged for confirmation). The size cap must be per-endpoint, not Flask's global `MAX_CONTENT_LENGTH`, which would also cap event-packet uploads (research D5). Import-id inputs must be validated against a strict pattern before any path join (research D6). `openapi.json` must be regenerated in the same PR
**Scale/Scope**: 1 new backend module, 1 modified backend endpoint, 3 new backend endpoints, 2 test modules (1 new, 1 amended), 1 new frontend page, 3 modified frontend files, 2 config files, 1 regenerated contract. Study-agnostic — no `STUDY_TYPE` branch, no new flag in `study_config.py`

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

Evaluated against constitution v1.4.0.

| Principle | Status | Assessment |
|---|---|---|
| **I. Single Codebase, Many Studies** | ✅ PASS | All changes land in shared modules and shared pages. The VTE fork (`frontend/src/studies/vte/EventAddMany.jsx`) posts to the same `/api/events/bulk` endpoint and therefore inherits archiving with no code change; it is deliberately **not** given a copy of the new page (research D9) |
| **II. Study Data Isolation** | ✅ PASS | The archive lives inside the deployment's own uploads volume, which is already per-study. No cross-study path, no shared directory, no new DB grant. Isolation is inherited from the existing mount rather than newly asserted |
| **III. Backwards Compatibility With Legacy Data** | ✅ PASS | No schema change, no migration, no change to `events`, `criterias`, or the event lifecycle. The `/api/events/bulk` response is extended additively with `import_id`; existing consumers reading `imported` and `errors` are unaffected. Existing deployments with no `imports/` directory get one created on first use |
| **IV. Configuration Over Code Forks** | ✅ PASS | Two new optional environment variables (`IMPORT_ARCHIVE_DIR`, `MAX_IMPORT_CSV_BYTES`), both with working defaults derived from existing config, following the established `UPLOAD_DIR` / `DOWNLOADS_DIR` precedent. No `STUDY_TYPE` branch — nothing about this feature varies by study |
| **V. Workflow and Role Parity** | ✅ PASS | No state added, removed, renamed, or bypassed. Events created by a bulk import still enter at `created` exactly as today. No new role — the archive is scoped to the `admin` role that already owns bulk import |
| **VI. Pre-Release Iteration and Discovery** | ✅ PASS | Current behavior is recorded before modification in research.md (D1), including that non-persistence is asserted by an existing test and that the endpoint's "single transaction" docstring is false on MyISAM (D8). The obsolete test is rewritten to assert the new intent rather than deleted or left to pass vacuously (research D7) |

**Security & Data Governance**

| Rule | Status | Assessment |
|---|---|---|
| **PHI handling** | ✅ PASS | Archived CSVs contain site patient identifiers and event dates and are treated as PHI. No file content, original file name, or skipped-row text is logged at INFO or above; failures log the import id and an error class only. No new secret is introduced |
| **File storage** | ✅ PASS | Writes go to `<DOWNLOADS_DIR>/imports/`, never to `FILES_DIR`, which is read-only by rule and mounted `:ro` in `docker-compose.yaml`. The archive is a subdirectory rather than the `DOWNLOADS_DIR` root so archived files can never be matched by `events_download`'s candidate scan, nor event packets by the import list (research D4) |
| **Authorization** | ✅ PASS | All three new endpoints declare `@requires_auth` + `@requires_roles('admin')` at definition time, matching `events_bulk`. Nothing is open by default. The frontend route is wrapped in `ProtectedRoute requiredRoles={['admin']}`, but the backend decorator is the enforcement point |
| **Network exposure** | ✅ PASS | No new service, port, or binding. Archived files are reachable only through the authenticated API, never via the static `/files/<path>` route |
| **Data isolation audits** | ✅ PASS | No new DB user, schema, or origin |

**Development Workflow & Quality Gates**

| Gate | Status | Assessment |
|---|---|---|
| **Change review** | ✅ PASS | Affects all studies (shared code). To be stated in the PR description |
| **Schema changes** | ✅ N/A | None — no migration plan required (research D2) |
| **API contracts** | ⚠ ACTION | Three new endpoints and one changed response. `openapi.json` must be regenerated via `python -m flask_backend.generate_openapi` and land in the same PR. Tracked as a task |
| **Testing discipline** | ✅ PASS | New endpoints get pytest coverage including the non-admin refusal path; the archiving behavior of `events_bulk` gets coverage for success, partial, rejected, collision, oversize, and unwritable-archive cases |
| **Feature-flag discipline** | ✅ N/A | No workflow flag. `MAX_IMPORT_CSV_BYTES` is a limit, not a behavior switch; its default is the conservative documented value |
| **Deployment parity** | ✅ PASS | Works with the standard `docker compose up` stack; the archive directory is created on demand inside the existing volume |

**Result**: PASS. No violations, so Complexity Tracking is omitted. One action item (regenerate `openapi.json`) is carried into tasks.

**Post-design re-check** (after Phase 1): still PASS, unchanged. The design
added no table, no role, no study branch, and no service. Two points the design
work strengthened rather than weakened: the archive subdirectory keeps the
import namespace provably disjoint from the packet namespace (File storage),
and the strict id whitelist plus containment assertion closes the path-traversal
surface the new read endpoints introduce (research D6). One pre-existing gap was
found and deliberately left alone — `events_download` has `@requires_auth` but no
role decorator (research D6, quickstart Notes); fixing it belongs in its own
change, not this diff.

## Project Structure

### Documentation (this feature)

```text
specs/009-save-import-csv/
├── plan.md              # This file
├── research.md          # Phase 0 output — 9 decisions
├── data-model.md        # Phase 1 output — archive layout and record shape
├── quickstart.md        # Phase 1 output — manual verification walkthrough
├── contracts/
│   └── imports-api.yaml # Phase 1 output — OpenAPI fragment for the 4 endpoints
├── checklists/
│   └── requirements.md  # Spec quality checklist (/speckit.specify output)
└── tasks.md             # Phase 2 output (/speckit.tasks — NOT created here)
```

### Source Code (repository root)

```text
flask_backend/
├── import_archive.py            # NEW — archive read/write, id generation and validation
├── app.py                       # MODIFIED — archive in events_bulk; 3 new admin endpoints
└── tests/
    ├── test_import_archive.py   # NEW — archiving, listing, access control, path safety
    └── test_app.py              # MODIFIED — non-persistence test rewritten

frontend/src/
├── pages/
│   ├── EventImports.jsx         # NEW — admin list of past imports + detail + download
│   ├── Admin.jsx                # MODIFIED — link to the import list
│   └── Home.jsx                 # MODIFIED — link in the admin section
└── App.jsx                      # MODIFIED — admin-protected /events/imports route

docker-compose.yaml              # MODIFIED — pass the two new env vars to `backend`
default.env                      # MODIFIED — document the two new env vars
openapi.json                     # REGENERATED — contract for the new endpoints
```

**Structure Decision**: The existing Flask-backend + React-SPA split is kept
unchanged. The one structural addition is `flask_backend/import_archive.py`:
the archive's filename grammar, id validation, manifest serialization, and
directory scan are pure functions over a directory path, and putting them in a
module keeps them unit-testable without the Flask app while holding the line on
`app.py`, which is already ~1,750 lines. The endpoints in `app.py` stay thin —
auth decorators, request parsing, and response shaping only.

## Phase 2 Notes (for `/speckit.tasks`)

Work decomposes along the spec's three priorities, and each is independently
shippable:

- **P1 — retention**: `import_archive.py` write path + `events_bulk` change +
  its tests + `default.env` / `docker-compose.yaml`. Delivers FR-001 to FR-008
  with no interface at all.
- **P2 — outcome record**: manifest write + manifest tests. Delivers FR-003.
  Layered on P1 without changing what P1 wrote.
- **P3 — review interface**: three read endpoints, `openapi.json`, the new
  page, the route, and the two links. Delivers FR-009 to FR-011.

FR-012 (no automatic deletion) is satisfied by omission and needs no task; it
is verified by inspection in the quickstart.

---

# Amendment (2026-08-17) — import feedback and button legibility

The plan above (P1–P3, backend archival) is **implemented and unchanged**. This
amendment plans the spec's Stories 4 and 5, added after the archival work
landed. Read it as a second, additive slice on the same branch.

## Summary (amendment)

Three defects on the bulk-import page, one of which is causing data harm.

The harmful one: a successful import reported "CSV upload failed". Research D10
traced it to two independent causes. `EventAddMany.jsx:20-25` swallows a JSON
parse error, defaults `imported` to `0`, and falls into the failure branch — so
the client asserts failure from the *absence* of evidence. And the body it
failed to parse was the Vite dev server's transformed `index.html`, proven by
the `/@react-refresh` and `/@vite/client` injections that exist in no file in
this repository. An administrator told a completed import failed re-runs it,
which creates duplicate events.

The second: nothing indicates a running import. 800 rows takes about a minute
because `app.py:1509-1513` does one federated `patients_view` lookup per row
over the SSH-tunnelled bridge (research D12). The page looks inert for that
minute, so administrators resubmit.

The third: `index.css:39` is the unmodified Vite scaffold button —
`#f9f9f9` on `#ffffff`, roughly **1.05:1**, effectively invisible. It is the
only `button` rule in the repository, so one edit reaches all 90 buttons.

The fix is frontend and configuration only. A shared submit hook classifies
every response into one of six outcomes with `Content-Type` checked *before*
parsing, so a non-JSON body becomes `undetermined` rather than "failed". A
shared result panel renders the outcome in the page body — scrollable, copyable,
persistent — replacing the toast that joined hundreds of error strings onto one
line. `Toast.js` keeps its signature but makes `warning`/`error` persistent,
reaching all 77 call sites without editing any. `vite.config.js` gains the
`/api` proxy it never had.

**No backend change. No schema change. No new dependency.** `openapi.json` is
not regenerated this time, because no route, request, or response shape moves.

## Technical Context (amendment)

**Language/Version**: JavaScript / JSX, React 19 (frontend); CSS. **No backend change**
**Primary Dependencies**: React 19, react-router-dom 6, Vite 7 — **no new dependency**. The spinner is CSS, the timer is `setInterval`, the copy control is `navigator.clipboard`
**Storage**: None. All new state is client-side and transient (data-model amendment)
**Testing**: `npm run lint` (eslint is the only tooling `frontend/package.json` declares — no vitest, no testing-library). Verification is the manual walkthrough in quickstart steps 6–11. Backend tests are untouched and must still pass unchanged, which is itself the check that this amendment did not reach the backend
**Target Platform**: Linux server, Docker Compose stack. Note `docker-compose.yaml:98` builds `web` with `target: development_build`, so the Vite dev server is what serves study deployments — this is why an unproxied `/api` path on it is a live trap rather than a dev-only curiosity
**Project Type**: Web application — frontend-only change in this amendment
**Performance Goals**: No change to import wall-clock; the ~60s stands and is documented, not optimized (research D12). The elapsed counter ticks once per second. The result panel must stay responsive rendering several hundred skipped-row lines
**Constraints**: FR-019 is only partly satisfiable in-tree — the Apache `ProxyTimeout` lives in a separate repository (research D11). The `Toast.js` change alters behavior at 59 of 77 call sites, so it needs an app-wide smoke pass, not just a bulk-import pass. The VTE fork is out of scope but inherits the shared CSS and notification changes regardless, so it is included in that smoke pass as an observation, not as work (research D18)
**Scale/Scope**: 2 new frontend modules, 4 modified frontend files, 1 CSS rule, 1 Vite config addition. 90 buttons restyled by a single rule. **0 backend files, 0 schema files, 0 contract regenerations, 0 files under `studies/vte/`**

## Constitution Check (amendment)

Re-evaluated against constitution v1.4.0 for the amendment's changes only.

| Principle | Status | Assessment |
|---|---|---|
| **I. Single Codebase, Many Studies** | ✅ PASS | Every change lands in shared modules — one `button` rule, one notification component, one submit path on the study-agnostic page. Nothing is added or modified under `studies/vte/`, which is legacy the project does not intend to use and is not linked from the shared tree (research D18). No study-specific branch is introduced anywhere |
| **II. Study Data Isolation** | ✅ PASS | No data path touched. No new storage, no new endpoint, no new origin. Skipped-row text already rendered in the browser is rendered there again, in a different container |
| **III. Backwards Compatibility With Legacy Data** | ✅ PASS | No schema change, no migration, no legacy consumer affected. The `/api/events/bulk` contract is read differently by the client but is not itself modified |
| **IV. Configuration Over Code Forks** | ✅ PASS | No new environment variable, no `STUDY_TYPE` branch. Nothing here varies by study — a button's contrast and an honest error message are study-agnostic. The `vite.config.js` proxy is build/serve configuration, not study differentiation |
| **V. Workflow and Role Parity** | ✅ PASS | No state, role, or lifecycle transition added, removed, or renamed. Bulk import remains admin-only and still creates events at `created` |
| **VI. Pre-Release Iteration and Discovery** | ✅ PASS | The obligation to record current behavior before changing it is met at length: research D10 documents the false-failure mechanism line by line, D12 documents the per-row federated lookup behind the ~60s, and D17 records the measured 1.05:1 contrast of the rule being replaced. Each says "I verified it did X by Y, and am replacing it with Z because…" rather than rewriting opaque code |

**Security & Data Governance**

| Rule | Status | Assessment |
|---|---|---|
| **PHI handling** | ⚠ NOTE — PASS with care | Skipped-row messages embed `site_patient_id` values (`app.py:1516-1518`). They are already displayed on screen today, so the panel is not a new disclosure — but it makes them **persistent and copyable**, which is a longer-lived exposure on a shared screen. Two obligations follow: the panel must be dismissible (it is, FR-017) and nothing may write those strings to a log or to `localStorage`. No new logging is introduced |
| **File storage** | ✅ N/A | No file is written or read by this amendment |
| **Authorization** | ✅ PASS | No new endpoint. Both pages remain behind `ProtectedRoute requiredRoles={['admin']}`, and the backend decorators on `/api/events/bulk` are untouched — the enforcement point does not move |
| **Network exposure** | ✅ PASS | The `vite.config.js` proxy forwards `/api` to `backend` on the internal Compose network. It adds no host port and no binding; `docker-compose.yaml` still binds `web` and `backend` to `127.0.0.1`. Traffic continues to reach the stack only through the Apache basic+ldap edge |
| **Data isolation audits** | ✅ N/A | No new DB user, schema, or origin |

**Development Workflow & Quality Gates**

| Gate | Status | Assessment |
|---|---|---|
| **Change review** | ✅ PASS | Affects **all studies** — the button rule and the notification component are shared definitions, so every page in every deployment is touched. To be stated in the PR description, along with the D15 scope note that 59 of 77 notifications become click-to-dismiss |
| **Schema changes** | ✅ N/A | None |
| **API contracts** | ✅ N/A | **No backend route, request, or response shape changes**, so `openapi.json` is not regenerated. This differs from the original 009 plan, which carried a regeneration action item. Verified by the tasks' requirement that `git diff --stat flask_backend/` be empty |
| **Testing discipline** | ⚠ ACCEPTED GAP | The gate asks for integration tests on new *backend* endpoints; there are none, so it does not bind. The repository has no frontend test framework, so this amendment ships with `npm run lint` and the quickstart walkthrough as its verification. Stated plainly rather than papered over: the honest-reporting logic (research D14) is exactly the kind of branch-heavy code a unit test would serve well, and introducing vitest is a reasonable follow-up — it is not bundled here because adding a test framework is its own change with its own review |
| **Local development parity** | ✅ PASS | The `vite.config.js` proxy improves parity: `/api` resolves the same way inside the Compose stack as it does through the edge |
| **Feature-flag discipline** | ✅ N/A | No flag. These are unconditional fixes |
| **Unused subsystem hygiene** | ✅ PASS | Nothing left dead. The import-result toast is removed when the panel replaces it, not left in place alongside |

**Result**: PASS. No violations, so Complexity Tracking is omitted. Two items
carried into tasks: the PHI-persistence note above, and the accepted
frontend-testing gap.

**Post-design re-check**: still PASS. The design added no endpoint, no
dependency, no flag, and no file under `studies/vte/`. One point strengthened
during design: extracting the shared modules (D18) turned what could have been
a second forked copy of the fix into a net reduction in fork divergence.

## Project Structure (amendment)

```text
frontend/src/
├── components/
│   ├── useCsvImport.js       # NEW — submit, six-way classification (D14), elapsed timer (D13)
│   ├── ImportResult.jsx      # NEW — result panel: summary, scrollable rows, copy, record link (D16)
│   ├── Toast.js              # MODIFIED — warning/error persist + dismiss; same signature (D15)
│   └── BaseLayout.jsx        # MODIFIED — #toast-root gains max-height + overflow (D15)
├── pages/
│   ├── EventAddMany.jsx      # MODIFIED — consume hook + panel; drop the joined-string toast
│   └── EventImports.jsx      # MODIFIED — accept ?import_id= and pre-select (D19)
└── index.css                 # MODIFIED — the one button rule; +:disabled, :focus-visible (D17)

frontend/vite.config.js       # MODIFIED — server.proxy for /api with a long timeout (D11)

flask_backend/                # UNCHANGED — no file in this tree is touched
openapi.json                  # UNCHANGED — no contract delta
```

**Structure Decision**: The two new modules have a single consumer, so this is
a readability split rather than a reuse one. `useCsvImport.js` holds the state
machine and the six-way classification — branch-heavy logic that would otherwise
double the size of a page component, and the part most worth being importable if
a frontend test framework is added later. `ImportResult.jsx` is presentation
over the outcome it returns. An earlier draft justified the split under
Principle I by making the VTE fork a second consumer; that scope was withdrawn
(research D18), and the split is retained on these narrower grounds.

## Phase 2 Notes (amendment, for `/speckit.tasks`)

Spec Stories 4 and 5 ship independently, and Story 5 is the safest thing to land
first:

- **Story 5 — button legibility (P3)**: one rule in `index.css`, plus the
  measurement step. No JavaScript, no behavior change, trivially reviewable, and
  it supplies the `:disabled` state that Story 4 needs to make its disabled
  button legible. Delivers FR-020 / SC-012.
- **Story 4a — in-flight indication (P1)**: `useCsvImport.js` state machine, the
  spinner, the elapsed counter, the disabled submit. Delivers FR-013, FR-014,
  SC-009, SC-010. Independently shippable — it stops the resubmission problem
  even before the reporting is fixed.
- **Story 4b — honest reporting (P1, highest value)**: the five-way
  classification and the result panel. Delivers FR-015, FR-016, FR-018, SC-008,
  SC-011. This is the slice that stops duplicate events being created.
- **Story 4c — persistent notifications (P1)**: the `Toast.js` change plus the
  container bound. Delivers FR-017. Sequence it *after* 4b so the app-wide smoke
  pass happens once, against the final behavior.
- **Story 4d — deep link (P1, small)**: `?import_id=` on `EventImports.jsx`.
  Delivers the record-link half of FR-018. Depends on 4b having an id to link.
- **Config — timeout headroom**: the `vite.config.js` proxy. Delivers the
  in-tree half of FR-019 / SC-013. Independent of everything above. The Apache
  half is a deployment action, not a task — it must be called out in the PR
  description so it is not silently dropped.

Ordering constraint: 4b before 4c and 4d. Everything else is parallel.
