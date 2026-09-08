# Contract: `POST /api/events/{event_id}/mark_no_packet`

**Feature**: `011-no-packet-submit` | **Status**: new route

Records that no chart packet can be obtained for an event, and resolves the
event to the existing `no_packet_available` terminal state.

Shaped after the two lifecycle-transition endpoints already in
`flask_backend/app.py`: `POST /api/events/<id>/screen` (line 1249) and
`POST /api/events/<id>/review` (line 2034) — JSON body, enumerated decision,
actor + date stamp, `{'data': {...}}` envelope.

---

## Authorization

```python
@app.route('/api/events/<int:event_id>/mark_no_packet', methods=['POST'])
@requires_auth
@requires_any_role('uploader', 'admin')
```

Plus a same-site check for non-admins: `auth_user['site']` must equal the
event's patient site. Admins act across all sites. Identical to the rule
`upload_raw` enforces at `app.py:1863-1872`; lift it into a shared helper
rather than copying it (research D3).

---

## Request

`Content-Type: application/json`

```jsonc
{
  "reason": "Outside hospital",   // required, one of the 4 enum values
  "two_attempts": true,           // required iff reason == "Outside hospital"
  "other_cause": "Data corruption", // required iff reason == "Other"
  "prior_event_date_known": true, // required iff reason == prior-event
  "prior_event_month": 11,        // optional; only when date_known
  "prior_event_year": 2011,       // optional; only when date_known
  "prior_event_onsite": false     // required iff reason == prior-event
}
```

### Fields

| Field | Type | Required when | Notes |
|-------|------|---------------|-------|
| `reason` | string | always | `Outside hospital`, `Ascertainment diagnosis error`, `Ascertainment diagnosis referred to a prior event`, `Other` |
| `two_attempts` | boolean | reason = Outside hospital | `false` is valid and recorded — observed in production |
| `other_cause` | string | reason = Other | trimmed; 1-100 chars |
| `prior_event_date_known` | boolean | reason = prior-event | `false` ⇒ `prior_event_date` stored NULL |
| `prior_event_month` | int/null | never | 1-12; null/absent ⇒ `00` |
| `prior_event_year` | int/null | never | 4-digit; null/absent ⇒ `0000` |
| `prior_event_onsite` | boolean | reason = prior-event | |

Fields outside the selected reason's column are **ignored, not rejected** —
the coordinator may have answered them before switching reasons. They are
never persisted (FR-006).

---

## Responses

### 200 — recorded

```json
{ "data": { "event_id": 4821, "status": "no_packet_available", "marked_date": "2026-09-08" } }
```

### 400 — validation failed

```json
{ "error": "two_attempts is required when the reason is \"Outside hospital\"" }
```

Message must name the offending field (FR-011 to FR-015). Representative
messages:

| Condition | Message |
|-----------|---------|
| reason missing/unrecognized | `reason must be one of: Outside hospital, Ascertainment diagnosis error, Ascertainment diagnosis referred to a prior event, Other` |
| V2 | `two_attempts is required when the reason is "Outside hospital"` |
| V3 | `other_cause is required when the reason is "Other"` |
| V4 | `other_cause must be 100 characters or fewer` |
| V5 | `prior_event_onsite is required for this reason` |
| V6 | `Enter a month or a year for the prior event, or answer that the date is not known` |
| V7 | `prior_event_month must be between 1 and 12` |
| V8 | `prior_event_year must be a four-digit year` |

### 403 — not permitted

```json
{ "error": "Uploader must match patient site" }
```

Wording matches `upload_raw`'s existing message for the same condition.

### 404 — no such event

```json
{ "error": "Event not found" }
```

### 409 — event is past packet collection

```json
{ "error": "Event 4821 is already \"uploaded\" and cannot be marked as having no packet" }
```

### 500 — write failed

```json
{ "error": "Failed to mark event as having no packet" }
```

Handler rolls back; the row is unchanged (FR-016).

---

## Persistence on success

Single transaction, all-or-nothing (FR-009):

```text
status                   = 'no_packet_available'
no_packet_reason         = <reason>
marker_id                = <acting user id>
markNoPacket_date        = <today>
two_attempts_flag        = 1/0        if reason = Outside hospital, else NULL
prior_event_date         = 'MM-YYYY'  if prior-event and date known, else NULL
prior_event_onsite_flag  = 1/0        if reason = prior-event,      else NULL
other_cause              = <text>     if reason = Other,            else NULL
```

Non-applicable fields are assigned NULL explicitly rather than left alone —
the row may carry stale values from an earlier edit.

---

## Logging (PHI constraint)

Log the event id and error class only. `other_cause` is coordinator-authored
free text about a patient's records and is **PHI-adjacent — never log it**,
at any level. Follows the precedent in `_write_import_record`
(`app.py:1275-1287`), which deliberately logs neither file name nor row text.

---

## Contract regeneration

`openapi.json` must be regenerated in the same PR:

```bash
python -m flask_backend.generate_openapi   # from repo root
```

Required by the constitution's API-contracts gate. Add the Flasgger docstring
to the handler so the spec picks up request/response shapes.
