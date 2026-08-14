# Contract: `GET /api/events/{event_id}`

**Feature**: `008-upload-event-identifiers`
**Change type**: Additive — one new key in an existing response. No key is
removed, renamed, or retyped.

Pre-release per Constitution Principle VI, so no deprecation window is needed.
`openapi.json` MUST be regenerated in the same PR
(`python -m flask_backend.generate_openapi`, from the repository root).

## Route

| | |
|---|---|
| Method | `GET` |
| Path | `/api/events/<int:event_id>` |
| Handler | `flask_backend/app.py:614` `get_event_details` |
| Auth | `@requires_auth` — **unchanged** (see research D5) |
| Roles | None declared — **unchanged**. Pre-existing gap, documented as follow-up, deliberately not altered here |

## Response — 200

```jsonc
{
  "data": {
    "id": 4711,
    "patient_id": 1832,
    "site_patient_id": "UW-00412",
    "site": "UW",
    "status": "created",
    "event_date": "2026-03-14",
    "add_date": "2026-03-15",
    "upload_date": null,

    // ...remaining existing date and *_username keys, unchanged...

    // NEW — added by this feature
    "criteria": [
      { "name": "Troponin",   "value": "0.42 ng/mL" },
      { "name": "ECG",        "value": "ST elevation" }
    ]
  }
}
```

### `criteria` — new key

| Property | Contract |
|---|---|
| Type | Array of objects. Always present |
| Empty case | `[]` when the event has no criteria. Never `null`, never omitted |
| `name` | String. From `criterias.name` (`varchar(50)`, NOT NULL) |
| `value` | String. From `criterias.value` (`varchar(100)`, NOT NULL) |
| Order | `name` ascending, then `id` ascending. Total and stable across calls (FR-008) |

The `name` ordering matches `GROUP_CONCAT(c.name ORDER BY c.name ...)` in the
list queries (`flask_backend/table_service.py:154`), so criteria appear in the
same sequence on the upload page as in the events lists' `Criteria` column.

## Response — 404

Unchanged. Returned when no event has that id (`app.py:619-620`).

```json
{ }
```

Consumed by this feature as a verification failure: the upload page shows a
not-found message and withholds the upload control (FR-010, FR-011).

## Response — 500

Unchanged shape.

```json
{ "error": "Failed to fetch event details" }
```

Treated by the upload page as a transient failure — message plus retry, upload
withheld until it succeeds (FR-011a).

## Consumers

| Consumer | Impact |
|---|---|
| `frontend/src/pages/EventUpload.jsx` | **New consumer.** Reads `patient_id`, `site_patient_id`, `event_date`, `criteria` |
| `frontend/src/pages/EventScrub.jsx:17` | Unaffected — additive change; ignores the new key |
| `frontend/src/pages/EventScreen.jsx` | Unaffected — same |
| `frontend/src/studies/vte/*` | Out of scope. VTE fork is not extended (spec Assumptions) |

## Backward compatibility

Legacy CNICS/CakePHP consumers are unaffected: this is one of the React/Flask
app's own routes, not a legacy contract, and the change adds a key rather than
altering any existing one. Principle III is not engaged.
