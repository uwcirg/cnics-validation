# Research: `dischargesummaries` Study Type

**Feature**: 013-dischargesummaries-study | **Date**: 2026-10-23
**Baseline**: `python3 -m pytest flask_backend/tests` → 309 passed (main @ 0cdd7ac)

Each decision gives the decision, the rationale and the alternatives considered.
Where a decision changes shared code, the current behavior is recorded first
(Constitution Principle VI).

---

## D1. The workflow profile is a new entry in the profile table, not new branching

**Decision**: Add a `dischargesummaries` profile to `_PROFILES` in
`flask_backend/study_config.py`: `scrubbing=True, screening=False,
sending=False, reviewer_count=1`. Everything downstream already reads these
four flags: the queues in `table_service.py` (`_ready_to_assign_predicate`,
`get_to_be_scrubbed_with_total`, `get_to_be_screened_with_total`,
`get_events_for_review`), assignment in `app.py`, `_may_receive_raw_packet`,
and the `workflow` block of `GET /api/config` that the frontend uses to show
or hide steps.

**Rationale**: Principle V explicitly allows any combination of the four
flags. Code review confirmed that no queue or action has been verified only
for the two existing combinations: every gate checks one flag at a time.
With scrub on and screen off, the ready-to-assign predicate becomes
`upload_date IS NOT NULL AND scrub_date IS NOT NULL AND assign_date IS NULL`,
which is the FR-007 gate.

**Alternatives considered**: Only documenting `.env` overrides
(`STUDY_TYPE=dischargesummaries` plus `ENABLE_SCREENING=false` and so on).
Rejected: FR-002 requires the study type alone to supply the defaults, as
`scans` does, and a missed override would silently give a two-reviewer,
screened, sent workflow.

**Test gap to close**: no test currently covers scrub-on with screen-off.
Add a parametrized workflow test modeled on `test_scans_workflow.py`.

---

## D2. Server-side guard: only events that have passed every enabled pre-assignment stage can be given a first reviewer

**Observed behavior** (verified by reading `table_service.assign_events`,
`table_service.py:671`): assignment does not check the event's state. Any
event id posted to `POST /api/events/assign_many` with `slot: "first"` gets a
reviewer and status `assigned`, even if it was never uploaded or scrubbed.
Only the UI prevents this, because the assign page offers only rows from the
ready-to-assign queue.

**Decision**: In `assign_events`, for `slot == "first"`, refuse the whole
batch (`ValidationError` → 400) if any event fails the enabled-stage part of
`_ready_to_assign_predicate()`, which is the same logic minus
`assign_date IS NULL`. The error names the offending event ids.

**Rationale**: This is the spec's edge case ("assign an event that has not
been scrubbed: refused"). The rule is written once and shared, so `mci` and
`scans` get the same guard. Their valid flows are unchanged, because the UI
already offers only ready events. The `assign_date IS NULL` term is excluded
so that any future reassignment path is not blocked by this change. The
guard reuses the predicate builder instead of repeating it (Principle IV).
Because reviewers are only ever served the scrubbed file (D4), the risk
without the guard is a reviewer with nothing to review rather than a PHI
leak. Even so, it is cheap and makes the lifecycle hold.

**Alternatives considered**: (a) Leave it UI-only. Rejected because the spec
requires refusal. (b) Skip invalid ids and assign the rest. Rejected because
partial success on a bulk action hides mistakes; the existing bulk endpoints
return one clear error.

---

## D3. Server-side guard: a site uploader can upload only while the event is `created` or `uploaded`

**Observed behavior** (verified by reading `events_upload_raw`,
`app.py:1981`): there is no status check. A same-site uploader can upload at
any status, and the handler always sets `status = 'uploaded'`. That silently
moves a scrubbed, assigned or even `done` event back to `uploaded`, while its
`scrub_date` and `assign_date` stay set. The intended replace path is
`EventReupload.jsx`, whose queue is `status = 'uploaded'` (`table_service.py:284`).

**Decision**: Non-admin callers may upload only when the status is `created`
or `uploaded`. Otherwise the endpoint returns 409 with "This event has moved
past upload; ask an administrator." Admins are not restricted, so the
coordinating center can still recover.

**Rationale**: The spec's edge case says "after scrubbing, only an admin can
act on it". The study's whole point is a scrubbed copy that reviewers rely
on, and a later site upload must not quietly invalidate it. The guard is
shared: the same hole exists in `mci`. This is a correctness fix there, and
no legitimate `mci` flow uploads past `uploaded`. `mark_no_packet` already
requires `created`.

**Alternatives considered**: Restrict admins too. Rejected because it removes
the only recovery path for a wrong upload discovered after scrubbing.

---

## D4. Scrub and storage are reused; the download endpoint gets a per-event access check

**Scrub (unchanged)**: `upload_scrubbed` is admin-only and stores
`<event_id>.<ext>`. Allowed types stay `zip, pdf, doc, docx`.

**Observed download behavior** (verified by reading `events_download`,
`app.py:1216`): the only decorator is `@requires_auth`.
- The **scrubbed** packet is served to any authenticated user. That includes
  reviewers not assigned to the event and uploaders at other sites.
- With scrubbing on, the **raw** packet is served to any admin or any
  uploader (`_may_receive_raw_packet`), with no site check.

Both break FR-008 and FR-009 (and the spec 012 rule that reviewers act only
on their own assignments). The gap exists in `mci` today.

**Decision**: Add one shared helper, `_may_download_event_packet(session,
event, auth_user, raw: bool)`, and call it from `events_download`:

| Caller | Scrubbed packet | Raw packet (scrubbing on) |
|--------|-----------------|---------------------------|
| admin | yes | yes |
| uploader at the event's site (`_uploader_may_act_on_event`) | yes | yes |
| reviewer assigned to the event (`reviewer1_id` / `reviewer2_id` / `reviewer3_id`) | yes | no |
| anyone else | 403 | 403 |

With scrubbing off (`scans`), the raw packet is the packet, so the scrubbed
column applies to it. That keeps `scans` reviewers working as they do now.
A refused caller gets 403, not 404, so the UI can say "not your event".

**Rationale**: The spec requires it, and the rule must be shared: a
`dischargesummaries`-only check would be a code fork (Principle IV). For
`mci` and `scans`, legitimate users are unaffected. The UI only links
reviewers to their assigned events and uploaders to their site's events.
Only cross-event and cross-site downloads change, from allowed to refused.
This must be stated in the PR description.

**Alternatives considered**: Fix it only for this study. Rejected for the
reason above. Defer it to its own feature. Rejected: FR-008 is a
privacy requirement of this study, and SC-003 ("0 reviewers can obtain the
original") is false without it for unassigned reviewers who are also
uploaders.

---

## D5. The review form ships in two phases because the questions are not known yet

**Context**: FR-011 is open: the study owner is still deciding what the
reviewer records. Every study module must export `REVIEW_FORM`. Without it,
the resolver falls back to `mci`, whose review form and `reviews` table
would be applied to this database. That breaks Principle II/IV isolation
(FR-021) and would leave MI-shaped tables needing a migration later, as
happened with `scans` in spec 012.

**Decision**:

- **Phase A (now)**: a non-dormant study module and schema that own the
  database's `reviews` table from day one.
  - `init/studies/dischargesummaries.sql` creates `reviews` with only the
    shared columns: `id`, `event_id`, `reviewer_id`, `created`.
  - `flask_backend/models/studies/dischargesummaries.py` exports
    `REVIEW_FORM = "dischargesummaries"` and `REVIEW_TABLES = ("reviews",)`.
    Its `parse_review` always raises `ReviewValidationError("The
    discharge-summary review form is not available yet.")`. `get_reviews`
    returns `[]`, `export_csv` writes the shared heading row only, and
    `check_schema` returns `None`.
  - The frontend registers a `dischargesummaries` form that renders a
    "review form not yet available" notice with no submit button.

  Upload, scrub, assignment and the reviewer queue can then be exercised
  end to end on dev, and the database never contains another study's tables.
- **Phase B (when the questions arrive)**: add the answer columns or child
  tables, real `parse_review`/`add_review`/`get_reviews`/`export_csv`/
  `check_schema`, the React form and summary, and
  `app/webroot/files/instructions/dischargesummaries.html`. Existing Phase A
  databases get a one-off `init/migrations/013-dischargesummaries-reviews.sh`,
  modeled on `012-scans-reviews.sh`, that applies the new DDL. Because Phase A
  refuses every submission, `reviews` is guaranteed empty, so the migration
  can create or alter tables without moving data.

**Rationale**: This unblocks deployment work and end-to-end testing of
everything except the review itself. It keeps FR-021 true from the first
deploy and makes the Phase B migration trivial.

**Alternatives considered**:
(a) A dormant module that falls back to `mci`. Rejected: it puts MI tables in
the database and gives reviewers a working MI form for the wrong study.
(b) Wait for the questions before any work. Rejected: the deployment and
workflow work is independent and is most of the risk.
(c) Guess at the fields. Rejected because there is no basis for a guess.

**Prod gate**: do not open a prod deployment to reviewers until Phase B is
merged. The quickstart says so.

---

## D6. Study wording: `STUDY_TITLE` in `.env`, plus a new optional `STUDY_LABEL`

**Observed behavior**: the banner and tab title use `STUDY_TITLE` when it is
set, and otherwise derive "<UPPERCASE> Project". Page headings ("Packet for
MCI 4821") and assignment emails use `get_study_label()`, which is
`STUDY_TYPE` upper-cased. For this study that gives "DISCHARGESUMMARIES 4821"
and "DISCHARGESUMMARIES Review Assignment".

**Decision**: Add an optional `STUDY_LABEL` setting, read only by
`get_study_label()`. When it is set and not blank, it is used verbatim.
Otherwise the current behavior is unchanged: upper-cased `STUDY_TYPE`, or
empty when that is unset. The worked example sets
`STUDY_TITLE=Discharge Summaries Validation` and
`STUDY_LABEL=Discharge Summary`, giving headings like "Packet for Discharge
Summary 4821" and the email subject "Discharge Summary Review Assignment".

**Rationale**: Principle IV ranks environment settings first. No study name is
written into shared code, and `mci`/`scans` are unaffected because the label
is unset there. `formatStudyTitle` needs no change, since the banner uses
`STUDY_TITLE`.

**Alternatives considered**: (a) A per-study display-name map in
`study_config.py`. Workable, but it is study-specific content inside a
shared module, and an operator cannot adjust it. (b) Accept
"DISCHARGESUMMARIES". Rejected as unreadable.

**For the user to confirm**: the exact label wording ("Discharge Summary" or
"DS") is cosmetic and can be changed in `.env` at any time.

---

## D7. Home page and document links: a new `STUDY_GUIDANCE` entry

**Decision**: Add a `dischargesummaries` entry to
`frontend/src/components/reviewGuidance.js`:

- `packets.items`: "The hospital discharge summary for the event" and
  "Please redact personal identifiers (name, birth date, medical record
  number, dates of service as instructed) before uploading". The wording is a
  placeholder for the study owner to confirm.
- `instructions.links`: empty in Phase A. Phase B adds
  `/files/instructions/dischargesummaries.html`.
- `scrubbing.links`: empty unless the study owner provides a scrubbing
  protocol document. Empty links render no document area (spec 010 FR-016).

**Rationale**: This is the established keyed-content map (specs 007/010), and
the review and scrub pages already read their links from it.

---

## D8. Two stacks on one VM: make the internal network settable from `.env`

**Observed**: `docker-compose.yaml` hard-codes `networks.internal` to subnet
`172.31.222.0/24` and gateway `172.31.222.1`. A second compose project on
the same host with the same subnet fails at `docker compose up` ("Pool
overlaps with other one on this address space"). The compose comment's fix,
"change both here and CNICS_DATA_DB_HOST", would mean editing
`docker-compose.yaml` per deployment, which Principle IV forbids.

Everything else is already per-deployment. Container, volume and network
names are namespaced by `COMPOSE_PROJECT_NAME`, and host ports come from
`EXTERNAL_PORT` / `BACKEND_EXTERNAL_PORT`.

**Decision**: Interpolate the subnet and gateway in `docker-compose.yaml` as
`${INTERNAL_SUBNET:-172.31.222.0/24}` and `${INTERNAL_GATEWAY:-172.31.222.1}`,
and document both in `default.env`. The defaults equal today's values, so
existing deployments, including `scans`, are unaffected (FR-018, FR-022). The
worked example uses `172.31.223.0/24` / `172.31.223.1`.

**Alternatives considered**: Let Docker auto-assign the second stack's subnet.
That works in socket mode (dev) but leaves no stable, documented address for
a firewall rule. It also makes the shared-tunnel setup (D9) depend on an
address that can change on recreate. Rejected.

---

## D9. Prod: reuse the `scans` SSH tunnel (user decision)

**Observed** (`docs/cnics_data-bridge.md`): the prod tunnel is an autossh
`LocalForward 172.31.222.1:13306 localhost:3306`, managed by puppet. The
host firewall has
`INPUT -s 172.31.222.0/24 -d 172.31.222.1 -p tcp --dport 13306 -j ACCEPT`,
and without that rule containers time out (errno 110).

**Decision**: The `dischargesummaries` `.env` on prod sets
`CNICS_DATA_BRIDGE_MODE=tcp`, `CNICS_DATA_DB_HOST=172.31.222.1` (the scans
gateway, where the tunnel listens) and `CNICS_DATA_DB_PORT=13306`. It also
reuses the same read-only bridge user, unless the data owner wants a separate
one. No code change is needed, because `init/06-create-patients-view.sh`
already reads these settings.

**Operator steps the docs must state**:

1. **Firewall**: add one more INPUT rule for the new subnet,
   `-s 172.31.223.0/24 -d 172.31.222.1 -p tcp --dport 13306 -j ACCEPT`, and
   make it durable in puppet next to the existing rule. Traffic from a
   container to a host address goes through INPUT, not FORWARD, so Docker's
   bridge-to-bridge isolation does not block it. The existing rule's source
   range does.
2. **Dependency**: the listen address `172.31.222.1` exists only while the
   `scans` compose network exists. `docker compose stop` keeps it, but
   `docker compose down` on `scans` removes it. The tunnel then cannot bind
   until `scans` is brought back up, and `dischargesummaries` loses patient
   lookups in the meantime. The docs say this plainly. One alternative, an
   operator decision outside this feature, is to rebind the tunnel to the
   default `docker0` gateway (`172.17.0.1`), which exists as long as Docker
   runs and is shared by both stacks.

**Dev** (socket mode) needs nothing beyond the defaults. The same host socket
directory is bind-mounted into each stack.

---

## D10. Database name, credentials and domain are per-deployment configuration only

**Decision**: There is no code change. The worked `.env` uses its own
`DB_NAME` (e.g. `cnics_dischargesummaries`), `DB_USER`, passwords,
`COMPOSE_PROJECT_NAME=cnics-dischargesummaries`, distinct ports, `SERVER_NAME`
and `FRONTEND_ORIGIN`, and its own Apache vhost with LDAP basic-auth. Each
stack has its own MariaDB container and volume, so isolation (Principle II,
FR-021) comes from the deployment boundary.

---

## D11. Known behavior carried over from `scans`, not changed here

With sending off, assignment sends no email to the reviewer. Emails are
sent only by the send step (`table_service.send_events`), plus third-reviewer
emails. This is how `scans` behaves today. It is called out because
discharge-summary reviewers will likewise learn of new work only by checking
their queue. Changing it would affect `scans` and belongs in its own feature.

---

## D12. Testing approach

- Backend (pytest, system `python3`):
  - Extend `test_study_config.py` with the profile and the `STUDY_LABEL` override.
  - Add `test_dischargesummaries_workflow.py`: upload → scrub → assign → review queue. Cover the D2 refusal for unscrubbed events and the D3 refusal for site re-upload after scrub.
  - Extend `test_event_download.py` with the D4 access table (admin, same-site and other-site uploader, assigned and unassigned reviewer; scrubbing on and off).
  - Extend `test_study_modules.py` so `dischargesummaries` resolves to its own module with no fallback warning.
  - Add a Phase A test that the review endpoint refuses a submission.
  - Run the existing suites unchanged (FR-022, SC-006).
- Frontend: `npm run lint` and `npm run build` only. No new packages (user rule).
- Compose: `docker compose config` with and without `INTERNAL_SUBNET` set shows the interpolated and default values. Operators run this; the implementer cannot run a stack locally.
