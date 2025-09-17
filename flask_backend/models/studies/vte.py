# VTE (Venothromboembolic) Study Models
# This module is commented out until VTE study migration is needed
# Uncomment and modify as needed for VTE study deployment

"""
from sqlalchemy import Column, Integer, String, Enum, Text, ForeignKey
from sqlalchemy.orm import relationship
from ..base import Base

class VTEReview(Base):
    __tablename__ = 'reviews'
    
    id = Column(Integer, primary_key=True)
    event_id = Column(Integer, ForeignKey('events.id'), nullable=False)
    reviewer_id = Column(Integer, ForeignKey('users.id'), nullable=False)
    
    # VTE-specific outcome classifications
    outcome = Column(Enum('Definite', 'Probable', 'No'), nullable=False)
    
    # VTE-specific fields
    vte_type = Column(Enum('DVT', 'PE', 'Both', 'Other'))
    dvt_location = Column(Enum('Proximal', 'Distal', 'Upper', 'Other'))
    pe_severity = Column(Enum('Massive', 'Submassive', 'Low_risk'))
    imaging_evidence = Column(Integer)  # tinyint(1)
    anticoagulation = Column(Integer)   # tinyint(1)
    thrombophilia_workup = Column(Integer)  # tinyint(1)
    dvt_symptoms = Column(Integer)      # tinyint(1)
    pe_symptoms = Column(Integer)       # tinyint(1)
    risk_factors = Column(Text)
    treatment_duration_days = Column(Integer)
    
    # Relationships
    event = relationship("Events", back_populates="reviews")
    reviewer = relationship("Users", back_populates="reviews")

class VTEEventDerivedData(Base):
    __tablename__ = 'event_derived_datas'
    
    id = Column(Integer, primary_key=True)
    event_id = Column(Integer, ForeignKey('events.id'), nullable=False)
    
    # VTE-specific outcome classifications
    outcome = Column(Enum('Definite', 'Probable', 'No'))
    primary_secondary = Column(Enum('Primary', 'Secondary'))
    false_positive_event = Column(Integer)  # tinyint(1)
    
    # VTE-specific secondary causes
    secondary_cause = Column(Enum('Surgery', 'Trauma', 'Cancer', 'Pregnancy', 'Immobility', 'Other', 'NC'))
    secondary_cause_other = Column(String(100))
    false_positive_reason = Column(Enum('Cellulitis', 'Lymphedema', 'Baker_cyst', 'Other'))
    
    # VTE-specific imaging
    imaging_type = Column(Enum('Ultrasound', 'CT', 'MRI', 'VQ_scan', 'Other'))
    imaging_positive = Column(Integer)  # tinyint(1)
    
    # Relationships
    event = relationship("Events", back_populates="derived_data")
"""
