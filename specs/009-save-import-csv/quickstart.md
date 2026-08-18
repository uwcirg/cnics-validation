# Quickstart: Archive Bulk-Import CSV Files

## What this feature does

Every CSV submitted at `/events/addMany` is written to disk before it is
parsed, together with a record of what the import did — who submitted it, when,
how many events were created, and every skipped row with its reason. Today the
file is read into memory and discarded, and the skipped-row list survives only
as long as a toast notification.

Administrators can review past imports at `/events/imports` and download any
submission exactly as it was sent.

## Files involved

**Backend**

- `flask_backend/import_archive.py` — **new**. Pure functions over an archive
  directory: `new_import_id()`, `is_valid_import_id(s)`,
  `archive_dir()`, `write_submission(dir, id, data)` (exclusive create),
  `write_record(dir, id, record)`, `list_records(dir, limit, offset)`,
  `read_record(dir, id)`, `submission_path(dir, id)`. No Flask import — keeps
  it unit-testable without the app.
- `flask_backend/app.py`:
  - **add** the archive-dir constant beside `DOWNLOADS_DIR` (line 101), and
    `MAX_IMPORT_CSV_BYTES`.
  - **edit** `events_bulk` (line 1249): read bytes → size check (413) → write
    submission (500 and return if it fails, before any parse) → existing parse
    and import, unchanged → write manifest → add `import_id` to the response.
    Also add the docstring YAML block from `contracts/imports-api.yaml`; this
    path is an empty `{}` placeholder in `openapi.json` today.
  - **add** three routes, each with `@requires_auth` +
    `@requires_roles('admin')`: `GET /api/events/imports`,
    `GET /api/events/imports/<import_id>`,
    `GET /api/events/imports/<import_id>/file`.
- `openapi.json` — **regenerate**, do not hand-edit:
  `python -m flask_backend.generate_openapi`.

**Frontend**

- `frontend/src/pages/EventImports.jsx` — **new**. Fetches
  `/api/events/imports`, renders via `components/DataTable.jsx` (columns:
  Submitted, Submitted by, File, Events created, Skipped, Outcome). Selecting a
  row shows its skipped rows and a download link to
  `/api/events/imports/<id>/file`.
- `frontend/src/App.jsx` — **edit**: add `/events/imports` wrapped in
  `ProtectedRoute requiredRoles={['admin']}`, beside the `/events/addMany`
  route (line 165).
- `frontend/src/pages/Admin.jsx` and `frontend/src/pages/Home.jsx` — **edit**:
  add "View past CSV imports" to the Events list.
- **No** copy under `frontend/src/studies/vte/` — the VTE bulk-import page
  posts to the same endpoint and is archived without a frontend change
  (research D9).

**Config**

- `docker-compose.yaml` — **edit**: add `IMPORT_ARCHIVE_DIR` and
  `MAX_IMPORT_CSV_BYTES` under the `backend` service's `environment:` block.
  Compose does **not** auto-inject `.env` variables into containers.
- `default.env` — **edit**: document both, commented out at their defaults.

**Tests**

- `flask_backend/tests/test_import_archive.py` — **new**.
- `flask_backend/tests/test_app.py` — **edit**: rewrite
  `test_bulk_csv_upload_does_not_persist_file` (line 152). It asserts the
  opposite of the new behavior, and — because it checks for a file literally
  named `events.csv` — it would keep passing while the behavior reversed
  (research D7).

## Verify

### 1. Tests

```bash
pytest flask_backend/tests/test_import_archive.py flask_backend/tests/test_app.py
```

The suite needs no database: the archive write happens before
`models.get_session()`, and the malformed-CSV fixtures fail row validation
before any query, which is how the existing test already works.

Cases that must be covered:

| Case | Expectation |
|---|---|
| All rows valid | 201, events created, one `.csv` + one `.json` in the archive |
| Some rows invalid | 201, `.csv` holds the whole file including bad rows; manifest `outcome: partial`, `errors` matches the response |
| Every row invalid | 400, **file still archived**, manifest `outcome: rejected` |
| Not UTF-8 | 400, **file still archived** (write precedes decode) |
| Same filename twice | Two distinct `.csv` files; neither overwritten |
| Over `MAX_IMPORT_CSV_BYTES` | 413, no events created, **no `.csv`** stored — but a `refused` record **is** written, so the attempt is visible |
| `refused` record downloaded | 404 — there are no contents to return |
| Archive dir unwritable | 500, no events created (fail-closed) |
| `import_id` = `../../etc/passwd` | 404, no file read outside the archive |
| Non-admin caller | 403 on all four endpoints |

### 2. Manual walkthrough (on the deployed stack)

1. Sign in as an administrator and go to `/events/addMany`.
2. Submit a CSV with a deliberate mix — some valid rows, some malformed. Note
   the toast: it must read exactly as it does today (FR-008).
3. On the backend host:

   ```bash
   docker compose exec backend ls -l /opt/backend/uploads/imports/
   ```

   Expect a `.csv` / `.json` pair named `<YYYYMMDD>T<HHMMSS>Z-<8 hex>`.

4. Compare the archived file against your source — they must be identical
   (SC-003):

   ```bash
   docker compose exec backend cat /opt/backend/uploads/imports/<id>.csv | md5sum
   md5sum /path/to/your/original.csv
   ```

5. Open `/events/imports`. The import appears at the top with your login, the
   submission time, the original file name, and the event count. Selecting it
   shows the same skipped rows the toast showed. Download returns your file.
6. Submit the *same file again*. A second, distinct pair appears; the first is
   untouched (FR-002).
7. Sign in as a reviewer or uploader. `/events/imports` must be refused, and
   `curl` against `/api/events/imports` must return 403 — the route guard is
   convenience, the decorator is the control (FR-011).

### 3. Fail-closed check

This is the behavior flagged for confirmation in the spec's Assumptions, so
verify it deliberately:

```bash
docker compose exec backend chmod a-w /opt/backend/uploads/imports
```

Submit a valid CSV. Expect a clear failure message and **zero** events created
— confirm the event count in `/events/viewAll` is unchanged. Then:

```bash
docker compose exec backend chmod u+w /opt/backend/uploads/imports
```

and confirm the same CSV imports normally.

### 4. Oversize forensics check (FR-006)

```bash
head -c 12000000 /dev/urandom | base64 > /tmp/too-big.csv
```

Submit it. Expect a clear "too large" message, no events created, and **no**
`.csv` added to the archive — but `/events/imports` must show a new row marked
refused, carrying your login, the time, `too-big.csv`, and the actual size. The
row offers no download; requesting `/api/events/imports/<id>/file` for it
returns 404. The point of this check is that a mis-selected giant file cannot
pass through unrecorded.

### 5. Retention check (FR-012)

Nothing in the application deletes archived files. Confirm by inspection:
`grep -rn "os.remove\|shutil.rmtree\|unlink" flask_backend/` must return no hit
inside `import_archive.py` or the new endpoints.

## Notes

- Archived CSVs contain site patient identifiers and event dates. They are PHI
  and live in the same protected volume as event packets — never under
  `FILES_DIR`, which is mounted read-only and served statically.
- Nothing about this feature varies by study. There is no new `STUDY_TYPE`
  branch and no new workflow flag.
- Pre-existing gap noticed while working here, **not** fixed by this feature:
  `GET /api/events/download/<event_id>` (`app.py:1059`) has `@requires_auth`
  but no role decorator, so any authenticated user can fetch any event's
  packet. Worth its own issue.

---

# Amendment (2026-08-17) — verifying the feedback and button work

All of this is frontend and configuration. `frontend/package.json` declares no
test framework — only `eslint` — so verification is `npm run lint` plus the
manual walkthrough below. Per the project's working constraint, none of this can
be exercised locally; run it on the deployed stack.

## 6. Button legibility (FR-020, SC-012)

1. Load any page with a button. The button must be plainly visible as a shape
   against the page, not merely a line of text.
2. Measure it. In devtools, take the button's computed `background-color` and
   the body's `#ffffff`, and compute the ratio — or use the Accessibility pane's
   contrast readout on the label.
   - Button vs. page background: **≥ 3:1**
   - Label vs. button fill: **≥ 4.5:1**
   Record both numbers; SC-012 is a measurement, not an impression.
3. Tab to the button. The focus ring must be clearly visible.
4. Hover it. The hover state must be distinguishable from rest.
5. Repeat on two other pages — one under `pages/`, one under `studies/vte/` —
   to confirm the single `index.css` rule reached everything.

## 7. In-flight indication (FR-013, FR-014, SC-009, SC-010)

Use a CSV large enough to take several seconds; on the deployed stack a few
hundred rows suffices, since each row costs a federated patient lookup
(research D12).

1. Submit it. Immediately confirm, without waiting:
   - the Add button is disabled and reads as busy,
   - a spinner is visible,
   - an elapsed-seconds counter is incrementing.
2. While it runs, click Add repeatedly and press Enter in the form. Open the
   Network pane and confirm **exactly one** `POST /api/events/bulk` was sent.
3. When it resolves, confirm the spinner and counter are gone and the button is
   usable again.
4. There must be no interval longer than a second with no sign of activity
   (SC-009) — watch the counter, which is the evidence.

## 8. Honest outcome reporting (FR-015, FR-016, SC-008)

This is the important one. Verify all six classifications.

| To produce | Do this | Expect |
|---|---|---|
| `imported` | A CSV where every row is valid | Count of events created |
| `partial` | The valid/invalid mix from step 2 of the original walkthrough | Both counts and every skipped row |
| `nothing` | A CSV where every `site_patient_id` is unknown | The server's reason plus the per-row messages |
| `refused` | A file over `MAX_IMPORT_CSV_BYTES` | The oversize message and the limit |
| `undetermined` | See below | "Outcome unknown", a link to the history, and **no** use of the word "failed" |
| `network` | Devtools → offline, then submit | "Request did not complete", link to the history |

**Forcing `undetermined`** — reproduce the original defect deliberately. Easiest
is devtools request interception: override the response to
`POST /api/events/bulk` with `Content-Type: text/html` and any HTML body, status
200. The panel must report an undetermined outcome. Then check the import list:
the record is there, and it says what actually happened.

**SC-008 is the regression this whole amendment exists for.** Run the
`undetermined` case against an import that genuinely succeeds, then confirm in
the database that the events were created *and* that the UI never said
"failed". Before this change, that combination printed "CSV upload failed."

## 9. The result panel (FR-017, FR-018, SC-011)

1. Run an import where **every** row fails — several hundred rows with bad
   patient ids. This is the case from the original report.
2. The result must render each skipped row on its own line in a scrollable
   region, not joined onto one line.
3. Leave it alone for a minute. It must still be there — nothing removes it on a
   timer.
4. Select its text with the mouse: it must be selectable. Then use the copy
   control and paste elsewhere: the full list must come through.
5. Take a screenshot. The result must be legible in it — this is the literal
   user requirement.
6. Dismiss it. It must disappear and not return.
7. Follow the "view this import" link. It must land on
   `/events/imports?import_id=<id>` with **that** record already open
   (research D19).

## 10. Persistent notifications app-wide (FR-017, D15 scope note)

59 of the 77 `showToast` call sites become persistent. Spot-check that this did
not create a nuisance:

1. Trigger an `error` toast on any page — a `403` from a non-admin action works.
   It must stay until dismissed and offer a dismiss control reachable by keyboard.
2. Trigger a `success` toast — a normal save. It must still auto-dismiss.
3. Trigger several errors in a row on one page. The container must scroll or cap
   rather than growing a column off the top of the viewport.

## 11. Timeout headroom (FR-019, SC-013)

1. Confirm `frontend/vite.config.js` now declares `server.proxy` for `/api`
   with an explicit long timeout.
2. Request an `/api/...` path directly against the `web` service. It must **not**
   return HTML. Before this change it returned the Vite-transformed
   `index.html`, which is the whole of research D10.
3. Run an import at or near the size cap and confirm it completes without the
   request being cut off.
4. **If step 3 still fails**, the remaining timeout is at the Apache edge, whose
   vhost is managed in a separate repository (research D11). Raise `ProxyTimeout`
   there. This is a deployment action and cannot be done from this repo — but
   note that FR-015 means the administrator now gets an honest "outcome unknown"
   rather than a false failure even while it remains unfixed.

## Notes (amendment)

- **No backend change in this amendment.** No route, request, or response shape
  moves, so `openapi.json` does **not** need regeneration this time — unlike the
  original 009 work, which did. Confirm with `git diff --stat flask_backend/`
  showing no change to routes before merging.
- The ~60-second import is **not** fixed here. It is one federated patient
  lookup per row over the SSH-tunnelled bridge (research D12). Batching those
  lookups is the obvious next improvement and is deliberately out of scope; the
  requirement was to communicate the wait, not remove it.
- `frontend/src/studies/vte/EventAddMany.jsx` is **out of scope** (research
  D18) — legacy, reachable only by typing `/vte`, and not linked from any shared
  page. It is not modified. It does inherit the button and notification changes,
  since those are shared definitions, so its false-failure message now persists
  instead of vanishing. Expected, not a regression to chase. Settling the VTE
  tree's status per Principle VI is a separate follow-up.
