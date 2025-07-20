from sqlalchemy import Table, select, func

from . import models


def get_session():
    """Lazily create a new SQLAlchemy session."""
    return models.get_session()

def get_table_data(name: str):
    """Return up to 100 rows from the specified table."""
    session = get_session()
    table = Table(name, models.Base.metadata, autoload_with=models.engine)
    stmt = select(table).limit(100)
    rows = session.execute(stmt).mappings().all()
    session.close()
    return rows


def _events_by_status(status: str):
    session = get_session()
    stmt = (
        select(
            models.Event.id.label("ID"),
            models.Event.patient_id.label("Patient ID"),
            models.Event.event_date.label("Date"),
            func.group_concat(models.Criteria.name).label("Criteria"),
        )
        .join(models.Criteria, models.Event.id == models.Criteria.event_id)
        .join(models.UwPatient2, models.Event.patient_id == models.UwPatient2.id)
        .where(models.Event.status == status)
        .group_by(models.Event.id)
        .limit(100)
    )
    rows = session.execute(stmt).mappings().all()
    session.close()
    return rows


def get_events_need_packets():
    """Return up to 100 events that still require packet uploads."""
    return _events_by_status("created")


def get_events_for_review():
    """Return up to 100 events with uploaded packets awaiting review."""
    return _events_by_status("uploaded")


def get_events_for_reupload():
    """Return up to 100 events that were rejected and need reupload."""
    return _events_by_status("rejected")


def get_event_status_summary():
    """Return a mapping of event status names to row counts."""
    session = get_session()
    stmt = select(models.Event.status, func.count().label("count")).group_by(models.Event.status)
    rows = session.execute(stmt).all()
    session.close()
    return {row[0]: row[1] for row in rows}
