# Contract: `GET /api/config` — `study_label`

**Feature**: `010-study-aware-packet-heading` | **Date**: 2026-09-08

One additive field on one existing endpoint. No other route, request body, or
response shape changes anywhere in this feature.

## Endpoint

`GET /api/config` — `flask_backend/app.py:744`. Auth and roles unchanged:
`@requires_auth`, `@requires_any_role('admin', 'uploader', 'reviewer', 'third_reviewer')`.

## Response — before

```json
{
  "data": {
    "study_type": "mci",
    "study_title": "",
    "workflow": { "scrubbing": true, "screening": true, "sending": true, "reviewer_count": 2 }
  }
}
```

## Response — after

```json
{
  "data": {
    "study_type": "mci",
    "study_label": "MCI",
    "study_title": "",
    "workflow": { "scrubbing": true, "screening": true, "sending": true, "reviewer_count": 2 }
  }
}
```

## `study_label`

| | |
|---|---|
| Type | `string` |
| Required | Always present |
| Derivation | `STUDY_TYPE`, trimmed, upper-cased |
| Empty when | `STUDY_TYPE` is unset or blank — and only then |
| Never | Null, absent, `"undefined"`, or another study's value |

### Worked values

| `STUDY_TYPE` | `study_type` | `study_label` |
|---|---|---|
| `mci` | `"mci"` | `"MCI"` |
| `scans` | `"scans"` | `"SCANS"` |
| `  Cva  ` | `"  Cva  "` | `"CVA"` |
| *(a study never served before)* | as configured | its upper-cased value |
| *(unset)* | `"mci"` — the existing default, unchanged | `""` |

The last row is the point of the field. `study_type` keeps its default so
nothing that reads it changes behaviour; `study_label` reports the truth so the
headings and the email can omit the study word (FR-005, FR-024).

## Compatibility

**Additive.** Existing consumers read `study_type`, `study_title`, and
`workflow`, all unchanged. A client that ignores `study_label` behaves exactly
as before.

Constitution Principle VI permits changes to this application's own API shapes
pre-release. The Development Workflow gate requires the regenerated
`openapi.json` to land in the same PR:

```bash
python -m flask_backend.generate_openapi   # from the repository root
```

## Consumers

| Consumer | Use |
|---|---|
| `frontend/src/App.jsx` | Stores as `studyLabel`; passes to the four pages alongside `configResolved` |
| `EventUpload`, `EventScreen`, `EventScrub`, `EventReview` | Renders in the page heading |

The assignment email does **not** go through this endpoint — it is composed on
the server and calls `get_study_label()` directly. Both paths resolve from that
one function, so the email and the page it links to cannot disagree (FR-021,
SC-012).

## Test expectations

`flask_backend/tests/test_config_endpoint.py`:

1. With `STUDY_TYPE=mci`, the response contains `study_label == "MCI"`.
2. With `STUDY_TYPE` unset, `study_label == ""` **and** `study_type == "mci"` —
   asserting together that the new field reports the unset state while the
   existing field keeps its default.
3. With `STUDY_TYPE=" scans "`, `study_label == "SCANS"`.
4. The `workflow` object and `study_title` are unchanged in every case.
