from types import SimpleNamespace
from typing import Optional
from sqlalchemy import text, bindparam
import logging
import datetime

logger = logging.getLogger(__name__)

try:
    from . import models  # type: ignore
except Exception:  # pragma: no cover - models may not be importable during tests
    models = SimpleNamespace(get_session=lambda: None, get_external_session=lambda: None)


class ValidationError(Exception):
    """Raised when inputs are invalid or required resources are unavailable."""


def _get_external_session_or_none():
    """Return an external DB session if configured, else None.

    This allows the service layer to gracefully fall back to the primary
    database for patient lookups/creation when the external DB URL is not
    configured or the external DB is unavailable.
    """
    try:
        return models.get_external_session()
    except Exception as exc:  # pragma: no cover - depends on env config
        logger.warning(
            "External DB not available; falling back to primary DB for patients: %s",
            exc,
        )
        return None


def get_session():
    """Lazily create a new SQLAlchemy session."""
    return models.get_session()


def get_table_data(name: str, limit: Optional[int] = None, offset: int = 0):
    """Return rows from ``name`` with optional ``limit`` and ``offset``."""
    logger.debug(
        "Fetching %srows from table %s starting at %d",
        f"up to {limit} " if limit is not None else "all ",
        name,
        offset,
    )
    session = get_session()
    stmt = f"SELECT * FROM {name}"
    params = {}
    if limit is not None:
        stmt += " LIMIT :limit"
        params["limit"] = limit
        stmt += " OFFSET :offset"
        params["offset"] = offset
    elif offset:
        stmt += " LIMIT 18446744073709551615 OFFSET :offset"
        params["offset"] = offset
    rows = session.execute(text(stmt), params).mappings().all()
    logger.debug("Fetched %d rows from table %s", len(rows), name)
    session.close()
    return [dict(r) for r in rows]


def get_events_by_status(status: str, limit: Optional[int] = None, offset: int = 0):
    """Return events filtered by status with optional pagination."""
    logger.debug(
        "Fetching %sevents with status %s starting at %d",
        f"up to {limit} " if limit is not None else "all ",
        status,
        offset,
    )
    session = get_session()
    query = (
        "SELECT events.id AS `ID`, events.patient_id AS `Patient ID`, "
        "events.event_date AS `Date`, "
        "GROUP_CONCAT(criterias.name ORDER BY criterias.name SEPARATOR ', ') AS `Criteria` "
        "FROM events "
        "JOIN criterias ON events.id = criterias.event_id "
        "JOIN patients ON events.patient_id = patients.id "
        "WHERE events.status = :status "
        "GROUP BY events.id, events.patient_id, events.event_date"
    )
    params = {"status": status}
    if limit is not None:
        query += " LIMIT :limit OFFSET :offset"
        params.update({"limit": limit, "offset": offset})
    elif offset:
        query += " LIMIT 18446744073709551615 OFFSET :offset"
        params["offset"] = offset
    rows = session.execute(text(query), params).mappings().all()
    logger.debug("Fetched %d events with status %s", len(rows), status)
    session.close()
    return [dict(r) for r in rows]


def get_events_need_packets(limit: Optional[int] = None, offset: int = 0):
    """Return events that still require packet uploads."""
    return get_events_by_status("created", limit, offset)


def get_events_for_review(limit: Optional[int] = None, offset: int = 0):
    """Return events with uploaded packets awaiting review."""
    return get_events_by_status("uploaded", limit, offset)


def get_events_for_reupload(limit: Optional[int] = None, offset: int = 0):
    """Return events that were rejected and need reupload."""
    return get_events_by_status("rejected", limit, offset)


def get_event_status_summary():
    """Return a mapping of event status names to row counts."""
    logger.debug("Fetching event status summary")
    session = get_session()
    stmt = text("SELECT status, COUNT(*) AS count FROM events GROUP BY status")
    rows = session.execute(stmt).all()
    logger.debug("Fetched summary for %d statuses", len(rows))
    session.close()
    return {row[0]: row[1] for row in rows}


def get_events_with_patient_site(limit: Optional[int] = None, offset: int = 0):
    """Return events with site info from the external database."""
    logger.debug(
        "Fetching %sevents with patient site information starting at %d",
        f"up to {limit} " if limit is not None else "all ",
        offset,
    )
    session = get_session()
    try:
        stmt = "SELECT id, patient_id FROM events"
        params = {}
        if limit is not None:
            stmt += " LIMIT :limit OFFSET :offset"
            params.update({"limit": limit, "offset": offset})
        elif offset:
            stmt += " LIMIT 18446744073709551615 OFFSET :offset"
            params["offset"] = offset
        rows = session.execute(text(stmt), params).mappings().all()
        rows = [dict(r) for r in rows]

        patient_ids = [row["patient_id"] for row in rows]
        ext_session = _get_external_session_or_none()
        if patient_ids:
            stmt = text("SELECT id, site FROM patients WHERE id IN :ids").bindparams(
                bindparam("ids", expanding=True)
            )
            if ext_session is not None:
                ext_rows = ext_session.execute(stmt, {"ids": patient_ids}).mappings().all()
                ext_rows = [dict(r) for r in ext_rows]
            else:
                ext_rows = session.execute(stmt, {"ids": patient_ids}).mappings().all()
                ext_rows = [dict(r) for r in ext_rows]
            logger.debug("Fetched site info for %d patients", len(ext_rows))
        else:
            ext_rows = []
        if ext_session is not None:
            ext_session.close()

        lookup = {r["id"]: r["site"] for r in ext_rows}
        for r in rows:
            r["site"] = lookup.get(r["patient_id"]) 
        return rows
    finally:
        session.close()


def create_event(data: dict) -> dict:
    """Create a new event and associated criteria."""
    session = get_session()
    ext_session = _get_external_session_or_none()
    patients_session = ext_session or session
    try:
        site_patient_id = (data.get("site_patient_id") or "").strip()
        site = (data.get("site") or "").strip()
        if not site_patient_id:
            raise ValidationError("site_patient_id is required")
        if not site:
            raise ValidationError("site is required")
        patient = (
            patients_session.query(models.Patients)
            .filter_by(site_patient_id=site_patient_id, site=site)
            .first()
        )
        if not patient:
            if ext_session is None:
                # Primary DB's patients table is not designed for writes; without
                # the external DB, we cannot create a new patient safely.
                raise ValidationError(
                    "Patient not found and external patient DB is unavailable"
                )
            patient = models.Patients(site_patient_id=site_patient_id, site=site)
            patients_session.add(patient)
            patients_session.commit()
        patient_id = patient.id

        event_date_str = (data.get("event_date") or "").strip()
        if not event_date_str:
            raise ValidationError("event_date is required (YYYY-MM-DD)")
        try:
            event_date = datetime.date.fromisoformat(event_date_str)
        except ValueError:
            raise ValidationError("event_date must be in YYYY-MM-DD format")

        event = models.Events(
            patient_id=patient_id,
            creator_id=data.get("creator_id", 1),
            event_date=event_date,
            add_date=datetime.date.today(),
        )
        session.add(event)
        session.commit()

        if data.get("criterion_name") and data.get("criterion_value"):
            crit = models.Criterias(
                event_id=event.id,
                name=data["criterion_name"],
                value=data["criterion_value"],
            )
            session.add(crit)
            session.commit()

        result = {
            "id": event.id,
            "patient_id": patient_id,
            "event_date": event.event_date.isoformat() if event.event_date else None,
        }
        return result
    finally:
        session.close()
        if ext_session is not None:
            ext_session.close()


def create_user(data: dict) -> dict:
    """Create a new user record and return the saved fields."""
    session = get_session()
    user = models.Users(
        username=data.get("username"),
        login=data.get("login"),
        first_name=data.get("first_name"),
        last_name=data.get("last_name"),
        site=data.get("site"),
        uploader_flag=1 if data.get("uploader") else 0,
        reviewer_flag=1 if data.get("reviewer") else 0,
        third_reviewer_flag=1 if data.get("third_reviewer") else 0,
        admin_flag=1 if data.get("admin") else 0,
    )
    session.add(user)
    session.commit()
    result = {
        "id": user.id,
        "username": user.username,
        "login": user.login,
        "first_name": user.first_name,
        "last_name": user.last_name,
        "site": user.site,
        "uploader_flag": user.uploader_flag,
        "reviewer_flag": user.reviewer_flag,
        "third_reviewer_flag": user.third_reviewer_flag,
        "admin_flag": user.admin_flag,
    }
    session.close()
    return result
