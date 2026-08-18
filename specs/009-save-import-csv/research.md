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

---

# Phase 0 (amendment, 2026-08-17) — import feedback and button legibility

Decisions D10–D19 cover the spec amendment. D1–D9 above are unchanged.

## D10. Why a successful import reported "CSV upload failed" (recorded before modification, per Principle VI)

**Observed**: The administrator's browser received, as the response to
`POST /api/events/bulk`, the application's own HTML shell. The captured body
contains `import { injectIntoGlobalHook } from "/@react-refresh"` and
`<script type="module" src="/@vite/client">`. Neither string exists in any file
in this repository — both are injected at request time by the **Vite dev
server**. The response therefore came from the `web` service, not from Flask
and not from a static build.

**Mechanism**: `frontend/default.env:5` sets `VITE_API_URL=` (empty), so
`EventAddMany.jsx:7` resolves `apiUrl` to `''` and posts **same-origin** to
`/api/events/bulk` — at the frontend origin. `frontend/vite.config.js` declares
no `server.proxy`, so any `/api/...` request that actually arrives at the Vite
dev server falls through its history-fallback middleware and is answered with a
200 and the transformed `index.html`. For the app to work at all the edge must
normally proxy `/api` to `backend`; the observed body is what comes back when
that proxy does not handle the request and it lands on the SPA fallback instead.
A read timeout on a ~60-second request is the failure mode that fits: the
backend keeps working and commits, while the client is handed the fallback page.

**Why the UI called it a failure**: `EventAddMany.jsx:20-25` wraps
`await res.json()` in a `try` and, on any parse error, assigns `body = {}`.
`imported` then defaults to `0` at line 26, and the `res.ok && imported > 0`
test at line 28 fails, routing an import that created every row into the error
branch at line 39. `res.ok` is never consulted on its own, and a response that
is not JSON is never distinguished from a JSON-encoded error. The database
state the administrator found — one `events` row and its `criterias` rows per
CSV line — is the correct outcome; only the message was wrong.

**Conclusion**: Two independent defects. A transport defect (the request can be
answered by the wrong server) and a reporting defect (the client asserts failure
from the absence of evidence). FR-019 addresses the first, FR-015 the second.
FR-015 is the load-bearing one: it holds even when the transport cannot be
fixed.

---

## D11. Where the read timeout is fixed

**Decision**: Two places, and only the first is in this repository.

1. **`frontend/vite.config.js` — add `server.proxy` for `/api`** with an
   explicit long `timeout` and `proxyTimeout`. This is a real fix, not a
   workaround: it makes the Vite dev server forward `/api` to `backend` instead
   of answering with `index.html`. Once present, the specific failure captured
   in D10 — an HTML shell returned for an API call — becomes impossible at that
   hop, because the path is no longer eligible for the SPA fallback.
2. **The Apache edge `ProxyTimeout` / `Timeout`** — out of tree. The
   `.htaccess` at the repository root is annotated *"Managed in
   https://gitlab.cirg.washington.edu/"* and carries no proxy directive; the
   real vhost lives in that other repository. Apache's default `Timeout` is 60
   seconds, which is the same order as the observed ~60-second import. Raising
   it is a deployment action.

**Rationale**: The clarification session recorded "the timeout may live outside
this repository" as an open risk. Investigation narrowed it: part of it is
in-tree and fixable here, part is not. Shipping (1) is worthwhile on its own —
the `web` service runs `target: development_build` in the canonical
`docker-compose.yaml:98`, so the Vite dev server is what serves study
deployments, and an unproxied `/api` path on it is a live trap.

**Alternatives considered**: Point `VITE_API_URL` at the backend origin so the
browser bypasses the frontend host entirely. Rejected — it would move the app
off same-origin onto the CORS path that `.htaccess` only allows for one
hard-coded origin, and `default.env` documents same-origin as the recommended
configuration.

---

## D12. Why 800 rows takes a minute

**Decision**: Documented, not fixed. No optimization in this feature.

**Rationale**: `app.py:1509-1513` runs one
`session.query(models.PatientsView).filter_by(...)` per CSV row. `patients_view`
is the FederatedX bridge to `cnics_data`, which in production is reached over an
SSH tunnel to a separate VM. 800 rows is 800 sequential round-trips across that
tunnel; ~75 ms each accounts for the full minute. The cost is per-row network
latency, not parsing or insertion.

A single batched lookup — collect all `(site, site_patient_id)` pairs, one `IN`
query, resolve from a dict — would very likely cut the wall-clock by an order of
magnitude and is the obvious future improvement. It is deliberately out of scope
here: it changes import semantics under concurrent modification, it needs its
own tests against the federated bridge, and the user's stated requirement is
that the interface *communicate* the wait, not that the wait disappear. Recorded
so the next person does not have to rediscover it.

**Consequence for the plan**: because the duration stays, FR-013 (in-flight
indication) and FR-019 (timeout headroom) are both load-bearing rather than
belt-and-braces.

---

## D13. In-flight indication

**Decision**: A single `submitting` boolean in the page drives three things: the
submit button's `disabled` + busy label, an indeterminate CSS spinner in a
`role="status"` live region, and an elapsed-second counter driven by a
`setInterval` started at submit and cleared in a `finally`. No percentage.

**Rationale**: The clarification chose spinner + disabled + elapsed and
explicitly rejected an estimated percentage. The server cannot report progress
within one synchronous request, so any bar would be a guess that drifts against
the per-row federated latency measured in D12. An elapsed counter is honest, is
free to compute, and is what distinguishes "working" from "hung" over a
60-second wait where a static spinner reads as frozen.

`disabled` on the submit button is also the FR-014 mechanism: it removes the
double-submit path structurally rather than by guarding a flag inside the
handler, so a double-click cannot slip between the check and the `fetch`.

**Alternatives considered**: `AbortController` with a client-side timeout —
rejected, because aborting the request does not abort the import, which
continues on the server; the client would then be certain of nothing while the
server commits, which is the D10 failure with extra steps.

---

## D14. Classifying the response

**Decision**: Classify every completed request into exactly one of five
outcomes, in this order, before any message is composed:

| Order | Test | Outcome |
|---|---|---|
| 1 | `fetch` itself threw | `network` |
| 2 | `res.status === 413` | `refused` |
| 3 | response `Content-Type` is not JSON, **or** `res.json()` throws | `undetermined` |
| 4 | parsed, `imported > 0` | `imported` (with `errors[]` → partial) |
| 5 | parsed, `imported === 0` | `nothing` (with the server's reason) |

**Rationale**: This is the FR-015 / FR-016 core. The decisive change is that a
non-JSON body is its own outcome rather than collapsing into failure. Checking
`Content-Type` *before* calling `res.json()` matters: it catches the D10 case on
the header rather than relying on a parse throw, which makes the classification
explicit instead of incidental. Ordering `413` ahead of the JSON test keeps the
existing oversize-refusal path (009 FR-006) intact, since that response *is*
JSON and would otherwise be reported as a plain failure.

The `undetermined` message must name the uncertainty and route to evidence:
the import may have succeeded, and `/events/imports` is where the truth is —
009 writes a record for every submission the server processed, so the answer is
always there.

**Alternatives considered**: Treat any `res.ok === false` as failure and
everything else as success. Rejected — the D10 response was `200 OK` with an
HTML body, so status alone would have reported that import as a *success* with
zero events, which is a different wrong answer.

---

## D15. Persistent notifications without rewriting 77 call sites

**Decision**: Change `frontend/src/components/Toast.js` internally and keep its
signature. `showToast(message, type, timeoutMs)` continues to work unchanged;
`warning` and `error` now ignore the timeout and render a dismiss control,
while `success` and `info` keep auto-dismissing.

**Rationale**: There are 77 `showToast` call sites across 30 files — 48
`error`, 11 `warning`, 14 `success`, 2 `info`. Changing behavior by type inside
the one shared module reaches all of them without touching any; changing the API
would mean 77 edits and a migration window. The two call sites that pass an
explicit `timeoutMs` (`EventAddMany.jsx:36` and `:43`, both about to be rewritten
anyway) are the only places the ignored argument is even visible.

**Scope note, stated plainly**: this makes **59 of 77** notifications
click-to-dismiss application-wide. That is the intent of the clarification, not
a side effect, but it is a broad behavioral change and every affected page
should be smoke-tested for a notification that now lingers where a transient one
was assumed.

**Stacking**: `BaseLayout.jsx:6` mounts `#toast-root` as a bare fixed container
at the lower right with no height bound. Persistent entries can now accumulate,
so the container gains `max-height` with `overflow-y: auto`, and the module caps
the number of simultaneously visible persistent toasts, removing the oldest
first. Without this, a page that reports several errors in a loop would grow a
column of undismissable boxes off the top of the viewport.

**Accessibility**: the dismiss control is a real `<button>` with an accessible
name, so it is keyboard-reachable; error toasts get `role="alert"`. Text is
already selectable — the current implementation sets no `user-select` — and
gains an explicit copy affordance only in the import result (D16), not in every
toast.

---

## D16. Where the import result is shown

**Decision**: Not in a toast. The bulk-import page renders a dedicated result
panel in the page body, below the form. The toast for the import result is
dropped.

**Rationale**: FR-018 requires a summary, every skipped row on its own line in a
scrollable region, a copy-all control, and a link to the import record. A
lower-right toast is the wrong container for all four: it is narrow, it is not
scrollable, and the observed failing file produced hundreds of error strings,
which the current code joins with `'; '` into one line
(`EventAddMany.jsx:31-33`) — the direct cause of the unreadable dialog in the
screenshot. A panel in the normal document flow can be sized, scrolled,
selected, and screenshotted, and it stays where the administrator is already
looking.

Persistent toasts (D15) remain the right mechanism for the many other pages that
report a one-line error. The two mechanisms are complementary: D15 fixes the
general case, D16 handles the one result that is a structured document rather
than a sentence.

**Copy control**: `navigator.clipboard.writeText` with the panel's text
content, falling back to leaving the text selected if the clipboard API is
unavailable — it requires a secure context, which the deployment has
(`SSLRequireSSL` in `.htaccess`), but a fallback costs nothing.

**Alternatives considered**: Expand the toast into a resizable dialog. Rejected
as building a modal system for one screen when the page already has the room.

---

## D17. The button style

**Decision**: Replace the rule at `frontend/src/index.css:39-55` with a single
solid, bordered button style plus explicit `:hover`, `:focus-visible`,
`:disabled`, and `:active` states. No new classes, no variants, no markup
changes anywhere.

**Rationale**: The current rule is the unmodified Vite scaffold —
`background-color: #f9f9f9` with `border: 1px solid transparent` on the
`#ffffff` body set at `index.css:8`. That is a contrast ratio of about
**1.05:1** against the page, far below the 3:1 that FR-020 requires; the button
is effectively invisible except for its text, which is exactly what the
screenshot shows.

The survey that makes this a one-file change: **90 `<button>` elements across 42
files**, and `index.css` is the *only* stylesheet in the repository containing a
`button` rule. Just four buttons carry a `className` at all
(`EventViewAll.jsx:97,99` and the VTE copy at `:89,91`, using `hide`/`show`),
and no CSS defines those classes, so nothing overrides the element rule and
nothing is at risk of a specificity conflict. One rule reaches every button in
the application.

A `:disabled` state is newly required rather than merely nice: FR-013 disables
the Add button during import, and the scaffold rule defines no disabled
appearance, so without it the button would look identical while inert.

**Colors**: a mid-dark blue fill with white text clears both thresholds with
margin (≥3:1 fill-to-page, ≥4.5:1 text-to-fill) and keeps the existing visual
language, which already uses blue for links and for the info toast
(`Toast.js:14`). Exact values are chosen and *measured* during implementation
rather than asserted here — FR-020 is stated as a measurement, and the
verification step records the computed ratios.

**Alternatives considered**: Primary/secondary variants, offered during
clarification and declined. Recorded so the choice is not silently revisited:
it would require auditing all 90 buttons and adding classes, for hierarchy the
user did not ask for.

---

## D18. The VTE fork's copy of the same bug

**Decision**: **Out of scope.** `frontend/src/studies/vte/EventAddMany.jsx` is
not modified. It inherits the button restyle (D17) and the notification change
(D15) automatically, because those are shared, and receives nothing else.

**Rationale**: The VTE tree is legacy the project does not intend to use. It is
reachable only by typing `/vte` — `App.jsx:140` registers the route with no
`STUDY_TYPE` gate, but no shared page links into it; the only links to
`/vte/addMany` are `studies/vte/Admin.jsx:21` and `studies/vte/Home.jsx:149`,
both inside the fork itself. Route registration is not use.

An earlier draft of this decision put the page in scope on the grounds that
FR-015 is written without qualification. That was the wrong reading. The spec
governs the system the project intends to run; applying a requirement to a
subsystem slated for retirement converts a requirement into busywork and grows
the fork's surface at the moment it should be shrinking. Principle VI's unused
subsystem hygiene gives the correct treatment for code in this state —
*document as unused, or remove* — and neither is "bring it up to spec".

**What it inherits anyway**: the button rule in `index.css` and the behavior
change in `Toast.js` are single shared definitions, so the VTE page's button
becomes legible and its error notification becomes persistent whether or not
anyone wants that. This is free and unavoidable, not scope. Its "CSV upload
failed. Please check the file and try again." message at line 28 keeps the
false-failure bug, now displayed persistently rather than briefly. That is
acceptable for a page nobody is meant to open, and is one more reason to
resolve the fork's status.

**Consequence for the shared modules**: with a single consumer, the extraction
in D16/D13 is no longer required by Principle I. It is retained on narrower
grounds — the six-way classification plus the timer state machine is
branch-heavy logic worth keeping out of a page component, and worth being
importable if a frontend test framework is added later. Had the logic been
trivial, it would now belong inline in the page.

**Follow-up, not in this feature**: the VTE tree's status should be settled
explicitly — documented as unused or removed — per Principle VI. Twenty-four
`studies/vte/*` modules are imported and routed unconditionally in `App.jsx`,
which is what made the fork look live during this research. Worth its own
issue.

---

## D19. Making "view this import" a real link

**Decision**: `frontend/src/pages/EventImports.jsx` accepts an
`?import_id=<id>` query parameter and opens that record's detail on load.

**Rationale**: FR-018 requires the result panel to link to *this submission's*
record. `POST /api/events/bulk` already returns `import_id` in its response body
(added by 009), so the page has the value. But `EventImports.jsx:38` holds the
selection in local `useState` with no URL binding, so today the only reachable
target is the undifferentiated list — the administrator would have to find their
own import by timestamp. Reading the parameter on mount and pre-selecting is a
small change to an existing page that turns the requirement into an actual
one-click route.

This also makes the D14 `undetermined` message useful: it can link straight to
the record for the import whose outcome the client failed to learn — except in
the one case where the client never received the id, where it links to the list.

---

## Resolved unknowns summary (amendment)

| Unknown | Resolution |
|---|---|
| Why a successful import reported failure | Vite SPA fallback + client asserting failure from unparseable body (D10) |
| Where the timeout is fixed | `vite.config.js` proxy in-tree; Apache `ProxyTimeout` out of tree (D11) |
| Why the import is slow | One federated patient lookup per row; documented, not optimized (D12) |
| Progress indication | Spinner + disabled button + elapsed counter; no percentage (D13) |
| Distinguishing outcomes | Five-way ordered classification, content-type checked first (D14) |
| Persisting notifications | Behavior by type inside shared `Toast.js`; 59 of 77 become persistent (D15) |
| Where the result renders | Dedicated in-page panel, not a toast (D16) |
| Button restyle scope | One rule in `index.css`; 90 buttons, no competing stylesheet (D17) |
| The VTE fork | Out of scope — legacy, not linked from the shared tree; inherits shared CSS/Toast only (D18) |
| Linking to the import record | `?import_id=` deep link on the existing page (D19) |
| Frontend test strategy | Unchanged — no test framework in `package.json`; manual per quickstart |
