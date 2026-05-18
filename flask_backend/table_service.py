from types import SimpleNamespace
from typing import Optional
from sqlalchemy import text
import logging
import datetime
import re

logger = logging.getLogger(__name__)

try:
    from . import models  # type: ignore
except Exception:  # pragma: no cover - models may not be importable during tests
    models = SimpleNamespace(get_session=lambda: None)


class ValidationError(Exception):
    """Raised when inputs are invalid or required resources are unavailable."""


def get_session():
    """Lazily create a new SQLAlchemy session."""
    return models.get_session()


def _derive_date_like(q: Optional[str]) -> Optional[str]:
    """Return a LIKE pattern for ISO date (YYYY-MM-DD) if q looks like a date.

    Accepts common inputs:
    - YYYY-MM-DD
    - YYYY/MM/DD
    - YYYYMMDD
    - MM/DD/YYYY or M/D/YYYY
    - MM-DD-YYYY or M-D-YYYY
    - M/D/YY or MM/DD/YY (assumes 20YY)
    """
    if not q:
        return None
    s = q.strip()
    # Try compact 8-digit YYYYMMDD
    if re.fullmatch(r"\d{8}", s):
        try:
            dt = datetime.date(int(s[0:4]), int(s[4:6]), int(s[6:8]))
            return f"%{dt.strftime('%Y-%m-%d')}%"
        except Exception:
            return None
    # Normalize separators to '-'
    s_norm = s.replace('/', '-').strip()
    # Try YYYY-MM-DD
    try:
        dt = datetime.date.fromisoformat(s_norm)
        return f"%{dt.strftime('%Y-%m-%d')}%"
    except Exception:
        pass
    # Try MM-DD-YYYY
    for fmt in ("%m-%d-%Y", "%m-%d-%y"):
        try:
            dt = datetime.datetime.strptime(s_norm, fmt).date()
            # Assume 20YY for 2-digit years where needed (strptime already handles century logic)
            return f"%{dt.strftime('%Y-%m-%d')}%"
        except Exception:
            continue
    # Try with '/' formats
    for fmt in ("%m/%d/%Y", "%m/%d/%y"):
        try:
            dt = datetime.datetime.strptime(s, fmt).date()
            return f"%{dt.strftime('%Y-%m-%d')}%"
        except Exception:
            continue
    return None


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


def get_events_by_status_with_total(
    status: str,
    limit: Optional[int] = None,
    offset: int = 0,
    q: Optional[str] = None,
    site: Optional[str] = None,
    sort_by: Optional[str] = None,
    sort_dir: Optional[str] = None,
):
    """Return (rows, total) for events filtered by status, with friendly columns.

    Friendly columns: ID, Date, Created, Uploaded, Scrubbed, Criteria, Site.
    Supports text search (q) across id, event_date, site_patient_id, and criteria name/value,
    and site filtering.
    """
    logger.debug(
        "Fetching %sevents with status %s starting at %d",
        f"up to {limit} " if limit is not None else "all ",
        status,
        offset,
    )
    session = get_session()
    like = f"%{q}%" if q else None
    like_date = _derive_date_like(q)
    where = ["e.status = :status"]
    params = {"status": status}
    if site:
        where.append("p.site = :site")
        params["site"] = site
    if q:
        where.append(
            "(CAST(e.id AS CHAR) LIKE :like "
            "OR e.event_date LIKE :like "
            "OR e.add_date LIKE :like "
            "OR e.upload_date LIKE :like "
            "OR e.scrub_date LIKE :like "
            "OR p.site LIKE :like "
            "OR p.site_patient_id LIKE :like "
            "OR EXISTS (SELECT 1 FROM criterias c2 WHERE c2.event_id = e.id AND (c2.name LIKE :like OR c2.value LIKE :like)))"
        )
        params["like"] = like
        if like_date:
            where.append(
                "(e.event_date LIKE :like_date OR e.add_date LIKE :like_date OR e.upload_date LIKE :like_date OR e.scrub_date LIKE :like_date)"
            )
            params["like_date"] = like_date

    where_sql = " AND ".join(where)

    query = (
        "SELECT e.id AS `ID`, "
        "e.event_date AS `Date`, "
        "e.add_date AS `Created`, "
        "e.upload_date AS `Uploaded`, "
        "e.scrub_date AS `Scrubbed`, "
        "GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') AS `Criteria`, "
        "p.site AS `Site` "
        "FROM events e "
        "LEFT JOIN patients_view p ON e.patient_id = p.id "
        "LEFT JOIN criterias c ON e.id = c.event_id "
        f"WHERE {where_sql} "
        "GROUP BY e.id, e.event_date, e.add_date, e.upload_date, e.scrub_date, p.site "
    )
    # Apply optional ORDER BY from whitelisted columns
    order_clause = None
    col_map = {
        'ID': 'e.id',
        'Date': 'e.event_date',
        'Created': 'e.add_date',
        'Uploaded': 'e.upload_date',
        'Scrubbed': 'e.scrub_date',
        'Site': 'p.site',
    }
    dir_sql = 'ASC' if (str(sort_dir or '').lower() == 'asc') else 'DESC'
    if sort_by and sort_by in col_map and (sort_dir or '').lower() in {'asc', 'desc'}:
        order_clause = f"ORDER BY {col_map[sort_by]} {dir_sql}, e.id ASC"
    if order_clause:
        query += order_clause + " "
    if limit is not None:
        query += " LIMIT :limit OFFSET :offset"
        params.update({"limit": limit, "offset": offset})
    elif offset:
        query += " LIMIT 18446744073709551615 OFFSET :offset"
        params["offset"] = offset
    rows = session.execute(text(query), params).mappings().all()

    count_q = text(
        "SELECT COUNT(DISTINCT e.id) FROM events e LEFT JOIN patients_view p ON e.patient_id = p.id "
        f"WHERE {where_sql}"
    )
    total = session.execute(count_q, params).scalar() or 0
    logger.debug("Fetched %d/%d events with status %s", len(rows), total, status)
    session.close()
    return [dict(r) for r in rows], int(total)


def get_events_by_status(status: str, limit: Optional[int] = None, offset: int = 0):
    rows, _total = get_events_by_status_with_total(status, limit, offset)
    return rows


def get_events_need_packets(limit: Optional[int] = None, offset: int = 0, site: Optional[str] = None):
    """Return events that still require packet uploads for the uploader's site.

    Legacy behavior: events joined to patients_view where p.site = <uploader site>
    and events.status = 'created', ordered by events.id ASC.
    """
    # Reuse the more general helper but enforce site filter and ordering by ID ASC
    rows, _total = get_events_by_status_with_total(
        status="created",
        limit=limit,
        offset=offset,
        q=None,
        site=site,
        sort_by='ID',
        sort_dir='asc',
    )
    return rows


def get_events_for_review(limit: Optional[int] = None, offset: int = 0):
    """Return events with packets ready for reviewer action.

    Align with main branch behavior: events are considered ready for review
    when they have been sent to reviewers. This corresponds to status 'sent'.
    """
    return get_events_by_status("sent", limit, offset)


def get_events_for_reupload(limit: Optional[int] = None, offset: int = 0, site: Optional[str] = None):
    """Return events eligible for re-upload by the uploader's site.

    Align with main branch: rows are events at the uploader's site with
    status 'uploaded', ordered by ID ascending.
    """
    rows, _total = get_events_by_status_with_total(
        status="uploaded",
        limit=limit,
        offset=offset,
        q=None,
        site=site,
        sort_by='ID',
        sort_dir='asc',
    )
    return rows


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
    """Return events with site info from `patients_view`."""
    logger.debug(
        "Fetching %sevents with patient site information starting at %d",
        f"up to {limit} " if limit is not None else "all ",
        offset,
    )
    session = get_session()
    try:
        stmt = (
            "SELECT e.id, e.patient_id, p.site "
            "FROM events e LEFT JOIN patients_view p ON p.id = e.patient_id"
        )
        params = {}
        if limit is not None:
            stmt += " LIMIT :limit OFFSET :offset"
            params.update({"limit": limit, "offset": offset})
        elif offset:
            stmt += " LIMIT 18446744073709551615 OFFSET :offset"
            params["offset"] = offset
        rows = session.execute(text(stmt), params).mappings().all()
        return [dict(r) for r in rows]
    finally:
        session.close()


def get_events_with_patient_site_with_total(
    limit: Optional[int] = None,
    offset: int = 0,
    q: Optional[str] = None,
    site: Optional[str] = None,
    sort_by: Optional[str] = None,
    sort_dir: Optional[str] = None,
):
    """Return (rows, total) for events with patient site, with optional filtering."""
    session = get_session()
    try:
        like = f"%{q}%" if q else None
        like_date = _derive_date_like(q)
        where = ["1=1"]
        params = {}
        if site:
            where.append("p.site = :site")
            params["site"] = site
        if q:
            where.append(
                "(CAST(e.id AS CHAR) LIKE :like OR CAST(e.patient_id AS CHAR) LIKE :like OR e.event_date LIKE :like OR p.site_patient_id LIKE :like OR EXISTS (SELECT 1 FROM criterias c2 WHERE c2.event_id = e.id AND (c2.name LIKE :like OR c2.value LIKE :like)))"
            )
            params["like"] = like
            if like_date:
                where.append("(e.event_date LIKE :like_date)")
                params["like_date"] = like_date
        where_sql = " AND ".join(where)

        stmt = (
            "SELECT e.id, e.patient_id, p.site FROM events e LEFT JOIN patients_view p ON e.patient_id = p.id "
            f"WHERE {where_sql} "
        )
        # Optional ORDER BY
        # Only allow known columns to avoid SQL injection
        col_map = {
            'id': 'e.id',
            'patient_id': 'e.patient_id',
            'site': 'p.site',
            'event_date': 'e.event_date',
            'add_date': 'e.add_date',
            'upload_date': 'e.upload_date',
            'scrub_date': 'e.scrub_date',
        }
        if sort_by and sort_by in col_map and (str(sort_dir or '').lower() in {'asc', 'desc'}):
            dir_sql = 'ASC' if str(sort_dir).lower() == 'asc' else 'DESC'
            stmt += f"ORDER BY {col_map[sort_by]} {dir_sql}, e.id ASC "
        if limit is not None:
            stmt += " LIMIT :limit OFFSET :offset"
            params.update({"limit": limit, "offset": offset})
        elif offset:
            stmt += " LIMIT 18446744073709551615 OFFSET :offset"
            params["offset"] = offset
        rows = session.execute(text(stmt), params).mappings().all()
        rows = [dict(r) for r in rows]

        count_q = text(
            "SELECT COUNT(*) FROM events e LEFT JOIN patients_view p ON e.patient_id = p.id "
            f"WHERE {where_sql}"
        )
        total = session.execute(count_q, params).scalar() or 0
        return rows, int(total)
    finally:
        session.close()


def _phase_rows_with_total(
    where_clause: str,
    params: dict,
    limit: Optional[int],
    offset: int,
    q: Optional[str],
    site: Optional[str],
    order_by: Optional[str] = None,
    sort_by: Optional[str] = None,
    sort_dir: Optional[str] = None,
):
    like = f"%{q}%" if q else None
    like_date = _derive_date_like(q)
    filt = []
    if site:
        filt.append("p.site = :site")
        params["site"] = site
    if q:
        filt.append(
            "(CAST(e.id AS CHAR) LIKE :like "
            "OR CAST(e.patient_id AS CHAR) LIKE :like "
            "OR e.event_date LIKE :like "
            "OR e.add_date LIKE :like "
            "OR e.upload_date LIKE :like "
            "OR e.scrub_date LIKE :like "
            "OR p.site LIKE :like "
            "OR p.site_patient_id LIKE :like "
            "OR EXISTS (SELECT 1 FROM criterias c2 WHERE c2.event_id = e.id AND (c2.name LIKE :like OR c2.value LIKE :like)))"
        )
        params["like"] = like
        if like_date:
            filt.append(
                "(e.event_date LIKE :like_date OR e.add_date LIKE :like_date OR e.upload_date LIKE :like_date OR e.scrub_date LIKE :like_date)"
            )
            params["like_date"] = like_date
    where_sql = where_clause
    if filt:
        where_sql = f"{where_clause} AND {' AND '.join(filt)}"

    session = get_session()
    try:
        query = (
            "SELECT e.id AS `ID`, e.event_date AS `Date`, e.add_date AS `Created`, "
            "e.upload_date AS `Uploaded`, e.scrub_date AS `Scrubbed`, "
            "GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') AS `Criteria`, p.site AS `Site` "
            "FROM events e LEFT JOIN patients_view p ON e.patient_id = p.id "
            "LEFT JOIN criterias c ON e.id = c.event_id "
            f"WHERE {where_sql} "
            "GROUP BY e.id, e.event_date, e.add_date, e.upload_date, e.scrub_date, p.site "
        )
        # Determine ORDER BY: explicit sort_by overrides default order_by
        dynamic_order = None
        col_map = {
            'ID': 'e.id',
            'Date': 'e.event_date',
            'Created': 'e.add_date',
            'Uploaded': 'e.upload_date',
            'Scrubbed': 'e.scrub_date',
            'Site': 'p.site',
        }
        dir_sql = 'ASC' if (str(sort_dir or '').lower() == 'asc') else 'DESC'
        if sort_by and sort_by in col_map and (sort_dir or '').lower() in {'asc', 'desc'}:
            dynamic_order = f"ORDER BY {col_map[sort_by]} {dir_sql}, e.id ASC"
        if dynamic_order:
            query += dynamic_order + " "
        elif order_by:
            query += order_by + " "
        if limit is not None:
            query += " LIMIT :limit OFFSET :offset"
            params.update({"limit": limit, "offset": offset})
        elif offset:
            query += " LIMIT 18446744073709551615 OFFSET :offset"
            params["offset"] = offset
        rows = session.execute(text(query), params).mappings().all()

        count_q = text(
            "SELECT COUNT(DISTINCT e.id) FROM events e LEFT JOIN patients_view p ON e.patient_id = p.id "
            f"WHERE {where_sql}"
        )
        total = session.execute(count_q, params).scalar() or 0
        return [dict(r) for r in rows], int(total)
    finally:
        session.close()


def get_to_be_scrubbed_with_total(limit: Optional[int], offset: int, q: Optional[str], site: Optional[str], sort_by: Optional[str] = None, sort_dir: Optional[str] = None):
    # Uploaded but not scrubbed
    return _phase_rows_with_total(
        "e.upload_date IS NOT NULL AND e.scrub_date IS NULL",
        {},
        limit,
        offset,
        q,
        site,
        "ORDER BY e.upload_date DESC, e.id ASC",
        sort_by,
        sort_dir,
    )


def get_to_be_screened_with_total(limit: Optional[int], offset: int, q: Optional[str], site: Optional[str], sort_by: Optional[str] = None, sort_dir: Optional[str] = None):
    # Scrubbed but not screened
    return _phase_rows_with_total(
        "e.scrub_date IS NOT NULL AND e.screen_date IS NULL",
        {},
        limit,
        offset,
        q,
        site,
        "ORDER BY e.scrub_date DESC, e.id ASC",
        sort_by,
        sort_dir,
    )


def get_to_be_assigned_with_total(limit: Optional[int], offset: int, q: Optional[str], site: Optional[str], sort_by: Optional[str] = None, sort_dir: Optional[str] = None):
    # Screened but not assigned
    return _phase_rows_with_total(
        "e.screen_date IS NOT NULL AND e.assign_date IS NULL",
        {},
        limit,
        offset,
        q,
        site,
        None,
        sort_by,
        sort_dir,
    )


def get_to_be_sent_with_total(limit: Optional[int], offset: int, q: Optional[str], site: Optional[str], sort_by: Optional[str] = None, sort_dir: Optional[str] = None):
    # Assigned but not sent
    return _phase_rows_with_total(
        "e.assign_date IS NOT NULL AND e.send_date IS NULL",
        {},
        limit,
        offset,
        q,
        site,
        None,
        sort_by,
        sort_dir,
    )


def get_to_be_reviewed_with_total(limit: Optional[int], offset: int, q: Optional[str], site: Optional[str], sort_by: Optional[str] = None, sort_dir: Optional[str] = None):
    # Sent but not yet fully reviewed (at least one reviewer pending)
    return _phase_rows_with_total(
        "e.send_date IS NOT NULL AND (e.review1_date IS NULL OR e.review2_date IS NULL)",
        {},
        limit,
        offset,
        q,
        site,
        None,
        sort_by,
        sort_dir,
    )


def get_events_export_rows() -> list[dict]:
    """Return rows suitable for CSV export, with criteria pivots and user names."""
    session = get_session()
    try:
        query = text(
            """
            WITH crit AS (
              SELECT
                event_id,
                MAX(CASE WHEN LOWER(name) IN ('diagnosis','mi_dx','dx') THEN value END) AS mi_dx,
                MAX(CASE WHEN LOWER(name) = 'creatine kinase mb quotient' THEN value END) AS ckmb_q,
                MAX(CASE WHEN LOWER(name) = 'creatine kinase mb mass' THEN value END) AS ckmb_m,
                MAX(CASE WHEN LOWER(name) = 'ckmb' THEN value END) AS ckmb,
                MAX(CASE WHEN LOWER(name) IN ('troponin','troponin t','troponin i','trop_i','trop_t','troponin i (tni)','troponin t (tnt)') THEN value END) AS troponin,
                GROUP_CONCAT(CASE WHEN LOWER(name) NOT IN (
                    'diagnosis','mi_dx','dx','creatine kinase mb quotient','creatine kinase mb mass','ckmb','troponin','troponin t','troponin i','trop_i','trop_t','troponin i (tni)','troponin t (tnt)'
                ) THEN CONCAT(name, ':', value) END SEPARATOR ';') AS other
              FROM criterias
              GROUP BY event_id
            )
            SELECT
              e.id,
              e.patient_id,
              p.site_patient_id,
              p.site,
              e.event_date,
              e.status,
              cu.username AS creator,
              crit.mi_dx,
              crit.ckmb_q,
              crit.ckmb_m,
              crit.ckmb,
              crit.troponin,
              crit.other,
              e.add_date,
              uu.username AS uploader,
              e.upload_date,
              mk.username AS marker,
              e.no_packet_reason,
              e.two_attempts_flag,
              e.prior_event_date,
              e.prior_event_onsite_flag,
              e.other_cause,
              e.markNoPacket_date,
              sb.username AS scrubber,
              e.scrub_date,
              sc.username AS screener,
              e.screen_date,
              e.rescrub_message,
              e.reject_message,
              asn.username AS assigner,
              e.assign_date,
              snd.username AS sender,
              e.send_date,
              r1.username AS reviewer1,
              rv1.mci AS review1_mci,
              rv1.abnormal_ce_values_flag AS review1_abnormal_ce,
              rv1.ce_criteria AS review1_ce_criteria,
              rv1.chest_pain_flag AS review1_chest_pain,
              rv1.ecg_changes_flag AS review1_ecg_changes,
              rv1.lvm_by_imaging_flag AS review1_lvm,
              rv1.ci AS review1_ci,
              rv1.type AS review1_type,
              rv1.secondary_cause AS review1_secondary_cause,
              rv1.other_cause AS review1_other_cause,
              rv1.false_positive_flag AS review1_false_positive,
              rv1.false_positive_reason AS review1_false_positive_reason,
              rv1.false_positive_other_cause AS review1_false_positive_other_cause,
              rv1.current_tobacco_use_flag AS review1_current_tobacco,
              rv1.past_tobacco_use_flag AS review1_past_tobacco,
              rv1.cocaine_use_flag AS review1_cocaine,
              rv1.family_history_flag AS review1_family_history,
              e.review1_date,
              r2.username AS reviewer2,
              rv2.mci AS review2_mci,
              rv2.abnormal_ce_values_flag AS review2_abnormal_ce,
              rv2.ce_criteria AS review2_ce_criteria,
              rv2.chest_pain_flag AS review2_chest_pain,
              rv2.ecg_changes_flag AS review2_ecg_changes,
              rv2.lvm_by_imaging_flag AS review2_lvm,
              rv2.ci AS review2_ci,
              rv2.type AS review2_type,
              rv2.secondary_cause AS review2_secondary_cause,
              rv2.other_cause AS review2_other_cause,
              rv2.false_positive_flag AS review2_false_positive,
              rv2.false_positive_reason AS review2_false_positive_reason,
              rv2.false_positive_other_cause AS review2_false_positive_other_cause,
              rv2.current_tobacco_use_flag AS review2_current_tobacco,
              rv2.past_tobacco_use_flag AS review2_past_tobacco,
              rv2.cocaine_use_flag AS review2_cocaine,
              rv2.family_history_flag AS review2_family_history,
              e.review2_date,
              a3.username AS assigner3rd,
              e.assign3rd_date,
              r3.username AS reviewer3,
              rv3.mci AS review3_mci,
              rv3.abnormal_ce_values_flag AS review3_abnormal_ce,
              rv3.ce_criteria AS review3_ce_criteria,
              rv3.chest_pain_flag AS review3_chest_pain,
              rv3.ecg_changes_flag AS review3_ecg_changes,
              rv3.lvm_by_imaging_flag AS review3_lvm,
              rv3.ci AS review3_ci,
              rv3.type AS review3_type,
              rv3.secondary_cause AS review3_secondary_cause,
              rv3.other_cause AS review3_other_cause,
              rv3.false_positive_flag AS review3_false_positive,
              rv3.false_positive_reason AS review3_false_positive_reason,
              rv3.false_positive_other_cause AS review3_false_positive_other_cause,
              rv3.current_tobacco_use_flag AS review3_current_tobacco,
              rv3.past_tobacco_use_flag AS review3_past_tobacco,
              rv3.cocaine_use_flag AS review3_cocaine,
              rv3.family_history_flag AS review3_family_history,
              e.review3_date,
              edd.outcome AS overall_outcome,
              edd.primary_secondary AS overall_primary_secondary,
              edd.false_positive_event AS overall_false_positive_event,
              edd.secondary_cause AS overall_secondary_cause,
              edd.secondary_cause_other AS overall_secondary_cause_other,
              edd.false_positive_reason AS overall_false_positive_reason,
              edd.ci AS overall_ci
            FROM events e
            LEFT JOIN patients_view p ON p.id = e.patient_id
            LEFT JOIN crit ON crit.event_id = e.id
            LEFT JOIN users cu ON cu.id = e.creator_id
            LEFT JOIN users uu ON uu.id = e.uploader_id
            LEFT JOIN users mk ON mk.id = e.marker_id
            LEFT JOIN users sb ON sb.id = e.scrubber_id
            LEFT JOIN users sc ON sc.id = e.screener_id
            LEFT JOIN users asn ON asn.id = e.assigner_id
            LEFT JOIN users snd ON snd.id = e.sender_id
            LEFT JOIN users r1 ON r1.id = e.reviewer1_id
            LEFT JOIN users r2 ON r2.id = e.reviewer2_id
            LEFT JOIN users a3 ON a3.id = e.assigner3rd_id
            LEFT JOIN users r3 ON r3.id = e.reviewer3_id
            LEFT JOIN reviews rv1 ON rv1.event_id = e.id AND rv1.reviewer_id = e.reviewer1_id
            LEFT JOIN reviews rv2 ON rv2.event_id = e.id AND rv2.reviewer_id = e.reviewer2_id
            LEFT JOIN reviews rv3 ON rv3.event_id = e.id AND rv3.reviewer_id = e.reviewer3_id
            LEFT JOIN event_derived_datas edd ON edd.event_id = e.id
            """
        )
        rows = session.execute(query).mappings().all()
        return [dict(r) for r in rows]
    finally:
        session.close()


def get_events_awaiting_review(user_id: int, q: Optional[str] = None, site: Optional[str] = None) -> list[dict]:
    """Return events awaiting the logged-in reviewer's action.

    - Reviewer 1: assigned to user, sent, and not yet reviewed by reviewer 1
    - Reviewer 2: assigned to user, sent, and not yet reviewed by reviewer 2
    - Reviewer 3: assigned to user and status 'third_review_assigned' and not reviewed yet
    """
    session = get_session()
    try:
        like = f"%{q}%" if q else None
        like_date = _derive_date_like(q)
        # Enforce stricter criteria to align with main branch expectations:
        # - Slots 1 & 2 require status 'sent' (in addition to send_date)
        # - Optional site filter via patients_view.site
        # Match main behavior:
        # - reviewer1: status in ('sent','reviewer2_done') and review1_date is null
        # - reviewer2: status in ('sent','reviewer1_done') and review2_date is null
        # - reviewer3: status 'third_review_assigned' and review3_date is null
        where_parts = [
            "(e.reviewer1_id = :uid AND e.status IN ('sent','reviewer2_done') AND e.review1_date IS NULL)",
            "(e.reviewer2_id = :uid AND e.status IN ('sent','reviewer1_done') AND e.review2_date IS NULL)",
            "(e.reviewer3_id = :uid AND e.status = 'third_review_assigned' AND e.review3_date IS NULL)",
        ]
        params = {"uid": int(user_id)}
        where_sql = " OR ".join(where_parts)
        site_filter = ""
        if site:
            site_filter = " AND p.site = :site"
            params["site"] = site
        q_filter = ""
        if q:
            q_terms = ["CAST(e.id AS CHAR) LIKE :like", "e.event_date LIKE :like"]
            params["like"] = like
            if like_date:
                q_terms.append("e.event_date LIKE :like_date")
                params["like_date"] = like_date
            q_filter = " AND (" + " OR ".join(q_terms) + ")"
        stmt = text(
            "SELECT e.id AS id, e.event_date AS event_date, "
            "CASE "
            " WHEN (e.reviewer1_id = :uid AND e.status IN ('sent','reviewer2_done') AND e.review1_date IS NULL) THEN 1 "
            " WHEN (e.reviewer2_id = :uid AND e.status IN ('sent','reviewer1_done') AND e.review2_date IS NULL) THEN 2 "
            " WHEN (e.reviewer3_id = :uid AND e.status = 'third_review_assigned' AND e.review3_date IS NULL) THEN 3 "
            " ELSE NULL END AS slot "
            "FROM events e LEFT JOIN patients_view p ON p.id = e.patient_id "
            f"WHERE ({where_sql}){site_filter}{q_filter} "
            "ORDER BY e.id ASC"
        )
        rows = session.execute(stmt, params).mappings().all()
        return [dict(r) for r in rows]
    finally:
        session.close()


def assign_events(event_ids: list[int], reviewer_id: int, slot: str, assigner_id: int) -> dict:
    """Assign a reviewer to many events for the given slot (first|second|third).

    Updates reviewerN_id and corresponding assign date/assigner fields where applicable.
    Returns { updated: N }.
    """
    if slot not in {"first", "second", "third"}:
        raise ValidationError("slot must be one of: first, second, third")
    if not event_ids:
        return {"updated": 0}
    session = get_session()
    try:
        now = datetime.date.today()
        updated = 0
        events = (
            session.query(models.Events)
            .filter(models.Events.id.in_(event_ids))
            .all()
        )
        for e in events:
            if slot == "first":
                e.reviewer1_id = reviewer_id
                # keep an audit of who assigned
                e.assigner_id = assigner_id
                e.assign_date = now
            elif slot == "second":
                e.reviewer2_id = reviewer_id
                e.assigner_id = assigner_id
                e.assign_date = now
            else:  # third
                e.reviewer3_id = reviewer_id
                e.assigner3rd_id = assigner_id
                e.assign3rd_date = now
            updated += 1
        session.commit()
        # If assigning third reviewer, send third-reviewer emails now
        if slot == "third":
            try:
                from . import emailer  # type: ignore
                _ = emailer.send_third_reviewer_emails_for_event_ids([int(e.id) for e in events])
            except Exception:
                # Do not fail assignment on email errors
                pass
        return {"updated": updated}
    finally:
        session.close()


def send_events(event_ids: list[int], sender_id: int) -> dict:
    """Mark many events as sent and send assignment emails to reviewers.

    Returns a dict with DB update count and email sending summary.
    """
    if not event_ids:
        return {"updated": 0, "email": {"attempted": 0, "sent": 0, "skipped": 0, "errors": []}}
    session = get_session()
    try:
        now = datetime.date.today()
        updated = 0
        events = (
            session.query(models.Events)
            .filter(models.Events.id.in_(event_ids))
            .all()
        )
        for e in events:
            e.sender_id = sender_id
            e.send_date = now
            updated += 1
        session.commit()

        # Defer import to avoid circulars at module import time
        try:
            from . import emailer  # type: ignore
            email_result = emailer.send_assignment_emails_for_event_ids([int(e.id) for e in events])
        except Exception as exc:
            email_result = {"attempted": 0, "sent": 0, "skipped": 0, "errors": [str(exc)]}

        return {"updated": updated, "email": email_result}
    finally:
        session.close()

def get_event_details(event_id: int) -> dict:
    """Return a single event with related usernames and patient identifiers.

    Includes core dates, status, creator/uploader/screener/assigner/sender usernames,
    and patient `site_patient_id` and `site`.
    """
    session = get_session()
    try:
        query = text(
            """
            SELECT
              e.id AS id,
              e.patient_id AS patient_id,
              p.site_patient_id AS site_patient_id,
              p.site AS site,
              e.status AS status,
              e.add_date AS add_date,
              e.event_date AS event_date,
              e.upload_date AS upload_date,
              e.markNoPacket_date AS markNoPacket_date,
              e.scrub_date AS scrub_date,
              e.screen_date AS screen_date,
              e.assign_date AS assign_date,
              e.send_date AS send_date,
              e.review1_date AS review1_date,
              e.review2_date AS review2_date,
              e.assign3rd_date AS assign3rd_date,
              e.review3_date AS review3_date,
              e.rescrub_message AS rescrub_message,
              e.reject_message AS reject_message,
              cu.username AS creator_username,
              uu.username AS uploader_username,
              su.username AS scrubber_username,
              sc.username AS screener_username,
              au.username AS assigner_username,
              se.username AS sender_username,
              r1.username AS reviewer1_username,
              r2.username AS reviewer2_username,
              r3.username AS reviewer3_username
            FROM events e
            LEFT JOIN patients_view p ON p.id = e.patient_id
            LEFT JOIN users cu ON cu.id = e.creator_id
            LEFT JOIN users uu ON uu.id = e.uploader_id
            LEFT JOIN users su ON su.id = e.scrubber_id
            LEFT JOIN users sc ON sc.id = e.screener_id
            LEFT JOIN users au ON au.id = e.assigner_id
            LEFT JOIN users se ON se.id = e.sender_id
            LEFT JOIN users r1 ON r1.id = e.reviewer1_id
            LEFT JOIN users r2 ON r2.id = e.reviewer2_id
            LEFT JOIN users r3 ON r3.id = e.reviewer3_id
            WHERE e.id = :event_id
            """
        )
        row = session.execute(query, {"event_id": event_id}).mappings().first()
        return dict(row) if row else {}
    finally:
        session.close()

def create_event(data: dict) -> dict:
    """Create a new event and associated criteria.

    The patient identified by (site_patient_id, site) must already exist
    in `patients_view`. This application does not create patient records;
    both halves of the view (uw_patients2 and cnics_data.Patient) are
    treated as read-only.
    """
    session = get_session()
    try:
        site_patient_id = (data.get("site_patient_id") or "").strip()
        site = (data.get("site") or "").strip()
        if not site_patient_id:
            raise ValidationError("site_patient_id is required")
        if not site:
            raise ValidationError("site is required")
        patient = (
            session.query(models.PatientsView)
            .filter_by(site_patient_id=site_patient_id, site=site)
            .first()
        )
        if not patient:
            raise ValidationError(
                f"Patient not found in patients_view for site={site!r}, "
                f"site_patient_id={site_patient_id!r}"
            )
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
