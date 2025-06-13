import datetime
import psycopg2
import psycopg2.pool
from .celery import app

# --- PostgreSQL Connection Helper ---
def get_db_connection():
    """
    Retrieves a PostgreSQL database connection from the worker's pool.
    Raises an error if the pool is not initialized on the app instance.
    """
    # Access the db_pool directly from the Celery app instance
    if not hasattr(app, 'db_pool') or app.db_pool is None:
        # This means the worker_process_init signal has not yet completed
        # or failed to initialize the pool.
        error_msg = f"[{datetime.datetime.now()}] ERROR: PostgreSQL connection pool not initialized on app instance for this worker."
        print(error_msg)
        raise RuntimeError(error_msg)
    try:
        # Get a connection from the pool
        conn = app.db_pool.getconn()
        print(f"[{datetime.datetime.now()}] Retrieved connection from PostgreSQL pool.")
        return conn
    except psycopg2.Error as e:
        print(f"[{datetime.datetime.now()}] Could not get connection from pool: {e}")
        raise # Re-raise to trigger Celery retry

def execute_db_query(query: str, params: tuple = None, fetch_one: bool = False, fetch_all: bool = False):
    """
    Executes a database query and optionally fetches results.
    Borrows a connection from the pool and returns it.
    """
    conn = None
    cursor = None
    results = None
    try:
        conn = get_db_connection() # Get from pool
        cursor = conn.cursor()

        cursor.execute(query, params)

        if query.strip().upper().startswith(('INSERT', 'UPDATE', 'DELETE')):
            conn.commit() # Commit changes for DML operations
            print(f"[{datetime.datetime.now()}] Database changes committed.")
        elif fetch_one:
            results = cursor.fetchone()
            print(f"[{datetime.datetime.now()}] Fetched one row from DB.")
        elif fetch_all:
            results = cursor.fetchall()
            print(f"[{datetime.datetime.now()}] Fetched all rows from DB.")
        # For SELECT queries without fetch_one/fetch_all, no explicit commit needed here.

    except psycopg2.Error as e:
        print(f"[{datetime.datetime.now()}] Database query error: {e}")
        if conn:
            conn.rollback() # Rollback on error
            print(f"[{datetime.datetime.now()}] Database transaction rolled back due to error.")
        raise # Re-raise to trigger Celery retry
    finally:
        if cursor:
            cursor.close()
        if conn:
            # IMPORTANT: For pooled connections, conn.close() RETURNS the connection to the pool.
            conn.close()
            print(f"[{datetime.datetime.now()}] PostgreSQL connection returned to pool.")
    return results

# You can add more DB-specific helper functions here later, e.g.:
# def fetch_all_competitions():
#     query = "SELECT * FROM competitions;"
#     return execute_db_query(query, fetch_all=True)