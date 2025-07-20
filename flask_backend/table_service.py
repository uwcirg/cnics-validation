from types import SimpleNamespace
from sqlalchemy import text

try:
    from . import models  # type: ignore
except Exception:  # pragma: no cover - models may not be importable during tests
    models = SimpleNamespace(get_session=lambda: None)


def get_session():
    """Lazily create a new SQLAlchemy session."""
    return models.get_session()


def get_table_data(name: str):
    """Return up to 100 rows from the specified table."""
    session = get_session()
    stmt = text(f"SELECT * FROM {name} LIMIT 100")
    rows = session.execute(stmt).mappings().all()
    session.close()
    return rows


def get_events_by_status(status: str):
    """Return up to 100 events filtered by status."""
    session = get_session()
    query = (
        "SELECT events.id AS `ID`, events.patient_id AS `Patient ID`, "
        "events.event_date AS `Date`, "
        "GROUP_CONCAT(criterias.name ORDER BY criterias.name SEPARATOR ', ') AS `Criteria` "
        "FROM events "
        "JOIN criterias ON events.id = criterias.event_id "
        "JOIN patients_view ON events.patient_id = patients_view.id "
        "WHERE events.status = :status "
        "GROUP BY events.id LIMIT 100"
    )
    rows = session.execute(text(query), {"status": status}).mappings().all()
    session.close()
    return rows


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
    session = get_session()
    stmt = text("SELECT status, COUNT(*) AS count FROM events GROUP BY status")
    rows = session.execute(stmt).all()
    session.close()
    return {row[0]: row[1] for row in rows}
