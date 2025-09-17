# CVA (Cerebrovascular Events - Stroke) Study Models
# This module is commented out until CVA study migration is needed
# Uncomment and modify as needed for CVA study deployment

"""
from sqlalchemy import Column, Integer, String, Enum, Text, ForeignKey, DateTime, JSON
from sqlalchemy.orm import relationship
from ..base import Base

class CVAReview(Base):
    __tablename__ = 'reviews'
    
    id = Column(Integer, primary_key=True)
    event_id = Column(Integer, ForeignKey('events.id'), nullable=False)
    reviewer_id = Column(Integer, ForeignKey('users.id'), nullable=False)
    
    # CVA-specific outcome classifications
    outcome = Column(Enum('Definite', 'Probable', 'Possible', 'No'), nullable=False)
    
    # CVA-specific fields
    stroke_type = Column(Enum('Ischemic', 'Hemorrhagic', 'TIA', 'Other'))
    nihss_score = Column(Integer)
    imaging_evidence = Column(Integer)  # tinyint(1)
    time_to_treatment = Column(Integer)  # minutes
    thrombolysis = Column(Integer)       # tinyint(1)
    mechanical_thrombectomy = Column(Integer)  # tinyint(1)
    stroke_location = Column(Enum('Anterior', 'Posterior', 'Lacunar', 'Other'))
    stroke_mechanism = Column(Enum('Large_vessel', 'Cardioembolic', 'Small_vessel', 'Other'))
    modified_rankin_score = Column(Integer)
    discharge_destination = Column(Enum('Home', 'Rehab', 'SNF', 'Other'))
    
    # Relationships
    event = relationship("Events", back_populates="reviews")
    reviewer = relationship("Users", back_populates="reviews")

class CVAQuestionnaire(Base):
    __tablename__ = 'questionnaires'
    
    id = Column(Integer, primary_key=True)
    event_id = Column(Integer, ForeignKey('events.id'), nullable=False)
    questionnaire_type = Column(Enum('baseline', 'followup', 'outcome'), nullable=False)
    completed_date = Column(DateTime)
    data = Column(JSON)
    reviewer_id = Column(Integer, ForeignKey('users.id'))
    
    # Relationships
    event = relationship("Events", back_populates="questionnaires")
    reviewer = relationship("Users", back_populates="questionnaires")

class CVAEventDerivedData(Base):
    __tablename__ = 'event_derived_datas'
    
    id = Column(Integer, primary_key=True)
    event_id = Column(Integer, ForeignKey('events.id'), nullable=False)
    
    # CVA-specific outcome classifications
    outcome = Column(Enum('Definite', 'Probable', 'Possible', 'No'))
    primary_secondary = Column(Enum('Primary', 'Secondary'))
    false_positive_event = Column(Integer)  # tinyint(1)
    
    # CVA-specific secondary causes
    secondary_cause = Column(Enum('Seizure', 'Migraine', 'Syncope', 'Other', 'NC'))
    secondary_cause_other = Column(String(100))
    false_positive_reason = Column(Enum('Seizure', 'Migraine', 'Syncope', 'Other'))
    
    # CVA-specific imaging
    imaging_type = Column(Enum('CT', 'MRI', 'CTA', 'MRA', 'Other'))
    imaging_positive = Column(Integer)  # tinyint(1)
    
    # Relationships
    event = relationship("Events", back_populates="derived_data")
"""
