"""Integration tests for POST /api/events/<id>/mark_no_packet.

Covers spec 011 FR-001 to FR-016 (persistence, field applicability, and
validation) and FR-021 to FR-023 (authorization and the created-only
conflict guard), against `contracts/mark-no-packet-api.md`.

The endpoint is exercised through `conftest.py`'s auto-authenticated
`admin_client` (acting user id = 1, site `TEST`, full admin) and, for the
site-restriction cases, through the local `uploader_client` fixture below —
`conftest.py` offers only a full-admin identity, and FR-021's rule is
invisible to an admin, who bypasses it by design (research D10).

The events and patients tables are mocked with the `FakeEvent` / `FakeSession`
pattern from `test_review_endpoint.py`, so field-by-field persistence and the
"non-applicable columns end NULL" rule (FR-006) are asserted without a
database.
"""

import datetime
import importlib
from unittest.mock import patch

import pytest
from flask import g


MARK_URL = "/api/events/{event_id}/mark_no_packet"

PRIOR_EVENT_REASON = "Ascertainment diagnosis referred to a prior event"


class FakeEvent:
    """Minimal stand-in for an `events` row with the no-packet columns.

    `two_attempts_flag` and friends default to None but are constructor
    arguments so a row carrying stale answers from an earlier reason can be
    set up for the FR-006 clearing test.
    """

    def __init__(
        self,
        status="created",
        patient_id=7,
        two_attempts_flag=None,
        prior_event_date=None,
        prior_event_onsite_flag=None,
        other_cause=None,
    ):
        self.id = 4821
        self.patient_id = patient_id
        self.status = status
        self.no_packet_reason = None
        self.two_attempts_flag = two_attempts_flag
        self.prior_event_date = prior_event_date
        self.prior_event_onsite_flag = prior_event_onsite_flag
        self.other_cause = other_cause
        self.marker_id = None
        self.markNoPacket_date = None
        # No packet exists, so these must never be written (data-model.md).
        self.upload_date = None
        self.uploader_id = None
        self.file_number = None
        self.original_name = None


class FakePatient:
    """Stand-in for the `patients_view` row the site check reads."""

    def __init__(self, site="TEST"):
        self.id = 7
        self.site = site


class FakeSession:
    """Session yielding the configured event, or patient, by model class.

    The handler asks for both — `models.Events` for the row it writes and
    `models.PatientsView` for the site check — so `get` has to answer
    according to what `query` was last handed.
    """

    def __init__(self, event, patient=None):
        self._event = event
        self._patient = patient if patient is not None else FakePatient()
        self._target = None
        self.committed = False
        self.rolled_back = False

    def query(self, model, *args, **kwargs):
        self._target = model
        return self

    def get(self, _row_id):
        models = importlib.import_module("flask_backend.models")
        if self._target is models.PatientsView:
            return self._patient
        return self._event

    def add(self, _obj):
        pass

    def commit(self):
        self.committed = True

    def rollback(self):
        self.rolled_back = True

    def close(self):
        pass


_FAKE_UPLOADER = {
    "id": 42,
    "username": "test-uploader",
    "admin": False,
    "uploader": True,
    "reviewer": False,
    "third_reviewer": False,
    "site": "TEST",
}


@pytest.fixture
def uploader_client():
    """Test client authenticated as a non-admin uploader at site `TEST`.

    Same mechanism as `conftest.py`'s `admin_client`, with the admin flag
    off: the same-site rule in FR-021 only has an observable effect for a
    non-admin, so the shared fixture cannot exercise it.
    """
    app_mod = importlib.import_module("flask_backend.app")
    app_mod.keycloak_openid = None

    def _load():
        g.auth_user = dict(_FAKE_UPLOADER)
        return g.auth_user

    with patch.object(app_mod, "_load_user_from_remote_header", _load):
        client = app_mod.app.test_client()
        client.environ_base["HTTP_X_REMOTE_USER"] = "test-uploader"
        yield client


def assert_unchanged(event):
    """A rejected submission must leave the row exactly as it was (FR-016)."""
    assert event.status == "created"
    assert event.no_packet_reason is None
    assert event.marker_id is None
    assert event.markNoPacket_date is None


def assert_marked(event, response, *, marker_id=1):
    """Assertions common to every accepted submission."""
    assert response.status_code == 200, response.get_json()
    assert event.status == "no_packet_available"
    assert event.marker_id == marker_id
    assert event.markNoPacket_date == datetime.date.today()
    # No packet exists, so the upload columns stay empty (data-model.md).
    assert event.upload_date is None
    assert event.uploader_id is None


# --- Persistence, one case per reason (FR-001 to FR-008) --------------------


@patch("flask_backend.models.get_session")
def test_outside_hospital_two_attempts_yes(mock_get_session, admin_client):
    """`Outside hospital` + "Yes, 2 attempts" — the user's original report."""
    event = FakeEvent()
    mock_get_session.return_value = FakeSession(event)

    res = admin_client.post(
        MARK_URL.format(event_id=4821),
        json={"reason": "Outside hospital", "two_attempts": True},
    )

    assert_marked(event, res)
    assert res.get_json()["data"] == {
        "event_id": 4821,
        "status": "no_packet_available",
        "marked_date": datetime.date.today().isoformat(),
    }
    assert event.no_packet_reason == "Outside hospital"
    assert event.two_attempts_flag == 1
    assert event.prior_event_date is None
    assert event.prior_event_onsite_flag is None
    assert event.other_cause is None


@patch("flask_backend.models.get_session")
def test_outside_hospital_two_attempts_no_is_recorded(mock_get_session, admin_client):
    """"No" is a recorded answer, not a refusal — legacy rows carry 0 (D5)."""
    event = FakeEvent()
    mock_get_session.return_value = FakeSession(event)

    res = admin_client.post(
        MARK_URL.format(event_id=4821),
        json={"reason": "Outside hospital", "two_attempts": False},
    )

    assert_marked(event, res)
    assert event.two_attempts_flag == 0


@patch("flask_backend.models.get_session")
def test_ascertainment_error_stores_reason_only(mock_get_session, admin_client):
    """`Ascertainment diagnosis error` raises no follow-up: all four NULL."""
    event = FakeEvent()
    mock_get_session.return_value = FakeSession(event)

    res = admin_client.post(
        MARK_URL.format(event_id=4821),
        json={"reason": "Ascertainment diagnosis error"},
    )

    assert_marked(event, res)
    assert event.no_packet_reason == "Ascertainment diagnosis error"
    assert event.two_attempts_flag is None
    assert event.prior_event_date is None
    assert event.prior_event_onsite_flag is None
    assert event.other_cause is None


@patch("flask_backend.models.get_session")
def test_other_stores_free_text_cause(mock_get_session, admin_client):
    """`Other` + the user's second reported attempt, "Data corruption"."""
    event = FakeEvent()
    mock_get_session.return_value = FakeSession(event)

    res = admin_client.post(
        MARK_URL.format(event_id=4821),
        json={"reason": "Other", "other_cause": "Data corruption"},
    )

    assert_marked(event, res)
    assert event.no_packet_reason == "Other"
    assert event.other_cause == "Data corruption"
    assert event.two_attempts_flag is None
    assert event.prior_event_date is None
    assert event.prior_event_onsite_flag is None


@patch("flask_backend.models.get_session")
def test_stale_answers_from_a_previous_reason_are_cleared(mock_get_session, admin_client):
    """FR-006: a row carrying earlier answers ends with them NULL.

    The coordinator answered the attempts question, changed the reason to
    `Other`, and submitted. The old answer must not survive alongside a
    reason it does not belong to.
    """
    event = FakeEvent(
        two_attempts_flag=1,
        prior_event_date="11-2011",
        prior_event_onsite_flag=0,
    )
    mock_get_session.return_value = FakeSession(event)

    res = admin_client.post(
        MARK_URL.format(event_id=4821),
        json={
            "reason": "Other",
            "other_cause": "Data corruption",
            # Sent but not applicable: ignored, not rejected (contract).
            "two_attempts": True,
        },
    )

    assert_marked(event, res)
    assert event.other_cause == "Data corruption"
    assert event.two_attempts_flag is None
    assert event.prior_event_date is None
    assert event.prior_event_onsite_flag is None


@patch("flask_backend.models.get_session")
def test_missing_event_returns_404(mock_get_session, admin_client):
    """V9: no such event."""
    mock_get_session.return_value = FakeSession(None)

    res = admin_client.post(
        MARK_URL.format(event_id=999),
        json={"reason": "Ascertainment diagnosis error"},
    )

    assert res.status_code == 404
    assert res.get_json()["error"] == "Event not found"


# --- Prior-event encoding (FR-013, FR-014; data-model "prior_event_date") ---
#
# `prior_event_date` is a zero-padded MM-YYYY varchar(7) with `00` / `0000`
# sentinels for a half the coordinator left blank, recovered from production
# rows (research D5). A NULL is a different answer again — "no approximate
# date is known at all" — and the three states must stay distinct.


@patch("flask_backend.models.get_session")
def test_prior_event_full_date(mock_get_session, admin_client):
    """Month 11, year 2011 → '11-2011', exactly as legacy stores it."""
    event = FakeEvent()
    mock_get_session.return_value = FakeSession(event)

    res = admin_client.post(
        MARK_URL.format(event_id=4821),
        json={
            "reason": PRIOR_EVENT_REASON,
            "prior_event_date_known": True,
            "prior_event_month": 11,
            "prior_event_year": 2011,
            "prior_event_onsite": False,
        },
    )

    assert_marked(event, res)
    assert event.no_packet_reason == PRIOR_EVENT_REASON
    assert event.prior_event_date == "11-2011"
    assert event.prior_event_onsite_flag == 0
    assert event.two_attempts_flag is None
    assert event.other_cause is None


@patch("flask_backend.models.get_session")
def test_prior_event_blank_month_uses_zero_sentinel(mock_get_session, admin_client):
    """Blank month with year 2008 → '00-2008' (observed in production)."""
    event = FakeEvent()
    mock_get_session.return_value = FakeSession(event)

    res = admin_client.post(
        MARK_URL.format(event_id=4821),
        json={
            "reason": PRIOR_EVENT_REASON,
            "prior_event_date_known": True,
            "prior_event_month": "",
            "prior_event_year": 2008,
            "prior_event_onsite": True,
        },
    )

    assert_marked(event, res)
    assert event.prior_event_date == "00-2008"
    assert event.prior_event_onsite_flag == 1


@patch("flask_backend.models.get_session")
def test_prior_event_blank_year_uses_zero_sentinel(mock_get_session, admin_client):
    """Month 1 with a blank year → '01-0000', zero-padded to varchar(7)."""
    event = FakeEvent()
    mock_get_session.return_value = FakeSession(event)

    res = admin_client.post(
        MARK_URL.format(event_id=4821),
        json={
            "reason": PRIOR_EVENT_REASON,
            "prior_event_date_known": True,
            "prior_event_month": 1,
            "prior_event_year": "",
            "prior_event_onsite": False,
        },
    )

    assert_marked(event, res)
    assert event.prior_event_date == "01-0000"


@patch("flask_backend.models.get_session")
def test_prior_event_date_not_known_stores_null(mock_get_session, admin_client):
    """Answering "No" to the date question stores NULL, not '00-0000'.

    The two are different claims: NULL means no approximate date is known at
    all, while '00-0000' would mean one is known but neither half was given.
    """
    event = FakeEvent()
    mock_get_session.return_value = FakeSession(event)

    res = admin_client.post(
        MARK_URL.format(event_id=4821),
        json={
            "reason": PRIOR_EVENT_REASON,
            "prior_event_date_known": False,
            # Sent but not applicable once the date is denied: ignored.
            "prior_event_month": 11,
            "prior_event_year": 2011,
            "prior_event_onsite": True,
        },
    )

    assert_marked(event, res)
    assert event.prior_event_date is None
    assert event.prior_event_onsite_flag == 1
    assert event.two_attempts_flag is None
    assert event.other_cause is None


# --- Validation V1-V8 (FR-010 to FR-016) ------------------------------------
#
# Every rejection must name the offending field and leave the row exactly as
# it was: validation runs before any assignment, so a refused submission
# cannot half-write the event (FR-016).


@patch("flask_backend.models.get_session")
@pytest.mark.parametrize("body", [{}, {"reason": ""}, {"reason": "Made up"}])
def test_missing_or_unknown_reason_rejected(mock_get_session, admin_client, body):
    """V1: the reason must be one of the four the form offers."""
    event = FakeEvent()
    mock_get_session.return_value = FakeSession(event)

    res = admin_client.post(MARK_URL.format(event_id=4821), json=body)

    assert res.status_code == 400
    assert "reason must be one of" in res.get_json()["error"]
    assert_unchanged(event)


@patch("flask_backend.models.get_session")
def test_outside_hospital_without_attempts_answer_rejected(mock_get_session, admin_client):
    """V2: unanswered is not the same as "No" — it is refused, not recorded."""
    event = FakeEvent()
    mock_get_session.return_value = FakeSession(event)

    res = admin_client.post(
        MARK_URL.format(event_id=4821), json={"reason": "Outside hospital"}
    )

    assert res.status_code == 400
    assert "two_attempts" in res.get_json()["error"]
    assert_unchanged(event)
    assert event.two_attempts_flag is None


@patch("flask_backend.models.get_session")
@pytest.mark.parametrize("cause", ["", "   ", None])
def test_other_without_cause_rejected(mock_get_session, admin_client, cause):
    """V3: `Other` with nothing written, or only whitespace, is refused."""
    event = FakeEvent()
    mock_get_session.return_value = FakeSession(event)

    res = admin_client.post(
        MARK_URL.format(event_id=4821), json={"reason": "Other", "other_cause": cause}
    )

    assert res.status_code == 400
    assert "other_cause" in res.get_json()["error"]
    assert_unchanged(event)


@patch("flask_backend.models.get_session")
def test_other_cause_at_the_limit_is_accepted(mock_get_session, admin_client):
    """V4 boundary: exactly 100 characters fits `varchar(100)`."""
    event = FakeEvent()
    mock_get_session.return_value = FakeSession(event)

    res = admin_client.post(
        MARK_URL.format(event_id=4821),
        json={"reason": "Other", "other_cause": "x" * 100},
    )

    assert_marked(event, res)
    assert event.other_cause == "x" * 100


@patch("flask_backend.models.get_session")
def test_over_long_other_cause_rejected_with_the_limit_stated(mock_get_session, admin_client):
    """V4: 101 characters is refused, and the message says how long is allowed.

    Silent truncation would lose the coordinator's words without telling
    them — the same class of failure this whole feature exists to end.
    """
    event = FakeEvent()
    mock_get_session.return_value = FakeSession(event)

    res = admin_client.post(
        MARK_URL.format(event_id=4821),
        json={"reason": "Other", "other_cause": "x" * 101},
    )

    assert res.status_code == 400
    error = res.get_json()["error"]
    assert "other_cause" in error
    assert "100" in error
    assert_unchanged(event)
    assert event.other_cause is None


@patch("flask_backend.models.get_session")
def test_prior_event_without_onsite_answer_rejected(mock_get_session, admin_client):
    """V5: the on-site question must be answered for the prior-event reason."""
    event = FakeEvent()
    mock_get_session.return_value = FakeSession(event)

    res = admin_client.post(
        MARK_URL.format(event_id=4821),
        json={"reason": PRIOR_EVENT_REASON, "prior_event_date_known": False},
    )

    assert res.status_code == 400
    assert "prior_event_onsite" in res.get_json()["error"]
    assert_unchanged(event)


@patch("flask_backend.models.get_session")
def test_date_known_with_both_halves_blank_rejected(mock_get_session, admin_client):
    """V6: "the date is known" with neither half given contradicts itself.

    Legacy tolerated '00-0000'; it says the coordinator knows a date and
    then supplies none of it, so it is refused rather than stored.
    """
    event = FakeEvent()
    mock_get_session.return_value = FakeSession(event)

    res = admin_client.post(
        MARK_URL.format(event_id=4821),
        json={
            "reason": PRIOR_EVENT_REASON,
            "prior_event_date_known": True,
            "prior_event_month": "",
            "prior_event_year": "",
            "prior_event_onsite": True,
        },
    )

    assert res.status_code == 400
    assert res.get_json()["error"]
    assert_unchanged(event)
    assert event.prior_event_date is None


@patch("flask_backend.models.get_session")
@pytest.mark.parametrize("month", [0, 13, -1, "abc"])
def test_month_outside_one_to_twelve_rejected(mock_get_session, admin_client, month):
    """V7: a supplied month must be a real month."""
    event = FakeEvent()
    mock_get_session.return_value = FakeSession(event)

    res = admin_client.post(
        MARK_URL.format(event_id=4821),
        json={
            "reason": PRIOR_EVENT_REASON,
            "prior_event_date_known": True,
            "prior_event_month": month,
            "prior_event_year": 2011,
            "prior_event_onsite": True,
        },
    )

    assert res.status_code == 400
    assert "prior_event_month" in res.get_json()["error"]
    assert_unchanged(event)


@patch("flask_backend.models.get_session")
@pytest.mark.parametrize("year", [11, 201, 20111, "20x1"])
def test_non_four_digit_year_rejected(mock_get_session, admin_client, year):
    """V8: a supplied year must have four digits — '201' is a typo, not 201."""
    event = FakeEvent()
    mock_get_session.return_value = FakeSession(event)

    res = admin_client.post(
        MARK_URL.format(event_id=4821),
        json={
            "reason": PRIOR_EVENT_REASON,
            "prior_event_date_known": True,
            "prior_event_month": 11,
            "prior_event_year": year,
            "prior_event_onsite": True,
        },
    )

    assert res.status_code == 400
    assert "prior_event_year" in res.get_json()["error"]
    assert_unchanged(event)


# --- Authorization and conflict (FR-021 to FR-023) --------------------------


@patch("flask_backend.models.get_session")
def test_uploader_at_the_same_site_may_mark(mock_get_session, uploader_client):
    """V10: a non-admin uploader resolves their own site's event."""
    event = FakeEvent()
    mock_get_session.return_value = FakeSession(event, FakePatient(site="TEST"))

    res = uploader_client.post(
        MARK_URL.format(event_id=4821),
        json={"reason": "Outside hospital", "two_attempts": True},
    )

    assert_marked(event, res, marker_id=_FAKE_UPLOADER["id"])


@patch("flask_backend.models.get_session")
def test_uploader_at_another_site_forbidden(mock_get_session, uploader_client):
    """V10: an uploader cannot resolve an event they cannot even see.

    Same rule, same wording as `upload_raw` — the two actions resolve the
    same queue item, so they must not be able to disagree about who may.
    """
    event = FakeEvent()
    mock_get_session.return_value = FakeSession(event, FakePatient(site="OTHER"))

    res = uploader_client.post(
        MARK_URL.format(event_id=4821),
        json={"reason": "Outside hospital", "two_attempts": True},
    )

    assert res.status_code == 403
    assert res.get_json()["error"] == "Uploader must match patient site"
    assert_unchanged(event)


@patch("flask_backend.models.get_session")
def test_admin_may_mark_across_sites(mock_get_session, admin_client):
    """V10: admins are not site-scoped, exactly as in `upload_raw`."""
    event = FakeEvent()
    mock_get_session.return_value = FakeSession(event, FakePatient(site="OTHER"))

    res = admin_client.post(
        MARK_URL.format(event_id=4821),
        json={"reason": "Ascertainment diagnosis error"},
    )

    assert_marked(event, res)


@patch("flask_backend.models.get_session")
@pytest.mark.parametrize(
    "status",
    [
        "uploaded",
        "scrubbed",
        "screened",
        "assigned",
        "sent",
        "done",
        "rejected",
        "no_packet_available",
    ],
)
def test_event_past_packet_collection_is_refused(mock_get_session, admin_client, status):
    """V11: only an event still awaiting a packet may be marked.

    Anything further along has either received a packet or been resolved
    already, and overwriting it would destroy that record. The message names
    the current status so the coordinator knows why (FR-023).
    """
    event = FakeEvent(status=status)
    mock_get_session.return_value = FakeSession(event)

    res = admin_client.post(
        MARK_URL.format(event_id=4821),
        json={"reason": "Outside hospital", "two_attempts": True},
    )

    assert res.status_code == 409
    error = res.get_json()["error"]
    assert status in error
    assert "4821" in error
    # The row is untouched: still its old status, no reason, no marker.
    assert event.status == status
    assert event.no_packet_reason is None
    assert event.marker_id is None
    assert event.markNoPacket_date is None


def test_mark_no_packet_requires_authentication():
    """With external auth configured, an unauthenticated request gets 401."""
    app_mod = importlib.import_module("flask_backend.app")
    app_mod.keycloak_openid = object()
    try:
        client = app_mod.app.test_client()
        res = client.post(
            MARK_URL.format(event_id=4821),
            json={"reason": "Ascertainment diagnosis error"},
        )
        assert res.status_code == 401
    finally:
        app_mod.keycloak_openid = None
