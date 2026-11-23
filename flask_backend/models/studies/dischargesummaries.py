"""Study module for STUDY_TYPE=dischargesummaries (spec 013).

A site uploader uploads a redacted hospital discharge summary, an admin
stores a scrubbed copy, an admin assigns one reviewer, and that review
completes the event.

**Phase A (research D5).** The review questions are not known yet (spec 013
FR-011). This module still exists and is usable, so the resolver does not
fall back to ``mci`` and the database never holds another study's tables.
Until Phase B:

* ``reviews`` has only the shared columns (``id``, ``event_id``,
  ``reviewer_id``, ``created``);
* :func:`parse_review` refuses every submission, so no review is stored and
  the Phase B migration starts from an empty table;
* :func:`get_reviews` returns no reviews, and :func:`export_csv` writes the
  shared event columns with empty review cells.

Phase B adds the answer columns, real validation, storage, retrieval, the
export's answer columns and a :func:`check_schema` that detects an
un-migrated Phase A table.

``dischargesummaries`` has a single reviewer, so it has no
``event_derived_datas`` (allowed by constitution v1.5.1).

DDL: ``init/studies/dischargesummaries.sql``. The interface is documented
in ``flask_backend/models/studies/__init__.py``.
"""

import datetime
from typing import Optional

from sqlalchemy import DateTime, text
from sqlalchemy.dialects.mysql import INTEGER
from sqlalchemy.orm import DeclarativeBase, Mapped, mapped_column

from . import ReviewValidationError

REVIEW_FORM = "dischargesummaries"

# Tables `/api/tables/<name>` refuses to non-admins (spec 012 FR-028).
REVIEW_TABLES = ("reviews",)

NOT_AVAILABLE_MESSAGE = "The discharge-summary review form is not available yet."


class DischargesummariesBase(DeclarativeBase):
    """Declarative base for dischargesummaries study tables (own metadata; see package docs)."""


class Reviews(DischargesummariesBase):
    """One completed review. Its date is ``events.review1_date``."""

    __tablename__ = "reviews"

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True)
    event_id: Mapped[int] = mapped_column(INTEGER(11))
    reviewer_id: Mapped[int] = mapped_column(INTEGER(11))
    created: Mapped[datetime.datetime] = mapped_column(
        DateTime, server_default=text("CURRENT_TIMESTAMP")
    )


def parse_review(data: dict) -> dict:
    """Refuse every submission: the review form is not defined yet (Phase A)."""
    raise ReviewValidationError(NOT_AVAILABLE_MESSAGE)


def add_review(session, event_id: int, reviewer_id: int, parsed) -> None:
    """Unreachable in Phase A, because :func:`parse_review` always refuses."""
    raise RuntimeError("dischargesummaries reviews cannot be stored until Phase B")


def get_reviews(session, event_id: int) -> list:
    """Return this event's reviews. Phase A stores none."""
    return []


EXPORT_HEADINGS = [
    "Event ID", "Patient ID", "Site", "Site Patient ID", "Event Date", "Status",
    "Reviewer", "Review Date",
]

_EXPORT_KEYS = (
    "event_id", "patient_id", "site", "site_patient_id", "event_date",
    "status", "reviewer", "review_date",
)

_EXPORT_SQL = """
    SELECT e.id AS event_id, e.patient_id, p.site, p.site_patient_id,
           e.event_date, e.status,
           r1.username AS reviewer, e.review1_date AS review_date
      FROM events e
      LEFT JOIN patients_view p ON p.id = e.patient_id
      LEFT JOIN users r1 ON r1.id = e.reviewer1_id
     ORDER BY e.id
"""


def export_csv(session, writer) -> None:
    """Write the events export (one row per event) to `writer`.

    Phase A has no answer columns; Phase B appends them.
    """
    writer.writerow(EXPORT_HEADINGS)
    for r in session.execute(text(_EXPORT_SQL)).mappings().all():
        writer.writerow(["" if r.get(key) is None else r[key] for key in _EXPORT_KEYS])


def check_schema(session) -> Optional[str]:
    """Phase A's table has only the shared columns, so there is nothing to check."""
    return None
