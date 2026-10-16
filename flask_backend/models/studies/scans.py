"""Study module for STUDY_TYPE=scans (DEXA scan review; spec 012).

A scans review records the body regions reported in a DEXA scan packet, each
with an optional laterality and optional T and Z scores. The number of
regions varies, typically 1–4, so the study's tables are:

* ``reviews``: one row per completed review (one per reviewer per event),
  satisfying the shared ``reviews(id, event_id, reviewer_id)`` invariant
  (constitution Principle IV);
* ``review_regions``: one row per body-region entry, in entry order.

``scans`` has a single reviewer, so it has no ``event_derived_datas``
(allowed by constitution v1.5.1).

DDL: ``init/studies/scans.sql``. The interface is documented in
``flask_backend/models/studies/__init__.py``.
"""

import datetime
import re
from decimal import Decimal, InvalidOperation
from typing import Optional

from sqlalchemy import DateTime, Enum, ForeignKey, Numeric, String, text
from sqlalchemy.dialects.mysql import INTEGER, TINYINT
from sqlalchemy.orm import DeclarativeBase, Mapped, mapped_column, relationship

from . import ReviewValidationError

REVIEW_FORM = "scans"

# Tables `/api/tables/<name>` refuses to non-admins (spec 012 FR-028).
REVIEW_TABLES = ("reviews", "review_regions")


class ScansBase(DeclarativeBase):
    """Declarative base for scans study tables (own metadata; see package docs)."""


class Reviews(ScansBase):
    """One completed scans review. Its date is ``events.review1_date``."""

    __tablename__ = "reviews"

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True)
    event_id: Mapped[int] = mapped_column(INTEGER(11))
    reviewer_id: Mapped[int] = mapped_column(INTEGER(11))
    created: Mapped[datetime.datetime] = mapped_column(
        DateTime, server_default=text("CURRENT_TIMESTAMP")
    )
    regions = relationship(
        "ReviewRegions",
        order_by="ReviewRegions.position",
        cascade="all, delete-orphan",
    )


class ReviewRegions(ScansBase):
    """One body-region entry of a scans review. ``None`` = not provided."""

    __tablename__ = "review_regions"

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True)
    review_id: Mapped[int] = mapped_column(INTEGER(11), ForeignKey("reviews.id", ondelete="CASCADE"))
    position: Mapped[int] = mapped_column(TINYINT(3, unsigned=True))
    region: Mapped[str] = mapped_column(String(40))
    region_other: Mapped[Optional[str]] = mapped_column(String(100))
    laterality: Mapped[Optional[str]] = mapped_column(Enum("Left", "Right", "Both", "Unspecified"))
    t_score: Mapped[Optional[Decimal]] = mapped_column(Numeric(4, 2, asdecimal=True))
    z_score: Mapped[Optional[Decimal]] = mapped_column(Numeric(4, 2, asdecimal=True))


# --- Validation rules --------------------------------------------------------
# Keep in sync with frontend/src/studies/scans/ScansReviewForm.jsx.

# Stored values, in dropdown order. The form displays "Other" as
# "Other: please specify". Frequently seen "Other" regions may be added here
# later; `region` is VARCHAR, so that needs no schema change (research D2).
SCANS_REGIONS = (
    "Spine",
    "Lumbar spine",
    "Thoracic spine",
    "Cervical spine",
    "Forearm/radius",
    "Hip",
    "Proximal femur",
    "Femoral neck",
    "Wrist",
    "Other",
)
SCANS_LATERALITIES = ("Left", "Right", "Both", "Unspecified")
SCORE_RE = re.compile(r"^[+-]?(\d{1,2}(\.\d{0,2})?|\.\d{1,2})$")
SCORE_MIN = Decimal(-10)
SCORE_MAX = Decimal(10)
MAX_REGIONS = 20
REGION_OTHER_MAX = 100


def _blank(value) -> bool:
    return value is None or str(value).strip() == ""


def _parse_score(value, label: str):
    """Return (Decimal | None, error message | None) for one typed score."""
    if _blank(value):
        return None, None
    token = str(value).strip()
    message = f"{label} score must be a number from -10 to 10, with at most 2 decimal places"
    if not SCORE_RE.match(token):
        return None, message
    try:
        score = Decimal(token)
    except InvalidOperation:
        return None, message
    if score < SCORE_MIN or score > SCORE_MAX:
        return None, message
    return score, None


def parse_review(data: dict) -> list:
    """Validate a scans review body; return the region entries to store.

    Every field problem is collected before raising, so the form can flag
    all of them at once.
    """
    regions = data.get("regions")
    if not isinstance(regions, list) or not regions:
        raise ReviewValidationError("At least one body region is required")
    if len(regions) > MAX_REGIONS:
        raise ReviewValidationError(f"Too many body regions (max {MAX_REGIONS})")

    entries, fields = [], []

    def problem(index, field, message):
        fields.append({"index": index, "field": field, "message": message})

    for index, raw in enumerate(regions):
        raw = raw if isinstance(raw, dict) else {}
        region = str(raw.get("region") or "").strip()
        if region not in SCANS_REGIONS:
            problem(index, "region", "Unknown body region")

        region_other = None
        if region == "Other":
            region_other = str(raw.get("region_other") or "").strip()
            if not region_other or len(region_other) > REGION_OTHER_MAX:
                problem(index, "region_other", "Please specify the body region")

        laterality = None
        if not _blank(raw.get("laterality")):
            laterality = str(raw.get("laterality")).strip()
            if laterality not in SCANS_LATERALITIES:
                problem(index, "laterality", "Unknown laterality")

        t_score, t_error = _parse_score(raw.get("t_score"), "T")
        if t_error:
            problem(index, "t_score", t_error)
        z_score, z_error = _parse_score(raw.get("z_score"), "Z")
        if z_error:
            problem(index, "z_score", z_error)

        entries.append({
            "position": index + 1,
            "region": region,
            "region_other": region_other,
            "laterality": laterality,
            "t_score": t_score,
            "z_score": z_score,
        })

    if fields:
        raise ReviewValidationError("Invalid review", fields)
    return entries


def add_review(session, event_id: int, reviewer_id: int, parsed: list) -> None:
    """Add one `reviews` row and its `review_regions` rows (no commit)."""
    review = Reviews(event_id=event_id, reviewer_id=reviewer_id)
    review.regions = [ReviewRegions(**entry) for entry in parsed]
    session.add(review)


# --- Schema check -------------------------------------------------------------


_MIGRATION_HINT = (
    "Review tables are not migrated for this study; "
    "see init/migrations/012-scans-reviews.sh"
)


def check_schema(session) -> Optional[str]:
    """Detect a scans database whose `reviews` table is still the MI shape.

    An existing scans deployment upgraded without running
    init/migrations/012-scans-reviews.sh still has the MI `reviews` table
    (research D10). Writing to it would fail with an obscure database error.
    Instead, the endpoints report this message.
    """
    columns = set(session.execute(text(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS "
        "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reviews'"
    )).scalars().all())
    if "mci" in columns or "created" not in columns:
        return _MIGRATION_HINT
    return None


# --- Retrieval and export -------------------------------------------------------


def _score_str(value) -> Optional[str]:
    return None if value is None else format(value, "f")


def get_reviews(session, event_id: int) -> list:
    """Return this event's scans reviews with their regions, in entry order."""
    rows = session.execute(text(
        """
        SELECT rv.id AS review_id, rv.reviewer_id,
               CASE WHEN rv.reviewer_id = e.reviewer2_id THEN e.review2_date
                    ELSE e.review1_date END AS review_date,
               rr.position, rr.region, rr.region_other, rr.laterality,
               rr.t_score, rr.z_score
          FROM reviews rv
          JOIN events e ON e.id = rv.event_id
          LEFT JOIN review_regions rr ON rr.review_id = rv.id
         WHERE rv.event_id = :event_id
         ORDER BY rv.id, rr.position
        """
    ), {"event_id": event_id}).mappings().all()

    reviews, by_id = [], {}
    for r in rows:
        review = by_id.get(r["review_id"])
        if review is None:
            date = r["review_date"]
            review = {
                "reviewer_id": r["reviewer_id"],
                "review_date": date.isoformat() if date is not None else None,
                "regions": [],
            }
            by_id[r["review_id"]] = review
            reviews.append(review)
        if r["position"] is not None:
            review["regions"].append({
                "position": r["position"],
                "region": r["region"],
                "region_other": r["region_other"],
                "laterality": r["laterality"],
                "t_score": _score_str(r["t_score"]),
                "z_score": _score_str(r["z_score"]),
            })
    return reviews


EXPORT_HEADINGS = [
    "Event ID", "Patient ID", "Site", "Site Patient ID", "Event Date", "Status",
    "Reviewer", "Review Date", "Position", "Region", "Region (other)",
    "Laterality", "T score", "Z score",
]

_EXPORT_SQL = """
    SELECT e.id AS event_id, e.patient_id, p.site, p.site_patient_id,
           e.event_date, e.status,
           r1.username AS reviewer, e.review1_date AS review_date,
           rr.position, rr.region, rr.region_other, rr.laterality,
           rr.t_score, rr.z_score
      FROM events e
      LEFT JOIN patients_view p ON p.id = e.patient_id
      LEFT JOIN users r1 ON r1.id = e.reviewer1_id
      LEFT JOIN reviews rv ON rv.event_id = e.id AND rv.reviewer_id = e.reviewer1_id
      LEFT JOIN review_regions rr ON rr.review_id = rv.id
     ORDER BY e.id, rr.position
"""


def _export_rows(session) -> list:
    """One row per region entry; one row with empty review cells per event
    that has no review."""
    return [dict(r) for r in session.execute(text(_EXPORT_SQL)).mappings().all()]


def export_csv(session, writer) -> None:
    """Write the scans events export (one row per region entry) to `writer`."""
    writer.writerow(EXPORT_HEADINGS)
    for r in _export_rows(session):
        writer.writerow([
            "" if r.get(key) is None else (
                format(r[key], "f") if isinstance(r[key], Decimal) else r[key]
            )
            for key in (
                "event_id", "patient_id", "site", "site_patient_id", "event_date",
                "status", "reviewer", "review_date", "position", "region",
                "region_other", "laterality", "t_score", "z_score",
            )
        ])
