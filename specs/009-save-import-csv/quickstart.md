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
