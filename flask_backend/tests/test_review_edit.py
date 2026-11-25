"""Editing a submitted review: PUT /api/events/<id>/review and the GET `mine`
fields (spec 014 research D6–D8).

- Single-reviewer studies (scans): the author may edit at any time, even
  after the event is `done`.
- Two-reviewer studies (mci): each reviewer may edit until the other has
  submitted; then both reviews are read-only.

An edit overwrites the answers and stamps `modified`; it never changes the
event's status or submission dates. The study module's `replace_review` and
`get_reviews` are patched, so these tests exercise the endpoint's rules.
"""

import datetime
import importlib
from unittest.mock import patch

import pytest

EVENT_ID = 5
D = datetime.date(2026, 11, 1)
MODIFIED = "2026-11-24T10:15:02"
LOCKED = "Both reviews for this event are complete, so they can no longer be changed."
NOTHING_TO_EDIT = "You have not submitted a review for this event"

SCANS_BODY = {"regions": [{"region": "Hip", "laterality": "Left", "t_score": "-1.5", "z_score": ""}]}
MCI_BODY = {"mci": "Definite", "cardiac_cath": "0", "chest_pain_flag": True}


class FakeEvent:
    def __init__(self, status="assigned", **fields):
        self.id = EVENT_ID
        self.status = status
        self.upload_date = D
        self.scrub_date = D
        self.screen_date = D
        self.assign_date = D
        self.send_date = None
        self.reviewer1_id = None
        self.reviewer2_id = None
        self.reviewer3_id = None
        self.review1_date = None
        self.review2_date = None
        self.review3_date = None
        for key, value in fields.items():
            setattr(self, key, value)


class FakeSession:
    def __init__(self, event):
        self._event = event
        self.committed = False

    def query(self, *args, **kwargs):
        return self

    def get(self, _id):
        return self._event

    def commit(self):
        self.committed = True

    def rollback(self):
        pass

    def close(self):
        pass


def _study(name):
    return importlib.import_module(f"flask_backend.models.studies.{name}")


@pytest.fixture
def scans_profile(monkeypatch):
    for var in ("REVIEWER_COUNT", "ENABLE_SCRUBBING", "ENABLE_SCREENING", "ENABLE_SENDING"):
        monkeypatch.delenv(var, raising=False)
    monkeypatch.setenv("STUDY_TYPE", "scans")
    return _study("scans")


@pytest.fixture
def mci_profile(monkeypatch):
    for var in ("STUDY_TYPE", "REVIEWER_COUNT", "ENABLE_SCRUBBING",
                "ENABLE_SCREENING", "ENABLE_SENDING"):
        monkeypatch.delenv(var, raising=False)
    return _study("mci")


def _call(client, method, event, study, *, body=None, replaced=True, stored=None):
    """Make one request with the event, study storage and schema check mocked.

    Returns (response, session, replace_review mock).
    """
    app_mod = importlib.import_module("flask_backend.app")
    session = FakeSession(event)
    stored = stored if stored is not None else []
    with patch("flask_backend.models.get_session", return_value=session), \
            patch.object(app_mod, "_study_schema_error", return_value=None), \
            patch.object(study, "replace_review", return_value=replaced) as replace, \
            patch.object(study, "get_reviews", return_value=stored):
        if method == "PUT":
            res = client.put(f"/api/events/{EVENT_ID}/review", json=body)
        else:
            res = client.get(f"/api/events/{EVENT_ID}/review")
    return res, session, replace


def _reviewer(client_as, uid=7):
    return client_as(id=uid, reviewer=True)


def _own(uid=7, **extra):
    return {"reviewer_id": uid, "review_date": "2026-11-01", "modified": MODIFIED, **extra}


# --- US2: single reviewer (scans) ------------------------------------------------


@pytest.mark.parametrize("status", ["done", "reviewer1_done"])
def test_author_may_edit(client_as, scans_profile, status):
    event = FakeEvent(status, reviewer1_id=7, review1_date=D)
    res, session, replace = _call(_reviewer(client_as), "PUT", event, scans_profile,
                                  body=SCANS_BODY, stored=[_own()])
    assert res.status_code == 200
    assert res.get_json() == {"data": {"event_id": EVENT_ID, "status": status, "modified": MODIFIED}}
    assert event.status == status
    assert event.review1_date == D
    assert session.committed
    replace.assert_called_once()
    _session, event_id, reviewer_id, parsed = replace.call_args.args
    assert (event_id, reviewer_id) == (EVENT_ID, 7)
    assert parsed == scans_profile.parse_review(SCANS_BODY)


def test_invalid_body_is_400_with_fields(client_as, scans_profile):
    event = FakeEvent("done", reviewer1_id=7, review1_date=D)
    bad = {"regions": [{"region": "Hip", "t_score": "abc"}]}
    res, session, replace = _call(_reviewer(client_as), "PUT", event, scans_profile, body=bad)
    assert res.status_code == 400
    assert res.get_json()["fields"]
    replace.assert_not_called()
    assert not session.committed


@pytest.mark.parametrize("who,fields", [
    ("other reviewer", {"reviewer1_id": 8, "review1_date": D}),
    ("slot 3", {"reviewer1_id": 8, "review1_date": D, "reviewer3_id": 7}),
])
def test_non_author_is_403(client_as, scans_profile, who, fields):
    event = FakeEvent("done", **fields)
    res, _session, replace = _call(_reviewer(client_as), "PUT", event, scans_profile, body=SCANS_BODY)
    assert res.status_code == 403
    replace.assert_not_called()


def test_admin_not_in_a_slot_is_403(client_as, scans_profile):
    event = FakeEvent("done", reviewer1_id=8, review1_date=D)
    res, _session, replace = _call(client_as(id=1, admin=True), "PUT", event, scans_profile, body=SCANS_BODY)
    assert res.status_code == 403
    replace.assert_not_called()


def test_author_without_a_submitted_review_is_409(client_as, scans_profile):
    event = FakeEvent("assigned", reviewer1_id=7)
    res, _session, replace = _call(_reviewer(client_as), "PUT", event, scans_profile, body=SCANS_BODY)
    assert res.status_code == 409
    assert res.get_json() == {"error": NOTHING_TO_EDIT}
    replace.assert_not_called()


def test_no_stored_row_is_409(client_as, scans_profile):
    event = FakeEvent("done", reviewer1_id=7, review1_date=D)
    res, session, _replace = _call(_reviewer(client_as), "PUT", event, scans_profile,
                                   body=SCANS_BODY, replaced=False)
    assert res.status_code == 409
    assert res.get_json() == {"error": NOTHING_TO_EDIT}
    assert not session.committed


def test_get_mine_carries_the_edit_fields(client_as, scans_profile):
    event = FakeEvent("done", reviewer1_id=7, review1_date=D)
    own = _own(regions=[{"position": 1, "region": "Hip"}])
    res, _session, _replace = _call(_reviewer(client_as), "GET", event, scans_profile, stored=[own])
    assert res.status_code == 200
    mine = res.get_json()["data"]["mine"]
    assert mine["slot"] == 1
    assert mine["review_date"] == "2026-11-01"
    assert mine["modified"] == MODIFIED
    assert mine["editable"] is True
    assert mine["locked_reason"] is None
    assert mine["answers"]["reviewer_id"] == 7
    assert mine["answers"]["regions"] == [{"position": 1, "region": "Hip"}]


def test_get_never_returns_another_reviewers_answers(client_as, scans_profile):
    """The caller's `answers` is their own item, and `reviews` stays empty."""
    event = FakeEvent("reviewer1_done", reviewer1_id=7, reviewer2_id=8, review1_date=D)
    stored = [_own(uid=8, secret="theirs"), _own(uid=7, mine="yes")]
    res, _session, _replace = _call(_reviewer(client_as), "GET", event, scans_profile, stored=stored)
    data = res.get_json()["data"]
    assert data["reviews"] == []
    assert data["mine"]["answers"]["reviewer_id"] == 7
    assert "secret" not in data["mine"]["answers"]
    assert "theirs" not in res.get_data(as_text=True)


def test_edit_refused_while_the_packet_is_reprocessed(client_as, scans_profile):
    event = FakeEvent("assigned", reviewer1_id=7, review1_date=D, upload_date=None)
    res, _session, replace = _call(_reviewer(client_as), "PUT", event, scans_profile, body=SCANS_BODY)
    assert res.status_code == 409
    assert res.get_json() == {"error": "This event's packet is being re-processed."}
    replace.assert_not_called()


# --- US3: two reviewers (mci) ------------------------------------------------------


def test_first_reviewer_may_edit_before_the_second_submits(client_as, mci_profile):
    event = FakeEvent("reviewer1_done", reviewer1_id=7, reviewer2_id=8, review1_date=D)
    res, session, replace = _call(_reviewer(client_as), "PUT", event, mci_profile,
                                  body=MCI_BODY, stored=[_own()])
    assert res.status_code == 200
    assert res.get_json()["data"]["status"] == "reviewer1_done"
    assert event.status == "reviewer1_done"
    assert session.committed
    replace.assert_called_once()


@pytest.mark.parametrize("uid", [7, 8])
def test_both_reviews_complete_locks_both(client_as, mci_profile, uid):
    event = FakeEvent("reviewer2_done", reviewer1_id=7, reviewer2_id=8, review1_date=D, review2_date=D)
    res, session, replace = _call(_reviewer(client_as, uid), "PUT", event, mci_profile, body=MCI_BODY)
    assert res.status_code == 409
    assert res.get_json() == {"error": LOCKED}
    replace.assert_not_called()
    assert not session.committed


def test_get_mine_is_locked_once_both_reviews_exist(client_as, mci_profile):
    event = FakeEvent("reviewer2_done", reviewer1_id=7, reviewer2_id=8, review1_date=D, review2_date=D)
    res, _session, _replace = _call(_reviewer(client_as), "GET", event, mci_profile, stored=[_own()])
    mine = res.get_json()["data"]["mine"]
    assert mine["editable"] is False
    assert mine["locked_reason"] == LOCKED
    assert mine["answers"]["reviewer_id"] == 7


def test_legacy_sent_event_with_a_missing_stage_date_is_still_editable(client_as, mci_profile):
    """A sent event cannot be mid-replacement (the window closed at send), so
    a legacy row sent without a `screen_date` is not 'being re-processed'."""
    event = FakeEvent("reviewer1_done", reviewer1_id=7, reviewer2_id=8, review1_date=D,
                      send_date=D, screen_date=None)
    res, _session, replace = _call(_reviewer(client_as), "PUT", event, mci_profile,
                                   body=MCI_BODY, stored=[_own()])
    assert res.status_code == 200
    replace.assert_called_once()


def test_second_review_landing_after_page_load_is_409(client_as, mci_profile):
    """The other review is submitted between page load and save: the PUT
    re-checks the rule and refuses."""
    event = FakeEvent("reviewer1_done", reviewer1_id=7, reviewer2_id=8, review1_date=D)
    res, _s, _r = _call(_reviewer(client_as), "GET", event, mci_profile, stored=[_own()])
    assert res.get_json()["data"]["mine"]["editable"] is True

    event.review2_date = D
    event.status = "reviewer2_done"
    res, session, replace = _call(_reviewer(client_as), "PUT", event, mci_profile, body=MCI_BODY)
    assert res.status_code == 409
    assert res.get_json() == {"error": LOCKED}
    replace.assert_not_called()
    assert not session.committed
