# Data Model: Packet Replacement Before Review, and Editable Reviews

**Feature**: 014-packet-replace-review-edit | **Date**: 2026-11-24

There is one schema change: a nullable `modified` column on each active study's `reviews` table. Nothing else changes in the schema. In particular there is no change to `events`, `users` or any other shared table, and no new state is added to the event lifecycle. The app starts writing rows to the existing shared `logs` table (see the last section).

## Review (per-study `reviews` table) — CHANGED

| Column | Type | Null | New? | Meaning |
|---|---|---|---|---|
| `id` | int | no | | Shared invariant (Principle IV) |
| `event_id` | int | no | | Shared invariant |
| `reviewer_id` | int | no | | Shared invariant; the author |
| *(study answer columns)* | | | | Unchanged per study |
| `created` | datetime | no | | scans and dischargesummaries only; unchanged |
| **`modified`** | **datetime** | **yes** | **yes** | When the author last edited the answers. NULL means never edited. |

Files to change:

- `init/studies/mci.sql`: add `modified` after `ecg_type`.
- `init/studies/scans.sql` and `init/studies/dischargesummaries.sql`: add `modified` after `created`.
- Existing databases: `init/migrations/014-reviews-modified.sh` (idempotent `ADD COLUMN IF NOT EXISTS`).
- The ORM classes `Reviews` in `flask_backend/models/studies/{mci,scans,dischargesummaries}.py` gain `modified: Mapped[Optional[datetime]]`.

The original submission date is not stored in `reviews`. It stays in `events.review1_date` / `review2_date` (date precision), unchanged.

### Validation rules

- An edit body is validated exactly as a new submission is (`study.parse_review`, FR-011).
- An edit overwrites the answers in place. The row's `id` (and `created`) are kept, so there is still exactly one row per reviewer per event (FR-012).
- In mci, an answer column that the edited body omits is set to NULL. NOT-NULL `mci` and `cardiac_cath` are always present in a parsed body.
- In scans, the `review_regions` rows of the edited review are replaced as a set, through the existing cascade.

### Editability (derived, not stored)

`review_editable(event, slot, cfg)` is true when all of these hold:

- `slot ∈ {1, 2}`, and that slot's `reviewN_date` is set;
- `cfg.reviewer_count == 1`, **or** the other slot's `reviewN_date` is null;
- every enabled pre-assignment stage is complete (research D3). This check applies only to an **unsent** event: the replacement window closes at send, so a sent event cannot be mid-replacement, and a legacy row sent with a stage date missing stays reviewable. The same rule guards `POST /review`.

The value of `locked_reason` when editability is false:

| Reason | Text |
|---|---|
| Other review complete | "Both reviews for this event are complete, so they can no longer be changed." |
| Packet being re-processed | "This event's packet is being re-processed." |

## Event (shared `events` table) — NO SCHEMA CHANGE, new behavior

### Packet replacement window (derived)

`packet_replaceable(event, auth_user)` decides whether this caller may replace the packet. The site check runs first, and a caller at the wrong site gets 403 before the window is considered.

| Event state | Admin | Uploader (same site) |
|---|---|---|
| `status ∈ {created, uploaded, scrubbed, screened, assigned, rejected}` and `send_date` null and both review dates null | allowed | allowed |
| `status = no_packet_available` | allowed (unchanged) | refused (unchanged) |
| anything else (`sent`, `reviewer1_done`, `reviewer2_done`, `third_review_*`, `done`, or any of the above with a send or review date) | **refused (new)** | refused |

### Field effects of a replacement

| Field | First upload (`created`) | Replacement |
|---|---|---|
| `file_number`, `original_name`, `uploader_id`, `upload_date` | set | overwritten (as today) |
| `scrub_date`, `scrubber_id` | — | cleared |
| `screen_date`, `screener_id` | — | cleared |
| `rescrub_message`, `reject_message` | — | kept (history) |
| `reviewer1_id`, `reviewer2_id`, `assigner_id`, `assign_date` | — | **kept** |
| `status` | `derive_pre_send_status` | `derive_pre_send_status` |
| scrubbed file(s) in `DOWNLOADS_DIR` | — | renamed to `superseded_<id>_<ts><ext>` |
| `logs` row `events`/`replace_packet` | — | inserted (FR-020) |

### `derive_pre_send_status(event, cfg)`

This helper replaces the hard-coded statuses written by `upload_raw`, `upload_scrubbed` and screen-accept. Rules are checked in order, and the first match wins.

1. `upload_date` null → `created`
2. scrubbing on and `scrub_date` null → `uploaded`
3. screening on and `screen_date` null → `scrubbed` if scrubbing on, else `uploaded`
4. `assign_date` null → `screened` if screening on, else `scrubbed` if scrubbing on, else `uploaded`
5. otherwise → `assigned`

For an event that has never been assigned, this gives the same status each endpoint writes today.

### State transitions added

Profile *full* is mci (scrub, screen and send on, two reviewers). Profile *ds* is dischargesummaries (scrub on, screen and send off, one reviewer).

```text
full:  scrubbed|screened|rejected ──replace──▶ uploaded ──scrub──▶ scrubbed ──screen──▶ screened (→ assign as today)
full:  assigned ──replace──▶ uploaded ──scrub──▶ scrubbed ──screen──▶ assigned   (assignment kept; then send as today)
ds:    assigned ──replace──▶ uploaded ──scrub──▶ assigned                        (reviewer sees it again)
scans: assigned ──replace──▶ assigned                                            (file swapped only)
```

A review edit causes no status transition.

## Superseded packet file (filesystem)

- **Name**: `superseded_<event_id>_<YYYYmmddHHMMSS><ext>` in `DOWNLOADS_DIR`.
- **Lifetime**: never deleted by the application; the systems operator cleans these up.
- **Effect on downloads**: the download lookup never matches these names, because it looks only for `<id><ext>` and `event_<id>.zip`.

## Packet version (existing column, new use)

`events.file_number` is regenerated on every raw upload, so it serves as the packet version.

- **Exposed**: on `GET /api/events/<id>`.
- **Sent back**: by the scrub page as `packet_version`.
- **Rule**: `upload_scrubbed` is refused when `packet_version != events.file_number` (FR-017).

## Replacement record (existing shared `logs` table, new rows)

| Column | Value |
|---|---|
| `user_id` | The user who replaced the packet |
| `controller` | `events` |
| `action` | `replace_packet` |
| `params` | `event_id=<id>;assigned=<0\|1>` (`assigned` = a first reviewer was set at replacement time) |
| `time` | Server time of the replacement (second precision) |

- **When written**: only for a replacement, meaning an upload to an event that already had `upload_date`. A first upload is not recorded.
- **Read by**: `packet_replaced_at` on `GET /api/events/<id>`, which is the latest `time` with `assigned=1`. It is computed only when sending is off (FR-019).

## Screening precondition

When scrubbing is on, a screening decision (`accept`, `rescrub` or `reject`) requires `scrub_date IS NOT NULL` (FR-018).
