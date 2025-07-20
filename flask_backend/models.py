import os
from dotenv import load_dotenv
from sqlalchemy import create_engine, Column, Integer, String, Date, ForeignKey
from sqlalchemy.orm import declarative_base, relationship, sessionmaker

load_dotenv()

DB_CONFIG = {
    'host': os.getenv('DB_HOST', 'localhost'),
    'user': os.getenv('DB_USER', 'root'),
    'password': os.getenv('DB_PASSWORD', ''),
    'database': os.getenv('DB_NAME', 'mci'),
}

DATABASE_URL = (
    f"mysql+mysqlconnector://{DB_CONFIG['user']}:{DB_CONFIG['password']}"
    f"@{DB_CONFIG['host']}/{DB_CONFIG['database']}"
)

engine = create_engine(DATABASE_URL, pool_size=10)
SessionLocal = sessionmaker(bind=engine)

Base = declarative_base()

class Event(Base):
    __tablename__ = 'events'
    id = Column(Integer, primary_key=True)
    patient_id = Column(Integer, nullable=False)
    event_date = Column(Date)
    status = Column(String(50))
    criterias = relationship('Criteria', back_populates='event')

class Criteria(Base):
    __tablename__ = 'criterias'
    id = Column(Integer, primary_key=True)
    event_id = Column(Integer, ForeignKey('events.id'))
    name = Column(String(50))
    value = Column(String(100))
    event = relationship('Event', back_populates='criterias')

class UwPatient2(Base):
    __tablename__ = 'uw_patients2'
    id = Column(Integer, primary_key=True)


def get_session():
    """Return a new SQLAlchemy session."""
    return SessionLocal()
