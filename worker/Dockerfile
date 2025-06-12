ARG PYTHON_BASE_IMAGE_TAG=3.11-slim

FROM python:${PYTHON_BASE_IMAGE_TAG}

WORKDIR /usr/src

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

# 4. Command to run the Celery worker when the container starts
# -A app.celery_app: Specifies the Celery app instance located at app/celery_app.py
# worker: Runs the worker process
# -Q submission_queue,winner_queue,notification_queue: Specifies the queues to consume from
# -l info: Sets the logging level to info
# --concurrency=1: Starts with 1 worker process (adjust based on your needs)
# --prefetch-multiplier=1: Only fetch one message at a time (good for long-running, critical tasks)
# CMD ["celery", "-A", "app.celery_app", "worker", "-Q", "submission_queue,winner_queue,notification_queue", "-l", "info", "--concurrency=1", "--prefetch-multiplier=1"]