# AFIB Study Models
# This module is commented out until AFIB study migration is needed
# Uncomment and modify as needed for AFIB study deployment
# Note: No immediate plans for use per Heidi

"""
from sqlalchemy import Column, Integer, String, Enum, Text, ForeignKey
from sqlalchemy.orm import relationship
from ..base import Base

class AFIBReview(Base):
    __tablename__ = 'reviews'
    
    id = Column(Integer, primary_key=True)
    event_id = Column(Integer, ForeignKey('events.id'), nullable=False)
    reviewer_id = Column(Integer, ForeignKey('users.id'), nullable=False)
    
    # AFIB-specific outcome classifications
    outcome = Column(Enum('Definite', 'Probable', 'Possible', 'No'), nullable=False)
    
    # AFIB-specific fields
    afib_type = Column(Enum('Paroxysmal', 'Persistent', 'Permanent', 'Unknown'))
    ecg_evidence = Column(Integer)  # tinyint(1)
    duration_hours = Column(Integer)
    anticoagulation = Column(Integer)  # tinyint(1)
    rate_control = Column(Integer)     # tinyint(1)
    rhythm_control = Column(Integer)   # tinyint(1)
    chads2_score = Column(Integer)
    chads2vasc_score = Column(Integer)
    bleeding_risk = Column(Enum('Low', 'Moderate', 'High'))
    cardioversion = Column(Integer)    # tinyint(1)
    ablation = Column(Integer)         # tinyint(1)
    
    # Relationships
    event = relationship("Events", back_populates="reviews")
    reviewer = relationship("Users", back_populates="reviews")

class AFIBEventDerivedData(Base):
    __tablename__ = 'event_derived_datas'
    
    id = Column(Integer, primary_key=True)
    event_id = Column(Integer, ForeignKey('events.id'), nullable=False)
    
    # AFIB-specific outcome classifications
    outcome = Column(Enum('Definite', 'Probable', 'Possible', 'No'))
    primary_secondary = Column(Enum('Primary', 'Secondary'))
    false_positive_event = Column(Integer)  # tinyint(1)
    
    # AFIB-specific secondary causes
    secondary_cause = Column(Enum('SVT', 'Atrial_flutter', 'Other', 'NC'))
    secondary_cause_other = Column(String(100))
    false_positive_reason = Column(Enum('SVT', 'Atrial_flutter', 'Other'))
    
    # AFIB-specific ECG
    ecg_type = Column(Enum('AFIB', 'Atrial_flutter', 'SVT', 'Normal', 'Other'))
    heart_rate = Column(Integer)
    
    # Relationships
    event = relationship("Events", back_populates="derived_data")
"""
