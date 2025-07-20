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


def get_events_need_packets():
    """Return up to 100 events that still require packet uploads."""
    conn = get_pool().get_connection()
    cursor = conn.cursor(dictionary=True)
    query = (
        "SELECT e.id AS `ID`, e.patient_id AS `Patient ID`, "
        "e.event_date AS `Date`, "
        "GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') AS `Criteria` "
        "FROM events e "
        "JOIN criterias c ON e.id = c.event_id "
        "JOIN patients_view p ON e.patient_id = p.id "
        "WHERE e.status = 'created' "
        "GROUP BY e.id LIMIT 100"

    )
    rows = session.execute(stmt).mappings().all()
    session.close()
    return rows


def get_events_need_packets():
    """Return up to 100 events that still require packet uploads."""
    return _events_by_status("created")


def get_events_for_review():
    """Return up to 100 events with uploaded packets awaiting review."""
    conn = get_pool().get_connection()
    cursor = conn.cursor(dictionary=True)
    query = (
        "SELECT e.id AS `ID`, e.patient_id AS `Patient ID`, "
        "e.event_date AS `Date`, "
        "GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') AS `Criteria` "
        "FROM events e "
        "JOIN criterias c ON e.id = c.event_id "
        "JOIN patients_view p ON e.patient_id = p.id "
        "WHERE e.status = 'uploaded' "
        "GROUP BY e.id LIMIT 100"
    )
    cursor.execute(query)
    rows = cursor.fetchall()
    cursor.close()
    conn.close()
    return rows



def get_events_for_reupload():
    """Return up to 100 events that were rejected and need reupload."""
    conn = get_pool().get_connection()
    cursor = conn.cursor(dictionary=True)
    query = (
        "SELECT e.id AS `ID`, e.patient_id AS `Patient ID`, "
        "e.event_date AS `Date`, "
        "GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') AS `Criteria` "
        "FROM events e "
        "JOIN criterias c ON e.id = c.event_id "
        "JOIN patients_view p ON e.patient_id = p.id "
        "WHERE e.status = 'rejected' "
        "GROUP BY e.id LIMIT 100"
    )
    cursor.execute(query)
    rows = cursor.fetchall()
    cursor.close()
    conn.close()
    return rows


def get_event_status_summary():
    """Return a mapping of event status names to row counts."""
    session = get_session()
    stmt = select(models.Event.status, func.count().label("count")).group_by(models.Event.status)
    rows = session.execute(stmt).all()
    session.close()
    return {row[0]: row[1] for row in rows}
