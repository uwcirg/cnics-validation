# Quickstart: Populate event identifiers on the upload page

## What this feature does

`/events/upload?event_id=[n]` shows the event's **Patient ID**, **Site Patient
ID**, **Date**, and **Criteria** so an uploader can cross-reference them
against the packet in hand before attaching it. Today those fields are blank
because the page reads them from the URL instead of from the event record; the
"upload" action button and any bookmarked URL carry none of them, and Patient
ID is broken on every route.

The page now fetches the event and renders from the record. If it cannot, it
says so and withholds the upload control rather than showing an unverifiable
form.

## Files involved

**Backend**

- `flask_backend/table_service.py` — **edit** `get_event_details` (~line 874):
  add a second indexed query against `criterias` and return
  `criteria: [{name, value}]`, ordered by `name` then `id`. `[]` when none.
  All other keys unchanged.
- `flask_backend/app.py` — **edit** the docstring of `get_event_details`
  (line 614) to document the new key for the OpenAPI generator. No decorator
  change.
- `openapi.json` — **regenerate**, do not hand-edit.

**Frontend**

- `frontend/src/pages/EventUpload.jsx` — **edit**: fetch
  `/api/events/<event_id>` on mount (mirror `EventScrub.jsx:15-24`); render
  the four values from the response; drop the `patient_id` / `date` /
  `criteria` reads at lines 120-123; add the four UI states; gate the upload
  form on VERIFIED; simplify the row link at line 78.
- `frontend/src/pages/EventReupload.jsx` — **edit**: simplify the row link at
  line 68 to carry `event_id` only.

**Tests**

- `flask_backend/tests/test_table_service.py` — **add** coverage for the new
  `criteria` key. `get_event_details` has none today.

**Not touched**: `init/` (no schema change, no migration), any file under
`frontend/src/studies/vte/`, and the authorization on
`GET /api/events/<id>` (see the follow-up note below).

## Data flow

```text
URL ?event_id=n
   └─▶ GET /api/events/n
          ├─ events.patient_id                    ──▶ Patient ID
          ├─ patients_view.site_patient_id        ──▶ Site Patient ID
          ├─ events.event_date  (ISO)             ──▶ Date
          └─ criterias[] {name, value}  (NEW)     ──▶ Criteria
```

Three of the four already come back from this endpoint. Criteria is the only
gap: `get_event_details` doesn't select it, and the list queries that do use
`GROUP_CONCAT(c.name …)` — names only, values discarded.

## The four states

| State | When | Upload control |
|---|---|---|
| LOADING | request in flight | withheld |
| VERIFIED | 200 + all three required identifiers present | **enabled** |
| NOT VERIFIED | 404, 403, or a required identifier null | withheld |
| UNAVAILABLE | network error or 500 | withheld, with retry |

The three required identifiers are mandatory upstream at event creation, so
their absence means the event couldn't be verified — not that a field is
merely empty. **Criteria are optional and must never gate the upload.**

## Verify

Run the backend suite:

```bash
python -m pytest flask_backend/tests/test_table_service.py -v
```

Regenerate the contract (must land in the same PR):

```bash
python -m flask_backend.generate_openapi
```

Then, against the compose stack, walk the routes for one known event id — all
four must show identical values (SC-002):

1. Events-needing-packets list → press the **"upload"** action button.
   *This is the route from the bug report; it previously showed everything blank.*
2. Same list → click the **row body** rather than the button.
   *Patient ID previously rendered as `undefined` here.*
3. Reupload list → click a row through to the upload page.
4. Paste `/events/upload?event_id=[n]` directly into the address bar.

Then check the edges:

- An event with **no criteria** → criteria shows `—`, and the upload control is
  still enabled.
- An event with **several criteria** → each shows `name: value`, same order as
  the `Criteria` column on the events list.
- A **nonexistent** event id → message shown, no blank fields, no upload
  control.
- An **old bookmark** carrying `&patient_id=undefined&date=…&criteria=…` →
  the extra parameters are ignored and correct values render from the record.

## Follow-up raised, not fixed here

`GET /api/events/<id>` carries `@requires_auth` with **no role decorator**
(`flask_backend/app.py:614-616`), so any authenticated user can read any
event's patient identifiers regardless of site — unlike `need_packets` and
`upload_raw`, which both scope non-admins to their own site. This feature
neither creates nor widens that gap, but it does surface those identifiers on
one more page. Tightening it would change behavior for `EventScrub` and
`EventScreen`, so it belongs in its own reviewed change. See research.md D5.
