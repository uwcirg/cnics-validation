# Data Model: Archive Bulk-Import CSV Files

**Feature**: `009-save-import-csv` | **Date**: 2026-08-13

**No database schema change.** The archive is a filesystem structure. This
document defines its layout, the manifest shape, the id grammar, and the
validation and lifecycle rules that the implementation must hold to.

---

## Storage layout

```text
<DOWNLOADS_DIR>/                       # /opt/backend/uploads in the compose stack
├── 4711.zip                           # existing: scrubbed packet for event 4711
├── orig_4711_837261234.zip            # existing: raw packet for event 4711
└── imports/                           # NEW — created on demand, mode 0o750
    ├── 20260813T144512Z-7f3a1c2b.csv  # verbatim submitted bytes
    ├── 20260813T144512Z-7f3a1c2b.json # manifest for that submission
    ├── 20260813T151003Z-91e0d4f5.csv
    └── 20260813T151003Z-91e0d4f5.json
```

The directory is resolved once at import time as
`IMPORT_ARCHIVE_DIR` if set, else `<DOWNLOADS_DIR>/imports`, mirroring how
`DOWNLOADS_DIR` itself resolves (`app.py:101-104`). Creation is attempted
lazily on each write and tolerates `FileExistsError`; unlike `DOWNLOADS_DIR`,
an `OSError` here is **not** swallowed — it propagates so the request can fail
closed (research D3).

---

## Entities

### Archived submission

The verbatim bytes of one uploaded CSV. Not parsed, not re-encoded, not
normalized — byte-for-byte what the client sent (SC-003). Present for every
submission the system attempts to process; absent only for one refused over the
size cap, which leaves a manifest alone.

| Property | Type | Notes |
|---|---|---|
| `import_id` | string | The filename stem; see id grammar below |
| bytes | binary | The submission, unmodified |

**Rules**:

- Written with exclusive creation (`os.open(..., O_CREAT \| O_EXCL \| O_WRONLY)`)
  so an id collision raises rather than overwriting (FR-002).
- Written **before** the file is decoded or parsed, so submissions that are not
  valid UTF-8 or not valid CSV are still archived (FR-001).
- Never deleted, moved, or rewritten by the application (FR-012).

### Import record (manifest)

One JSON object per submission, written after the import completes.

| Field | Type | Required | Notes |
|---|---|---|---|
| `record_version` | integer | yes | `1`. Readers must tolerate unknown higher values by rendering what they understand |
| `import_id` | string | yes | Matches the file stem; redundant on purpose so a copied manifest is self-describing |
| `submitted_at` | string | yes | ISO 8601 UTC, e.g. `2026-08-13T14:45:12Z` |
| `submitted_by_id` | integer \| null | yes | `users.id` of the submitter; null only if the identity could not be resolved |
| `submitted_by` | string \| null | yes | Login name, for display without a `users` join |
| `original_name` | string | yes | Filename as submitted, truncated to 255 chars. May contain identifying text — treated as PHI |
| `size_bytes` | integer | yes | Length of the submission. For a refused submission this is the size that got it refused, not the size stored (which is zero) |
| `outcome` | enum | yes | `imported` \| `partial` \| `rejected` \| `refused` — see derivation below |
| `file_available` | boolean | yes | Whether the contents were archived. `false` only on `refused` |
| `imported_count` | integer | yes | Events created; matches the response `imported` |
| `skipped_count` | integer | yes | `len(errors)` |
| `errors` | array of string | yes | Skipped-row messages verbatim as returned to the client; `[]` when none |

**`outcome` derivation** (no independent state; a pure function of what
happened):

| Condition | `outcome` | `file_available` |
|---|---|---|
| Over the size cap — contents never read into the archive | `refused` | `false` |
| `imported_count` > 0, `skipped_count` = 0 | `imported` | `true` |
| `imported_count` > 0, `skipped_count` > 0 | `partial` | `true` |
| `imported_count` = 0 | `rejected` | `true` |

`rejected` covers every zero-event case where the file *was* archived: a decode
failure, a file with no usable rows, an empty file, and a DB error. The reason
appears in `errors`. `refused` is the one case where the submission was turned
away before its contents were kept; its single `errors` entry names the limit
and the actual size.

**Rules**:

- Written once, after the import attempt, whatever its outcome (FR-003).
- A failed manifest write does not fail the request (research D8).
- Never contains file contents beyond the `errors` strings the client already
  received.

### Skipped row entry

Not a separate stored entity — an element of `errors`, in file order, carrying
the exact text `events_bulk` already produces, e.g.
`Line 4: expected at least site_patient_id, site_name, event_date`. Persisting
them verbatim is what makes SC-004 checkable against what the administrator saw
on screen.

---

## Id grammar

```text
import_id  ::= <date> "T" <time> "Z-" <suffix>
date       ::= 8 digits, UTC YYYYMMDD
time       ::= 6 digits, UTC HHMMSS
suffix     ::= 8 lowercase hex digits (uuid4().hex[:8])

regex      ::= ^\d{8}T\d{6}Z-[0-9a-f]{8}$
```

Properties this buys:

- **Lexical sort = chronological sort**, so "newest first" is `sorted(...,
  reverse=True)` over filenames with no manifest reads (FR-009).
- **Collision-safe** for concurrent same-second submissions (FR-002).
- **Non-identifying** — no original filename, no site, no patient id (spec edge
  case).
- **Path-safe by construction** — the regex admits no `/`, `.`, or `\`, so a
  validated id cannot traverse (research D6).

Every client-supplied id **MUST** be matched against the regex before any
`os.path.join`, and the joined real path **MUST** be asserted to sit inside the
resolved archive directory. A failure of either check returns 404.

---

## Relationships

```text
Archived submission  1 ──── 0..1  Import record      (same stem; manifest may be absent)
Import record        1 ──── 0..n  Skipped row entry  (inline in `errors`)
Import record        1 ──── 0..n  Events             (by count only — no stored link)
```

There is deliberately **no** foreign key from `events` to an import. Linking an
individual event back to the import that created it is listed as out of scope
in the spec; adding it would require an `events` column and therefore a
migration.

---

## Derived read model

The directory holds three shapes, and the reader must handle all three:

| On disk | Meaning | Rendered as |
|---|---|---|
| `.csv` + `.json` | Normal — processed submission with its outcome | The manifest as written |
| `.csv` only | Manifest write failed after the import (research D8) | Degraded record, `outcome: unknown` |
| `.json` only | Refused over the size cap before archiving (research D5) | The manifest, `outcome: refused` |

`list_records(dir, limit, offset)` returns, newest first:

1. Glob `*.csv` **and** `*.json`, take the **union** of stems, sort descending
   (chronological, per the grammar). Globbing only `*.csv` would write refused
   submissions and then never show them.
2. Slice by `offset`/`limit`.
3. For each, read the sibling `.json`; on missing or unparseable manifest,
   synthesize a **degraded record**:

```json
{
  "record_version": 1,
  "import_id": "20260813T144512Z-7f3a1c2b",
  "submitted_at": "2026-08-13T14:45:12Z",
  "submitted_by_id": null,
  "submitted_by": null,
  "original_name": null,
  "size_bytes": 4213,
  "outcome": "unknown",
  "file_available": true,
  "imported_count": null,
  "skipped_count": null,
  "errors": [],
  "incomplete": true
}
```

`submitted_at` and `size_bytes` are recoverable from the id and a `stat`, so a
degraded record is still useful and still downloadable. `incomplete: true` is
present only on degraded records; the UI labels the row so it is not mistaken
for a genuine zero-event import.

A `refused` record is the mirror image: the manifest is complete and
authoritative, but `file_available` is `false`, so the UI offers no download
and `GET /api/events/imports/<id>/file` returns 404.

---

## Configuration

| Variable | Default | Purpose |
|---|---|---|
| `IMPORT_ARCHIVE_DIR` | `<DOWNLOADS_DIR>/imports` | Override the archive location, e.g. to a separately backed-up mount |
| `MAX_IMPORT_CSV_BYTES` | `10485760` (10 MB) | Reject larger submissions before archiving (FR-006) |

Both are optional with working defaults, and both must be listed explicitly
under the `backend` service in `docker-compose.yaml` — Compose reads `.env` for
`${...}` substitution but does not auto-inject variables into containers, as
the comment at `docker-compose.yaml:71-74` warns.

---

## Retention

None automated. Nothing in the application deletes, truncates, rotates, or
overwrites an archived file or manifest (FR-012). Growth is bounded in practice
by usage: a few submissions a month at a few hundred KB each. Purging, if ever
needed, is an operator action on the volume.

---

# Amendment (2026-08-17) — import feedback state

Nothing below is persisted. The archive layout, manifest, and id grammar above
are unchanged; this section models the **client-side state** the bulk-import
page holds while a submission is in flight and after it resolves. It exists
because the spec added "Import outcome, as shown" as an entity distinct from
the stored import record — the record is always written by the server, whereas
the shown outcome may be `undetermined` precisely when the client could not
learn what the record says.

## Submission state (client)

One page-level state machine, from the moment the administrator submits:

```text
idle ──submit──▶ submitting ──response──▶ resolved(outcome) ──dismiss──▶ idle
                      │
                      └──fetch threw──▶ resolved(network)
```

| Field | Type | Meaning |
|---|---|---|
| `phase` | `'idle' \| 'submitting' \| 'resolved'` | Drives the form's disabled state and which region renders |
| `startedAt` | epoch ms, set on submit | Origin for the elapsed counter (FR-013) |
| `elapsedSeconds` | integer, ticks while `submitting` | Displayed; interval cleared when the phase leaves `submitting` |
| `outcome` | see below, set on resolve | Which of the five results occurred (FR-016) |
| `result` | outcome payload, below | What the panel renders |

`phase === 'submitting'` is the single source for both the spinner and the
submit button's `disabled` attribute, so FR-013 and FR-014 cannot drift apart.

## Outcome, as shown

Exactly one of five, assigned by the ordered classification in research D14:

| `outcome` | When | Carries |
|---|---|---|
| `imported` | Parsed JSON, `imported > 0`, no `errors` | count, `import_id` |
| `partial` | Parsed JSON, `imported > 0`, `errors` non-empty | count, skipped count, ordered `errors[]`, `import_id` |
| `nothing` | Parsed JSON, `imported === 0` | server's reason, any `errors[]`, `import_id` |
| `refused` | HTTP 413 | server's reason, `import_id` |
| `undetermined` | Non-JSON `Content-Type`, or `res.json()` threw | no counts — the client has no evidence either way |
| `network` | `fetch` itself threw | no counts, no `import_id` |

Six rows, five *server-answered* outcomes plus `network`: FR-016 enumerates the
five the server can produce; a request that never completed is the sixth and is
reported as its own case rather than folded into `undetermined`, because the
administrator's next action differs — retry, versus go check the history.

**Invariant**: `undetermined` and `network` MUST NOT render any word asserting
failure of the import (FR-015). They assert only what is true: the client does
not know the outcome.

## Result payload

| Field | Type | Source | Notes |
|---|---|---|---|
| `importedCount` | integer \| null | `data.imported` | `null` when unknown |
| `skippedCount` | integer \| null | `data.errors.length` | `null` when unknown |
| `errors` | string[] | `data.errors` | Order preserved from the server; one entry per skipped row, rendered one per line (FR-018) |
| `importId` | string \| null | `data.import_id` | Feeds the record deep link (research D19) |
| `reason` | string \| null | `data.error` | The server's own message, shown verbatim |

`errors` is rendered as a list, never joined into a single string — the joining
at `EventAddMany.jsx:31-33` is what made the observed result unreadable. The
region is scrollable and its full text is what the copy control emits.

## Relationship to the stored record

| Shown outcome | Stored manifest `outcome` | Note |
|---|---|---|
| `imported` / `partial` | `imported` | Counts agree |
| `nothing` | `rejected` | Counts agree |
| `refused` | `refused` | Contents not archived (009 FR-006) |
| `undetermined` | any of the above | **The record is authoritative**; the client simply did not receive it |
| `network` | any, or none written | The request may not have reached the server at all |

The last two rows are the reason FR-018 requires a link into the import history:
for those outcomes the panel's job is not to report the result but to route the
administrator to where the result actually lives.

## Notification state (shared)

`components/Toast.js` gains per-entry state it did not have:

| Field | Derivation | Effect |
|---|---|---|
| `persistent` | `type === 'warning' \| 'error'` | Suppresses the auto-removal timer; renders a dismiss control (FR-017) |
| `dismissed` | user action | Removes the entry |

The container caps simultaneously visible persistent entries and evicts oldest
first (research D15). No call site supplies or reads these fields — they are
derived from the existing `type` argument, which is why all 77 call sites are
unaffected.
