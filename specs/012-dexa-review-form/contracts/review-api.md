# Contract: Review API

**Feature**: 012-dexa-review-form (rev. 2). All routes sit behind
`@requires_auth` (Apache basic+ldap → `X-Remote-User`). `openapi.json` MUST
be regenerated in the same PR.

Study-specific bodies and responses are produced by
`get_study_module()` (`flask_backend/models/studies/`). The route code
contains no study names.

---

## GET `/api/config` (changed: one added field)

```json
{ "data": { "workflow": {…}, "study_type": "scans", "study_label": "SCANS", "study_title": "…",
            "review_form": "scans" } }
```

`review_form` is `"mci"` or `"scans"`. It is `"mci"` for any study without a
live study module.

---

## POST `/api/events/{event_id}/review` (changed)

Roles: `reviewer` or `admin`, and the caller must be the event's assigned
`reviewer1_id` or `reviewer2_id`. Both are unchanged.

- `mci` body: unchanged.
- `scans` body:

```json
{
  "regions": [
    { "region": "Lumbar spine", "laterality": "",     "t_score": "-1.1", "z_score": "-0.4" },
    { "region": "Hip",          "laterality": "Left", "t_score": "-0.9", "z_score": ""     },
    { "region": "Other", "region_other": "Total body", "laterality": "", "t_score": "", "z_score": "" }
  ]
}
```

- Scores are **strings**. `""`, `null`, and an absent key all mean "not
  provided".
- `region_other` is read only when `region == "Other"`.

| Status | When | Body |
|---|---|---|
| 200 | One `reviews` row and N `review_regions` rows are inserted, and the lifecycle advances, all in one transaction | `{"data": {"event_id": 4821, "status": "done"}}` |
| 400 | Body missing or invalid | `{"error": "Invalid review", "fields": [{"index": 1, "field": "t_score", "message": "…"}]}` (`fields` is omitted for whole-body errors) |
| 403 | Caller is not an assigned reviewer | unchanged |
| 404 | No such event | unchanged |
| **409** | **New, all studies (FR-024)**: the caller's slot already has a review date | `{"error": "This event has already been reviewed by you"}` |
| **503** | **New**: the study's `check_schema()` reports unmigrated tables | `{"error": "Review tables are not migrated for this study; see init/migrations/012-scans-reviews.sh"}` |

Nothing is written on any non-200 response.

---

## GET `/api/events/{event_id}/review` (new; rev. 4: content admin-only, adds `slot` and `mine`)

Roles: `admin`, or a `reviewer` assigned to the event. Anyone else gets 403,
and an unknown event gets 404.

**Rev. 4 (research D15, D16)**: what the caller receives depends on role.

| Caller | `reviews` | `mine` |
|---|---|---|
| admin | every review, full content, each with `slot` | caller's own status (normally `null`) |
| assigned reviewer, not admin | `[]`: never any answers, own included | `{ "slot": 1, "review_date": "2026-10-16" }` once reviewed, else `null` |

`mine` uses the same slot rule as the POST 409 guard
(`reviewer{N}_id == caller` and `review{N}_date` is set), so the review page's
"already reviewed" state (FR-029) and the server's refusal (FR-024) cannot
disagree. `mine` covers **slots 1 and 2 only**: non-admin access to this
GET admits only `reviewer1_id`/`reviewer2_id`, and POST accepts only those
slots. Third review is outside this feature. `slot` on admin `reviews`
items is 1, 2 or 3, matched from `reviewer{1,2,3}_id`, or `null` if the
reviewer is no longer in any slot.

`scans` response (admin):

```json
{ "data": { "event_id": 4821, "reviews": [
  { "reviewer_id": 17, "review_date": "2026-10-14", "regions": [
    { "position": 1, "region": "Lumbar spine", "region_other": null, "laterality": null,   "t_score": "-1.10", "z_score": "-0.40" },
    { "position": 2, "region": "Hip",          "region_other": null, "laterality": "Left", "t_score": "-0.90", "z_score": null }
  ] } ] } }
```

- Scores are strings, rendered from the `Decimal` value. "Not provided" is
  `null`.
- An event with no review returns `"reviews": []`.
- `mci` returns the MI columns of each `reviews` row (plus `slot`). Rev. 4:
  `/events/edit` renders it for admins (FR-027).

---

## GET `/api/events/export` (changed: study-specific body)

Roles and response type are unchanged (admin, `text/csv`).

- `mci`: output is byte-for-byte unchanged. The code moves to
  `models/studies/mci.py:export_csv`.
- `scans`: one row per region entry. Columns are
  `Event ID, Patient ID, Site, Site Patient ID, Event Date, Status, Reviewer,
  Review Date, Position, Region, Region (other), Laterality, T score, Z score`.
  An event with no review gets one row with the review columns empty. Rows
  are ordered by event, then position.

It returns **503** with the same body as the POST when `check_schema()`
reports unmigrated tables.

Per the constitution's PHI rule, nothing from this endpoint is logged above
DEBUG.

---

## GET `/files/instructions/{study}.html` (existing route, new file)

This is the existing `GET /files/<path>`. There is no route change. It serves
the self-contained instructions document as `text/html`.

---

## Rev. 4 changes to existing endpoints (research D14, D16)

### GET `/api/events/for_review` (changed: scoped for non-admins)

Non-admin callers get only events where they hold an unreviewed slot:
`(reviewer1_id = me AND review1_date IS NULL) OR (reviewer2_id = me AND review2_date IS NULL)`,
on top of the existing status gate (`sent`, or `assigned` when sending is
disabled). Admins: unchanged (all events in that status). The response shape
is unchanged.

### GET `/api/events/{event_id}` (changed: role decorator + access check)

The endpoint now declares
`@requires_any_role('admin', 'uploader', 'reviewer', 'third_reviewer')`
(constitution "Authorization"). A signed-in user with none of those roles
gets **403**. Callers with `admin` or `uploader` are unchanged. Any other caller must be
one of the event's `reviewer{1,2,3}_id`, otherwise **403** `{ "error": "Not
authorized for this event" }`. The response shape is unchanged. It contains
no review content.

### GET `/api/tables/{name}` (changed: role decorator, name validation, review tables)

- The endpoint now declares
  `@requires_any_role('admin', 'uploader', 'reviewer', 'third_reviewer')`. A
  signed-in user with none of those roles gets **403**.

- `name` not matching `^[A-Za-z_][A-Za-z0-9_]*$` → **404**. Before this, the
  name was interpolated into SQL unchecked.
- `name` in the study module's `REVIEW_TABLES` and caller not `admin` →
  **403**.
- Otherwise unchanged. (Follow-up, out of scope: a full allowlist with
  per-table roles.)

### POST `/api/events/{event_id}/screen` and POST `/api/events/{event_id}/upload_scrubbed` (changed: roles)

Both are now `@requires_roles('admin')` (FR-033, research D19). They were
`reviewer|admin` and `reviewer|uploader|admin`. Non-admins get **403**. Bodies
and responses are unchanged.

### GET `/files/{path}` (unchanged route; reachability fix)

The Vite dev server now proxies `/files` to the backend, as it does `/api`
(research D12). Before this, `/files/*` on dev-server deployments returned the
SPA's `index.html`.
