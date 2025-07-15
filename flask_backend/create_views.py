import os
from mysql import connector
from dotenv import load_dotenv

load_dotenv()

DB_CONFIG = {
    'host': os.getenv('DB_HOST', 'localhost'),
    'user': os.getenv('DB_USER', 'root'),
    'password': os.getenv('DB_PASSWORD', ''),
    'database': os.getenv('DB_NAME', 'mci'),
}


def create_views():
    """Create database views used by the frontend."""
    conn = connector.connect(**DB_CONFIG)
    cursor = conn.cursor()

    cursor.execute(
        """
        CREATE OR REPLACE VIEW events_view AS
        SELECT e.*, p.site_patient_id, p.site
        FROM events e
        JOIN uw_patients2 p ON e.patient_id = p.id
        """
    )

    conn.commit()
    cursor.close()
    conn.close()


if __name__ == "__main__":
    create_views()
