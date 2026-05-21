# Quickstart: Stand up and verify a `scans` deployment

**Feature**: 003-scans-study | **Date**: 2026-05-20

This walks through deploying a `scans` study and verifying the four user
stories. It assumes the feature has been implemented and the Docker Compose
stack is available.

## 1. Configure a `scans` deployment

From a fresh deployment directory:

```bash
cp default.env .env
```

Edit `.env` to select the `scans` study. Selecting `STUDY_TYPE=scans` is
sufficient — the bypass profile is supplied as the default (FR-006). The four
controls are shown explicitly here for clarity (this mirrors the worked
example added to `docs/template-setup-guide.md`):

```bash
STUDY_TYPE=scans
ENABLE_SCRUBBING=false
ENABLE_SCREENING=false
ENABLE_SENDING=false
REVIEWER_COUNT=1
```

Set the deployment-specific values too (`DB_*`, `FRONTEND_ORIGIN`,
`COMPOSE_PROJECT_NAME` if running side-by-side, SMTP, etc.).

## 2. Bring up the stack

```bash
docker compose up -d
```

The backend reads the four controls through the shared configuration layer at
startup.

## 3. Verify User Story 1 — full scan-validation, upload → done

1. As an **uploader**, upload a packet for a `created` event.
   - Expect: event becomes `uploaded`, eligible for assignment with **no**
     `scrubbed` or `screened` state (Acceptance 1.1).
2. As an **admin**, assign the `uploaded` event to a reviewer.
   - Expect: event becomes `assigned`; it is in the reviewer's queue with **no**
     separate send step (Acceptance 1.2); only a single first-reviewer slot is
     offered (Acceptance 1.4).
3. As that **reviewer**, open the event and submit the review.
   - Expect: event advances straight to `done` — no second reviewer, no admin
     completion step (Acceptance 1.3).
4. Confirm the event's history shows exactly
   `created → uploaded → assigned → reviewer1_done → done` (SC-002), with no
   scrubbing/screening/send entries.

**Unsupported reviewer count**: set `REVIEWER_COUNT=3` in `.env` and
`docker compose up`. Expect the backend to fail to start and serve no request,
with an error naming `REVIEWER_COUNT` (Acceptance 1.5, SC-004). Restore to `1`.

## 4. Verify User Story 2 — interface matches the bypassed workflow

- Log in as **admin**: the dashboard shows no scrubbing, screening, or sending
  queues/actions; assignment offers no second-/third-reviewer controls
  (Acceptance 2.1, 2.2).
- Log in as **reviewer**: assigned events are directly pickable for review
  (Acceptance 2.3).
- (Cross-check) On a full-workflow study (e.g. `STUDY_TYPE=mci`, controls at
  defaults) the scrubbing/screening/sending and multi-reviewer controls remain
  present — the difference is driven by the controls, not the study name
  (Acceptance 2.4, SC-008).

## 5. Verify User Story 3 — deployable from documentation

Using only `default.env`, `README.md`, and `docs/template-setup-guide.md`
(no source code), confirm an operator can:
- find the four controls with their conservative defaults and descriptions in
  `default.env` (Acceptance 3.1);
- find the `scans` worked example in the setup guide (Acceptance 3.2);
- produce a correct `scans` `.env` and bring the stack up (Acceptance 3.3,
  SC-005).

## 6. Verify User Story 4 — existing studies & tooling unaffected

```bash
# Backend tests must pass unmodified (SC-006)
pytest flask_backend/tests/

# Regenerate and inspect the API contract
python -m flask_backend.generate_openapi
```

- Inspect `flask_backend/models.py` and confirm `scrubbed`, `screened`, `sent`,
  `reviewer2_done`, `third_review_needed`, `third_review_assigned` are still in
  the `events.status` enum (Acceptance 4.1, SC-007).
- Run a full-workflow study (`STUDY_TYPE=mci`, default controls) and confirm
  behavior is unchanged (Acceptance 4.2, FR-017).

## 7. Done criteria

- [ ] `scans` event reaches `done` in four transitions, one reviewer.
- [ ] Bad `REVIEWER_COUNT` refuses startup.
- [ ] No bypassed-stage UI visible in a `scans` deployment.
- [ ] All pre-existing `pytest` tests pass without modification.
- [ ] `openapi.json` regenerated and committed.
- [ ] `default.env`, `README.md`, `docs/template-setup-guide.md` updated.
