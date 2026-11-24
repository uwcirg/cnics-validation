# API Contract Changes: 014-packet-replace-review-edit

All routes keep `@requires_auth`. Role decorators are listed per route. `openapi.json` must be regenerated in the same PR (`python -m flask_backend.generate_openapi`).

---

## POST `/api/events/<id>/upload_raw` — CHANGED

**Roles:** `uploader` or `admin` (unchanged).

| Case | Before | After |
|---|---|---|
| Non-admin, other site | 403 | 403 (unchanged) |
| Inside the window (data-model.md), admin or same-site uploader | 200 (uploader: `created`/`uploaded` only) | **200** |
| Non-admin, status `scrubbed`/`screened`/`assigned`/`rejected`, not sent, not reviewed | 409 | **200** |
| Admin, status `sent` or later, or any send/review date set | 200 | **409** |
| Non-admin, status `sent` or later | 409 | 409 (new message) |
| `no_packet_available` | admin 200 / uploader 409 | unchanged |

**409 body (new message):**

```json
{"error": "This packet has already been released to reviewers and can no longer be replaced here. Contact the systems operator."}
```

**200 side effects on a replacement:** see data-model.md, "Field effects of a replacement". This includes one `logs` row (`events` / `replace_packet`). The response body is unchanged: `{"data": {"saved": true}}`.

---

## POST `/api/events/<id>/upload_scrubbed` — CHANGED

**New required form field:** `packet_version` (integer), the `file_number` the scrub page loaded.

| Status | When |
|---|---|
| 400 | `packet_version` missing or not an integer: `{"error": "packet_version is required"}` |
| 409 | `packet_version` ≠ the event's current `file_number`: `{"error": "This event's packet was replaced on 2026-11-24. Download the new packet and scrub it."}`. Nothing is saved. |
| 200 | As today. The `status` written is now `derive_pre_send_status(event)`. |

## POST `/api/events/<id>/screen` — CHANGED

- **New 409.** When scrubbing is on and the event has no `scrub_date`, any decision is refused with `{"error": "This event's packet has not been scrubbed yet."}`. Nothing is saved.
- **`accept`.** The `status` written is now `derive_pre_send_status(event)`.

For an event that has never been assigned, `derive_pre_send_status` gives today's value (`scrubbed` / `screened`). For an event that kept its assignment through a replacement, it gives `assigned` once every enabled pre-assignment stage is complete.

---

## POST `/api/events/send_many` — CHANGED (new guard)

If any event in the batch has an incomplete enabled pre-assignment stage, the whole batch is refused and nothing changes. No email is sent.

```json
400 {"error": "These events are not ready to send: 12, 15"}
```

## GET `/api/events/by_status/assigned` (to-be-sent queue) — CHANGED

The predicate gains "every enabled pre-assignment stage complete". An event being re-processed after a replacement does not appear in this queue until its stages are complete.

---

## GET `/api/events/need_reupload` — CHANGED

**Roles:** `reviewer`, `uploader` or `admin` (unchanged); site-scoped for non-admins (unchanged).

- **Before:** events with `status = 'uploaded'`.
- **After:** events inside the replacement window for the caller, excluding `created`. That means status `uploaded`, `scrubbed`, `screened`, `assigned` or `rejected`, with no send date and no review date. The row shape is unchanged.

## GET `/api/events/<id>` — CHANGED (additive)

The response's `data` gains:

| Field | Type | Meaning |
|---|---|---|
| `packet_replaceable` | boolean | Whether *this caller* may replace the packet now (site check plus window). |
| `file_number` | integer \| null | Packet version, sent back as `packet_version` by the scrub page |
| `packet_replaced_at` | string \| null | ISO datetime of the latest replacement made while a reviewer was assigned. Always null when sending is on. |

---

## GET `/api/events/<id>/review` — CHANGED (additive)

**Roles:** `reviewer` or `admin` (unchanged).

- `reviews[]` (admins only, as before): each item gains `modified` (ISO-8601 datetime string, or null).
- `mine` (non-null once the caller has reviewed in slot 1 or 2) gains these fields:

| Field | Type | Meaning |
|---|---|---|
| `slot` | int | unchanged |
| `review_date` | string | unchanged (original submission date) |
| `modified` | string \| null | Last edit, ISO-8601 |
| `editable` | boolean | `review_editable()` (data-model.md) |
| `locked_reason` | string \| null | Present when `editable` is false |
| `answers` | object | The caller's **own** review, in the same study-specific shape as an admin `reviews[]` item |

Other reviewers' answers are never included for non-admins (spec 012 FR-028 is preserved).

## PUT `/api/events/<id>/review` — NEW

**Roles:** `reviewer` or `admin` (an admin must also be the slot's reviewer, as with POST).

**Body:** identical to POST `/review` for this study, validated by `study.parse_review`.

| Status | When | Body |
|---|---|---|
| 200 | Edit saved | `{"data": {"event_id": 7, "status": "reviewer1_done", "modified": "2026-11-24T10:15:02"}}` |
| 400 | Invalid body | Same as POST (`error`, optional `fields`) |
| 403 | Caller is not reviewer 1 or 2 of this event | `{"error": "Submitter is not an assigned reviewer for this event"}` |
| 404 | Event not found | |
| 409 | Caller has not submitted a review to edit | `{"error": "You have not submitted a review for this event"}` |
| 409 | Other reviewer has completed theirs (two-reviewer studies) | `{"error": "Both reviews for this event are complete, so they can no longer be changed."}` |
| 409 | Packet being re-processed | `{"error": "This event's packet is being re-processed."}` |
| 503 | Study's review tables not migrated | `{"error": "<names init/migrations/014-reviews-modified.sh>"}` |

**Effects:** the caller's answers are replaced and `modified` is set to now. `events.status` and `events.reviewN_date` are **not** changed.

## POST `/api/events/<id>/review` — CHANGED (one new refusal)

There is one new 409, "This event's packet is being re-processed.", returned when an enabled pre-assignment stage is incomplete. It is reachable only after a replacement under the dischargesummaries profile. Everything else is unchanged, including 409 on a second submission.

---

## GET `/api/events/export` — CHANGED (columns)

| Study | Change |
|---|---|
| scans | New `Last Edited` column immediately after `Review Date` |
| dischargesummaries | New `Last Edited` column immediately after `Review Date` (empty in Phase A) |
| mci | `Review 1 Last Edited`, `Review 2 Last Edited` **appended at the end**; all existing columns keep their positions |

---

## Study-module interface (`flask_backend/models/studies/__init__.py`) — CHANGED

| Member | Change |
|---|---|
| `replace_review(session, event_id, reviewer_id, parsed) -> bool` | **New.** Overwrites the reviewer's answers in place and sets `modified`. Does not commit. Returns False when there is no row. |
| `get_reviews(...)` | Each item gains `modified` (ISO string or null). |
| `check_schema(...)` | Also reports a missing `reviews.modified` column (names the 014 migration). |
| `export_csv(...)` | Adds the column(s) above. |
