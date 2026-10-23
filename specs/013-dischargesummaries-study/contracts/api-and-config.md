# Contract: API and Configuration Changes

**Feature**: 013-dischargesummaries-study

Pre-release (Principle VI): these are this app's own contracts, changed in
place. Only the endpoints listed here change.

## `GET /api/config` — response shape unchanged, new values

On a `dischargesummaries` deployment:

```json
{
  "data": {
    "study_type": "dischargesummaries",
    "study_label": "Discharge Summary",
    "study_title": "Discharge Summaries Validation",
    "review_form": "dischargesummaries",
    "workflow": { "scrubbing": true, "screening": false, "sending": false, "reviewer_count": 1 }
  }
}
```

`study_label` is `STUDY_LABEL` when set and not blank. Otherwise it is
`STUDY_TYPE` trimmed and upper-cased, or `""` when that is unset. Other keys
are unchanged.

## `POST /api/events/assign_many` — new refusal (shared, D2)

When `slot` is `"first"` and any listed event has not completed every
enabled pre-assignment stage (uploaded; scrubbed if scrubbing is on; screened
if screening is on):

- `400 {"error": "These events are not ready for assignment: 12, 15"}`
- No event in the batch is changed.

## `POST /api/events/<id>/upload_raw` — new refusal (shared, D3)

For a non-admin caller when the event status is not `created` or `uploaded`:

- `409 {"error": "This event has moved past upload; ask an administrator."}`
- Admins are not affected. The existing 403 (wrong site) and 400 (file type)
  responses are unchanged and are checked first.

## `GET /api/events/download/<id>` — per-event access check (shared, D4)

| Result | When |
|--------|------|
| 200, scrubbed file | a scrubbed file exists and the caller is an admin, a same-site uploader, or a reviewer assigned to the event |
| 200, raw file | no scrubbed file, and the caller is an admin or same-site uploader; or scrubbing is off and the caller is an assigned reviewer |
| 403 `{"error": "You do not have access to this event's packet"}` | otherwise, when a file exists |
| 404 | no file the caller could receive exists (unchanged) |

## `POST /api/events/<id>/review` — Phase A behavior

Existing access checks, which allow only the assigned reviewer, are
unchanged. In Phase A, a body that passes them gets:

- `400 {"error": "The discharge-summary review form is not available yet."}`
- Nothing is stored, and the status is unchanged.

Phase B replaces this with the study's real validation. The body shape is
defined then.

## `GET /api/events/<id>/review` — Phase A

Returns the existing envelope with `reviews: []`.

## `GET /api/events/export`

Phase A writes the shared heading row and no answer rows. Phase B defines
the answer columns.

## Environment (`default.env`, documented)

| Variable | Change |
|----------|--------|
| `STUDY_TYPE` | adds the recognized value `dischargesummaries` |
| `STUDY_LABEL` | new, optional; free text; not validated |
| `INTERNAL_SUBNET`, `INTERNAL_GATEWAY` | new, optional; defaults to today's fixed values |

## `docker-compose.yaml`

```yaml
networks:
  internal:
    ipam:
      config:
        - subnet: ${INTERNAL_SUBNET:-172.31.222.0/24}
          gateway: ${INTERNAL_GATEWAY:-172.31.222.1}
```

There is no other compose change. Per Principle IV, a deployment never edits
this file.
