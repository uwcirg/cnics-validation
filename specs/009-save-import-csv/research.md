# Phase 0 Research: Archive Bulk-Import CSV Files

**Feature**: `009-save-import-csv` | **Date**: 2026-08-13

All Technical Context unknowns are resolved below. Decisions are numbered D1–D9
and referenced from plan.md, data-model.md, and quickstart.md.

---

## D1. Current behavior (recorded before modification, per Principle VI)

**Finding**: Non-persistence is deliberate, not accidental.

`POST /api/events/bulk` (`flask_backend/app.py:1249`) does:

```python
file = request.files['events_csv']
text = file.read().decode('utf-8-sig')     # app.py:1276
reader = csv.reader(io.StringIO(text))     # app.py:1287
```

There is no `file.save()` anywhere in the handler. Werkzeug spools the upload
to a `SpooledTemporaryFile` once it exceeds its in-memory threshold, but that
is deleted when the request ends and is not an artifact.

More importantly, `flask_backend/tests/test_app.py:152` is named
`test_bulk_csv_upload_does_not_persist_file` and asserts:

```python
assert not any(p.name == 'events.csv' for p in tmp_path.iterdir())
```

So someone once chose non-persistence and guarded it. This feature reverses
that choice; the test must be rewritten rather than silently invalidated (D7).

Also recorded: skipped-row reasons are returned in the response `errors` array
(`app.py:1356-1357`) and rendered by `frontend/src/pages/EventAddMany.jsx:31-38` as
a single 10-second toast. Nothing persists them.

**Decision**: Treat the reversal as an explicit, documented change of intent,
carried in the rewritten test's name and docstring.

---

## D2. Where the import record lives — JSON sidecar vs. new database table

**Decision**: A JSON manifest file written beside the archived CSV. No new
table.

**Rationale**:

- **No migration burden.** There is no migration framework in the repo — a
  `find` for `*migrat*` returns nothing, and `init/02-schema.sql` is a
  `mysqldump` with `DROP TABLE IF EXISTS`, i.e. a fresh-install script only. A
  new table would need a hand-written `ALTER`/`CREATE` script applied to every
  live study deployment, which the constitution requires to ship with a
  migration plan. The sidecar needs none.
- **The file and its record cannot diverge.** The CSV must live on disk
  regardless; a DB record would split one fact across two stores with no
  transaction spanning them. Keeping both in one directory means a backup, a
  restore, or a volume copy moves them together.
- **Study isolation comes for free.** The uploads volume is already per
  deployment (Principle II), so no new isolation claim is needed.
- **Scale is trivially adequate.** SC-007 asks for usability at 500 imports.
  Listing is a directory scan plus 500 reads of a few hundred bytes, served
  from page cache. Bulk imports happen a few times a month, not a second.

**Alternatives considered**:

- *New `event_imports` + `event_import_errors` tables.* Gives SQL querying and
  a real join to `users`. Rejected: the querying is not needed (one flat list,
  newest-first), and the cost is a hand-rolled migration against six live
  schemas plus two new models. Revisit if imports ever need filtering by
  submitter or date range at scale.
- *Reuse the legacy `logs` table.* `models.Logs` exists
  (`flask_backend/models.py:89`) but is written by nothing in the Flask backend
  — it is dormant CakePHP-era furniture. Its `params varchar(1000)` cannot hold
  a skipped-row list, and putting PHI-adjacent row text into a general audit
  log is exactly what the PHI rule warns against. Rejected.

---

## D3. Behavior when the archive cannot be written

**Decision**: Fail closed. The archive write happens **before** parsing; if it
raises, the request returns 500 with a clear message and **no events are
created**.

**Rationale**: An unarchived bulk import is the precise situation this feature
exists to prevent, and it is invisible after the fact — nobody would know the
archive was skipped. Refusing is loud, recoverable, and retryable once storage
is fixed. Ordering the write first also means a file that fails to decode as
UTF-8 is still archived, which spec scenario 3 requires.

**Cost, stated plainly**: a full or read-only uploads volume now blocks event
creation via CSV. Single-event creation through `/events/add` is unaffected, so
there is a workaround. This is flagged in the spec's Assumptions for
confirmation.

**Alternatives considered**:

- *Best-effort archive, import proceeds, warning logged.* Never blocks work.
  Rejected: it silently reintroduces the unauditable import, and the warning
  lands in a log nobody reads at the moment it matters.
- *Archive after a successful import.* Would leave rejected submissions
  unarchived, contradicting FR-001 and the "reasons are unrecoverable" problem
  in the spec's Context.

---

## D4. Archive location and filename grammar

**Decision**: `<DOWNLOADS_DIR>/imports/`, with names
`<YYYYMMDD>T<HHMMSS>Z-<8 hex>.csv` and a matching `.json`.

Example: `20260813T144512Z-7f3a1c2b.csv`.

**Rationale**:

- **`DOWNLOADS_DIR`, not `FILES_DIR`.** `FILES_DIR` is read-only by
  constitutional rule and mounted `:ro` in `docker-compose.yaml:87`; writing
  there is defined as a bug. `DOWNLOADS_DIR` resolves to `UPLOAD_DIR` →
  `/opt/backend/uploads`, backed by the `cnics-downloads` named volume
  (`app.py:101-104`, `docker-compose.yaml:47,88`).
- **A subdirectory, not the root.** `events_download` (`app.py:1059`) scans
  `DOWNLOADS_DIR` for `<event_id><ext>` across `ALLOWED_PACKET_EXTENSIONS`.
  Keeping imports in `imports/` guarantees the two namespaces can never
  interfere in either direction, no matter how the naming schemes evolve.
- **UTC timestamp prefix** makes the directory listing chronological under a
  plain lexical sort, so "newest first" costs no manifest reads to order.
- **Random suffix** resolves same-second collisions between concurrent
  submissions (spec edge case, acceptance scenario 4). Second-precision
  timestamps alone are not unique; `uuid4().hex[:8]` makes a collision
  vanishingly unlikely, and the writer uses exclusive creation (`O_EXCL`) so a
  collision fails loudly rather than overwriting.
- **The original file name is not in the stored name.** Administrators name
  files after sites and patients; the stored name is deliberately
  non-identifying, and the original name is carried inside the manifest, which
  has the same access protection as the file contents.

**Alternatives considered**: a per-day subdirectory tree (`imports/2026/08/`)
— rejected as premature for a few imports a month; and using the original
filename with a numeric suffix — rejected because it puts potentially
identifying text in a name that appears in directory listings and logs.

---

## D5. Enforcing the size cap

**Decision**: A per-endpoint check in `events_bulk` against
`MAX_IMPORT_CSV_BYTES` (default 10,485,760). **Not** Flask's global
`MAX_CONTENT_LENGTH`.

**Rationale**: `MAX_CONTENT_LENGTH` is not currently set anywhere — a grep of
`flask_backend/` finds no reference, so there is no upload size limit today at
all. Setting it globally would also cap `upload_raw` and `upload_scrubbed`,
which carry chart packets that are legitimately large ZIPs; a 10 MB global cap
would break packet upload. The check is therefore local to the CSV endpoint.

**Mechanics**: check `request.content_length` first for an early 413 without
reading the body, then re-check the actual byte length after reading, because
`Content-Length` is client-supplied and may be absent under chunked transfer.
The response is 413 with a message naming the limit, so the administrator
learns what to do.

**A refused submission still gets a manifest.** The contents are not kept, but
a manifest with `outcome: 'refused'` and `file_available: false` is written,
carrying submitter, timestamp, original name, actual size, and the reason. The
first cut of this design wrote nothing at all on a 413, which left the one
event most worth investigating — someone posting a 200 MB file at the events
endpoint — completely invisible. That is the opposite of the feature's purpose.
Keeping the record while discarding the bytes preserves the cap's reason for
existing (bounded storage) at the cost of a few hundred bytes per refusal.

This makes the archive directory hold three shapes, all of which the reader
must handle (see D8 and data-model.md § Derived read model):

| On disk | Meaning |
|---|---|
| `.csv` + `.json` | Normal — processed submission with its outcome |
| `.csv` only | Manifest write failed after the import (D8); `outcome: unknown` |
| `.json` only | Refused before archiving; `outcome: refused` |

Consequence for the reader: `list_records` must glob the **union** of `*.csv`
and `*.json` stems, not just `*.csv`, or refused submissions would be written
and then never shown.

---

## D6. Path-traversal safety on the read endpoints

**Decision**: Validate every client-supplied import id against
`^\d{8}T\d{6}Z-[0-9a-f]{8}$` before it touches `os.path.join`. A
non-conforming id returns 404, not 400 — an invalid id and a missing record are
indistinguishable to the caller.

**Rationale**: `GET /api/events/imports/<import_id>` and its `/file` sibling
take a string straight from the URL. Without validation, `../../` or an
absolute path could escape the archive directory and stream arbitrary files —
including event packets or, worse, anything readable in the container. The id
grammar is fully machine-generated (D4), so a strict whitelist costs nothing.
Belt and braces: after joining, assert the resolved real path is inside the
resolved archive directory.

**Related existing gap, deliberately out of scope**: `events_download`
(`app.py:1059`) carries `@requires_auth` but **no role decorator**, so any
authenticated user can download any event's packet by id. That predates this
feature and touching it would widen the diff; noted here so it is not lost.

---

## D7. What happens to `test_bulk_csv_upload_does_not_persist_file`

**Decision**: Rewrite it as `test_bulk_csv_upload_archives_rejected_file`,
asserting the new intent — a rejected submission still leaves exactly one
`.csv` in the archive directory.

**Rationale**: This is a landmine. The existing assertion is
`not any(p.name == 'events.csv' ...)`, and under the new naming scheme
(D4) the archived file is *never* named `events.csv`. The test would keep
passing while asserting the opposite of the shipped behavior — a green suite
lying about a reversed decision. It must be rewritten deliberately, not left
alone because it happens to pass.

The test also confirms the archive is testable: it monkeypatches
`DOWNLOADS_DIR` to `tmp_path` and posts a malformed CSV that fails validation
before any DB access, so no database is needed for archive assertions.

---

## D8. Manifest write failure, and the MyISAM rollback that isn't

**Decision**: If the manifest write fails after events were created, log a
warning (id and error class only — no PHI) and still return success. The list
view renders a `.csv` with no `.json` as an incomplete record: timestamp and
downloadable file, outcome shown as unknown.

**Rationale**: The alternative is undoing the import, which is not possible.
`events`, `criterias`, and every other shared table are `ENGINE=MyISAM`
(`init/02-schema.sql:35,127`), which has no transactions. The handler's
docstring claims valid rows "are imported in a single transaction" and its
`except` branch calls `session.rollback()` (`app.py:1349`) — on MyISAM that
rollback silently does nothing and already-inserted rows stay. This is
pre-existing behavior, recorded here per Principle VI and not changed by this
feature, but it rules out a two-phase commit between the DB and the manifest.

Making the reader tolerant of a missing manifest is the honest response: the
irreplaceable artifact (the bytes) is already safe, and the manifest is
reconstructible by eye from the CSV and the events it produced.

---

## D9. Frontend placement, and the VTE fork

**Decision**: One new shared page, `frontend/src/pages/EventImports.jsx`, at
`/events/imports`. **No** copy under `frontend/src/studies/vte/`.

**Rationale**: `frontend/src/studies/vte/EventAddMany.jsx:17` posts to the same
`/api/events/bulk` endpoint as the shared page, so VTE submissions are archived
with no frontend change — the backend is the only thing that had to move. The
VTE directory is a legacy fork that Principle I treats as debt to be reduced,
not extended; adding a ninth forked page to reach a study-agnostic list would
make it worse. VTE administrators reach the shared page by URL and via the
shared `Admin.jsx` link.

The page reuses `components/DataTable.jsx` (sortable, paginated at 20 rows,
already used by the event lists), so SC-007's "usable at 500 imports" is
satisfied by the existing pagination rather than new code.

---

## Resolved unknowns summary

| Unknown | Resolution |
|---|---|
| Storage backend for the record | JSON sidecar; no schema change (D2) |
| Storage location | `<DOWNLOADS_DIR>/imports/` (D4) |
| Failure semantics | Fail closed on archive; tolerant on manifest (D3, D8) |
| Size limit mechanism | Per-endpoint check, not global (D5) |
| Path safety | Strict id whitelist + containment assert (D6) |
| Existing test disposition | Rewritten, not deleted (D7) |
| Study-specific handling | None needed; no VTE fork (D9) |
| Migration plan | Not applicable — no schema change (D2) |
