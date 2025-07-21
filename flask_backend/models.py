from typing import Optional
from sqlalchemy import Column, Date, DateTime, Enum, Float, String, TIMESTAMP, text, ForeignKey, create_engine
from sqlalchemy.dialects.mysql import INTEGER, TINYINT, VARCHAR
from sqlalchemy.orm import (
    DeclarativeBase,
    Mapped,
    mapped_column,
    relationship,
    sessionmaker,
    Session,
)
import datetime
import os

class Base(DeclarativeBase):
    pass


class Criterias(Base):
    __tablename__ = 'criterias'

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True)
    event_id: Mapped[int] = mapped_column(ForeignKey("events.id"), INTEGER(11), comment='foreign key in events table')
    name: Mapped[str] = mapped_column(String(50))
    value: Mapped[str] = mapped_column(String(100))
    event = relationship("Events", back_populates="criterias")

class EventDerivedDatas(Base):
    __tablename__ = 'event_derived_datas'

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True)
    event_id: Mapped[int] = mapped_column(ForeignKey("events.id"), INTEGER(11), comment='foreign key into events table')
    outcome: Mapped[Optional[str]] = mapped_column(Enum('Definite', 'Probable', 'No', 'No [resuscitated cardiac arrest]'))
    primary_secondary: Mapped[Optional[str]] = mapped_column(Enum('Primary', 'Secondary'))
    false_positive_event: Mapped[Optional[int]] = mapped_column(TINYINT(1))
    secondary_cause: Mapped[Optional[str]] = mapped_column(Enum('MVA', 'Overdose', 'Anaphlaxis', 'GI bleed', 'Sepsis/bacteremia', 'Procedure related', 'Arrhythmia', 'Cocaine or other illicit drug induced vasospasm', 'Hypertensive urgency/emergency', 'Hypoxia', 'Hypotension', 'Other', 'NC'))
    secondary_cause_other: Mapped[Optional[str]] = mapped_column(String(100))
    event = relationship("Events", back_populates="derived_data")
    false_positive_reason: Mapped[Optional[str]] = mapped_column(Enum('Congestive heart failure', 'Myocarditis', 'Pericarditis', 'Pulmonary embolism', 'Renal failure', 'Severe sepsis/shock', 'Other'))
    ci: Mapped[Optional[int]] = mapped_column(TINYINT(1))
    ci_type: Mapped[Optional[str]] = mapped_column(Enum('CABG/Surgery', 'PCI/Angioplasty', 'Stent', 'Unknown', 'NC'))
    ecg_type: Mapped[Optional[str]] = mapped_column(Enum('STEMI', 'non-STEMI', 'Other/Uninterpretable', 'New LBBB', 'Normal', 'No EKG', 'NC'))


class Events(Base):
    __tablename__ = 'events'

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True)
    patient_id: Mapped[int] = mapped_column(INTEGER(10))
    creator_id: Mapped[int] = mapped_column(ForeignKey("users.id"), INTEGER(11), comment='foreign key in users table')
    status: Mapped[str] = mapped_column(Enum('created', 'uploaded', 'scrubbed', 'screened', 'assigned', 'sent', 'reviewer1_done', 'reviewer2_done', 'third_review_needed', 'third_review_assigned', 'done', 'rejected', 'no_packet_available'), server_default=text("'created'"))
    add_date: Mapped[datetime.date] = mapped_column(Date)
    event_date: Mapped[datetime.date] = mapped_column(Date)
    uploader_id: Mapped[Optional[int]] = mapped_column(ForeignKey("users.id"), INTEGER(11), comment='foreign key in users table')
    file_number: Mapped[Optional[int]] = mapped_column(INTEGER(10))
    original_name: Mapped[Optional[str]] = mapped_column(String(100))
    marker_id: Mapped[Optional[int]] = mapped_column(INTEGER(11))
    scrubber_id: Mapped[Optional[int]] = mapped_column(INTEGER(11), comment='foreign key into users table')
    screener_id: Mapped[Optional[int]] = mapped_column(INTEGER(11), comment='foreign key in users table')
    assigner_id: Mapped[Optional[int]] = mapped_column(INTEGER(11), comment='foreign key into users table')
    sender_id: Mapped[Optional[int]] = mapped_column(INTEGER(11), comment='foreign key into users table')
    reviewer1_id: Mapped[Optional[int]] = mapped_column(INTEGER(11))
    reviewer2_id: Mapped[Optional[int]] = mapped_column(INTEGER(11))
    assigner3rd_id: Mapped[Optional[int]] = mapped_column(INTEGER(11), comment='foreign key into users table')
    reviewer3_id: Mapped[Optional[int]] = mapped_column(INTEGER(11))
    rescrub_message: Mapped[Optional[str]] = mapped_column(String(500))
    reject_message: Mapped[Optional[str]] = mapped_column(String(500))
    no_packet_reason: Mapped[Optional[str]] = mapped_column(Enum('Outside hospital', 'Ascertainment diagnosis error', 'Ascertainment diagnosis referred to a prior event', 'Other'), comment="reasons why 'no_packet_available'")
    two_attempts_flag: Mapped[Optional[int]] = mapped_column(TINYINT(1), comment="only set if 'outside hospital'")
    prior_event_date: Mapped[Optional[str]] = mapped_column(String(7), comment="only set if  'Ascertainment diagnosis referred to a prior event'; null if event date not known")
    prior_event_onsite_flag: Mapped[Optional[int]] = mapped_column(TINYINT(1), comment="only set if 'Ascertainment diagnosis referred to a prior event'")
    other_cause: Mapped[Optional[str]] = mapped_column(String(100), comment="only set if 'other'")
    upload_date: Mapped[Optional[datetime.date]] = mapped_column(Date)
    markNoPacket_date: Mapped[Optional[datetime.date]] = mapped_column(Date)
    scrub_date: Mapped[Optional[datetime.date]] = mapped_column(Date)
    screen_date: Mapped[Optional[datetime.date]] = mapped_column(Date)
    assign_date: Mapped[Optional[datetime.date]] = mapped_column(Date)
    send_date: Mapped[Optional[datetime.date]] = mapped_column(Date)
    review1_date: Mapped[Optional[datetime.date]] = mapped_column(Date)
    review2_date: Mapped[Optional[datetime.date]] = mapped_column(Date)
    assign3rd_date: Mapped[Optional[datetime.date]] = mapped_column(Date)
    review3_date: Mapped[Optional[datetime.date]] = mapped_column(Date)
    criterias = relationship("Criterias", back_populates="event")
    derived_data = relationship("EventDerivedDatas", back_populates="event", uselist=False)
    reviews = relationship("Reviews", back_populates="event")
    solicitations = relationship("Solicitations", back_populates="event")
    creator = relationship("Users", foreign_keys=[creator_id])
    uploader = relationship("Users", foreign_keys=[uploader_id])


class Logs(Base):
    __tablename__ = 'logs'

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True)
    user_id: Mapped[int] = mapped_column(INTEGER(11))
    controller: Mapped[str] = mapped_column(String(30))
    action: Mapped[str] = mapped_column(String(30))
    time: Mapped[datetime.datetime] = mapped_column(DateTime)
    params: Mapped[Optional[str]] = mapped_column(String(1000))


class Reviews(Base):
    __tablename__ = 'reviews'

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True)
    event_id: Mapped[int] = mapped_column(ForeignKey("events.id"), INTEGER(11))
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
    event = relationship("Events", back_populates="reviews")
    reviewer = relationship("Users", foreign_keys=[reviewer_id])
class Solicitations(Base):
    __tablename__ = "solicitations"

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True)
    event_id: Mapped[int] = mapped_column(ForeignKey("events.id"), INTEGER(11))
    date: Mapped[datetime.date] = mapped_column(Date)
    contact: Mapped[str] = mapped_column(String(200))

    event = relationship("Events", back_populates="solicitations")

class Users(Base):
    __tablename__ = 'users'

    id: Mapped[int] = mapped_column(INTEGER(11), primary_key=True)
    username: Mapped[str] = mapped_column(String(200))
    login: Mapped[str] = mapped_column(String(200))
    first_name: Mapped[str] = mapped_column(String(64))
    last_name: Mapped[str] = mapped_column(String(64))
    site: Mapped[str] = mapped_column(String(20))
    uploader_flag: Mapped[int] = mapped_column(TINYINT(1), server_default=text('0'))
    reviewer_flag: Mapped[int] = mapped_column(TINYINT(1), server_default=text('1'))
    third_reviewer_flag: Mapped[int] = mapped_column(TINYINT(1), server_default=text('0'))
    admin_flag: Mapped[int] = mapped_column(TINYINT(1), server_default=text('0'))


class UwPatients(Base):
    __tablename__ = 'uw_patients'

    id: Mapped[int] = mapped_column(INTEGER(10), primary_key=True)
    site_patient_id: Mapped[str] = mapped_column(String(64), server_default=text("''"))
    site: Mapped[str] = mapped_column(String(20))
    last_update: Mapped[datetime.datetime] = mapped_column(TIMESTAMP, server_default=text('current_timestamp() ON UPDATE current_timestamp()'))
    create_date: Mapped[datetime.datetime] = mapped_column(DateTime, server_default=text("'0000-00-00 00:00:00'"))


t_uw_patients2 = Table(
    'uw_patients2', Base.metadata,
    Column('id', INTEGER(10), nullable=False, server_default=text('0')),
    Column('site_patient_id', VARCHAR(64), nullable=False),
    Column('site', String(20), nullable=False),
    Column('last_update', TIMESTAMP, nullable=False, server_default=text("'0000-00-00 00:00:00'")),
    Column('create_date', DateTime, nullable=False, server_default=text("'0000-00-00 00:00:00'"))
)

# --- Database session handling -------------------------------------------------
_engine = None
_SessionFactory = None


def get_engine():
    """Lazily create and return the SQLAlchemy engine."""
    global _engine
    if _engine is None:
        user = os.getenv("DB_USER", "root")
        pw = os.getenv("DB_PASSWORD", "")
        host = os.getenv("DB_HOST", "localhost")
        db = os.getenv("DB_NAME", "cnics")
        url = f"mysql+mysqlconnector://{user}:{pw}@{host}/{db}"
        _engine = create_engine(url, pool_pre_ping=True)
    return _engine


def get_session() -> Session:
    """Return a new SQLAlchemy session configured from environment variables."""
    global _SessionFactory
    if _SessionFactory is None:
        _SessionFactory = sessionmaker(bind=get_engine())
    return _SessionFactory()


