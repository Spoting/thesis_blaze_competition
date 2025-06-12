ARG PYTHON_BASE_IMAGE_TAG=3.11-slim

FROM python:${PYTHON_BASE_IMAGE_TAG}

WORKDIR /usr/src

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

# CMD ["celery", "-A", "app.celery", "worker", \
#      "-Q", "submission_normal_queue,submission_premium_queue,competition_winner_generation_queue", \
#      "-l", "info", "--concurrency=1", "--prefetch-multiplier=1"]


# celery -A app.celery worker -Q submission_normal_queue,submission_premium_queue,competition_winner_generation_queue -l info --concurrency=1 --prefetch-multiplier=1
