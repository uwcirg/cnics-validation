# Implementation Plan: Packet Replacement Before Review, and Editable Reviews

**Branch**: `014-packet-replace-review-edit` | **Date**: 2026-11-24 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/014-packet-replace-review-edit/spec.md`

## Summary

This feature makes two changes to shared workflow rules. Both are driven by configuration flags, not by study names.

1. **Packet replacement window.** A site uploader, or an admin, may replace an event's packet until the packet is released to reviewers. With sending on (mci), that point is when the event is sent. With sending off (scans, dischargesummaries), it is when the first review is submitted.
   - Admins lose their current ability to replace a packet at any stage. Past the window, replacement is a task for the systems operator.
   - A replacement after scrubbing or screening undoes those stages and moves the old scrubbed file aside. It keeps the reviewer assignment.
   - A new helper derives the event's status, so a still-assigned event returns to `assigned` once it has been re-processed (research D1–D4).
   - **Guards against acting on the old packet.**
     - A scrubbed-copy upload must name the packet version it was made from (D12).
     - Screening requires a scrubbed current packet (D13).
     - Every replacement is recorded in the shared `logs` table. Where reviewers can get the packet before sending, their review page shows when it was replaced (D14).
   - **Operator procedure.** quickstart.md documents the manual steps once the window has closed (D15).
2. **Editable reviews.** A reviewer may edit their own submitted review in these cases:
   - in single-reviewer studies, always;
   - in two-reviewer studies, until the other reviewer submits.

   This is a new `PUT /api/events/<id>/review`, which uses the study's existing validation. It overwrites the answers and stamps a new per-study `reviews.modified` column. Admins see that date on the event page and in exports. Neither the status nor the submission date changes (research D5–D9).

## Technical Context

**Language/Version**: Python 3.11 (Flask backend); JavaScript / JSX, React 19 (frontend)
**Primary Dependencies**: Flask, SQLAlchemy, mysql-connector-python; React 19, react-router-dom 6, Vite 7. **No new dependency.**
**Storage**: MariaDB 10.11.
- **Schema change**: a nullable `modified DATETIME` on each active study's `reviews` table (`init/studies/{mci,scans,dischargesummaries}.sql`).
- **Migration**: one study-agnostic, idempotent script for existing databases, `init/migrations/014-reviews-modified.sh`.
- **No shared-table change.**
- **Files**: superseded scrubbed packets are renamed in `DOWNLOADS_DIR`, never deleted.

**Testing**: pytest (`python3 -m pytest flask_backend/tests`, with system python3); frontend lint and build.
**Target Platform**: Linux server, Docker Compose deployments, one study per deployment.
**Project Type**: web application (Flask API + React SPA).
**Performance Goals**: no new hot paths. Each guard adds one in-memory check per request.
**Constraints**:
- No PHI in logs. File-move logs record the event id and a count, not names.
- `FILES_DIR` is read-only.
- The mci export keeps its existing column positions (Principle III).
- Shared tables are MyISAM, so there are no transactions: guards are re-checked immediately before the write.

**Scale/Scope**:
- ~10 backend touch points in `app.py` and `table_service.py`;
- 3 study modules;
- 3 SQL files and 1 migration;
- 4 frontend pages and 2 study forms;
- 2 new test modules and 2–3 updated ones.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

| Principle / rule | Assessment |
|---|---|
| I. Single codebase | ✅ Shared helpers. Study-specific form changes stay in `frontend/src/studies/<study>/`; study-specific storage stays in `models/studies/<study>.py`. |
| II. Data isolation | ✅ No cross-study access. The migration acts only on the deployment's own `reviews`. |
| III. Legacy compatibility | ✅ The legacy `logs` table only gains new rows, in the existing column layout. The only change to the legacy mci `reviews` table is an additive nullable column, shipped with a migration script. The mci export **appends** two columns and keeps every existing position; the golden test is updated deliberately. No change to the lifecycle states or the role flags. |
| IV. Configuration over forks | ✅ The window and the editability rules read `cfg.scrubbing`, `cfg.screening` and `cfg.reviewer_count`, never `STUDY_TYPE`. The `modified` column is in each study's own schema file. Shared code reads review rows only through the study module (`replace_review`, `get_reviews`). It relies only on `reviewer_id` to pick out the caller's own item. |
| V. Workflow parity | ✅ No state is added, removed or renamed. `derive_pre_send_status` generalizes the existing transitions using the existing bypass flags. |
| VI. Pre-release discovery | ✅ research.md records the observed behavior before each change. The upload guard's prior behavior (spec 013 D3) and the admin bypass are cited with `file:line`. |
| Security: authorization | ✅ The new PUT carries `@requires_auth` and `@requires_any_role('reviewer','admin')`, plus a slot check. The upload site check is unchanged. Admin rights are narrowed, not widened. |
| Security: file storage | ✅ Writes go to `DOWNLOADS_DIR` only. A legacy scrubbed copy in `FILES_DIR` is masked by the `scrub_date` check, not moved. |
| Workflow: schema changes ship a migration plan | ✅ `init/migrations/014-reviews-modified.sh`, plus the order of steps in quickstart.md. |
| Workflow: `openapi.json` regenerated | ✅ Required in the same PR (contracts/api.md). |
| Workflow: PR states the studies affected | ✅ **All studies** (shared code), with per-study notes in contracts/api.md. |

**Result: PASS. No violations, so the Complexity Tracking section is empty.**

*Post-design re-check (after Phase 1): PASS.* One rejected alternative is worth recording: storing the edit date on `events`. It was rejected because an un-migrated database would break every event query (research D5).

## Project Structure

### Documentation (this feature)

```text
specs/014-packet-replace-review-edit/
├── spec.md
├── plan.md              # this file
├── research.md          # D1–D15
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── api.md
│   └── ui.md
├── checklists/requirements.md
└── tasks.md             # /speckit.tasks (not created here)
```

### Source Code (repository root)

```text
init/
├── studies/mci.sql                      # + reviews.modified
├── studies/scans.sql                    # + reviews.modified
├── studies/dischargesummaries.sql       # + reviews.modified
└── migrations/014-reviews-modified.sh   # NEW, idempotent, any study

flask_backend/
├── app.py
│   ├── packet_replaceable()             # NEW (D1); used by upload_raw, GET /events/<id>
│   ├── events_upload_raw                # window guard; downstream reset; file move; logs row (D2, D4, D14)
│   ├── events_upload_scrubbed           # packet_version guard (D12); status via derive_pre_send_status (D3)
│   ├── events_screen                    # scrub-first guard (D13); accept status via derive_pre_send_status (D3)
│   ├── events_get (GET /events/<id>)    # + packet_replaceable, file_number, packet_replaced_at (D10, D12, D14)
│   ├── events_download                  # ignore scrubbed copy when scrub_date is NULL (D4)
│   ├── events_review (POST)             # + re-processing 409 (D3)
│   ├── events_review_put                # NEW PUT (D6)
│   ├── events_review_get                # mine.{modified,editable,locked_reason,answers} (D8)
│   └── review_editable()                # NEW (D6)
├── table_service.py
│   ├── derive_pre_send_status(), pre_assignment_complete()  # NEW, Foundation (D3)
│   ├── to-be-sent predicate + send_events guard (D3)
│   └── get_events_for_reupload          # window predicate (D10)
├── models/studies/__init__.py           # interface docs: replace_review, modified
├── models/studies/mci.py                # modified column, replace_review, check_schema, export
├── models/studies/scans.py              # same
├── models/studies/dischargesummaries.py # same (replace_review unreachable in Phase A)
└── tests/
    ├── test_packet_replace_window.py    # NEW
    ├── test_review_edit.py              # NEW
    ├── test_upload_guard.py             # updated expectations
    ├── test_mci_export_golden.py        # + two trailing columns
    └── test_scans_review.py             # export column, get_reviews.modified

frontend/src/
├── pages/EventReview.jsx                # edit / read-only states; replacement notice (ui.md)
├── pages/EventScrub.jsx                 # send packet_version; show 400/409 text
├── pages/EventScreen.jsx                # show 409 text
├── App.jsx                              # pass `workflow` to EventReview (reviewer_count)
├── pages/EventUpload.jsx                # packet_replaceable, "Replace packet"
├── pages/EventReupload.jsx              # copy
├── pages/EventEdit.jsx                  # "Last edited"
├── studies/mci/MciReviewForm.jsx        # initialReview, mode, onSaved
├── studies/scans/ScansReviewForm.jsx    # initialReview, mode, onSaved
└── studies/dischargesummaries/DischargesummariesReviewForm.jsx  # accept props (no-op)

openapi.json                              # regenerated
```

**Structure Decision**: This is the existing web-application layout (`flask_backend/`, `frontend/`, `init/`). No new directories except the new migration file.

## Delivery Order (for /speckit.tasks)

1. **Foundation.** `derive_pre_send_status` / `pre_assignment_complete` (needed by every story); schema files, the migration, the ORM `modified` column, `check_schema` (needed by US2/US3).
2. **US1, packet replacement (P1).**
   - `packet_replaceable`, `derive_pre_send_status`, the `upload_raw` reset and file move;
   - the scrub and screen status helper, the send guard and queue, the download mask, the reupload queue, `GET /events/<id>.packet_replaceable`;
   - the stale-scrub and screen-before-scrub guards, the `logs` record, `packet_replaced_at`;
   - the upload, reupload, scrub and screen pages, and the review-page replacement notice;
   - tests.
3. **US2 + US3, review editing (P1/P2).** They share one rule, `review_editable`.
   - `replace_review` per study, PUT, the GET `mine` fields, the POST re-processing guard;
   - the form props, the EventReview states, EventEdit "Last edited", the exports;
   - tests.
4. **Polish.** `openapi.json`, the interface docstring, the PR description listing studies affected and the admin-rights narrowing.

US1 and US2/US3 are independent after step 1 and can ship in either order.

## Complexity Tracking

No constitution violations; nothing to justify.
