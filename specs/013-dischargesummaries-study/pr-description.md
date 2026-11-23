# Add the `dischargesummaries` study type (spec 013, Phase A)

## Summary

Adds a third usable study, `dischargesummaries`. A site uploader uploads a
redacted hospital discharge summary, an admin uploads a scrubbed copy, an
admin assigns one reviewer, and that review completes the event:
`created → uploaded → scrubbed → assigned → reviewer1_done → done`.

This is **Phase A**: everything except the review form. The review questions
are not known yet (FR-011). The review page shows "The review form for this
study is not available yet", and the backend refuses review submissions with
the same reason. Phase B adds the form, its storage and export columns, the
instructions page, and a migration for Phase A databases.

- `STUDY_TYPE=dischargesummaries` profile: scrub on, screen off, send off, one reviewer (`flask_backend/study_config.py`).
- Own study module and schema, so the database never gets `mci` tables: `flask_backend/models/studies/dischargesummaries.py`, `init/studies/dischargesummaries.sql` (`reviews` with the shared columns only).
- Frontend: placeholder review form and summary (`frontend/src/studies/dischargesummaries/`), registered in `reviewForms.js`; home-page packet guidance in `reviewGuidance.js`.
- Optional `STUDY_LABEL` for readable headings and emails ("Packet for Discharge Summary 4821").
- `docker-compose.yaml` network range is now set from `.env`, so a second stack can run beside `scans` on the same VM.
- Three server-side guards in shared code (below).
- Docs: shared-tunnel section in `docs/cnics_data-bridge.md`, worked example in `docs/template-setup-guide.md`, `README.md`, `default.env`. `openapi.json` regenerated.

## Behavior changes for `mci` and `scans`

These guards are shared, not study checks. Before this PR, each rule was kept
only by the UI hiding the option. Valid flows are unchanged.

1. **Assignment readiness (research D2).** *Before:* `POST /api/events/assign_many` with `slot: "first"` assigned any event id, even one never uploaded or scrubbed. *Now:* the whole batch is refused with 400 `"These events are not ready for assignment: 12, 15"`, and nothing changes, unless every event has passed every enabled stage before assignment (uploaded; scrubbed if scrubbing is on; screened if screening is on). This uses the same stage list as the To Be Assigned queue. `assign_date` is not checked.
2. **Upload after the upload stage (D3).** *Before:* a same-site uploader could upload at any status. That reset a scrubbed, assigned or done event to `uploaded` while its scrub and assign dates stayed set. *Now:* a non-admin gets 409 `"This event has moved past upload; ask an administrator."` unless the status is `created` or `uploaded`, and nothing is saved. Admins are not restricted. The wrong-site 403 and bad-file-type 400 are checked first, as before.
3. **Download access (D4).** *Before:* any signed-in user could download the scrubbed packet. With scrubbing on, any admin or uploader could download the raw one. There was no site or assignment check. *Now:*

   | Caller | Scrubbed copy | Raw packet (scrubbing on) |
   |---|---|---|
   | admin | yes | yes |
   | uploader at the event's site | yes | yes |
   | reviewer assigned to the event (any slot) | yes | no |
   | anyone else | 403 | 403 |

   With scrubbing off (`scans`), the raw packet is the packet, and assigned reviewers still get it. A file the caller may not have now returns 403 `{"error": "You do not have access to this event's packet"}`. Before, a reviewer asking for an unscrubbed packet got 404. No file at all is still 404. If the database cannot be read during a download, an admin still gets the file, and anyone else gets 500 (fail closed).

   Frontend: the download links on the review and edit pages, and the `/events/download` page, now check with a HEAD request first. A 403 then shows "You do not have access to this event's document." instead of saving the error as a file (`components/PacketDownloadLink.jsx`, `components/packetAccess.js`).

## Other small changes

- **`CLINICAL_SITES` now reaches the backend.** It was documented in `default.env` but missing from the backend's `environment:` list in `docker-compose.yaml`, so it was silently ignored and every deployment got the built-in site list. Unset still means the built-in list. A deployment that already sets it in `.env` will now see its own list in the edit-page site picker.
- **Edit page:** the "Reviewer 2:" row is shown only when a second reviewer is set. Before, single-reviewer studies showed it empty.

## New settings

| Variable | Default | Purpose |
|---|---|---|
| `STUDY_TYPE=dischargesummaries` | — | new recognized value |
| `STUDY_LABEL` | unset → `STUDY_TYPE` in capitals | study name in headings and emails |
| `INTERNAL_SUBNET` / `INTERNAL_GATEWAY` | `172.31.222.0/24` / `172.31.222.1` | internal Docker network range; must differ per deployment on one host |

The defaults equal the old fixed values, so existing deployments need no `.env` change.

## UI review findings (T021, T022)

Checked by reading the code for `{scrubbing: true, screening: false, sending: false, reviewer_count: 1}`:

- **`EventViewAll.jsx`: no change needed.** It shows To Be Uploaded, To Be Scrubbed, To Be Assigned, Not Yet Reviewed, All Done and No Packet Available. To Be Screened, To Be Sent and both third-reviewer sections are hidden, each by its single flag. To Be Assigned and Not Yet Reviewed call `by_status/screened` and `by_status/sent`, which the backend maps to the flag-aware queues, so they list scrubbed and assigned events. The Rejected section still shows. It can only fill from screening, so it stays empty here, as it already does for `scans`. It was left alone so the `scans` UI is unchanged.
- **`EventAssignMany.jsx`: no change needed.** One reviewer field, from `reviewer_count`.
- **`MenuBar.jsx`: no change needed.** It has no stage-specific links. The legacy `isVtePage` branches were not touched.
- **`Home.jsx`: no change needed.** Its guidance comes from the new `reviewGuidance.js` entry.
- **`EventEdit.jsx`: changed.** The empty "Reviewer 2:" row is now hidden. Other history rows already show only when their date is set.
- **T022: no change needed.** After upload, the confirmation says "queued for scrubbing" when `workflow.scrubbing` is true. The scrub and review headings use `headingSuffix(studyLabel, id)`.

## Tests

- Backend: `python3 -m pytest flask_backend/tests` → **385 passed** (baseline 309; 76 new).
  - New: `test_dischargesummaries_workflow.py`, `test_upload_guard.py`.
  - Extended: `test_assign_many.py`, `test_event_download.py`, `test_review_endpoint.py`, `test_study_config.py`, `test_study_modules.py`, `test_config_endpoint.py`, `test_emailer_study_naming.py`.
  - The guards are tested under the `mci`, `scans` and `dischargesummaries` profiles.
  - New `client_as` fixture in `conftest.py` for non-admin identities.
- Pre-existing tests updated:
  - Three assignment tests built events with no stage dates (one had `status="screened"` and no `screen_date`). Their fixtures now carry the dates. What they assert is unchanged: `test_assign_many.py::test_assign_events_two_reviewer_atomic`, `test_scans_workflow.py::test_first_assignment_sets_status_assigned`, `test_full_workflow_defaults.py::test_full_workflow_assignment_advances_status`.
  - `test_event_download.py`: the fake event now assigns the test reviewer, since an unassigned reviewer is now refused. `test_reviewer_is_not_handed_the_unscrubbed_packet` expects 403 instead of 404. That test asserted the old refusal code.
  - `STUDY_LABEL` was added to the env-clearing lists in three test files.
- `test_mci_export_golden.py` and `test_scans_review.py` pass unchanged. `init/04-study-schema.sh` needs no change: it picks up `dischargesummaries.sql` because the file exists and is not marked dormant.
- Frontend:
  - `npm run build` passes.
  - `npm run lint` reports the same 31 problems as before this PR (25 errors, 6 warnings), all in files that were already failing. No new problems.
- **Not run:** `docker compose config`, because Docker is not available on the development machine. The interpolation was checked by parsing the YAML. Operator, please run:
  ```bash
  docker compose config | grep -A3 ipam      # no INTERNAL_* set: 172.31.222.0/24 / 172.31.222.1
  INTERNAL_SUBNET=172.31.223.0/24 INTERNAL_GATEWAY=172.31.223.1 docker compose config | grep -A3 ipam
  ```

## Operator checklist

The full walk-through is in `specs/013-dischargesummaries-study/quickstart.md`.

- [ ] Separate checkout and `.env` with its own `COMPOSE_PROJECT_NAME`, ports, `SERVER_NAME`, `FRONTEND_ORIGIN`, DB name and credentials, and `INTERNAL_SUBNET=172.31.223.0/24` / `INTERNAL_GATEWAY=172.31.223.1`.
- [ ] Prod only: `CNICS_DATA_DB_HOST=172.31.222.1` (the scans tunnel). Add the firewall rule `iptables -I INPUT 1 -s 172.31.223.0/24 -d 172.31.222.1 -p tcp --dport 13306 -j ACCEPT` and make it durable in puppet.
- [ ] Prod only: use `docker compose stop`, never `down`, on `scans` while this stack is live. `down` removes the tunnel's listen address.
- [ ] After first start, confirm `04-study-schema` applied `dischargesummaries.sql`, not `mci.sql`.
- [ ] **Data-isolation check before go-live (required), quickstart §6:** the DB user sees only this deployment's database, and `SERVER_NAME` / `FRONTEND_ORIGIN` are unique on the VM. Record the results.
- [ ] **Prod gate:** upload and scrub only until Phase B is merged. Do not open prod to reviewers.

## Known behavior, unchanged (D11)

With sending off, reviewers get **no assignment email**. Emails are sent only
by the send step, and the third-reviewer path, so reviewers find new work by
checking their queue. `scans` already works this way. Changing it would
affect `scans` and belongs in its own feature.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
