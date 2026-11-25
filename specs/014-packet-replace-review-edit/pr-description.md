# PR: 014-packet-replace-review-edit

Packet replacement before review, and editable reviews.
Spec: `specs/014-packet-replace-review-edit/spec.md`; decisions D1–D15 in `research.md`.

## Summary

1. **Packet replacement window.** A same-site uploader or an admin may replace an event's packet until it is released to reviewers: when it is sent (sending on, mci), or when a review is submitted (sending off, scans and dischargesummaries). A replacement after scrubbing or screening undoes those stages, renames the old scrubbed copy aside, keeps the reviewer assignment, and records the replacement in `logs`. The event returns to `assigned` once re-processed.
2. **Editable reviews.** A reviewer may edit their own submitted review: always in single-reviewer studies, and in two-reviewer studies until the other reviewer submits. New `PUT /api/events/<id>/review`; the edit date is stored in a new `reviews.modified` column and shown to admins and in exports. Status and submission dates do not change.

## Studies affected

**All** (mci, scans, dischargesummaries): the changes are in shared code, driven by `cfg.scrubbing`, `cfg.screening`, `cfg.sending` and `cfg.reviewer_count`, never by `STUDY_TYPE`.

| Study | Packet replacement | Review editing | Export |
|---|---|---|---|
| mci (scrub, screen, send; 2 reviewers) | until sent; replacement goes back through scrub + screen | until the other reviewer submits | `Review 1 Last Edited`, `Review 2 Last Edited` **appended at the end** |
| scans (no scrub/screen/send; 1 reviewer) | until reviewed; file swapped, status unchanged | always, also after `done` | `Last Edited` inserted after `Review Date` |
| dischargesummaries (scrub; 1 reviewer) | until reviewed; back through scrub; reviewer sees a "replaced on" notice | Phase A has no form, so nothing to edit yet | `Last Edited` inserted after `Review Date` (empty) |

### Will this break another study's deployment?

Not if the migration is run first (below). Without it, the review page and the review endpoints answer **503** naming `init/migrations/014-reviews-modified.sh` (the rest of the app keeps working), because every study's `check_schema` now requires `reviews.modified`.

## ⚠️ Deploy: run the migration BEFORE deploying

```bash
docker compose exec -T mariadb bash -s < init/migrations/014-reviews-modified.sh
docker compose up -d --build backend web     # restart: the schema check is cached per process
```

Idempotent and study-agnostic; adds a nullable `reviews.modified` if missing. For an old scans database still on the MI-shaped table, run `012-scans-reviews.sh` first. See quickstart.md §1.

## Behaviour changes worth calling out

- **Admin rights narrowed.** Before, an admin could replace a packet at any status, including after it was sent or reviewed (`app.py:2094` at `9182d7b`). Now admins have the same window as uploaders; past it, replacement is an operator task (quickstart.md §4).
- **Uploader rights widened.** Uploaders may now replace at `scrubbed`, `screened`, `assigned` and `rejected` (spec 013 limited them to `created`/`uploaded`). "Re-upload Existing Packets" lists that whole window.
- **New refusals** (all 4xx, nothing written):
  - `upload_scrubbed` now **requires** `packet_version` (400 without it), and refuses a scrubbed copy of a superseded packet (409). The scrub page sends it; any other client of this endpoint must too.
  - `screen` refuses any decision while scrubbing is on and the packet has no `scrub_date` (409).
  - `send_many` refuses a batch containing an event with an incomplete enabled stage (400), and the To Be Sent queue hides such events.
  - `POST /review` refuses an **unsent** event whose packet is being re-processed (409).
- **Downloads.** With scrubbing on and no `scrub_date`, a leftover scrubbed copy is not served (covers a legacy copy in read-only `FILES_DIR`). Reviewers get 403 until the new packet is scrubbed.
- **First use of the `logs` table.** Each replacement writes `controller='events', action='replace_packet', params='event_id=<id>;assigned=<0|1>'`. A first upload writes nothing. `GET /api/events/<id>.packet_replaced_at` reads the latest `assigned=1` row, only when sending is off. `logs` has no index on these columns; the lookup runs only on sending-off (new, near-empty) databases (research D14).
- **Files are never deleted.** Superseded scrubbed copies are renamed `superseded_<id>_<YYYYmmddHHMMSS><ext>` in `DOWNLOADS_DIR`; a name collision (e.g. `<id>.zip` and legacy `event_<id>.zip`) gets a `_<n>` suffix rather than overwriting.

## API changes (`openapi.json` regenerated)

- `POST /api/events/<id>/upload_raw`: window guard, replacement side effects, new 409 text.
- `POST /api/events/<id>/upload_scrubbed`: `packet_version` form field; 400/409.
- `POST /api/events/<id>/screen`: 409 before scrub; `accept` status derived.
- `POST /api/events/send_many`: 400 "These events are not ready to send: …" (docstring added).
- `GET /api/events/<id>`: `packet_replaceable`, `file_number`, `packet_replaced_at`.
- `GET /api/events/<id>/review`: `mine.{modified, editable, locked_reason, answers}`; admin `reviews[].modified`.
- **New** `PUT /api/events/<id>/review`.
- `POST /api/events/<id>/review`: new 409 "This event's packet is being re-processed."
- `GET /api/events/export`: columns per the table above.

Study-module interface (`flask_backend/models/studies/__init__.py`): new `replace_review`; `get_reviews` items carry `modified`; `check_schema` reports a missing `reviews.modified` (`MODIFIED_MIGRATION_HINT`).

## Observed behaviour before this change (constitution Principle VI)

- **D1** Admin replacement at any status; uploader at `created`/`uploaded` only (spec 013).
- **D2** A replacement left `scrub_date` set and the old scrubbed file in place, so reviewers could be sent the old packet and the event never re-entered To Be Scrubbed.
- **D3** `upload_raw`, `upload_scrubbed` and screen-accept wrote fixed statuses (`uploaded`/`scrubbed`/`screened`); now `derive_pre_send_status` gives the same values for never-assigned events and `assigned` for a kept assignment.
- **D4** `_find_scrubbed_packet` could prefer an old `.pdf` over a new `.zip`.
- **D12** `upload_scrubbed` accepted any file at any status.
- **D13** `screen` accepted any decision at any status.
- **D14** Nothing recorded a replacement; `logs` was unused by this app.

## Implementation notes beyond tasks.md

- **`onConflict(message)` form prop.** Added to the three review forms so a 409 on save reaches the page, which re-fetches and switches to read-only (the form unmounts, so it cannot show the message itself). Documented in contracts/ui.md.
- **Re-processing check only on unsent events.** `POST /review` and `review_editable` treat an event as "being re-processed" only if it has no `send_date`. A sent event cannot be mid-replacement, so this changes nothing for spec 014 flows, but it keeps legacy mci rows that were sent with a missing stage date reviewable. (The send guard and To Be Sent queue do still require every enabled stage, per D3: a legacy assigned-but-unsent mci event missing `scrub_date`/`screen_date` would no longer appear in To Be Sent.)
- **`packet_replaceable` fails closed.** An error computing it is logged and reported as `false` rather than failing `GET /api/events/<id>`, which every event page uses. `upload_raw` re-checks the rule.
- **ISO export cells.** `Last Edited` values are written as ISO-8601 (`2026-11-24T10:15:02`).
- **Test infrastructure.** `conftest.py` gains an autouse fixture that resets the per-process schema-check cache between tests; since every study's `check_schema` can now return a message, a cached result could otherwise leak between tests.

## Tests

- New: `test_derive_pre_send_status.py`, `test_reviews_modified_schema.py`, `test_packet_replace_window.py`, `test_reprocess_guards.py`, `test_event_details_replace_fields.py`, `test_review_edit.py`, `test_mci_replace_review.py`.
- Updated deliberately:
  - `test_upload_guard.py`: new window and message; admin refused after send.
  - `test_mci_export_golden.py`: two trailing columns and SQL select items, appended.
  - `test_scans_review.py`: `Last Edited` column, `modified` in `get_reviews`, `replace_review`; "migrated" fake table includes `modified`.
  - `test_review_access.py`: `mine` carries the new keys; "migrated" fake table includes `modified`.
  - `test_table_service.py`: `need_reupload` predicate.
  - `test_study_modules.py`: Phase A `check_schema` is no longer a no-op.
  - `test_event_download.py`, `test_review_endpoint.py`, `test_scans_workflow.py`, `test_full_workflow_defaults.py`: fakes gained the stage dates the new guards read (no expectation changed); download tests gained two D4 cases.
- Results: `python3 -m pytest flask_backend/tests` → **497 passed**. `npm run lint` → 31 problems, identical to baseline (none in new code). `npm run build` → OK.

## Baseline

Recorded before any change (T001), on commit `9182d7b` (= `origin/main`).

- **Backend:** `python3 -m pytest flask_backend/tests` → 385 passed, 0 failed.
- **Frontend build:** `npm run build` → succeeds.
- **Frontend lint:** `npm run lint` → 31 problems (25 errors, 6 warnings), all pre-existing, in
  `components/DevAuthTester.jsx`, `components/MenuBar.jsx`, `components/review/ReviewQueue.jsx`,
  `pages/EventDownload.jsx`, `pages/EventEdit.jsx` (1), `pages/EventUpload.jsx` (4),
  `pages/EventViewAll.jsx`, `pages/Home.jsx`, and five files under `studies/vte/`.
  This feature must not add to that count.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
