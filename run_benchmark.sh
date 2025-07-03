#!/bin/bash

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


# --- Database Reset ---
echo "### Clearing database tables"
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
echo ""

echo "###Scaling $WORKER_COUNT Workers"
docker compose up -d --scale "$WORKER_SERVICE"="$WORKER_COUNT"
echo "Done Scaling Workers ###"

echo ""
echo ""

# --- Monitor for a period or until conditions are met ---
echo "--- Monitoring benchmark performance ---"
# Here you would typically keep Prometheus/Grafana open and observe.
# You could also add a `sleep` command to let the benchmark run for a set duration.
# sleep 300 # Run for 5 minutes


# # --- Monitor and Wait for Queue to be Empty ---
# echo "### Waiting for queues to be processed ###"
# # This is the new crucial loop
# QUEUE_CHECK_INTERVAL=5 # Check every 5 seconds
# MAX_WAIT_TIME=600    # Max 10 minutes (600 seconds) to wait for queues to clear
# ELAPSED_WAIT_TIME=0

# while [ "$ELAPSED_WAIT_TIME" -lt "$MAX_WAIT_TIME" ]; do
#     ALL_QUEUES_EMPTY=true
#     TOTAL_MESSAGES_IN_QUEUES=0

#     for QUEUE in "${QUEUES_TO_PURGE[@]}"; do
#         # Get queue messages_ready count using rabbitmqadmin
#         # Filter for the specific queue name and extract messages_ready
#         MESSAGES_READY=$(docker compose exec rabbitmq rabbitmqadmin -u guest -p guest get queue="$QUEUE")

#         if [ -z "$MESSAGES_READY" -lt ]; then
#             MESSAGES_READY=0
#         fi

#         TOTAL_MESSAGES_IN_QUEUES=$((TOTAL_MESSAGES_IN_QUEUES + MESSAGES_READY))

#         if [ "$MESSAGES_READY" -gt 0 ]; then
#             ALL_QUEUES_EMPTY=false
#             echo "  Queue '$QUEUE' has $MESSAGES_READY messages remaining."
#         fi
#     done

#     if [ "$ALL_QUEUES_EMPTY" = true ]; then
#         echo "All relevant queues are empty. Proceeding to stop workers."
#         break # Exit the while loop
#     else
#         echo "Total messages across relevant queues: $TOTAL_MESSAGES_IN_QUEUES. Waiting..."
#         sleep "$QUEUE_CHECK_INTERVAL"
#         ELAPSED_WAIT_TIME=$((ELAPSED_WAIT_TIME + QUEUE_CHECK_INTERVAL))
#     fi
# done

# if [ "$ALL_QUEUES_EMPTY" = false ]; then
#     echo "Warning: Max wait time ($MAX_WAIT_TIME seconds) reached. Queues are not empty. Stopping workers anyway."
# fi
# echo "Done waiting for queues to process. ###"
# echo ""



echo "--- Benchmark run complete ---"

# --- Optional: Stop Producers or Clean Up ---
# (If your producers are long-running)