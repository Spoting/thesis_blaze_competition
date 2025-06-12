# app/celery.py

from celery import Celery
from kombu import Queue, Exchange
from . import config # Relative import for config within the 'app' package

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

# Auto-discover tasks in the 'app.tasks' module
app.conf.include = ['app.tasks']

app.conf.accept_content = ['json']
app.conf.task_serializer = 'json'

# Set timezone for consistent task scheduling (important for `eta` and `countdown`)
app.conf.timezone = 'UTC'
app.conf.enable_utc = True

if __name__ == '__main__':
    app.start()