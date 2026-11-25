"""Schema check for the spec 014 `reviews.modified` column (research D5).

Every study's `check_schema` names init/migrations/014-reviews-modified.sh
when the column is missing, and the review endpoints turn that into a 503.
"""

import importlib
from unittest.mock import patch

import pytest

from flask_backend.models import studies

HINT = studies.MODIFIED_MIGRATION_HINT


class _Result:
    def __init__(self, values):
        self._values = values

    def scalars(self):
        return self

    def all(self):
        return list(self._values)


class _ColumnsSession:
    """Answers the information_schema query with fixed `reviews` columns."""

    def __init__(self, columns):
        self._columns = columns

    def execute(self, *args, **kwargs):
        return _Result(self._columns)


MCI_COLUMNS = ("id", "event_id", "reviewer_id", "mci", "cardiac_cath")
SHARED_COLUMNS = ("id", "event_id", "reviewer_id", "created")


@pytest.mark.parametrize("study,columns", [
    ("mci", MCI_COLUMNS),
    ("scans", SHARED_COLUMNS),
    ("dischargesummaries", SHARED_COLUMNS),
])
def test_missing_modified_is_reported(study, columns):
    module = importlib.import_module(f"flask_backend.models.studies.{study}")
    message = module.check_schema(_ColumnsSession(columns))
    assert message == HINT
    assert "init/migrations/014-reviews-modified.sh" in message


@pytest.mark.parametrize("study,columns", [
    ("mci", MCI_COLUMNS),
    ("scans", SHARED_COLUMNS),
    ("dischargesummaries", SHARED_COLUMNS),
])
def test_present_modified_passes(study, columns):
    module = importlib.import_module(f"flask_backend.models.studies.{study}")
    assert module.check_schema(_ColumnsSession(columns + ("modified",))) is None


def test_scans_012_check_still_runs_first():
    """An MI-shaped scans table names the 012 migration, not 014."""
    scans = importlib.import_module("flask_backend.models.studies.scans")
    message = scans.check_schema(_ColumnsSession(MCI_COLUMNS))
    assert "init/migrations/012-scans-reviews.sh" in message


class _EventSession:
    def query(self, *args, **kwargs):
        return self

    def get(self, _id):
        return None

    def close(self):
        pass


def test_review_get_returns_503_naming_the_migration(admin_client, monkeypatch):
    monkeypatch.delenv("STUDY_TYPE", raising=False)
    mci = importlib.import_module("flask_backend.models.studies.mci")
    with patch.object(mci, "check_schema", return_value=HINT), \
            patch("flask_backend.models.get_session", return_value=_EventSession()):
        res = admin_client.get("/api/events/1/review")
    assert res.status_code == 503
    assert res.get_json() == {"error": HINT}
