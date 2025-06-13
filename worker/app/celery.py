import datetime # For logging timestamps
from celery import Celery
from kombu import Queue, Exchange
from celery.signals import worker_process_init, worker_process_shutdown # Import signals
import psycopg2.pool # Import psycopg2.pool for the connection pool

from . import config # Import your config for database URL

# Initialize Celery app instance
app = Celery('my_symfony_consumer_app',
             broker=config.CELERY_BROKER_URL,
             backend=config.CELERY_RESULT_URL)

# --- Define Exchanges (matching your RabbitMQ topology) ---
submission_exchange = Exchange(
    'submission_exchange',
    type='direct',
    durable=True
)

delayed_winner_exchange = Exchange(
    'delayed_winner_exchange',
    type='x-delayed-message',
    durable=True,
)

# --- Configure Celery Queues (matching your RabbitMQ topology) ---
app.conf.task_queues = (
    Queue(
        'submission_normal_queue',
        exchange=submission_exchange,
        routing_key='normal_submission',
        durable=True,
        queue_arguments={'x-max-priority': config.MAX_PRIORITY}
    ),
    Queue(
        'submission_premium_queue',
        exchange=submission_exchange,
        routing_key='premium_submission',
        durable=True,
        queue_arguments={'x-max-priority': config.MAX_PRIORITY}
    ),
    Queue(
        'competition_winner_generation_queue',
        exchange=delayed_winner_exchange,
        routing_key='winner_trigger',
        durable=True
    ),
)

# --- Define how Celery tasks are routed to these queues ---
# Task names now reference 'app.tasks' because tasks.py is inside the 'app' package
app.conf.task_routes = {
    'app.tasks.process_normal_submission': {
        'queue': 'submission_normal_queue',
        'routing_key': 'normal_submission'
    },
    'app.tasks.process_premium_submission': {
        'queue': 'submission_premium_queue',
        'routing_key': 'premium_submission'
    },
    'app.tasks.trigger_winner_generation': {
        'queue': 'competition_winner_generation_queue',
        'routing_key': 'winner_trigger'
    },
}

# --- Recommended Celery Settings for Robustness ---
app.conf.task_acks_late = True             # Acknowledge task only after completion
app.conf.worker_prefetch_multiplier = 1    # Fetch one task at a time per worker process

app.conf.include = ['app.tasks']

app.conf.accept_content = ['json']
app.conf.task_serializer = 'json'

app.conf.timezone = 'UTC'
app.conf.enable_utc = True

# --- PostgreSQL Connection Pool Initialization (Per Worker Process) ---
# IMPORTANT: The pool is now attached directly to the 'app' object.
@worker_process_init.connect
def init_db_pool(**kwargs):
    """
    Initializes a database connection pool for each Celery worker process.
    This runs once per forked worker process. The pool is attached to the app instance.
    """
    try:
        app.db_pool = psycopg2.pool.SimpleConnectionPool( # Attach pool to app
            minconn=1,  # Minimum number of connections to keep open
            maxconn=10, # Maximum number of connections in the pool
            dsn=config.DATABASE_URL
        )
        print(f"[{datetime.datetime.now()}] PostgreSQL connection pool initialized for worker process.")
    except psycopg2.Error as e:
        print(f"[{datetime.datetime.now()}] ERROR: Could not initialize PostgreSQL connection pool: {e}")
        # It's critical for the worker to be able to connect to the DB.
        # Re-raising here will likely cause the worker to fail to start.
        raise


@worker_process_shutdown.connect
def shutdown_db_pool(**kwargs):
    """
    Closes the database connection pool when a Celery worker process shuts down.
    """
    try:
        if hasattr(app, 'db_pool') and app.db_pool: # Check if db_pool attribute exists on app
            app.db_pool.closeall()
            print(f"[{datetime.datetime.now()}] PostgreSQL connection pool closed for worker process.")
    except Exception as e:
        print(f"[{datetime.datetime.now()}] ERROR: Could not close PostgreSQL connection pool cleanly: {e}")


if __name__ == '__main__':
    app.start()