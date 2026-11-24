---
description: "Task list for 014-packet-replace-review-edit"
---

# Tasks: Packet Replacement Before Review, and Editable Reviews

**Input**: Design documents from `/specs/014-packet-replace-review-edit/`
**Prerequisites**: plan.md, spec.md, research.md (D1–D15), data-model.md, contracts/api.md, contracts/ui.md, quickstart.md

**Tests**: Included. plan.md and research D11 name the new test modules, and the constitution's testing discipline asks for integration tests on changed endpoints.
- Backend tests: `python3 -m pytest flask_backend/tests` (system python3; `.venv` lacks pytest).
- Frontend checks: lint and build only (`cd frontend && npm run lint && npm run build`). Install no packages.

**Conventions**:
- **Fixtures.** Reuse the existing fixtures: `admin_client` and `client_as` in `flask_backend/tests/conftest.py`, and the `FakeEvent`, `downloads`, `_install` and `_upload` patterns in `flask_backend/tests/test_upload_guard.py`.
- **Config.** Read every workflow flag through `get_workflow_config()`. Never branch on `STUDY_TYPE`.
- **Logging.** Log no PHI: ids and counts only.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: can run in parallel (different files, no dependency on an incomplete task)
- **[Story]**: US1 = packet replacement, US2 = single-reviewer review editing, US3 = two-reviewer review editing (spec.md)

---

## Phase 1: Setup

- [X] T001 Confirm the baseline before any change: run `python3 -m pytest flask_backend/tests` and `cd frontend && npm run lint && npm run build` from the repo root, and record any failures that already exist in `specs/014-packet-replace-review-edit/pr-description.md`, under "Baseline".

---

## Phase 2: Foundational

- **T008a and T008b** block **all** stories.
- **T002–T009** (other than T008a and T008b) block **US2 and US3** only, so US1 may start once T008a and T008b are done.
- Any deploy that includes T002–T007 requires the 014 migration first (quickstart §1).

**Purpose**: the workflow-status helpers (research D3), the `reviews.modified` column (research D5), its migration, and the schema checks.

- [X] T002 [P] Add `` `modified` datetime NULL DEFAULT NULL `` to `CREATE TABLE reviews` in `init/studies/mci.sql`, after `ecg_type`, with a comment `-- last edit by the author; NULL = never edited (spec 014)`.
- [X] T003 [P] Add `` `modified` datetime NULL DEFAULT NULL `` after `created` in `CREATE TABLE IF NOT EXISTS reviews`, in both `init/studies/scans.sql` and `init/studies/dischargesummaries.sql`.
- [X] T004 [P] Create `init/migrations/014-reviews-modified.sh`. Model it on `init/migrations/012-scans-reviews.sh`: `set -euo pipefail`, read `MYSQL_DATABASE` and the root password the same way, and define the same `sql`/`has_column` helpers, but **with no STUDY_TYPE restriction**.
  - If `reviews` exists and lacks `modified`, run ``ALTER TABLE `reviews` ADD COLUMN IF NOT EXISTS `modified` datetime NULL DEFAULT NULL``.
  - Print `[014-reviews-modified] Added reviews.modified.` or `... already present.`
  - Exit 0 when there is no `reviews` table, printing a notice.
  - Make the script executable (`chmod +x`).
- [X] T005 [P] In `flask_backend/models/studies/mci.py`, add `modified: Mapped[Optional[datetime.datetime]] = mapped_column(DateTime, nullable=True)` to `Reviews`, importing what is needed. Replace `check_schema` so it queries `information_schema.COLUMNS` for `reviews` (same SQL as `scans.check_schema`). It returns `"reviews.modified is missing; run init/migrations/014-reviews-modified.sh and restart the backend"` when `modified` is absent, else None.
- [X] T006 [P] In `flask_backend/models/studies/scans.py`, add the `modified` mapped column to `Reviews`. Extend `check_schema`: keep the existing 012 check first, then return the same 014 message as T005 when `modified` is not in the column set.
- [X] T007 [P] In `flask_backend/models/studies/dischargesummaries.py`, add the `modified` mapped column to `Reviews`. Replace `check_schema` with the 014 column check (same message as T005).
- [X] T008 Update the interface docstring in `flask_backend/models/studies/__init__.py`, per contracts/api.md "Study-module interface":
  - document `replace_review(session, event_id, reviewer_id, parsed) -> bool`;
  - note that `get_reviews` items carry `modified` (an ISO string or null);
  - note that `check_schema` also reports a missing `reviews.modified`;
  - state that any study activated in future must provide `modified`, both in its `reviews` DDL and in its `get_reviews` items. Shared code reads that key from module output (constitution Principle IV).
- [X] T008a In `flask_backend/table_service.py`, next to `_pre_assignment_stages`, add two helpers, with docstrings citing research D3:
  - `derive_pre_send_status(event, cfg) -> str`, implementing the data-model.md rules in order and built on `_pre_assignment_stages(cfg)`;
  - `pre_assignment_complete(event, cfg) -> bool`, true when every column from `_pre_assignment_stages(cfg)` is non-null.
- [X] T008b [P] Create `flask_backend/tests/test_derive_pre_send_status.py` as a pure unit test of `table_service.derive_pre_send_status` and `pre_assignment_complete`. Cover every row of the data-model.md table under three profiles: full (scrub, screen and send on); dischargesummaries (scrub on, screen and send off); scans (all off). In particular, an event with all enabled stage dates plus `assign_date` → `assigned`, and an event with stages but no `assign_date` → today's literal (`screened`, `scrubbed` or `uploaded`).
- [X] T009 [P] Add `flask_backend/tests/test_reviews_modified_schema.py`. For each of `mci`, `scans` and `dischargesummaries`, `check_schema` returns the 014 message when a mocked `information_schema` result lacks `modified`, and None when it has it. (For scans, the result must also include `created` and no `mci`.) Also add a test that, with `check_schema` patched to return the message, `GET /api/events/1/review` returns 503 with that text.

**Checkpoint**: after T008a and T008b, US1 can begin. After all of Phase 2, the schema and checks are ready, so US2 and US3 can begin.

---

## Phase 3: User Story 1 — Replace a packet before it reaches a reviewer (P1) 🎯 MVP

**Goal**: uploaders (same site) and admins can replace a packet until it is released to reviewers. Downstream work on the old packet is undone or refused, the assignment is kept, the event returns to `assigned` after re-processing, and reviewers in studies with no send step are told about the replacement (spec FR-001 to FR-006, FR-017 to FR-020; research D1 to D4, D10, D12 to D15).

**Independent test**: quickstart.md §2 steps 1–8.

### Tests for US1 (write first; they must fail before implementation)

- [X] T010 [P] [US1] Create `flask_backend/tests/test_packet_replace_window.py` covering `upload_raw` against the window (research D1). Use the `FakeEvent`/`_install` pattern from `test_upload_guard.py`, extended with `send_date`, `review1_date`, `review2_date`, `scrub_date`, `screen_date`, `assign_date` and the reviewer ids.
  - (a) Same-site uploader: 200 for each of `uploaded`, `scrubbed`, `screened`, `assigned` and `rejected`, with no send or review date.
  - (b) Uploader **and** admin: 409, with the exact message from contracts/api.md, for `sent`, `reviewer1_done`, `done`, and for `assigned` with `review1_date` set.
  - (c) `no_packet_available`: admin 200, uploader 409.
  - (d) Other-site uploader: 403 at `scrubbed`.
- [X] T011 [US1] In the same file, test the replacement side effects (research D2, D4, D14) with scrubbing on (default profile).
  - A `scrubbed` event with `reviewer1_id` set: `scrub_date`, `scrubber_id`, `screen_date` and `screener_id` are cleared, while `reviewer1_id` and `assign_date` are kept.
  - A pre-existing `<id>.pdf` in the `downloads` tmp dir is renamed to match `superseded_<id>_\d{14}\.pdf`.
  - A `models.Logs` row is added with `controller='events'`, `action='replace_packet'` and `params='event_id=<id>;assigned=1'`.
  - A first upload (`created`) adds no `Logs` row and renames nothing.
*(T012 moved to Phase 2 as T008b, so the helper tests sit with the helpers.)*
- [X] T013 [P] [US1] Create `flask_backend/tests/test_reprocess_guards.py` (research D3, D12, D13).
  - (a) `upload_scrubbed` without `packet_version` → 400. With a mismatched `packet_version` → 409 with the "was replaced on" text, and no file written. With the matching version → 200, and the status comes from the helper: `assigned` for an event with `assign_date` under the dischargesummaries profile.
  - (b) `POST /screen` with scrubbing on and `scrub_date=None` → 409 for `accept`, `rescrub` and `reject`, with nothing changed. `accept` on a scrubbed, still-assigned event → `assigned`.
  - (c) `send_many` with one event lacking `scrub_date` → 400 "These events are not ready to send: <id>", and no event changed.
  - (d) `POST /review` on an assigned event with `scrub_date=None` under the dischargesummaries profile → 409 "This event's packet is being re-processed."
- [X] T014 [P] [US1] Create `flask_backend/tests/test_event_details_replace_fields.py` for `GET /api/events/<id>` (research D10, D14).
  - `packet_replaceable` is true for a same-site uploader at `scrubbed`, false at `sent`, and false for an other-site uploader.
  - `file_number` is present.
  - `packet_replaced_at` is the ISO time of the latest `replace_packet` row with `assigned=1` when sending is off, null when sending is on, and null when only `assigned=0` rows exist.
- [X] T015 [US1] Update `flask_backend/tests/test_upload_guard.py` to the new rules. Rename `test_admin_may_upload_after_scrub` to assert admin 200 at `scrubbed` **and** 409 at `sent`. Change `test_same_site_uploader_is_refused_after_upload_stage` to parametrize over post-window statuses (`sent`, `reviewer1_done`, `done`) and assert the new 409 message. Keep the 403-before-409 and 400 tests. Update the module docstring to cite spec 014.

### Implementation for US1

- [X] T016 [US1] In `flask_backend/table_service.py`, next to the T008a helpers, add the window constants:
  - `REPLACEABLE_STATUSES = ('created','uploaded','scrubbed','screened','assigned','rejected')`, and `_replace_window_predicate() -> str` returning `"e.status IN ('uploaded','scrubbed','screened','assigned','rejected') AND e.send_date IS NULL AND e.review1_date IS NULL AND e.review2_date IS NULL"`, which excludes `created` (research D10).

  Write docstrings that cite research D1 and D10.
- [X] T017 [US1] In `flask_backend/app.py`, add `packet_replaceable(session, event, auth_user) -> tuple[bool, Optional[str]]` beside `_uploader_may_act_on_event`. It returns `(False, 'site')` when `_uploader_may_act_on_event` fails. Otherwise, admin with `status == 'no_packet_available'` → allowed; non-admin `no_packet_available` → `(False, 'window')`; otherwise allowed iff `status in REPLACEABLE_STATUSES` and `send_date`, `review1_date` and `review2_date` are all None. Write a docstring recording the observed pre-014 behavior (admin bypass at any status; uploader `created`/`uploaded` only, `app.py:2094`) per Principle VI.
- [X] T018 [US1] Rewrite the guard and update block of `events_upload_raw` in `flask_backend/app.py` (depends on T008a, T016, T017).
  - **Guard.** Replace the spec 013 status check with `packet_replaceable`: `'site'` → 403 as today; `'window'` → 409 with `"This packet has already been released to reviewers and can no longer be replaced here. Contact the systems operator."`
  - **Replacement.** Before saving, note `is_replacement = e.upload_date is not None` and `was_assigned = e.reviewer1_id is not None`. After saving the file, when `is_replacement`:
    - (i) rename each existing `DOWNLOADS_DIR/<id><ext>` for `ext in ALLOWED_PACKET_EXTENSIONS`, plus `event_<id>.zip`, to `superseded_<id>_<YYYYmmddHHMMSS><ext>`, and log `"Superseded %d scrubbed file(s) for event %d"` at INFO;
    - (ii) set `scrub_date`, `scrubber_id`, `screen_date` and `screener_id` to None;
    - (iii) add `models.Logs(user_id=int(auth_user.get('id') or 0), controller='events', action='replace_packet', params=f'event_id={event_id};assigned={1 if was_assigned else 0}', time=datetime.datetime.now())`.
  - **Status.** **After** setting `upload_date` and the other upload metadata (otherwise a first upload would derive `created`), set `e.status = table_service.derive_pre_send_status(e, get_workflow_config())` instead of `'uploaded'`.
  - **Docs.** Update the docstring and the YAML `responses` per contracts/api.md.
- [X] T019 [US1] In `events_upload_scrubbed` in `flask_backend/app.py` (research D12):
  - Read `request.form.get('packet_version')`. Missing or non-integer → 400 `{"error": "packet_version is required"}`.
  - Load the event **before** `file.save`. If `int(packet_version) != e.file_number`, return 409 `{"error": f"This event's packet was replaced on {e.upload_date}. Download the new packet and scrub it."}` with nothing written.
  - Move the save after these checks. Set `e.status = table_service.derive_pre_send_status(e, cfg)` after stamping `scrub_date`.
  - Update the docstring and YAML.
- [X] T020 [US1] In `events_screen` in `flask_backend/app.py` (research D13): after loading the event, if `get_workflow_config().scrubbing` and `e.scrub_date is None`, return 409 `{"error": "This event's packet has not been scrubbed yet."}`. For `accept`, set `e.status = table_service.derive_pre_send_status(e, cfg)` after stamping `screen_date`. Update the docstring.
- [X] T021 [US1] In `flask_backend/table_service.py` (research D3):
  - `get_to_be_sent_with_total`: AND the existing predicate with every `e.<col> IS NOT NULL` from `_pre_assignment_stages(cfg)`.
  - `send_events`: before any change, compute the ids failing `pre_assignment_complete`, and raise `ValidationError(f"These events are not ready to send: {ids}")` if any do.
  - `events_send_many` in `flask_backend/app.py`: catch `table_service.ValidationError` → 400 `{'error': str(ve)}`.
- [X] T022 [US1] In `events_review` (POST) in `flask_backend/app.py`, after the slot check and before the duplicate check: if `not table_service.pre_assignment_complete(e, cfg)`, return 409 `{"error": "This event's packet is being re-processed."}`. Add the 409 to the YAML.
- [X] T023 [US1] In `events_download` in `flask_backend/app.py` (research D4): when `get_workflow_config().scrubbing` and the event exists with `scrub_date is None`, skip `_find_scrubbed_packet`, so `path` comes from the raw lookup. Add a comment explaining that a legacy scrubbed copy in read-only `FILES_DIR` cannot be renamed.
- [X] T024 [US1] In `flask_backend/table_service.py`, change `get_events_for_reupload` to use `_replace_window_predicate()` through the same `_phase_rows_with_total` helper the phase queues use. Keep the site scoping and the ID-ascending order, and keep the row shape (ID, Patient ID, Date, Criteria) used by `EventReupload.jsx`. Update its docstring (research D10).
- [X] T025 [US1] In `get_event_details` (`GET /api/events/<id>`) in `flask_backend/app.py`, and in `table_service.get_event_details`:
  - add `e.file_number AS file_number` to the details query;
  - add `packet_replaceable` (from `packet_replaceable(...)[0]`);
  - add `packet_replaced_at`. When `not cfg.sending`, it is `SELECT MAX(time) FROM logs WHERE controller='events' AND action='replace_packet' AND params = :p` with `p = f'event_id={id};assigned=1'`, formatted ISO; otherwise None.

  Update the YAML response schema (contracts/api.md).
- [X] T026 [P] [US1] In `frontend/src/pages/EventScrub.jsx`, append `form.append('packet_version', String(details?.file_number ?? ''))` before posting. For any non-OK response other than 401/403, read `j.error` from the JSON and show it in the toast; on 409, also call `e.target.reset()`. Remove the stale "Placeholder endpoint" comment.
- [X] T027 [P] [US1] In `frontend/src/pages/EventScreen.jsx`, for a non-OK response other than 401/403, read the JSON `error` and render it in place of "Failed to save." (store it in state, e.g. `errorText`).
- [X] T028 [P] [US1] In `frontend/src/pages/EventUpload.jsx` (contracts/ui.md):
  - When `details && details.status !== 'created' && details.packet_replaceable === false`, render the released-to-reviewers message instead of the upload form. Leave the no-packet section's existing behavior unchanged.
  - When `details?.upload_date` is set, label the submit button "Replace packet".
  - When `(workflow.scrubbing || workflow.screening)`, show the one-line note. The `workflow` prop is already passed in by `App.jsx:290`.
- [X] T029 [P] [US1] In `frontend/src/pages/EventReupload.jsx`, change the count line to `` `${rows.length} event(s) can have their packet replaced. A packet can be replaced until it is sent to reviewers.` `` and the empty state to "No packets can be replaced right now." (contracts/ui.md).
- [X] T030 [P] [US1] In `frontend/src/pages/EventReview.jsx`, when `eventDetails?.packet_replaced_at` is set, render above "Step 1" a `<div role="alert" className="warning-notice">` with "This event's packet was replaced on {packet_replaced_at}. If you downloaded it before then, download it again before reviewing." Use an existing warning style if one exists in `frontend/src/index.css` or `App.css`; otherwise add a minimal `.warning-notice` rule there.
- [X] T031 [US1] Run `python3 -m pytest flask_backend/tests` until T010–T015 pass along with the full suite. Fix regressions caused by the status-helper changes in `test_scans_workflow.py`, `test_dischargesummaries_workflow.py` and `test_full_workflow_defaults.py`, and record any intended expectation change in `pr-description.md`.

**Checkpoint**: US1 is complete and shippable alone (MVP).

---

## Phase 4: User Story 2 — Single-reviewer review editing (P1)

**Goal**: in a `REVIEWER_COUNT=1` deployment, the author can reopen their review pre-filled, edit it, and save. Answers are overwritten, `modified` is stamped, and status and dates are unchanged. This works even after `done` (FR-007, FR-010 to FR-016; research D6 to D9). The shared rule built here also contains the two-reviewer branch, which US3 exercises.

**Independent test**: quickstart.md §3, "Single-reviewer study".

### Tests for US2

- [X] T032 [P] [US2] Create `flask_backend/tests/test_review_edit.py` with the scans profile (`STUDY_TYPE=scans`, so reviewer_count is 1). Patch `_study_schema_error` to None and patch the study module's `replace_review` and `get_reviews` where needed.
  - (a) The author (slot 1, `review1_date` set) PUTs a valid scans body → 200 with `modified`; `status` and `review1_date` are unchanged; `replace_review` is called with the parsed body.
  - (b) The same at `status='done'` → 200.
  - (c) An invalid body → 400 with `fields`.
  - (d) A non-assigned reviewer → 403. A slot-3 reviewer → 403. An admin not in a slot → 403.
  - (e) The author with no `review1_date` → 409 "You have not submitted a review for this event".
  - (f) `replace_review` returns False → 409, same message.
  - (g) `GET /review` as the author → `mine.editable` is true, `mine.locked_reason` is None, `mine.answers` is the caller's own item, and `mine.modified` is present.
  - (h) `GET` as a non-admin never includes another reviewer's answers.
- [X] T033 [P] [US2] In `flask_backend/tests/test_scans_review.py`, add tests for `scans.replace_review` and `get_reviews`:
  - `replace_review` replaces the `regions` set and sets `modified`, using a mocked session or the existing in-memory approach in that file;
  - `get_reviews` items include `modified` (an ISO string or None);
  - the export header includes `Last Edited` immediately after `Review Date`, and the row value is the ISO `modified` or empty.

### Implementation for US2

- [X] T034 [US2] In `flask_backend/app.py`, add `review_editable(e, slot, cfg) -> tuple[bool, Optional[str]]` beside `_reviewer_slot`, per data-model.md "Editability":
  - not slot 1/2, or no own `reviewN_date` → `(False, None)`;
  - not `table_service.pre_assignment_complete(e, cfg)` → `(False, "This event's packet is being re-processed.")`;
  - `cfg.reviewer_count == 2` and the other slot's date is set → `(False, "Both reviews for this event are complete, so they can no longer be changed.")`;
  - otherwise `(True, None)`.
- [X] T035 [US2] In `flask_backend/models/studies/scans.py`:
  - add `replace_review(session, event_id, reviewer_id, parsed) -> bool`. It loads `Reviews` by `(event_id, reviewer_id)`, returns False if absent, sets `review.regions = [ReviewRegions(**entry) for entry in parsed]` and `review.modified = datetime.datetime.now()`, returns True, and does not commit;
  - in `get_reviews`, select `rv.modified` and emit `"modified": iso-or-None` per review;
  - add `"Last Edited"` after `"Review Date"` in `EXPORT_HEADINGS`, add `rv.modified AS modified` to `_EXPORT_SQL`, and add `"modified"` after `"review_date"` in the `export_csv` key tuple.
- [X] T036 [P] [US2] In `flask_backend/models/studies/dischargesummaries.py`:
  - add `replace_review`, which raises `RuntimeError("dischargesummaries reviews cannot be stored until Phase B")`;
  - add `"Last Edited"` after the review-date heading in `EXPORT_HEADINGS`, with an empty cell per row in `export_csv`;
  - update the module docstring.
- [X] T037 [US2] Add `PUT /api/events/<int:event_id>/review` to `flask_backend/app.py` as `events_review_put`, with `@requires_auth` and `@requires_any_role('reviewer', 'admin')` (contracts/api.md, research D6). The flow mirrors POST:
  1. empty body → 400;
  2. `study.parse_review` → 400 on `ReviewValidationError`;
  3. schema check → 503;
  4. event → 404;
  5. `_reviewer_slot` not in (1, 2) → 403;
  6. `review_editable`: on False with reason None → 409 "You have not submitted a review for this event"; otherwise 409 with the reason;
  7. `study.replace_review(...)`: False → 409 "You have not submitted a review for this event";
  8. commit, then return `{"data": {"event_id", "status", "modified"}}`.

  It never touches `status` or `reviewN_date`. Roll back and log on exception. Write a full YAML docstring.
- [X] T038 [US2] Extend `events_review_get` in `flask_backend/app.py` (research D8). When `mine` is built, load `own = [r for r in study.get_reviews(session, event_id) if r.get('reviewer_id') == user_id]` and add `modified` (from `own[0]` if present), `editable` and `locked_reason` (from `review_editable`), and `answers` (`own[0]` or None). Admin `reviews` items already carry `modified` through `get_reviews`. Update the YAML.
- [X] T039 [P] [US2] In `frontend/src/studies/scans/ScansReviewForm.jsx`:
  - accept `initialReview = null`, `mode = 'create'` and `onSaved`;
  - when `initialReview?.regions` is set, initialize `entries` from it in `position` order, mapping `region`, `region_other`, `laterality`, `t_score` and `z_score` to the entry fields, with keys generated the way `addEntry` does;
  - when `mode === 'edit'`, use `method: 'PUT'` and the button label "Save changes";
  - on 2xx call `onSaved?.()`, and do not lock the form as "submitted" in edit mode;
  - on 409 show the server `error`.
- [X] T040 [P] [US2] In `frontend/src/studies/dischargesummaries/DischargesummariesReviewForm.jsx`, accept and ignore `initialReview`, `mode` and `onSaved` (extend the eslint-disable comment).
- [X] T041 [US2] In `frontend/src/pages/EventReview.jsx`, implement the `mine` states table from contracts/ui.md:
  - `reviewStatus` becomes `'open' | 'editable' | 'locked' | 'loading' | 'error'`, from `mine?.editable`;
  - `'editable'`: the notice "You submitted this review on {review_date}{, last edited {modified}}. You can change your answers until the other reviewer submits theirs." (drop the "until…" clause when `workflow.reviewer_count === 1`; pass `workflow={workflow}` to `<EventReview>` in `frontend/src/App.jsx:312`, since `App.jsx` already holds `workflow.reviewer_count`), then `<ReviewForm eventId apiUrl initialReview={mine.answers} mode="edit" onSaved={refetch} />`;
  - `'locked'`: "You have already reviewed this event (submitted …{, last edited …})." plus `mine.locked_reason`, then `resolveReviewSummary(reviewForm)` rendering `review={mine.answers}`;
  - after `onSaved`, re-fetch `GET /review` and show "Your changes were saved.";
  - a 409 reported by the form triggers a re-fetch, which switches the page to locked.
- [X] T042 [P] [US2] In `frontend/src/pages/EventEdit.jsx`, in `reviewRow(slot)`, render `<div>Last edited: {r.modified}</div>` under `<ReviewSummary review={r} />` when `r.modified` is set (contracts/ui.md).
- [X] T043 [US2] Run `python3 -m pytest flask_backend/tests` and `cd frontend && npm run lint && npm run build`; fix failures.

**Checkpoint**: single-reviewer editing works end to end.

---

## Phase 5: User Story 3 — Two-reviewer review editing, with a lock (P2)

**Goal**: in a `REVIEWER_COUNT=2` deployment (mci), each reviewer may edit until the other has submitted; after that both are read-only (FR-008, FR-009; research D6, D7, D9).

**Independent test**: quickstart.md §3, "Two-reviewer study".

### Tests for US3

- [X] T044 [P] [US3] Add two-reviewer cases to `flask_backend/tests/test_review_edit.py`, with `STUDY_TYPE=mci` (default reviewer_count 2):
  - (a) slot 1 reviewed, `review2_date` None → PUT 200, and `status` stays `reviewer1_done`;
  - (b) both dates set → PUT 409 "Both reviews…" for slot 1 **and** slot 2, with `replace_review` not called;
  - (c) `GET` for the slot-1 author with both dates set → `editable` false and `locked_reason` "Both reviews…";
  - (d) race: `review2_date` set between page load and save → 409 (simulate by setting it on the fake event before the PUT).
- [X] T045 [P] [US3] Update `flask_backend/tests/test_mci_export_golden.py`:
  - append `,Review 1 Last Edited,Review 2 Last Edited` to the header of `EXPECTED_CSV`, with the matching values (`review1_modified-v`, `review2_modified-v`) on `ROW_1` and empty cells on `ROW_2`;
  - add `review1_modified`/`review2_modified` keys to `ROW_1`;
  - append `rv1.modified AS review1_modified, rv2.modified AS review2_modified` to the end of the select list in `EXPECTED_EXPORT_SQL`, after `edd.ci AS overall_ci`;
  - add a comment citing spec 014 research D9 (appended, so no existing position moves).
- [X] T046 [P] [US3] Add `flask_backend/tests/test_mci_replace_review.py`. `mci.replace_review` sets every answer column from `parsed`, sets absent columns to None, sets `modified`, and returns False when there is no row. `mci.get_reviews` returns `modified` as an ISO string.

### Implementation for US3

- [X] T047 [US3] In `flask_backend/models/studies/mci.py`:
  - add `replace_review(session, event_id, reviewer_id, parsed) -> bool`. It loads the row by `(event_id, reviewer_id)` (`.first()`), returns False if missing, and for every column in `Reviews.__table__.columns` except `id`, `event_id`, `reviewer_id` and `modified` sets `parsed.get(name)` (NOT NULL `mci`/`cardiac_cath` are always in `parsed`). It sets `modified = datetime.datetime.now()`, returns True, and does not commit;
  - in `get_reviews`, isoformat `modified`;
  - append `rv1.modified AS review1_modified, rv2.modified AS review2_modified` to `_EXPORT_SQL`, and append the headings `Review 1 Last Edited` and `Review 2 Last Edited` and their keys at the end of the `export_csv` header and row lists.
- [X] T048 [P] [US3] In `frontend/src/studies/mci/MciReviewForm.jsx`:
  - accept `initialReview = null`, `mode = 'create'` and `onSaved`;
  - when `initialReview` is set, initialize each `useState` from it: `mci`; flags `abnormal_ce_values_flag`, `chest_pain_flag`, `ecg_changes_flag`, `lvm_by_imaging_flag` and `false_positive_flag` → booleans; `ci`, `current_tobacco_use_flag`, `past_tobacco_use_flag`, `cocaine_use_flag`, `family_history_flag` and `cardiac_cath` → `'1'`/`'0'`/`''`; text fields `ce_criteria`, `ci_type`, `type`, `secondary_cause`, `other_cause`, `false_positive_reason`, `false_positive_other_cause` and `ecg_type` → strings, with `''` for null;
  - in edit mode, PUT and use the button label "Save changes";
  - on 2xx call `onSaved?.()`; on 409 show the server `error`.
- [X] T049 [US3] Run `python3 -m pytest flask_backend/tests` and `cd frontend && npm run lint && npm run build`; fix failures.

**Checkpoint**: all three stories are complete.

---

## Phase 6: Polish & cross-cutting

- [ ] T050 Regenerate the API contract: `python -m flask_backend.generate_openapi` from the repo root, and commit `openapi.json`. Verify that the diff shows the new PUT `/review`, the `packet_version` field, and the new 409/400 responses.
- [ ] T051 [P] Write `specs/014-packet-replace-review-edit/pr-description.md`. It covers:
  - studies affected (**all**: shared code; per-study notes from contracts/api.md);
  - the "will this break another study's deployment?" answer;
  - **admin rights narrowed** (no replacement after send or review);
  - the **migration must run before deploy** (quickstart §1);
  - the export column changes (mci appended at the end; scans and dischargesummaries inserted);
  - first use of the `logs` table;
  - observed-behavior notes (research D1–D4, D12–D14);
  - the baseline from T001.

  End with the attribution line from the session instructions.
- [ ] T052 [P] Update `docs/` operator material if a deployment or runbook doc lists migrations. Search with `grep -rl "012-scans-reviews" docs README* 2>/dev/null` and add `014-reviews-modified.sh` beside each mention, with one line on when to run it.
- [ ] T053 Walk through quickstart.md §2 and §3 against the code, as a desk check: there is no local stack. Confirm each message string in quickstart.md, contracts/api.md and contracts/ui.md matches the implemented string exactly, and fix any drift in the docs or the code.
- [ ] T054 Final full run: `python3 -m pytest flask_backend/tests` and `cd frontend && npm run lint && npm run build`, all green.

---

## Dependencies & execution order

- **Phase 1** (T001) → everything.
- **T008a, T008b** → all stories.
- **Phase 2, rest** (T002–T009) → blocks **US2 and US3** only. US1 may run in parallel with it.
- **US1** (T010–T031): tests T010 (with T011, same file), T013 and T014 [P] first; then T015. Implementation order: T016 → T017 → T018; then T019, T020, T021, T022, T023, T024 and T025. These are all in `app.py`/`table_service.py`, so run them in sequence. The frontend tasks T026–T030 are [P] with one another, after T019/T020/T025; then T031.
- **US2** (T032–T043): needs Phase 2 only. `pre_assignment_complete` comes from T008a. Order: tests T032 and T033 [P]; T034 → T035 → T036 [P] → T037 → T038; frontend T039, T040 and T042 [P], then T041 (depends on T039); then T043.
- **US3** (T044–T049): needs US2's T034, T037 and T038 (the shared PUT/GET and rule). T044–T046 [P]; T047; T048 [P with T047]; T049.
- **Polish** (T050–T054): after all chosen stories.

### Shared-file notes

`flask_backend/app.py` is touched by T017–T025, T034, T037 and T038, and `flask_backend/table_service.py` by T008a, T016, T021, T024 and T025. Do these tasks one at a time.

## Parallel examples

```text
# Phase 2: T008a first, then in parallel (alongside US1 tests):
T008b, T002, T003, T004, T005, T006, T007, T009

# US1 tests:
T010/T011 (same new file: write together), T013, T014

# US1 frontend, after the backend lands:
T026, T027, T028, T029, T030

# US2 frontend:
T039, T040, T042   → then T041

# US3:
T044, T045, T046, T048
```

## Implementation strategy

1. **MVP = US1.** US1 needs no migration **only if it ships without T002–T007**, because those tasks map `reviews.modified` in the code.
   - To ship US1 alone: merge Phase 1, T008a, T008b and Phase 3, and hold T002–T007 and T009 for increment 2.
   - Otherwise: run `init/migrations/014-reviews-modified.sh` before the deploy (quickstart §1).
2. **Increment 2 = Phase 2 + US2.** This requires the migration first (quickstart §1). It makes scans editable now; dischargesummaries gets editing when Phase B adds its form.
3. **Increment 3 = US3.** mci two-reviewer editing and the export columns.
4. **Polish**: openapi, the PR description, and the docs, before merge.
