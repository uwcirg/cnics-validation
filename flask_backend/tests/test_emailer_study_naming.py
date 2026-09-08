"""The assignment email names this deployment's own study (spec 010, US3).

Before this feature both strings were hard-coded to the cardiology study: the
subject read ``... MI Review Assignment - Event N`` and the body opened with
``You have been assigned a Myocardial Infarction (MI) review.`` — wording a
reviewer on a DEXA-scans or stroke deployment received verbatim. Both now take
the configured study label.

``_format_subject`` and ``_build_body`` are shared by all three reviewer slots
(reviewers 1 and 2, and the third-reviewer path), so covering both functions
covers every recipient — asserted directly by the third-reviewer test below
(FR-025).

``EMAIL_TEST_MODE=1`` makes the senders return the composed ``subject`` and a
``body_preview`` instead of contacting an SMTP server, so these are real
end-to-end composition tests with no network and no mail server.
"""

from unittest.mock import patch

import pytest

from flask_backend import emailer

_ENV_VARS = ("STUDY_TYPE", "EMAIL_SUBJECT_PREFIX", "EMAIL_TEST_MODE")


class _FakeUser:
    def __init__(self, uid=7):
        self.id = uid
        self.username = "reviewer@example.org"
        self.login = "reviewer"
        self.first_name = "Ann"
        self.last_name = "Lee"


class _FakeEvent:
    def __init__(self, eid=4821, reviewer1_id=7, reviewer2_id=None, reviewer3_id=None):
        self.id = eid
        self.reviewer1_id = reviewer1_id
        self.reviewer2_id = reviewer2_id
        self.reviewer3_id = reviewer3_id


class _FakeQuery:
    def __init__(self, obj):
        self._obj = obj

    def get(self, _id):
        return self._obj


class _FakeSession:
    """Serves one event and one user, which is all either sender looks up."""

    def __init__(self, event, user):
        self._event = event
        self._user = user

    def query(self, model):
        if model.__name__ == "Events":
            return _FakeQuery(self._event)
        return _FakeQuery(self._user)

    def close(self):
        pass


@pytest.fixture
def clean_env(monkeypatch):
    """Start from a known environment, with test mode on and no study set."""
    for var in _ENV_VARS:
        monkeypatch.delenv(var, raising=False)
    monkeypatch.setenv("EMAIL_TEST_MODE", "1")
    return monkeypatch


def _send(event=None, third=False):
    """Compose one assignment email and return its detail dict."""
    event = event or _FakeEvent()
    session = _FakeSession(event, _FakeUser())
    with patch("flask_backend.models.get_session", return_value=session):
        # No attachment on disk for this event id, so nothing is attached.
        if third:
            result = emailer.send_third_reviewer_emails_for_event_ids([event.id])
        else:
            result = emailer.send_assignment_emails_for_event_ids([event.id])
    assert result["sent"] == 1, result
    return result["details"][0]


# --- A configured study is named (FR-020, FR-021) --------------------------


def test_subject_names_the_configured_study(clean_env):
    """`mci` is named in the subject in place of the old hard-coded `MI`."""
    clean_env.setenv("STUDY_TYPE", "mci")

    detail = _send()

    assert detail["subject"] == "CNICS / NA-ACCORD MCI Review Assignment – Event 4821"


def test_body_names_the_configured_study(clean_env):
    """The body's opening sentence names the study, with no article before it."""
    clean_env.setenv("STUDY_TYPE", "mci")

    body = _send()["body_preview"]

    assert "You have been assigned a review in the MCI study." in body
    # FR-022: no indefinite article may precede the label. "an MCI" is right
    # and "a CVA" is right, and neither is derivable from a configured value,
    # so the phrasing avoids the choice entirely.
    assert " a MCI" not in body
    assert " an MCI" not in body
    # FR-023: the cardiology-only clinical name is gone from every deployment.
    assert "Myocardial" not in body
    assert "(MI)" not in body


def test_scans_deployment_is_named_scans(clean_env):
    """A non-cardiology deployment names its own study, not MI."""
    clean_env.setenv("STUDY_TYPE", "scans")

    detail = _send()

    assert detail["subject"] == "CNICS / NA-ACCORD SCANS Review Assignment – Event 4821"
    assert "You have been assigned a review in the SCANS study." in detail["body_preview"]
    assert "MI Review Assignment" not in detail["subject"]
    assert "Myocardial" not in detail["body_preview"]
    assert " a SCANS" not in detail["body_preview"]


def test_study_label_is_normalized_in_the_email(clean_env):
    """Case and whitespace in STUDY_TYPE never reach the reader (FR-008)."""
    clean_env.setenv("STUDY_TYPE", "  Cva  ")

    detail = _send()

    assert "CVA Review Assignment" in detail["subject"]
    assert "a review in the CVA study" in detail["body_preview"]
    # A study never served before needs no entry anywhere (FR-003).
    assert "  " not in detail["subject"]


# --- No study configured: name none, and leave no gap (US4, FR-024) --------


def test_subject_omits_the_study_when_unset(clean_env):
    """Unset STUDY_TYPE: no study token, and no double space where it was.

    Load-bearing. `get_study_type()` would return `mci` here and every other
    test in this module would still pass while reviewers on an unconfigured
    deployment were told they had been assigned an MCI review.
    """
    # STUDY_TYPE deliberately left unset by the fixture.
    detail = _send()

    assert detail["subject"] == "CNICS / NA-ACCORD Review Assignment – Event 4821"
    assert "  " not in detail["subject"]
    assert "MCI" not in detail["subject"]
    assert "MI Review" not in detail["subject"]


def test_body_omits_the_study_clause_when_unset(clean_env):
    """Unset STUDY_TYPE: the sentence collapses rather than naming a guess."""
    body = _send()["body_preview"]

    assert "You have been assigned a review." in body
    assert "study" not in body.split("Please download")[0]
    assert "Myocardial" not in body
    assert "  " not in body.split("\r\n")[2]


def test_blank_study_type_is_treated_as_unset(clean_env):
    """A whitespace-only STUDY_TYPE names no study either."""
    clean_env.setenv("STUDY_TYPE", "   ")

    detail = _send()

    assert detail["subject"] == "CNICS / NA-ACCORD Review Assignment – Event 4821"
    assert "You have been assigned a review." in detail["body_preview"]


# --- Every reviewer slot, same naming (FR-025) -----------------------------


def test_third_reviewer_email_names_the_same_study(clean_env):
    """The third-reviewer path produces the same naming as reviewers 1 and 2.

    Both paths call the same two helpers, and this asserts that has not been
    forked — a third reviewer must not receive different wording from the two
    reviewers looking at the same event.
    """
    clean_env.setenv("STUDY_TYPE", "scans")

    first = _send(_FakeEvent(reviewer1_id=7))
    third = _send(_FakeEvent(reviewer1_id=None, reviewer3_id=7), third=True)

    assert third["slot"] == 3
    assert third["subject"] == first["subject"]
    assert "You have been assigned a review in the SCANS study." in third["body_preview"]
    assert "Myocardial" not in third["body_preview"]


def test_third_reviewer_email_omits_the_study_when_unset(clean_env):
    """The unset case holds on the third-reviewer path too (FR-024, FR-025)."""
    third = _send(_FakeEvent(reviewer1_id=None, reviewer3_id=7), third=True)

    assert third["subject"] == "CNICS / NA-ACCORD Review Assignment – Event 4821"
    assert "You have been assigned a review." in third["body_preview"]


# --- Everything else about the email is untouched --------------------------


def test_subject_prefix_override_still_applies(clean_env):
    """EMAIL_SUBJECT_PREFIX keeps working, and composes with the label."""
    clean_env.setenv("STUDY_TYPE", "mci")
    clean_env.setenv("EMAIL_SUBJECT_PREFIX", "CNICS")

    assert _send()["subject"] == "CNICS MCI Review Assignment – Event 4821"


def test_body_still_carries_the_reviewer_name_and_links(clean_env):
    """Only the opening sentence changed; the rest of the body is intact."""
    clean_env.setenv("STUDY_TYPE", "mci")

    body = _send()["body_preview"]

    assert body.startswith("Dear Ann Lee,")
    assert "Please download the charts and complete the review at the links below." in body
