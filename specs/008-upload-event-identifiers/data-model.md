# Phase 1 Data Model: Populate event identifiers on the upload page

**Feature**: `008-upload-event-identifiers`
**Date**: 2026-08-13

**No schema change.** No table, column, index, or constraint is added, altered,
or dropped. No migration is required and nothing under `init/` changes. This
feature reads data that already exists and is already populated. The model
below documents the entities as they stand and the shape in which they are
newly exposed.

## Entities (existing, unchanged)

### Event — `events`

The clinical event a packet is uploaded for.

| Field | Type | Notes for this feature |
|---|---|---|
| `id` | `int(11)` PK | The `event_id` in the page address |
| `patient_id` | `int(11)` FK → `patients_view.id` | Displayed as **Patient ID**. Required at creation |
| `event_date` | `date` | Displayed as **Date**. Required at creation |
| `status` | enum | Not displayed; governs which list the event appears in |

Serialized dates are emitted ISO-8601 (`YYYY-MM-DD`) by the existing
comprehension in `get_event_details` (`flask_backend/table_service.py:934-937`),
which satisfies FR-007 without further work.

### Patient — `patients_view`

Read-only view unioning `uw_patients2` with the FederatedX proxy of
`cnics_data.Patient`. The application never writes to either half.

| Field | Type | Notes for this feature |
|---|---|---|
| `id` | `int(11)` PK | Joined from `events.patient_id` |
| `site_patient_id` | `varchar(64)` | Displayed as **Site Patient ID**. Required at creation |
| `site` | `varchar` | Not displayed — out of scope per spec Assumptions |

`create_event` (`table_service.py:941+`) rejects a create whose
`site_patient_id` or `site` is blank and requires the patient to already exist
in the view. This is the upstream enforcement the spec relies on when treating
these identifiers as guaranteed present.

The join is a `LEFT JOIN` (`table_service.py:914`), so an event whose patient
row cannot be resolved yields `NULL` for `site_patient_id`. Per FR-006 that is
an anomaly, not an ordinary empty value, and routes to the verification-failure
path rather than to a placeholder.

### Criterion — `criterias`

| Field | Type | Notes for this feature |
|---|---|---|
| `id` | `int(11)` PK | Secondary sort key, for determinism among equal names |
| `event_id` | `int(11)` FK → `events.id` | Indexed (`KEY event_id`) |
| `name` | `varchar(50)` NOT NULL | Displayed |
| `value` | `varchar(100)` NOT NULL | Displayed — **new**; no existing surface exposes it |

Cardinality is zero-or-more per event. Zero is a legitimate state (spec
Assumptions, FR-006b) and must not block upload.

Both columns are `NOT NULL` at the schema level, so a criterion with a name but
no value is not expected. The spec's edge case for it is defended in the view
layer only, as free text may be the empty string.

## Newly exposed shape

`get_event_details(event_id)` gains one key. Every existing key is unchanged;
this is additive, so `EventScrub` and `EventScreen` are unaffected.

```text
{
  ...existing keys (id, patient_id, site_patient_id, site, status,
     event_date, add_date, upload_date, ..., *_username)...,
  "criteria": [ { "name": str, "value": str }, ... ]   # NEW, [] when none
}
```

**Ordering**: `ORDER BY name, id`. The `name` sort matches the
`GROUP_CONCAT(c.name ORDER BY c.name ...)` already used by the list queries
(`table_service.py:154`), so the upload page presents criteria in the same
sequence as the events lists. The `id` tiebreak makes the order total, so it
cannot vary between loads when two criteria share a name — FR-008's stability
requirement.

**Empty case**: an event with no criteria yields `[]`, never `null`. A single
representation for "none" keeps the consuming component from branching on two
falsy shapes.

## Validation rules derived from requirements

| Rule | Source | Enforced where |
|---|---|---|
| Patient ID, Site Patient ID, and event date are present for any retrievable event | FR-006 | Upstream at `create_event`; the view treats absence as verification failure, not as a value to render |
| Absence of any required identifier blocks packet submission | FR-011 | Frontend gate on the upload form |
| Absence of criteria never blocks packet submission | FR-006b | Frontend gate excludes `criteria` from its condition |
| Criteria order is stable across loads | FR-008 | `ORDER BY name, id` in the query |
| Each criterion shows name and value | FR-008, FR-012 | Structured pairs in the response; rendered as pairs |
| Event date format matches the rest of the app | FR-007 | Existing ISO serialization in `get_event_details` |
| URL-borne identifier values are ignored | FR-003 | Frontend reads only `event_id` from the address |

## State transitions

None. This feature performs no write and does not advance the event lifecycle.
The packet upload itself (`POST /api/events/<id>/upload_raw`) continues to move
an event to `uploaded` exactly as it does today; the only change is that the
control which triggers it is withheld until the event has been verified.
