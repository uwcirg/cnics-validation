"""Study module for STUDY_TYPE=mci (MI / myocardial infarction).

Holds the MI ``reviews`` and ``event_derived_datas`` models and the MI review
form's server side. The models and the code below were moved verbatim from
``flask_backend/models.py``, ``app.py`` and ``table_service.py`` by spec 012;
behaviour is unchanged. The interface is documented in
``flask_backend/models/studies/__init__.py``.

``mci`` is also the fallback module for any study that has no usable module
of its own (see ``get_study_module``), matching the MI ``reviews`` table that
every deployment received before spec 012.

DDL: ``init/studies/mci.sql``.
"""

import datetime
from typing import Optional

from sqlalchemy import Enum, String, text
from sqlalchemy.dialects.mysql import INTEGER, TINYINT
from sqlalchemy.orm import DeclarativeBase, Mapped, mapped_column

from . import ReviewValidationError

REVIEW_FORM = "mci"

# Tables `/api/tables/<name>` refuses to non-admins (spec 012 FR-028).
REVIEW_TABLES = ("reviews", "event_derived_datas")


class MciBase(DeclarativeBase):
    """Declarative base for MI study tables (own metadata; see package docs)."""


class EventDerivedDatas(MciBase):
    __tablename__ = 'event_derived_datas'

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True)
    event_id: Mapped[int] = mapped_column(INTEGER(11), comment='foreign key into events table')
    outcome: Mapped[Optional[str]] = mapped_column(Enum('Definite', 'Probable', 'No', 'No [resuscitated cardiac arrest]'))
    primary_secondary: Mapped[Optional[str]] = mapped_column(Enum('Primary', 'Secondary'))
    false_positive_event: Mapped[Optional[int]] = mapped_column(TINYINT(1))
    secondary_cause: Mapped[Optional[str]] = mapped_column(Enum('MVA', 'Overdose', 'Anaphlaxis', 'GI bleed', 'Sepsis/bacteremia', 'Procedure related', 'Arrhythmia', 'Cocaine or other illicit drug induced vasospasm', 'Hypertensive urgency/emergency', 'Hypoxia', 'Hypotension', 'Other', 'NC'))
    secondary_cause_other: Mapped[Optional[str]] = mapped_column(String(100))
    false_positive_reason: Mapped[Optional[str]] = mapped_column(Enum('Congestive heart failure', 'Myocarditis', 'Pericarditis', 'Pulmonary embolism', 'Renal failure', 'Severe sepsis/shock', 'Other'))
    ci: Mapped[Optional[int]] = mapped_column(TINYINT(1))
    ci_type: Mapped[Optional[str]] = mapped_column(Enum('CABG/Surgery', 'PCI/Angioplasty', 'Stent', 'Unknown', 'NC'))
    ecg_type: Mapped[Optional[str]] = mapped_column(Enum('STEMI', 'non-STEMI', 'Other/Uninterpretable', 'New LBBB', 'Normal', 'No EKG', 'NC'))


class Reviews(MciBase):
    __tablename__ = 'reviews'

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True)
    event_id: Mapped[int] = mapped_column(INTEGER(11))
    reviewer_id: Mapped[int] = mapped_column(INTEGER(11))
    mci: Mapped[str] = mapped_column(Enum('Definite', 'Probable', 'No', 'No [resuscitated cardiac arrest]'))
    cardiac_cath: Mapped[int] = mapped_column(TINYINT(1))
    abnormal_ce_values_flag: Mapped[Optional[int]] = mapped_column(TINYINT(1), comment='abnormal cardiac enzyme values')
    ce_criteria: Mapped[Optional[str]] = mapped_column(Enum('Standard criteria', 'PTCA criteria', 'CABG criteria', 'Muscle trauma other than PTCA/CABG'), comment='cardiac enzyme criteria')
    chest_pain_flag: Mapped[Optional[int]] = mapped_column(TINYINT(1))
    ecg_changes_flag: Mapped[Optional[int]] = mapped_column(TINYINT(1))
    lvm_by_imaging_flag: Mapped[Optional[int]] = mapped_column(TINYINT(1), comment='Loss of viable myocardium or regional wall abnormalities by imaging')
    ci: Mapped[Optional[int]] = mapped_column(TINYINT(1), comment="Did the patient have a cardiac intervention (only set if mci = either type of 'no')")
    type: Mapped[Optional[str]] = mapped_column(Enum('Primary', 'Secondary'))
    secondary_cause: Mapped[Optional[str]] = mapped_column(Enum('MVA', 'Overdose', 'Anaphlaxis', 'GI bleed', 'Sepsis/bacteremia', 'Procedure related', 'Arrhythmia', 'Cocaine or other illicit drug induced vasospasm', 'Hypertensive urgency/emergency', 'Hypoxia', 'Hypotension', 'COVID', 'Other'))
    other_cause: Mapped[Optional[str]] = mapped_column(String(100))
    false_positive_flag: Mapped[Optional[int]] = mapped_column(TINYINT(1))
    false_positive_reason: Mapped[Optional[str]] = mapped_column(Enum('Congestive heart failure', 'Myocarditis', 'Pericarditis', 'Pulmonary embolism', 'Renal failure', 'Severe sepsis/shock', 'Other'))
    false_positive_other_cause: Mapped[Optional[str]] = mapped_column(String(100))
    current_tobacco_use_flag: Mapped[Optional[int]] = mapped_column(TINYINT(1))
    past_tobacco_use_flag: Mapped[Optional[int]] = mapped_column(TINYINT(1))
    cocaine_use_flag: Mapped[Optional[int]] = mapped_column(TINYINT(1))
    family_history_flag: Mapped[Optional[int]] = mapped_column(TINYINT(1))
    ci_type: Mapped[Optional[str]] = mapped_column(Enum('CABG/Surgery', 'PCI/Angioplasty', 'Stent', 'Unknown'))
    ecg_type: Mapped[Optional[str]] = mapped_column(Enum('STEMI', 'non-STEMI', 'Other/Uninterpretable', 'New LBBB', 'Normal', 'No EKG'))


# --- Review submission (moved from app.py `events_review`) -------------------

# Review-row fields accepted from a review-submission body. `mci` is required
# and handled explicitly below; `cardiac_cath` is a NOT NULL flag also handled
# explicitly. The rest are optional and copied through when present.
_REVIEW_FLAG_FIELDS = {
    'abnormal_ce_values_flag', 'chest_pain_flag', 'ecg_changes_flag',
    'lvm_by_imaging_flag', 'ci', 'false_positive_flag',
    'current_tobacco_use_flag', 'past_tobacco_use_flag', 'cocaine_use_flag',
    'family_history_flag',
}
_REVIEW_TEXT_FIELDS = {
    'ce_criteria', 'type', 'secondary_cause', 'other_cause',
    'false_positive_reason', 'false_positive_other_cause', 'ci_type', 'ecg_type',
}


def _coerce_flag(value) -> int:
    """Coerce a truthy/falsy review-form value to a 0/1 TINYINT."""
    if isinstance(value, bool):
        return 1 if value else 0
    if isinstance(value, (int, float)):
        return 1 if value else 0
    token = str(value or "").strip().lower()
    return 1 if token in {"1", "true", "yes", "on"} else 0


def parse_review(data: dict) -> dict:
    """Validate an MI review body; return the `Reviews` column values."""
    mci = str(data.get('mci') or '').strip()
    if not mci:
        raise ReviewValidationError('mci is required')
    parsed = {
        'mci': mci,
        'cardiac_cath': _coerce_flag(data.get('cardiac_cath')),
    }
    for field in _REVIEW_FLAG_FIELDS:
        if data.get(field) is not None:
            parsed[field] = _coerce_flag(data.get(field))
    for field in _REVIEW_TEXT_FIELDS:
        value = data.get(field)
        if value is not None and str(value).strip() != '':
            parsed[field] = str(value).strip()
    return parsed


def add_review(session, event_id: int, reviewer_id: int, parsed: dict) -> None:
    """Add one MI `reviews` row for this reviewer (no commit)."""
    session.add(Reviews(event_id=event_id, reviewer_id=reviewer_id, **parsed))


def get_reviews(session, event_id: int) -> list:
    """Return this event's MI `reviews` rows as column dicts."""
    rows = session.query(Reviews).filter(Reviews.event_id == event_id).order_by(Reviews.id).all()
    return [
        {c.name: getattr(r, c.name) for c in Reviews.__table__.columns}
        for r in rows
    ]


def check_schema(session) -> Optional[str]:
    """The MI tables need no migration check; always None."""
    return None


# --- Export (moved from app.py `events_export` and
#     table_service.get_events_export_rows) -----------------------------------

# Verbatim from table_service.get_events_export_rows (pinned by
# tests/test_mci_export_golden.py::test_mci_export_sql_unchanged).
_EXPORT_SQL = """
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


def _export_rows(session) -> list:
    """Rows for the MI CSV export, with criteria pivots and user names."""
    rows = session.execute(text(_EXPORT_SQL)).mappings().all()
    return [dict(r) for r in rows]


def export_csv(session, writer) -> None:
    """Write the MI events export (headings + one row per event) to `writer`."""
    rows = _export_rows(session)
    headings = [
        'Event ID', 'Patient ID', 'Patient Site', 'Site Patient ID', 'Event Date', 'Status', 'Creator',
        'Criteria: MI Dx', 'Criteria: CKMB_Q', 'Criteria: CKMB_M', 'Criteria: CKMB', 'Criteria: Troponin', 'Criteria: Other',
        'Add Date', 'Uploader', 'Upload Date', 'Marker (no packet)', 'No Packet Reason', 'Two Attempts?',
        'Prior Event Date', 'Prior Event Onsite?', 'Other Cause', 'Mark No Packet Date', 'Scrubber', 'Scrub Date',
        'Screener', 'Screen Date', 'Rescrub Message', 'Reject Message', 'Assigner', 'Assign Date', 'Sender', 'Send Date',
        'Reviewer 1', 'Review 1 MI', 'Review 1 Abnormal CE Values?', 'Review 1 CE Criteria', 'Review 1 Chest Pain?',
        'Review 1 ECG Changes?', 'Review 1 LVM by Imaging?', 'Review 1 Clinical Intervention?', 'Review 1 Type',
        'Review 1 Secondary Cause', 'Review 1 Other Cause', 'Review 1 False Positive?', 'Review 1 False Positive Reason',
        'Review 1 False Positive Other Cause', 'Review 1 Current Tobacco Use?', 'Review 1 Past Tobacco Use?',
        'Review 1 Cocaine Use?', 'Review 1 Family History?', 'Review 1 Date',
        'Reviewer 2', 'Review 2 MI', 'Review 2 Abnormal CE Values?', 'Review 2 CE Criteria', 'Review 2 Chest Pain?',
        'Review 2 ECG Changes?', 'Review 2 LVM by Imaging?', 'Review 2 Clinical Intervention?', 'Review 2 Type',
        'Review 2 Secondary Cause', 'Review 2 Other Cause', 'Review 2 False Positive?', 'Review 2 False Positive Reason',
        'Review 2 False Positive Other Cause', 'Review 2 Current Tobacco Use?', 'Review 2 Past Tobacco Use?',
        'Review 2 Cocaine Use?', 'Review 2 Family History?', 'Review 2 Date',
        '3rd Review Assigner', '3rd Review Assign Date', 'Reviewer 3', 'Review 3 MI', 'Review 3 Abnormal CE Values?',
        'Review 3 CE Criteria', 'Review 3 Chest Pain?', 'Review 3 ECG Changes?', 'Review 3 LVM by Imaging?',
        'Review 3 Clinical Intervention?', 'Review 3 Type', 'Review 3 Secondary Cause', 'Review 3 Other Cause',
        'Review 3 False Positive?', 'Review 3 False Positive Reason', 'Review 3 False Positive Other Cause',
        'Review 3 Current Tobacco Use?', 'Review 3 Past Tobacco Use?', 'Review 3 Cocaine Use?', 'Review 3 Family History?', 'Review 3 Date',
        'Overall Outcome', 'Overall Primary vs. Secondary', 'Overall False Positive Event?', 'Overall Secondary Cause',
        'Overall Secondary Cause Other', 'Overall False Positive Cause', 'Overall Cardiac Intervention?'
    ]
    writer.writerow(headings)
    for r in rows:
        writer.writerow([
            r['id'] if r.get('id') is not None else '',
            r.get('patient_id',''),
            r.get('site',''),
            r.get('site_patient_id',''),
            r.get('event_date',''),
            r.get('status',''),
            r.get('creator',''),
            r.get('mi_dx',''), r.get('ckmb_q',''), r.get('ckmb_m',''), r.get('ckmb',''), r.get('troponin',''), r.get('other',''),
            r.get('add_date',''), r.get('uploader',''), r.get('upload_date',''), r.get('marker',''), r.get('no_packet_reason',''),
            'Yes' if r.get('two_attempts_flag') else 'No' if r.get('two_attempts_flag') is not None else '',
            r.get('prior_event_date',''), 'Yes' if r.get('prior_event_onsite_flag') else 'No' if r.get('prior_event_onsite_flag') is not None else '',
            r.get('other_cause',''), r.get('markNoPacket_date',''), r.get('scrubber',''), r.get('scrub_date',''), r.get('screener',''), r.get('screen_date',''),
            r.get('rescrub_message',''), r.get('reject_message',''), r.get('assigner',''), r.get('assign_date',''), r.get('sender',''), r.get('send_date',''),
            r.get('reviewer1',''), r.get('review1_mci',''), r.get('review1_abnormal_ce',''), r.get('review1_ce_criteria',''), r.get('review1_chest_pain',''),
            r.get('review1_ecg_changes',''), r.get('review1_lvm',''), r.get('review1_ci',''), r.get('review1_type',''),
            r.get('review1_secondary_cause',''), r.get('review1_other_cause',''), r.get('review1_false_positive',''), r.get('review1_false_positive_reason',''),
            r.get('review1_false_positive_other_cause',''), r.get('review1_current_tobacco',''), r.get('review1_past_tobacco',''), r.get('review1_cocaine',''), r.get('review1_family_history',''), r.get('review1_date',''),
            r.get('reviewer2',''), r.get('review2_mci',''), r.get('review2_abnormal_ce',''), r.get('review2_ce_criteria',''), r.get('review2_chest_pain',''),
            r.get('review2_ecg_changes',''), r.get('review2_lvm',''), r.get('review2_ci',''), r.get('review2_type',''),
            r.get('review2_secondary_cause',''), r.get('review2_other_cause',''), r.get('review2_false_positive',''), r.get('review2_false_positive_reason',''),
            r.get('review2_false_positive_other_cause',''), r.get('review2_current_tobacco',''), r.get('review2_past_tobacco',''), r.get('review2_cocaine',''), r.get('review2_family_history',''), r.get('review2_date',''),
            r.get('assigner3rd',''), r.get('assign3rd_date',''), r.get('reviewer3',''), r.get('review3_mci',''), r.get('review3_abnormal_ce',''),
            r.get('review3_ce_criteria',''), r.get('review3_chest_pain',''), r.get('review3_ecg_changes',''), r.get('review3_lvm',''), r.get('review3_ci',''), r.get('review3_type',''), r.get('review3_secondary_cause',''), r.get('review3_other_cause',''),
            r.get('review3_false_positive',''), r.get('review3_false_positive_reason',''), r.get('review3_false_positive_other_cause',''), r.get('review3_current_tobacco',''), r.get('review3_past_tobacco',''), r.get('review3_cocaine',''), r.get('review3_family_history',''), r.get('review3_date',''),
            r.get('overall_outcome',''), r.get('overall_primary_secondary',''), 'Yes' if r.get('overall_false_positive_event') else 'No' if r.get('overall_false_positive_event') is not None else '', r.get('overall_secondary_cause',''), r.get('overall_secondary_cause_other',''), r.get('overall_false_positive_reason',''), 'Yes' if r.get('overall_ci') else 'No' if r.get('overall_ci') is not None else ''
        ])
