"""Unit tests for the pre-send status helpers (spec 014 research D3).

`derive_pre_send_status` replaces the status literals that `upload_raw`,
`upload_scrubbed` and screen-accept wrote. `pre_assignment_complete` keeps an
event being re-processed after a packet replacement out of the send and
review steps. Both are exercised under three workflow profiles:

- full: scrubbing, screening and sending on (mci);
- ds: scrubbing on, screening and sending off (dischargesummaries);
- scans: all three off.
"""

import datetime
from types import SimpleNamespace

import pytest

from flask_backend import table_service

D = datetime.date(2026, 11, 24)

FULL = SimpleNamespace(scrubbing=True, screening=True, sending=True, reviewer_count=2)
DS = SimpleNamespace(scrubbing=True, screening=False, sending=False, reviewer_count=1)
SCANS = SimpleNamespace(scrubbing=False, screening=False, sending=False, reviewer_count=1)


def _event(upload=None, scrub=None, screen=None, assign=None):
    return SimpleNamespace(
        upload_date=upload, scrub_date=scrub, screen_date=screen, assign_date=assign,
    )


# (profile, event, expected status, expected pre_assignment_complete)
CASES = [
    # Rule 1: no upload -> created, under every profile.
    (FULL, _event(), "created", False),
    (DS, _event(), "created", False),
    (SCANS, _event(), "created", False),
    # Rule 2: scrubbing on, not scrubbed -> uploaded.
    (FULL, _event(upload=D), "uploaded", False),
    (DS, _event(upload=D), "uploaded", False),
    # Rule 3: screening on, not screened -> scrubbed.
    (FULL, _event(upload=D, scrub=D), "scrubbed", False),
    # Rule 4: not assigned -> the last enabled stage's literal (today's value).
    (FULL, _event(upload=D, scrub=D, screen=D), "screened", True),
    (DS, _event(upload=D, scrub=D), "scrubbed", True),
    (SCANS, _event(upload=D), "uploaded", True),
    # Rule 5: every enabled stage done and assigned -> assigned.
    (FULL, _event(upload=D, scrub=D, screen=D, assign=D), "assigned", True),
    (DS, _event(upload=D, scrub=D, assign=D), "assigned", True),
    (SCANS, _event(upload=D, assign=D), "assigned", True),
    # A kept assignment does not skip a stage that a replacement cleared.
    (FULL, _event(upload=D, assign=D), "uploaded", False),
    (FULL, _event(upload=D, scrub=D, assign=D), "scrubbed", False),
    (DS, _event(upload=D, assign=D), "uploaded", False),
    # Disabled stages are ignored even when their dates are absent.
    (DS, _event(upload=D, scrub=D, screen=None, assign=D), "assigned", True),
]


@pytest.mark.parametrize("cfg,event,expected,_complete", CASES)
def test_derive_pre_send_status(cfg, event, expected, _complete):
    assert table_service.derive_pre_send_status(event, cfg) == expected


@pytest.mark.parametrize("cfg,event,_expected,complete", CASES)
def test_pre_assignment_complete(cfg, event, _expected, complete):
    assert table_service.pre_assignment_complete(event, cfg) is complete


def test_replace_window_predicate_excludes_created_and_released():
    sql = table_service._replace_window_predicate()
    assert "'created'" not in sql
    for status in ("uploaded", "scrubbed", "screened", "assigned", "rejected"):
        assert f"'{status}'" in sql
    for column in ("send_date", "review1_date", "review2_date"):
        assert f"e.{column} IS NULL" in sql


def test_replaceable_statuses():
    assert table_service.REPLACEABLE_STATUSES == (
        "created", "uploaded", "scrubbed", "screened", "assigned", "rejected",
    )
