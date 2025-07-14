import os
import mysql.connector
from mysql.connector.pooling import MySQLConnectionPool
from dotenv import load_dotenv

load_dotenv()

DB_CONFIG = {
    'host': os.getenv('DB_HOST', 'localhost'),
    'user': os.getenv('DB_USER', 'root'),
    'password': os.getenv('DB_PASSWORD', ''),
    'database': os.getenv('DB_NAME', 'mci'),
}

# Lazily initialized connection pool similar to the Node backend implementation
POOL = None


def get_pool():
    global POOL
    if POOL is None:
        POOL = MySQLConnectionPool(
            pool_name="mci_pool",
            pool_size=10,
            **DB_CONFIG,
        )
    return POOL

def get_table_data(name: str):
    """Return up to 100 rows from the specified table."""
    conn = get_pool().get_connection()
    cursor = conn.cursor(dictionary=True)
    cursor.execute(f"SELECT * FROM `{name}` LIMIT 100")
    rows = cursor.fetchall()
    cursor.close()
    conn.close()
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
        "WHERE e.status = 'created' "
        "GROUP BY e.id LIMIT 100"
    )
    cursor.execute(query)
    rows = cursor.fetchall()
    cursor.close()
    conn.close()
    return rows
