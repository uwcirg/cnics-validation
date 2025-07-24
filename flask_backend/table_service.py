from types import SimpleNamespace
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


def get_table_data(name: str):
    """Return up to 100 rows from the specified table."""
    logger.debug("Fetching up to 100 rows from table %s", name)
    session = get_session()
    stmt = text(f"SELECT * FROM {name} LIMIT 100")
    rows = session.execute(stmt).mappings().all()
    logger.debug("Fetched %d rows from table %s", len(rows), name)
    session.close()
    return [dict(r) for r in rows]


def get_events_by_status(status: str):
    """Return up to 100 events filtered by status."""
    logger.debug("Fetching events with status %s", status)
    session = get_session()
    query = (
        "SELECT events.id AS `ID`, events.patient_id AS `Patient ID`, "
        "events.event_date AS `Date`, "
        "GROUP_CONCAT(criterias.name ORDER BY criterias.name SEPARATOR ', ') AS `Criteria` "
        "FROM events "
        "JOIN criterias ON events.id = criterias.event_id "
        "JOIN patients ON events.patient_id = patients.id "
        "WHERE events.status = :status "
        "GROUP BY events.id, events.patient_id, events.event_date LIMIT 100"
    )
    rows = session.execute(text(query), {"status": status}).mappings().all()
    logger.debug("Fetched %d events with status %s", len(rows), status)
    session.close()
    return [dict(r) for r in rows]


def get_events_need_packets():
    """Return up to 100 events that still require packet uploads."""
    return get_events_by_status("created")


def get_events_for_review():
    """Return up to 100 events with uploaded packets awaiting review."""
    return get_events_by_status("uploaded")


def get_events_for_reupload():
    """Return up to 100 events that were rejected and need reupload."""
    return get_events_by_status("rejected")


def get_event_status_summary():
    """Return a mapping of event status names to row counts."""
    logger.debug("Fetching event status summary")
    session = get_session()
    stmt = text("SELECT status, COUNT(*) AS count FROM events GROUP BY status")
    rows = session.execute(stmt).all()
    logger.debug("Fetched summary for %d statuses", len(rows))
    session.close()
    return {row[0]: row[1] for row in rows}


def get_events_with_patient_site():
    """Return events with site info from the external database."""
    logger.debug("Fetching events with patient site information")
    session = get_session()
    rows = session.execute(text("SELECT id, patient_id FROM events LIMIT 100")).mappings().all()
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
