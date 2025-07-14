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
