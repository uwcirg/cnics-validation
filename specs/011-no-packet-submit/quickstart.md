# Quickstart: Verifying the No-Packet Submission

**Feature**: `011-no-packet-submit` | **Branch**: `011-no-packet-submit`

---

## Files touched

| File | Change |
|------|--------|
| `flask_backend/app.py` | new `POST /api/events/<id>/mark_no_packet`; lift `upload_raw`'s site check into a shared helper |
| `flask_backend/table_service.py` | `get_event_details`: 5 columns + marker join |
| `frontend/src/pages/EventUpload.jsx` | bind 4 inputs; add submit handler; add feedback states |
| `frontend/src/pages/EventEdit.jsx` | render the no-packet block |
| `flask_backend/tests/test_mark_no_packet.py` | new |
| `openapi.json` | regenerated |

No schema file, no migration, no new dependency.

---

## Backend tests

```bash
cd /home/debadmin/cnics-validation
python -m pytest flask_backend/tests/test_mark_no_packet.py -v
python -m pytest flask_backend/tests/ -q          # full suite, no regressions
```

Uses `conftest.py`'s `admin_client` fixture; the `FakeEvent` / `FakeSession`
pattern from `test_review_endpoint.py` covers lifecycle assertions without a
database. Site-restriction cases need a non-admin uploader identity — define
it locally in the test module rather than changing the shared fixture
(research D10).

### Cases to cover

**Persistence** — one per reason, asserting applicable fields set *and*
non-applicable fields NULL:

- `Outside hospital` + `two_attempts: true` → flag `1`, others NULL
- `Outside hospital` + `two_attempts: false` → flag `0` (valid; observed in production)
- `Ascertainment diagnosis error` → reason only, all four follow-ups NULL
- prior-event, date known, month 11 year 2011 → `'11-2011'`, onsite flag set
- prior-event, month blank year 2008 → `'00-2008'`
- prior-event, month 1 year blank → `'01-0000'`
- prior-event, date **not** known → `prior_event_date` NULL (distinct from `'00-0000'`)
- `Other` + `"Data corruption"` → `other_cause` set, others NULL
- every case: `status='no_packet_available'`, `marker_id`, `markNoPacket_date`
- every case: `upload_date` and `uploader_id` remain NULL

**Stale-field clearing (FR-006)**: an event carrying a previous
`two_attempts_flag` marked with reason `Other` → flag ends NULL.

**Validation (V1-V8)**: each returns 400, names the field, and leaves the row
untouched. Include `other_cause` at 100 chars (accepted) and 101 (rejected).

**Authorization & conflict**: 403 for an uploader at a different site; 200 for
an admin at a different site; 404 for a missing event; 409 for each non-`created`
status, with the current status in the message.

---

## Frontend verification

```bash
cd frontend && npm run lint
```

There is no JS test runner in this project (`package.json` declares only
`dev`, `build`, `lint`, `preview`; zero `*.test.*` files). Introducing one is
out of scope for a bug fix, so the UI is checked manually.

### Manual script — reproduces the original report

The user's two reported attempts, which must now persist:

1. Open `/events/upload?event_id=<id>` for an event with status `created`.
2. Under **If no packet is available**, select **Outside hospital**; the
   two-attempts question appears. Choose **Yes, 2 attempts were made**. Submit.
   → Confirmation appears naming the event.
3. Open that event's detail page. → Reason, the attempts answer, the marking
   date, and the marking user are all shown.
4. Open **Upload New Packets** with no event selected. → The event is gone
   from "Events That Need Packets".
5. Open **View All Events → no packet available**. → The event is listed.
6. Repeat on a second event with **Other** + `Data corruption`. → Stored and
   displayed; `two_attempts_flag` is NULL on that row.

### Also verify

- **Silence is gone**: select **Outside hospital**, answer nothing, Submit →
  a message names the missing answer; nothing is written.
- **Reason switch clears answers**: pick Outside hospital, answer the
  attempts question, switch to Other, type a cause, Submit → the row's
  `two_attempts_flag` is NULL.
- **Prior-event blanks**: date known with month blank and year `2008` →
  stored `'00-2008'`; answering the date is *not* known → NULL.
- **Over-long cause**: paste 101 characters → rejected with the limit stated,
  not silently truncated.
- **No double submit**: Submit stays disabled while in flight, and the form is
  disabled after success.
- **Conflict**: attempt a submission against an already-uploaded event → 409
  naming its current status.

---

## Known gap, deliberately not fixed

`frontend/src/studies/vte/EventUpload.jsx` carries a byte-identical inert
no-packet form (its `<form>` at line 265 also lacks `onSubmit`, same four
unbound inputs). It is **not** repaired here — fixing it would deepen the VTE
fork that Constitution Principle I exists to eliminate, and the fork is slated
for retirement. Recorded here rather than left silent, per Principle VI's
unused-subsystem hygiene rule. The new backend endpoint is study-agnostic, so
whoever retires the fork gets the working path for free.

---

## Before opening the PR

```bash
python -m flask_backend.generate_openapi     # from repo root; commit the delta
python -m pytest flask_backend/tests/ -q
cd frontend && npm run lint && npm run build
```

State in the PR description that this affects **shared code, all studies**
(no study-specific behavior; no workflow flag consulted), per the
constitution's change-review gate.
