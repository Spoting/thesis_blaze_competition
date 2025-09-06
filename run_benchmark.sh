#!/bin/bash

# Grafana DashBoards
## RabbitMQ
### RabbitMQ Queues Overview - Seventh State RabbitMQ Support
### RabbitMQ Queue Details - Seventh State RabbitMQ Support
### RabbitMQ-Overview
## Cadvisor
### cAdvisor Docker Insights
### Cadvisor exporter

# Load environment variables (optional, but good practice if you use a .env file)
if [ -f .env ]; then
  export $(cat .env | grep -v '#' | awk '/=/ {print $1}')
fi


# --- Script Parameters ---
# Default values
WORKER_COUNT=${1:-1}
MESSAGE_COUNT=${2:-100} # Default to 2,000,000 if no parameter is provided
TARGET_SCENARIO=${3:-high} # Default to round-robin
SEND_RATE=${4:-0}


WORKER_SERVICE=php_worker_submission_high_priority

echo "--- Benchmark Configuration ---"
echo "Workers Count: $WORKER_COUNT"
echo "Messages to produce: $MESSAGE_COUNT"
echo "Target Scenario: $TARGET_SCENARIO"
echo "Send rate (msg/s): $SEND_RATE (0 = no limit)"
echo "------------------------------"
echo ""
echo ""
echo ""


# Adjust the POSTGRES_USER and POSTGRES_DB if they differ from your .env or docker-compose defaults
DB_USER=${POSTGRES_USER:-app}
DB_NAME=${POSTRES_DB:-app}

# List of tables to truncate
TABLES_TO_TRUNCATE=(
  "submission"
  # "another_table_name"
)

QUEUES_TO_PURGE=(
    "submission_high_priority_queue"
    # "submission_low_priority_queue"
)

echo '### Stopping Workers' 
docker compose up -d --scale "$WORKER_SERVICE"=0
echo 'Done Stopping Workers###'
echo ""
echo ""


# --- Database Reset ---
echo "### Clearing database tables"

for TABLE in "${TABLES_TO_TRUNCATE[@]}"; do
    echo "$TABLE"
    echo "- Current Count:"
    docker compose exec -T database psql -p 5432 -U "$DB_USER" -d "$DB_NAME" -c "SELECT count(*) from "$TABLE";"
    echo "- Emptying..."
    docker compose exec -T database psql -p 5432 -U "$DB_USER" -d "$DB_NAME" -c "TRUNCATE TABLE "$TABLE" RESTART IDENTITY CASCADE;"
done

echo "Database tables cleared ###"
echo ""
echo ""

# --- Optional: Ensure RabbitMQ queues are empty (if needed for a clean state) ---
echo "### Purging RabbitMQ queues"
for QUEUE in "${QUEUES_TO_PURGE[@]}"; do
    echo "$QUEUE"
    docker compose exec rabbitmq rabbitmqadmin -u guest -p guest purge queue name="$QUEUE"
done

echo "Done purging RabbitMQ queues ###"

# --- Start/Run Your Benchmark Producers ---
echo ""
echo ""

echo "### Producing Messages for Benchmark"
# Example: If you have a separate service for producers, or a script to run them
# docker compose run --rm producer_service python your_producer_script.py
# Or if your producers are part of the 'php' service, you might run a command like:
# docker compose exec php bin/console app:generate-messages 10000 1kb
docker compose exec php bin/console app:produce-messages "$MESSAGE_COUNT" "$TARGET_SCENARIO" "$SEND_RATE"
echo "Done Producing Messages for Benchmark ###"

echo ""
echo "Pausing..."
sleep 5

echo "###Scaling $WORKER_COUNT Workers"
docker compose up -d --scale "$WORKER_SERVICE"="$WORKER_COUNT"
echo "Done Scaling Workers ###"

echo ""

# --- Monitor for a period or until conditions are met ---
echo "--- Monitoring benchmark performance ---"