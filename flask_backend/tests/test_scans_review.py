"""Tests for the scans review form's server side (spec 012, User Story 1).

Covers POST /api/events/<id>/review under STUDY_TYPE=scans: validation
(FR-003 – FR-007, FR-012, FR-014), the rows stored (FR-013, SC-002), and the
lifecycle (FR-015). Database access is replaced by a fake session that
records what the endpoint adds, in the style of test_review_endpoint.py.
The `admin_client` fixture acts as user id 1.
"""

from decimal import Decimal
from unittest.mock import patch

import pytest

from flask_backend.models.studies import scans


class FakeEvent:
    def __init__(self, reviewer1_id=None, reviewer2_id=None, status="assigned",
                 review1_date=None, review2_date=None):
        self.id = 5
        self.reviewer1_id = reviewer1_id
        self.reviewer2_id = reviewer2_id
        self.review1_date = review1_date
        self.review2_date = review2_date
        self.status = status


class _Result:
    def __init__(self, values):
        self._values = values

    def scalars(self):
        return self

    def all(self):
        return list(self._values)


class FakeSession:
    """Session whose `query(...).get(id)` yields the configured event and
    whose `add()` calls are recorded. `execute()` answers the schema check
    with a migrated scans `reviews` table unless told otherwise."""

    def __init__(self, event, reviews_columns=("id", "event_id", "reviewer_id", "created")):
        self._event = event
        self.added = []
        self.committed = False
        self._reviews_columns = reviews_columns

    def query(self, *args, **kwargs):
        return self

    def get(self, _event_id):
        return self._event

    def add(self, obj):
        self.added.append(obj)

    def execute(self, *args, **kwargs):
        return _Result(self._reviews_columns)

    def commit(self):
        self.committed = True

    def rollback(self):
        pass

    def close(self):
        pass


@pytest.fixture(autouse=True)
def _scans(monkeypatch):
    for var in ("REVIEWER_COUNT", "ENABLE_SCRUBBING", "ENABLE_SCREENING", "ENABLE_SENDING"):
        monkeypatch.delenv(var, raising=False)
    monkeypatch.setenv("STUDY_TYPE", "scans")
    # The schema check result is cached per process; start each test clean.
    import flask_backend.app as app_mod
    monkeypatch.setattr(app_mod, "_schema_errors", {}, raising=False)


def _region(region, laterality="", t="", z="", other=None):
    body = {"region": region, "laterality": laterality, "t_score": t, "z_score": z}
    if other is not None:
        body["region_other"] = other
    return body


def _post(client, session, regions_body):
    with patch("flask_backend.models.get_session", return_value=session):
        return client.post("/api/events/5/review", json=regions_body)


VALID = {"regions": [
    _region("Lumbar spine", t="-1.1", z="-0.4"),
    _region("Hip", laterality="Left", t="-0.9"),
    _region("Other", other="Total body"),
]}


# --- Successful submission ------------------------------------------------------


def test_valid_submission_stores_one_review_with_ordered_regions(admin_client):
    event = FakeEvent(reviewer1_id=1)
    session = FakeSession(event)

    res = _post(admin_client, session, VALID)

    assert res.status_code == 200
    assert res.get_json()["data"] == {"event_id": 5, "status": "done"}
    assert session.committed
    assert event.status == "done" and event.review1_date is not None

    assert len(session.added) == 1
    review = session.added[0]
    assert isinstance(review, scans.Reviews)
    assert (review.event_id, review.reviewer_id) == (5, 1)
    regions = review.regions
    assert [r.position for r in regions] == [1, 2, 3]
    assert [r.region for r in regions] == ["Lumbar spine", "Hip", "Other"]

    lumbar, hip, other = regions
    assert lumbar.laterality is None
    assert lumbar.t_score == Decimal("-1.1") and lumbar.z_score == Decimal("-0.4")
    assert lumbar.region_other is None
    assert hip.laterality == "Left" and hip.z_score is None
    assert other.region_other == "Total body"
    assert other.t_score is None and other.z_score is None


def test_region_other_ignored_unless_region_is_other(admin_client):
    session = FakeSession(FakeEvent(reviewer1_id=1))
    res = _post(admin_client, session, {"regions": [_region("Spine", other="ignored")]})
    assert res.status_code == 200
    assert session.added[0].regions[0].region_other is None


def test_repeated_region_is_accepted(admin_client):
    session = FakeSession(FakeEvent(reviewer1_id=1))
    body = {"regions": [_region("Wrist", "Left"), _region("Wrist", "Right")]}
    res = _post(admin_client, session, body)
    assert res.status_code == 200
    assert [r.laterality for r in session.added[0].regions] == ["Left", "Right"]


@pytest.mark.parametrize("value,expected", [
    ("-1.1", Decimal("-1.1")), ("2.0", Decimal("2.0")), ("0", Decimal("0")),
    ("+0.5", Decimal("0.5")), (".5", Decimal("0.5")), ("2", Decimal("2")),
    ("10", Decimal("10")), ("-10", Decimal("-10")), (" -2.25 ", Decimal("-2.25")),
])
def test_accepted_score_forms(admin_client, value, expected):
    session = FakeSession(FakeEvent(reviewer1_id=1))
    res = _post(admin_client, session, {"regions": [_region("Spine", t=value, z=value)]})
    assert res.status_code == 200
    region = session.added[0].regions[0]
    assert region.t_score == expected and region.z_score == expected


# --- Validation failures (nothing stored) ---------------------------------------


def _assert_400(res, session, index=None, field=None):
    assert res.status_code == 400
    assert session.added == [] and not session.committed
    body = res.get_json()
    assert body["error"]
    if field is not None:
        assert {"index": index, "field": field} in [
            {"index": f["index"], "field": f["field"]} for f in body["fields"]
        ]
    return body


@pytest.mark.parametrize("body", [
    {"regions": []},
    {"regions": "Spine"},
    {"something": "else"},
])
def test_missing_or_empty_regions_rejected(admin_client, body):
    session = FakeSession(FakeEvent(reviewer1_id=1))
    body = _assert_400(_post(admin_client, session, body), session)
    assert body["error"] == "At least one body region is required"


def test_too_many_regions_rejected(admin_client):
    session = FakeSession(FakeEvent(reviewer1_id=1))
    body = {"regions": [_region("Spine")] * 21}
    res = _assert_400(_post(admin_client, session, body), session)
    assert res["error"] == "Too many body regions (max 20)"


def test_twenty_regions_accepted(admin_client):
    session = FakeSession(FakeEvent(reviewer1_id=1))
    res = _post(admin_client, session, {"regions": [_region("Spine")] * 20})
    assert res.status_code == 200
    assert len(session.added[0].regions) == 20


def test_unknown_region_rejected(admin_client):
    session = FakeSession(FakeEvent(reviewer1_id=1))
    body = {"regions": [_region("Spine"), _region("Elbow")]}
    res = _assert_400(_post(admin_client, session, body), session, 1, "region")
    assert res["fields"][0]["message"] == "Unknown body region"


@pytest.mark.parametrize("other", [None, "", "   "])
def test_other_without_text_rejected(admin_client, other):
    session = FakeSession(FakeEvent(reviewer1_id=1))
    body = {"regions": [_region("Other", other=other)]}
    res = _assert_400(_post(admin_client, session, body), session, 0, "region_other")
    assert res["fields"][0]["message"] == "Please specify the body region"


def test_bad_laterality_rejected(admin_client):
    session = FakeSession(FakeEvent(reviewer1_id=1))
    body = {"regions": [_region("Hip", laterality="Middle")]}
    res = _assert_400(_post(admin_client, session, body), session, 0, "laterality")
    assert res["fields"][0]["message"] == "Unknown laterality"


@pytest.mark.parametrize("value", ["abc", "12", "-10.01", "1.234", "1..2", "1,5", "-", "."])
@pytest.mark.parametrize("field,label", [("t_score", "T"), ("z_score", "Z")])
def test_bad_scores_rejected(admin_client, value, field, label):
    session = FakeSession(FakeEvent(reviewer1_id=1))
    region = _region("Spine")
    region[field] = value
    res = _assert_400(_post(admin_client, session, {"regions": [region]}), session, 0, field)
    assert res["fields"][0]["message"] == (
        f"{label} score must be a number from -10 to 10, with at most 2 decimal places"
    )


def test_all_field_errors_reported_together(admin_client):
    session = FakeSession(FakeEvent(reviewer1_id=1))
    body = {"regions": [_region("Elbow", t="abc"), _region("Hip", z="99")]}
    res = _assert_400(_post(admin_client, session, body), session)
    got = {(f["index"], f["field"]) for f in res["fields"]}
    assert got == {(0, "region"), (0, "t_score"), (1, "z_score")}


# --- Access ------------------------------------------------------------------------


def test_non_assigned_caller_forbidden(admin_client):
    session = FakeSession(FakeEvent(reviewer1_id=99))
    res = _post(admin_client, session, VALID)
    assert res.status_code == 403
    assert session.added == []


# --- Duplicate submission and un-migrated schema ---------------------------------


def test_second_submission_conflicts(admin_client):
    event = FakeEvent(reviewer1_id=1, status="done", review1_date="2026-10-01")
    session = FakeSession(event)
    res = _post(admin_client, session, VALID)
    assert res.status_code == 409
    assert session.added == []


@pytest.mark.parametrize("columns", [
    ("id", "event_id", "reviewer_id", "mci", "cardiac_cath"),   # MI-shaped table
    (),                                                          # no reviews table
])
def test_unmigrated_schema_returns_503(admin_client, columns):
    session = FakeSession(FakeEvent(reviewer1_id=1), reviews_columns=columns)
    res = _post(admin_client, session, VALID)
    assert res.status_code == 503
    assert "init/migrations/012-scans-reviews.sh" in res.get_json()["error"]
    assert session.added == []


# --- GET /api/events/<id>/review (FR-016) -------------------------------------------


@pytest.fixture
def reviewer_client():
    """Client authenticated as a non-admin reviewer with user id 7."""
    import importlib
    from flask import g

    app_mod = importlib.import_module("flask_backend.app")
    app_mod.keycloak_openid = None

    def _load():
        g.auth_user = {"id": 7, "username": "rev", "admin": False, "uploader": False,
                       "reviewer": True, "third_reviewer": False, "site": "TEST"}
        return g.auth_user

    with patch.object(app_mod, "_load_user_from_remote_header", _load):
        client = app_mod.app.test_client()
        client.environ_base["HTTP_X_REMOTE_USER"] = "rev"
        yield client


STORED = [{"reviewer_id": 7, "review_date": "2026-10-14", "regions": [
    {"position": 1, "region": "Hip", "region_other": None, "laterality": "Left",
     "t_score": "-0.90", "z_score": None},
]}]


def _get(client, event):
    with patch("flask_backend.models.get_session", return_value=FakeSession(event)), \
         patch.object(scans, "get_reviews", return_value=STORED):
        return client.get("/api/events/5/review")


def test_get_review_admin(admin_client):
    res = _get(admin_client, FakeEvent(reviewer1_id=7))
    assert res.status_code == 200
    assert res.get_json()["data"] == {"event_id": 5, "reviews": STORED}


def test_get_review_assigned_reviewer(reviewer_client):
    assert _get(reviewer_client, FakeEvent(reviewer1_id=7)).status_code == 200


def test_get_review_other_reviewer_forbidden(reviewer_client):
    assert _get(reviewer_client, FakeEvent(reviewer1_id=8)).status_code == 403


def test_get_review_unknown_event(admin_client):
    assert _get(admin_client, None).status_code == 404


class _MappingResult:
    def __init__(self, rows):
        self._rows = rows

    def mappings(self):
        return self

    def all(self):
        return self._rows


class _RowsSession:
    def __init__(self, rows):
        self._rows = rows

    def execute(self, *args, **kwargs):
        return _MappingResult(self._rows)


def test_get_reviews_serialization():
    import datetime

    rows = [
        {"review_id": 3, "reviewer_id": 7, "review_date": datetime.date(2026, 10, 14),
         "position": 1, "region": "Lumbar spine", "region_other": None, "laterality": None,
         "t_score": Decimal("-1.10"), "z_score": Decimal("-0.40")},
        {"review_id": 3, "reviewer_id": 7, "review_date": datetime.date(2026, 10, 14),
         "position": 2, "region": "Other", "region_other": "Total body", "laterality": "Both",
         "t_score": None, "z_score": Decimal("2.00")},
    ]
    assert scans.get_reviews(_RowsSession(rows), 5) == [{
        "reviewer_id": 7, "review_date": "2026-10-14", "regions": [
            {"position": 1, "region": "Lumbar spine", "region_other": None, "laterality": None,
             "t_score": "-1.10", "z_score": "-0.40"},
            {"position": 2, "region": "Other", "region_other": "Total body", "laterality": "Both",
             "t_score": None, "z_score": "2.00"},
        ],
    }]


def test_get_reviews_none():
    assert scans.get_reviews(_RowsSession([]), 5) == []


# --- GET /api/events/export under scans (research D9) --------------------------------


def test_scans_export_one_row_per_region(admin_client):
    import csv
    import datetime
    import io

    rows = [
        {"event_id": 5, "patient_id": 11, "site": "UW", "site_patient_id": "A1",
         "event_date": datetime.date(2019, 3, 2), "status": "done", "reviewer": "rev",
         "review_date": datetime.date(2026, 10, 14), "position": 1, "region": "Hip",
         "region_other": None, "laterality": "Left", "t_score": Decimal("-0.90"), "z_score": None},
        {"event_id": 5, "patient_id": 11, "site": "UW", "site_patient_id": "A1",
         "event_date": datetime.date(2019, 3, 2), "status": "done", "reviewer": "rev",
         "review_date": datetime.date(2026, 10, 14), "position": 2, "region": "Other",
         "region_other": "Total body", "laterality": None, "t_score": None, "z_score": Decimal("1.20")},
        {"event_id": 6, "patient_id": 12, "site": "UW", "site_patient_id": "A2",
         "event_date": None, "status": "assigned", "reviewer": None, "review_date": None,
         "position": None, "region": None, "region_other": None, "laterality": None,
         "t_score": None, "z_score": None},
    ]
    with patch("flask_backend.models.get_session", return_value=FakeSession(None)), \
         patch.object(scans, "_export_rows", return_value=rows):
        res = admin_client.get("/api/events/export")
    assert res.status_code == 200
    table = list(csv.reader(io.StringIO(res.get_data(as_text=True))))
    assert table[0] == scans.EXPORT_HEADINGS
    assert table[1] == ["5", "11", "UW", "A1", "2019-03-02", "done", "rev", "2026-10-14",
                        "1", "Hip", "", "Left", "-0.90", ""]
    assert table[2][8:] == ["2", "Other", "Total body", "", "", "1.20"]
    assert table[3] == ["6", "12", "UW", "A2", "", "assigned"] + [""] * 8
    assert len(table) == 4
