from types import SimpleNamespace
from typing import Optional
from sqlalchemy import text, bindparam
import logging

logger = logging.getLogger(__name__)

try:
    from . import models  # type: ignore
except Exception:  # pragma: no cover - models may not be importable during tests
    models = SimpleNamespace(get_session=lambda: None, get_external_session=lambda: None)


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
    stmt = "SELECT id, patient_id FROM events"
    params = {}
    if limit is not None:
        stmt += " LIMIT :limit OFFSET :offset"
        params.update({"limit": limit, "offset": offset})
    elif offset:
        stmt += " LIMIT 18446744073709551615 OFFSET :offset"
        params["offset"] = offset
    rows = session.execute(text(stmt), params).mappings().all()
    session.close()
    rows = [dict(r) for r in rows]

    patient_ids = [row["patient_id"] for row in rows]
    ext_session = models.get_external_session()
    if patient_ids:
        stmt = text("SELECT id, site FROM patients WHERE id IN :ids").bindparams(
            bindparam("ids", expanding=True)
        )
        ext_rows = ext_session.execute(stmt, {"ids": patient_ids}).mappings().all()
        ext_rows = [dict(r) for r in ext_rows]
        logger.debug("Fetched site info for %d patients", len(ext_rows))
    else:
        ext_rows = []
    ext_session.close()

    lookup = {r["id"]: r["site"] for r in ext_rows}
    for r in rows:
        r["site"] = lookup.get(r["patient_id"])
    return rows


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
