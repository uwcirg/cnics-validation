"""`mci.replace_review` and `mci.get_reviews` (spec 014 research D6, D9).

An edit overwrites every answer column from the parsed body. A column the
edited body leaves out becomes NULL, as it would in a new submission; the
row's id is kept and `modified` is set.
"""

import datetime

from flask_backend.models.studies import mci


class _Query:
    def __init__(self, rows):
        self._rows = rows

    def filter(self, *args, **kwargs):
        return self

    def order_by(self, *args, **kwargs):
        return self

    def first(self):
        return self._rows[0] if self._rows else None

    def all(self):
        return list(self._rows)


class _Session:
    def __init__(self, rows):
        self._rows = rows

    def query(self, *args, **kwargs):
        return _Query(self._rows)


def _stored():
    return mci.Reviews(
        id=40, event_id=5, reviewer_id=7, mci="Definite", cardiac_cath=1,
        abnormal_ce_values_flag=1, ce_criteria="Standard criteria", chest_pain_flag=1,
        type="Secondary", secondary_cause="Other", other_cause="old cause",
        ecg_type="STEMI", modified=None,
    )


def test_replace_review_overwrites_every_answer():
    review = _stored()
    parsed = mci.parse_review({"mci": "No", "cardiac_cath": "0", "ci": "1", "ci_type": "Stent"})

    assert mci.replace_review(_Session([review]), 5, 7, parsed) is True

    assert (review.id, review.event_id, review.reviewer_id) == (40, 5, 7)
    assert review.mci == "No"
    assert review.cardiac_cath == 0
    assert review.ci == 1
    assert review.ci_type == "Stent"
    # Answers absent from the edited body are cleared, not kept.
    for column in ("abnormal_ce_values_flag", "ce_criteria", "chest_pain_flag", "type",
                   "secondary_cause", "other_cause", "ecg_type"):
        assert getattr(review, column) is None, column
    assert isinstance(review.modified, datetime.datetime)


def test_replace_review_without_a_row_returns_false():
    parsed = mci.parse_review({"mci": "Definite", "cardiac_cath": "0"})
    assert mci.replace_review(_Session([]), 5, 7, parsed) is False


def test_get_reviews_serializes_modified_as_iso():
    edited = _stored()
    edited.modified = datetime.datetime(2026, 11, 24, 10, 15, 2)
    never = mci.Reviews(id=41, event_id=5, reviewer_id=8, mci="No", cardiac_cath=0, modified=None)

    reviews = mci.get_reviews(_Session([edited, never]), 5)

    assert [r["modified"] for r in reviews] == ["2026-11-24T10:15:02", None]
    assert reviews[0]["mci"] == "Definite"
