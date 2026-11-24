# Research: Packet Replacement Before Review, and Editable Reviews

**Feature**: 014-packet-replace-review-edit | **Date**: 2026-11-24

Constitution Principle VI asks us to record observed behavior before changing it. Each decision below starts with what the code does today, citing `file:line` at commit `9182d7b`.

---

## D1. The replacement window

**Observed.** `events_upload_raw` (`flask_backend/app.py:2018`) applies two checks.

- **Site check.** A non-admin must be at the event's site (`_uploader_may_act_on_event`, `app.py:1997`).
- **Status check.** A non-admin may upload only while `status in ('created', 'uploaded')` (`app.py:2094`). After that the endpoint returns 409 "This event has moved past upload; ask an administrator." An admin passes both checks at any status, including after reviews exist.

A successful upload always sets `status = 'uploaded'` (`app.py:2112`). It leaves `scrub_date`, `screen_date` and `assign_date` untouched.

**Decision.** One shared predicate, `packet_replaceable(event, auth_user)`, applies to **admins and uploaders alike**. An event is inside the window when all of these hold:

1. `status` is one of `created`, `uploaded`, `scrubbed`, `screened`, `assigned` or `rejected`;
2. `send_date IS NULL`;
3. `review1_date IS NULL AND review2_date IS NULL`.

Two cases fall outside that rule:

- **`no_packet_available`**: today's behavior is kept. Admins may upload, uploaders may not. This feature does not touch the no-packet flow (spec edge case).
- **Every other status** (`sent`, `reviewer*_done`, `third_review_*`, `done`): replacement is refused for every role. The 409 message names the systems operator (FR-002, FR-006).

The site check is unchanged and still runs first, so an uploader at another site gets 403, not 409.

**Why statuses and dates together.** With sending on, the status set alone ends the window at `sent`. With sending off (scans, dischargesummaries), an event goes from `assigned` straight to `reviewer1_done` when the first review is submitted, so the same set ends the window at the first review. That is the cutoff the user confirmed for those studies. A single rule therefore covers both profiles without branching on `cfg.sending`.

The date checks are a safety net:

- `POST /review` does not check status (`app.py:2539`), so a review can exist on an `assigned` event.
- `send_events` stamps `send_date` even when sending is off (`table_service.py:760`).

**Alternatives rejected.**

- Branch on `cfg.sending` ("`sent` if sending, else first review"). It is equivalent, but it puts a flag branch where none is needed.
- Use "no review rows exist" as the only test. It ignores the user's Q1 answer, which put the cutoff at send.

---

## D2. What replacement undoes

**Observed.** A replacement after scrubbing leaves the old scrubbed file `<event_id><ext>` in `DOWNLOADS_DIR`. `events_download` prefers the scrubbed copy (`_find_scrubbed_packet`, `app.py:1142`), so reviewers would be sent the **old** packet. `scrub_date` stays set, so the event never re-enters the to-be-scrubbed queue (`table_service.py:536`).

**Decision.** When the window check passes and the event already has `scrub_date` or `screen_date`, `upload_raw` does the following, in addition to today's metadata writes:

- moves each scrubbed copy it can find in `DOWNLOADS_DIR` aside (D4);
- clears `scrub_date`, `scrubber_id`, `screen_date` and `screener_id`;
- **keeps** `reviewer1_id`, `reviewer2_id`, `assigner_id` and `assign_date` (spec FR-004);
- keeps `rescrub_message` and `reject_message` as history;
- sets `status` from the helper in D3, not the literal `'uploaded'`.

For a scans event (no scrub, no screen) the only effect is the new file and new upload metadata. If the event was already assigned it stays `assigned`.

**Alternative rejected: clear the assignment too.** That would make the existing queues work with no other change. Spec FR-004 requires the assignment to be kept, though, and losing it would quietly remove the event from the assigned reviewer's queue.

---

## D3. Returning a still-assigned event to `assigned` after re-processing

**Observed.** Each stage endpoint writes a hard-coded status: `upload_scrubbed` writes `'scrubbed'` (`app.py:1986`) and screen-accept writes `'screened'` (`app.py:1431`). The to-be-assigned queue requires `assign_date IS NULL` (`table_service.py:527`).

So an event that keeps its assignment through D2 never gets back to `assigned`: re-scrubbing leaves it `scrubbed`, and screening leaves it `screened`. It can't re-enter To Be Assigned either, because `assign_date` is still set. What that does depends on the profile:

- **Sending on (mci).** The to-be-sent queue is `assign_date IS NOT NULL AND send_date IS NULL` (`table_service.py:585`). It looks only at dates, so the event sits in To Be Sent the whole time, from the moment of replacement. An admin could send it **before** it has been re-scrubbed.
- **Sending off, scrubbing on (dischargesummaries).** The reviewer's queue requires `status = 'assigned'` (`table_service.py:644`). After the re-scrub the status is `scrubbed`, so the reviewer never sees the event again, and no admin queue offers a way to move it forward. It is stuck.

**Decision.** Add one shared helper, `derive_pre_send_status(event, cfg)`. It returns the status implied by which enabled stages are complete:

| Condition (first match wins) | Status |
|---|---|
| `upload_date` null | `created` |
| scrubbing on and `scrub_date` null | `uploaded` |
| screening on and `screen_date` null | `scrubbed` if scrubbing on, else `uploaded` |
| `assign_date` null | the last enabled stage's status: `screened`, `scrubbed` or `uploaded` |
| otherwise | `assigned` |

It is used by `upload_raw`, `upload_scrubbed` and screen-accept in place of their literals. For an event that was never assigned it gives exactly today's values, so existing tests should pass unchanged.

The helper is built on the existing `_pre_assignment_stages(cfg)` (`table_service.py:499`), so the queue predicates and the status cannot disagree.

**Gating send and review on re-processing.**

- The to-be-sent queue predicate, and a new guard in `send_events`, also require every pre-assignment stage to be complete. The guard mirrors the `assign_events` "not ready" guard (`table_service.py:714`): it refuses the whole batch with 400 and changes nothing.
- `POST` and `PUT /review` refuse with 409 when a pre-assignment stage is incomplete ("This event's packet is being re-processed"). This matters where scrubbing is on and sending is off (dischargesummaries). There an assigned event is visible to its reviewer, and its packet can be replaced and sent back for scrubbing.
- The reviewer queue keys on `status` (`table_service.py:636`). Because D3 sets `status` correctly, the queue needs no change.

---

## D4. The superseded scrubbed copy

**Observed.** `upload_scrubbed` writes `<event_id><ext>`. `_existing_packet` searches `DOWNLOADS_DIR`, then `FILES_DIR`, in `ALLOWED_PACKET_EXTENSIONS` order. So if the old copy was `.pdf` and the new one is `.zip`, the **old** file can still win. This is already a latent problem on the rescrub path. `FILES_DIR` is read-only (constitution, File storage).

**Decision.**

1. On replacement, rename every `<event_id><ext>` and the legacy `event_<id>.zip` in `DOWNLOADS_DIR` to `superseded_<event_id>_<YYYYmmddHHMMSS><ext>`. Nothing is deleted, so the operator decides what to keep.
2. As a belt-and-braces check, `events_download` does not serve a scrubbed copy when scrubbing is on and `scrub_date IS NULL`. That covers a legacy copy in read-only `FILES_DIR`, which cannot be renamed. A reviewer then gets 403 (raw packets are refused to reviewers when scrubbing is on). Admins and uploaders get the raw packet, which is the current one.

The log line records the event id and the file count only, never file names (PHI policy).

---

## D5. Where the "last edited" date lives

**Observed.** A review's submission date is `events.review1_date` / `review2_date` (date precision), written by shared code (`app.py:2632`). The per-study `reviews` tables have:

- **mci**: no timestamp columns (`init/studies/mci.sql:47`; legacy MyISAM);
- **scans**: `created` (`init/studies/scans.sql:24`);
- **dischargesummaries**: `created` (`init/studies/dischargesummaries.sql:24`).

**Decision.** Add a nullable `modified DATETIME` column to **each study's `reviews` table**, and write it through the study module (Principle IV: any review read or write other than `id`, `event_id` and `reviewer_id` goes through the study module). NULL means never edited. `modified` is CakePHP's conventional name.

**Alternative rejected: `events.review1_modified` / `review2_modified`.** The pair would sit next to the submission dates, and one migration would cover every study. It was rejected for two reasons.

1. `models.Events` is mapped on every request path. Mapping a column that an un-migrated database lacks makes **every** event query fail. A per-study column fails only the review endpoints, and those already have a graceful 503 path (`_study_schema_error`, `app.py:2520`).
2. The user confirmed the per-study-table approach.

**Migration.** Add `init/migrations/014-reviews-modified.sh`. It is study-agnostic and idempotent:

```sql
ALTER TABLE `reviews` ADD COLUMN IF NOT EXISTS `modified` datetime NULL DEFAULT NULL;
```

MariaDB 10.11 supports `IF NOT EXISTS` here. It is safe to run **before** deploying the new code, because the old code's ORM maps explicit columns and ignores an extra nullable one. New databases get the column from `init/studies/{mci,scans,dischargesummaries}.sql`.

The dormant study files (vte, afib, cva, hf) are not touched; they become active only when a future feature wires them.

**Schema check.** Each study's `check_schema` also reports a missing `modified` column, with a message naming the 014 migration.

- **mci**: today's check always returns None. It gains this one check.
- **scans**: the 012 check is kept, and a separate 014 message is added.
- **dischargesummaries**: in Phase A, `check_schema` exists and returns None. It gains the same check.

---

## D6. Edit endpoint and editability rule

**Observed.** `POST /review` returns 409 when the caller's slot date is already set (`app.py:2619`). `GET /review` returns `mine = {slot, review_date}` (`app.py:2733`).

**Decision.**

- **Endpoint.** Add `PUT /api/events/<id>/review`. It takes the same body as POST and is validated by the same `study.parse_review` (FR-011).
- **Editability rule.** A single helper, `review_editable(event, slot, cfg) -> (bool, reason)`, is shared by PUT and by GET's `mine.editable` / `mine.locked_reason`:
  - the slot is 1 or 2, and that slot's `reviewN_date` is set;
  - `cfg.reviewer_count == 1`, **or** the other slot's `reviewN_date` is null (FR-007 to FR-009, FR-016).
- **Responses.**
  - 403: not an assigned reviewer in slot 1 or 2. This includes slot 3 and admins who are not the reviewer, as with POST.
  - 409: no review by the caller to edit, or the review is locked. Distinct messages for each.
  - 400: validation failure, as with POST.
  - 503: un-migrated database.
  - 200: `{event_id, status, modified}`.
- **Effect.** PUT writes the answers and sets `modified = now`. It does **not** change `status` or `reviewN_date` (FR-013). In two-reviewer studies PUT never runs after both reviews exist, so the disagreement and third-review checks cannot be re-triggered.
- **Locating the row.** The review row is looked up by `(event_id, reviewer_id = caller)`. If the slot was reassigned, the new holder has no row and gets 409 (FR-010).
- **Concurrency.** Both the shared `events` table and the mci `reviews` table are MyISAM, so there are no row locks. The rule is re-checked in the same request just before the write. The window between the check and the write is a few milliseconds of a single request. For a two-reviewer study's two people this is accepted, and the spec's race scenario is handled at save time.

---

## D7. Study-module interface additions

The interface documented in `flask_backend/models/studies/__init__.py` gains:

- **`replace_review(session, event_id, reviewer_id, parsed) -> bool`.** Overwrites the caller's stored answers in place, sets `modified`, and does not commit. It returns False when no row exists.
  - **mci**: every answer column is set from `parsed`, and a column absent from `parsed` becomes NULL. `mci` and `cardiac_cath` are NOT NULL, and `parse_review` always supplies both.
  - **scans**: replaces `regions` (the existing `delete-orphan` cascade) and sets `modified`.
  - **dischargesummaries**: raises, like `add_review`. It cannot be reached in Phase A.
- **`get_reviews` items gain `modified`**, as an ISO-8601 string or null.
  - **mci**: today it returns raw column values. It now isoformats `modified`, so the JSON is not RFC-822.

In-place update keeps the review's `id` and scans' `created`. That satisfies "one row per reviewer per event", and the export joins (`reviewer_id = e.reviewerN_id`) need no change.

---

## D8. What the reviewer's own GET returns

**Observed.** `reviews` (answers) go to admins only. Other callers get `[]` (spec 012 FR-028).

**Decision.** `mine` gains the following fields:

- `modified`: ISO string or null;
- `editable`: boolean;
- `locked_reason`: string or null;
- `answers`: the caller's **own** review, in the same shape as an admin `reviews` item.

The frontend needs `answers` to pre-fill the form (FR-015). Reviewers still never see another reviewer's answers, so FR-028's intent, keeping the two reviews independent, is preserved.

---

## D9. Admin visibility and exports (FR-014)

- **`/events/edit` (`EventEdit.jsx`).** Each review row gains "Last edited: <date>" from the shared `item.modified`. This is rendered by the page, not by each study's summary, so no per-study summary changes.
- **scans export.** Adds `Last Edited` immediately after `Review Date`. Inserting mid-row is acceptable because this is the app's own pre-release format (Principle VI).
- **mci export.** Appends `Review 1 Last Edited` and `Review 2 Last Edited` at the **end** of the row, so position-based downstream consumers of the legacy layout keep working (Principle III). `test_mci_export_golden.py` is updated deliberately in the same change, and the PR states the column addition.
- **dischargesummaries export.** Adds `Last Edited` after `Review Date`. It is always empty in Phase A.

---

## D10. Uploader entry point (FR-005)

**Observed.**

- "Re-upload Existing Packets" (`/events/reupload`) lists `GET /api/events/need_reupload`, which returns `status = 'uploaded'` only, site-scoped for non-admins (`table_service.py:281`).
- Upload buttons elsewhere target `created` events (`/api/events/need_packets`).
- `EventUpload.jsx:225` already shows the server's `error` text on failure.

**Decision.**

- `need_reupload` returns every event inside the D1 window except `created`, which belongs to "Upload New Packets". The query is built from the same predicate as D1, in SQL form, next to `_ready_to_assign_predicate`.
- `GET /api/events/<id>` gains `packet_replaceable: bool`, computed by `packet_replaceable()` for the caller. The upload page uses it to show "This packet has been released to reviewers…" instead of a form that would only be refused.
- The 409 text is shown as the server sends it.

---

## D12. Refusing a scrubbed copy of a superseded packet (FR-017)

**Observed.**

- `events_upload_scrubbed` (`app.py:1937`) accepts any file at any status and sets `scrub_date` and `status`. It has no way to know which raw packet the file was made from.
- The scrub page (`frontend/src/pages/EventScrub.jsx:33`) loads `GET /api/events/<id>` and posts only the file. Every status except 401 and 403 is shown as a generic "Upload failed" toast.
- `get_event_details` (`table_service.py`) does not return `file_number`.

**Decision.** Use `events.file_number` as the packet version. It is a random integer that `upload_raw` regenerates on every upload (`app.py:2100`), so it changes exactly when the packet changes. It is not PHI.

- `GET /api/events/<id>` gains `file_number`.
- The scrub page sends the value it loaded as the form field `packet_version`.
- `upload_scrubbed` compares `packet_version` with the event's current `file_number` **before** saving the file:
  - missing → 400 "packet_version is required";
  - different → 409 "This event's packet was replaced on {upload_date}. Download the new packet and scrub it." Nothing is written to disk.
- The scrub page shows the server's `error` text for 400 and 409.

The page already offers the raw packet download, so the version the page loaded is the one the admin downloaded from that page.

**Known limit.** If the admin downloads the packet, a replacement then happens, and the admin reloads the scrub page before uploading, the reloaded page carries the new version and the guard cannot catch it. That needs a deliberate reload after a replacement, so it is accepted. The reviewer notice (D14) and the "uploaded on" date already shown on the scrub page help here.

**Alternatives rejected.**

- Compare file timestamps. `upload_date` has date precision only.
- Return the version in a download response header. The browser download flow cannot read headers back into the page.

---

## D13. Refusing screening before the new packet is scrubbed (FR-018)

**Observed.** `events_screen` (`app.py:1404`) applies `accept`, `rescrub` or `reject` at any status. After a replacement clears `scrub_date` (D2), an admin with an open screen page could accept the old scrubbed copy. That would stamp `screen_date` on an unscrubbed packet, and the event would skip screening after the re-scrub.

**Decision.** When `cfg.scrubbing` is on and `scrub_date IS NULL`, `events_screen` returns 409 "This event's packet has not been scrubbed yet." and saves nothing. This applies to all three decisions. A packet that has no scrub cannot be screened, rescrubbed or rejected.

The screen page shows the server's `error` text. With scrubbing off (no current profile combines screening on with scrubbing off), the guard does not apply.

---

## D14. Telling the reviewer about a replacement (FR-019, FR-020)

**Observed.**

- With sending off, an assigned reviewer can download the packet as soon as the event is assigned (`_may_download_event_packet`, `app.py:1177`; reviewer queue, `table_service.py:644`).
- Nothing records when a packet was replaced. `upload_date` is overwritten and has date precision, and `assign_date` has date precision too, so the two cannot be compared to tell "replaced after assignment" from "uploaded and assigned on the same day".
- The shared legacy table `logs` (`init/02-schema.sql:120`: `user_id`, `controller`, `action`, `params varchar(1000)`, `time datetime`) is mapped in `models.Logs` (`flask_backend/models/__init__.py:84`). No current code writes to it; it holds legacy CakePHP audit rows.

**Decision.**

- **Record every replacement** in `logs`, in the same request as the upload (FR-020):
  - `controller = 'events'`, `action = 'replace_packet'`;
  - `user_id` = the caller;
  - `time` = now (second precision);
  - `params = 'event_id=<id>;assigned=<0|1>'`, where `assigned` is 1 when `reviewer1_id` was set at the time.

  The params hold ids and flags only, no PHI. The fixed `key=value;` layout lets us look up an exact prefix. A first upload (`created` → `uploaded`) is not a replacement and is not logged.
- **Expose the latest one.** `GET /api/events/<id>` gains `packet_replaced_at`: the ISO datetime of the latest `replace_packet` row for the event with `assigned=1`, or null. It is computed only when `cfg.sending` is off, and is null otherwise, since in sending-on studies the reviewer has no copy before the window closes. This uses the flag, not the study name.
- **Show the notice.** The review page shows "This event's packet was replaced on {packet_replaced_at}. If you downloaded it before then, download it again before reviewing." It appears above Step 1 and stays visible in every review state (open, editable, read-only), because the information stays relevant.

**Why `logs` rather than a new column.**

- It is an existing shared table, so no schema change and no migration are needed.
- It is the legacy audit trail, so recording a replacement there is the table's intended use (Principle III: additive rows only).
- Writing it starts this app's use of the table. That is noted here per Principle VI.

**Cost.** `logs` has no index on `controller`, `action` or `params`, and a legacy mci database holds about 245k rows. The lookup is one query per review-page view, made only when sending is off (scans and dischargesummaries). Those are new databases with near-empty `logs` tables, so the scan is negligible. An index is not added, which keeps the schema unchanged. If an mci-like study ever turns sending off, an index can be added then.

**Alternatives rejected.**

- Email the reviewer (option b). scans and dischargesummaries send no email today.
- Add a new `events` column. That is a shared-table schema change, with the mapping risk described in D5.
- Use a sidecar file in `DOWNLOADS_DIR`. That puts audit data outside the database.

---

## D15. Operator procedure after the window closes (FR-002, FR-006)

Admins can no longer replace a packet once it has been sent or reviewed, so quickstart.md gains a short manual procedure for the systems operator. It covers:

- placing the new raw and scrubbed files under the existing naming;
- updating `file_number` and `original_name`;
- inserting a `logs` row;
- deciding, with the study team, whether existing reviews stand.

The application does not automate this.

---

## D11. Testing approach

Use backend pytest with the existing fixtures (`flask_backend/tests/conftest.py`), run with system `python3 -m pytest flask_backend/tests`. New test modules:

- **`test_packet_replace_window.py`**: role × status × profile matrix; downstream reset; file rename; assignment kept; `derive_pre_send_status`; send guard.
- **`test_review_edit.py`**: single-reviewer edit (scans profile, including after `done`); two-reviewer edit allowed and then locked (mci profile); slot 3 and other-reviewer 403; `modified` set and `status` unchanged; validation 400; GET `mine.editable` / `answers`; 503 on a missing column.
- **Also in `test_packet_replace_window.py`**: stale `packet_version` → 409 with nothing saved (D12); screen before scrub → 409 (D13); a `logs` row written on replacement and not on first upload; `packet_replaced_at` set only with sending off and `assigned=1` (D14).
- **Updated tests**: `test_upload_guard.py` (its 409 expectations change), `test_mci_export_golden.py`, and the scans export tests.

Frontend: lint and build only (user preference: no new packages, no test runner).
