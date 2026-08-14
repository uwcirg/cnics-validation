# Phase 0 Research: Populate event identifiers on the upload page

**Feature**: `008-upload-event-identifiers`
**Date**: 2026-08-13

This document records the current observed behavior before modification, as
required by Constitution Principle VI, and resolves the technical unknowns
carried into planning.

## Observed current behavior (pre-change)

Recorded here because Principle VI prohibits changing code whose behavior has
not first been documented.

### The upload page reads its identifiers from the address bar

`frontend/src/pages/EventUpload.jsx:120-123` resolves four values from the
query string, not from any request:

```js
const eventId = searchParams.get('event_id')
const patientId = searchParams.get('patient_id')
const date = searchParams.get('date')
const criteria = searchParams.get('criteria')
```

`EventUpload.jsx:211-218` renders them directly into the info box. The page
issues no request for event details at any point; the only fetch it performs is
the packet `POST` to `/api/events/<id>/upload_raw` (`EventUpload.jsx:164`).

### There are four ways in, and none of them work fully

| Entry route | Source | `patient_id` | `date` | `criteria` |
|---|---|---|---|---|
| "upload" action button | `EventUpload.jsx:201` | absent | absent | absent |
| Row click, needs-packets list | `EventUpload.jsx:78` | `undefined` | present | present |
| Row click, reupload list | `EventReupload.jsx:68` | `undefined` | present | present |
| Bookmark / pasted URL | user | absent | absent | absent |

The action button — the most prominent control on the row and the likeliest
path for an uploader — navigates with `event_id` alone, which is exactly the
URL shape in the bug report.

The row-click paths look better but are not. They build the link from
`row['Patient ID']`, and neither list query selects a column by that name:
`get_events_need_packets` (`flask_backend/table_service.py:148-161`) and the
reupload query (`table_service.py:401-407`) both select `ID`, `Date`,
`Created`, `Uploaded`, `Scrubbed`, `Criteria`, `Site`. `row['Patient ID']` is
therefore always `undefined`, and the string `"undefined"` is interpolated into
the URL and rendered on the page. **Patient ID is broken on every route, not
only on direct URLs.**

### Site Patient ID is not displayed anywhere on this page

No reference to `site_patient_id` exists in `EventUpload.jsx`. Comparable pages
do show it — `EventScrub.jsx:78` and `EventScreen.jsx:58` both render
`details.site_patient_id || details.patient_id || '—'` under a single
"Patient ID" label.

### The data itself is intact

Nothing needs backfilling. Per the spec's clarified data model, the internal
Patient ID, Site Patient ID, and event date are mandatory before an event can
be created; `create_event` (`table_service.py:941+`) enforces
`site_patient_id` and `site` and resolves the patient against `patients_view`
before inserting. The blank fields are purely a failure to request data that
already exists.

## Decisions

### D1: Source the identifiers from the existing event-details endpoint

**Decision**: `EventUpload.jsx` fetches `GET /api/events/<event_id>` on mount
and renders from the response, mirroring `EventScrub.jsx:15-24`.

**Rationale**: The endpoint already exists (`flask_backend/app.py:614`, backed
by `table_service.get_event_details` at `table_service.py:874`) and already
returns `patient_id`, `site_patient_id`, `site`, and `event_date`. Three of the
four required values need no backend work at all. Two sibling pages already
consume this endpoint for the same purpose, so the upload page becomes
consistent with them rather than novel.

**Alternatives considered**:

- *Add `Patient ID` and `Site Patient ID` columns to the list queries and keep
  passing everything through the URL.* Rejected: it cannot fix the action
  button or the bookmark route without threading params through every future
  link, and FR-003 explicitly requires the stored record to be the source. It
  also leaves the display vulnerable to stale links (spec edge case).
- *A new purpose-built endpoint for the upload page.* Rejected: it would
  duplicate an endpoint that already returns three of the four fields, against
  Principle I's consolidation intent, and would need its own role decorators
  and OpenAPI entry for no gain.

### D2: Return criteria as structured name/value pairs from the details endpoint

**Decision**: Extend `get_event_details` to include a `criteria` key holding a
list of `{"name": str, "value": str}` objects, ordered by `name` then `id`.
Fetch it with a second query against `criterias` rather than joining.

**Rationale**: This is the only genuine gap. `get_event_details` does not
select criteria at all, and the list queries that do
(`table_service.py:154`) use `GROUP_CONCAT(c.name ...)` — names only, already
flattened to a string, values discarded. FR-008 and FR-012 require both name
and value, so neither existing source suffices.

A separate query is used because joining `criterias` into the existing
single-row query would multiply the row by the criteria count and force either
a `GROUP_CONCAT` (which cannot carry structured pairs safely — names and values
are free text and could contain the separator) or client-side de-duplication of
every other column. The `criterias` table is indexed on `event_id`
(`init/02-schema.sql:32`), so the extra lookup is a single indexed read.

Ordering by `name` then `id` satisfies FR-008's stable-order requirement and
matches the `ORDER BY c.name` already used by the list queries, so the upload
page lists criteria in the same sequence as the events lists.

**Alternatives considered**:

- *`GROUP_CONCAT` with a delimiter pair.* Rejected: `name` is `varchar(50)` and
  `value` is `varchar(100)`, both free text with no delimiter guarantee.
  Parsing that back apart on the client is fragile and would corrupt any
  criterion containing the chosen separator.
- *A separate `/api/events/<id>/criteria` endpoint.* Rejected: it doubles the
  round trips for one page and splits a single logical read of "everything that
  identifies this event" across two contracts.

### D3: Gate the upload form on successful verification

**Decision**: Render the file input and submit control only when the details
request has succeeded and all three required identifiers are present. Otherwise
show a message and no upload control, with a retry affordance.

**Rationale**: FR-011 requires it, and the clarified data model makes it
unambiguous — the three identifiers exist for every real event, so their
absence always means the event on screen could not be verified. Gating the
form is what converts this feature from "show more information" into the actual
guarantee the user asked for: no packet is attached to an unverified event.

Criteria are deliberately excluded from the gate (FR-006b) because they are
optional; an event with none is ordinary and must stay uploadable.

**Alternatives considered**:

- *Warn but allow upload.* Rejected against FR-011. It preserves the
  wrong-packet risk in precisely the degraded state where it is most likely.
- *Redirect to the events list on failure.* Rejected: it discards the URL the
  uploader was working from and gives no way to retry (FR-011a).

### D4: Retire the identifier query parameters rather than leaving them

**Decision**: Remove the `patient_id`, `date`, and `criteria` parameters from
the links constructed in `EventUpload.jsx:78` and `EventReupload.jsx:68`,
leaving `event_id`. The page must tolerate their presence in old bookmarks by
ignoring them.

**Rationale**: Once the page sources everything from the stored record, these
parameters are read by nothing. Principle VI forbids leaving silently dead code
paths in the tree. Keeping them would also actively mislead — they are the
mechanism that currently interpolates `"undefined"` into the address bar, and a
future reader would reasonably assume they still drive the display.

Ignoring rather than rejecting unknown parameters keeps existing bookmarks
working, which the spec's stale-link edge case requires.

**Alternatives considered**:

- *Leave the parameters in place as a fallback when the fetch fails.* Rejected:
  it directly contradicts FR-003 and would resurrect the stale-data edge case
  the spec rules out, showing an uploader values that may no longer match the
  record.

### D5: Leave the endpoint's authorization unchanged, and document the gap

**Decision**: No change to the decorators on `GET /api/events/<int:event_id>`.
Record the observation and raise it as a follow-up outside this feature.

**Rationale**: The endpoint carries `@requires_auth` only
(`flask_backend/app.py:614-616`) — no role decorator — so any authenticated
user can read any event's details, including patient identifiers, regardless of
site. Every other event endpoint declares roles: `need_packets` is
`@requires_any_role('reviewer', 'uploader', 'admin')` and additionally scopes
non-admins to their own site (`app.py:455-484`), and `upload_raw` restricts
uploaders to the event's site (`app.py:1372-1386`).

This feature does not create the exposure and does not widen it — the upload
page is reached only by users who already hold uploader or admin rights, and
the endpoint is already consumed by `EventScrub` and `EventScreen`. But this
change does put patient identifiers on one more page, so the gap is worth
stating plainly rather than inheriting silently.

Tightening it is deliberately out of scope: adding a role decorator or a
site scope would change behavior for two pages this feature does not otherwise
touch, and belongs in a change reviewed for that purpose under the
constitution's "will this break any other study's deployment?" gate.

**Alternatives considered**:

- *Add `@requires_any_role('uploader', 'reviewer', 'admin')` in this PR.*
  Rejected as scope creep with cross-page blast radius. Flagged as follow-up.

### D6: Test at the backend layer; verify the frontend manually

**Decision**: Add pytest coverage for the criteria addition to
`get_event_details`. Verify the page states manually against the quickstart.

**Rationale**: The repository has an established backend suite —
`flask_backend/tests/` holds ten test modules including
`test_table_service.py`, which is where `get_event_details` coverage belongs —
and **no frontend test infrastructure at all** (no test runner, no `*.test.jsx`
files anywhere under `frontend/`). Standing up a frontend harness to test one
component's loading states is disproportionate to this change and would be a
larger decision than the feature warrants.

`get_event_details` currently has no direct test coverage, so the new tests are
additive rather than modifications.

**Alternatives considered**:

- *Introduce Vitest and React Testing Library for the four page states.*
  Rejected as out of scope. Worth proposing separately — the absence of any
  frontend test harness is a real gap — but not as a side effect of a
  display fix.

## Resolved unknowns

| Unknown | Resolution |
|---|---|
| Where do the four values come from? | `GET /api/events/<id>` for three; `criterias` table for the fourth (D1, D2) |
| Does the details endpoint return criteria? | No. This is the only backend gap (D2) |
| How are criteria stored? | `criterias(id, event_id, name varchar(50), value varchar(100))`, indexed on `event_id` (`init/02-schema.sql:26-35`) |
| What loading/error pattern do sibling pages use? | `useEffect` + `fetch` + `res.ok` guard, `details` state defaulting to `null` (`EventScrub.jsx:15-24`) |
| What placeholder convention exists? | `'—'` (em dash), per `EventScrub.jsx:78` and `EventScreen.jsx:58` |
| Is there frontend test infrastructure? | No (D6) |
| Does `openapi.json` need regenerating? | Yes — the response shape of an existing route changes. Constitution requires it land in the same PR |
| Is a schema change needed? | No. All data exists; no migration, no `init/` change |
