#!/bin/bash
set -e # Exit immediately if a command exits with a non-zero status

RABBITMQ_HOST=${RABBITMQ_HOST:-rabbitmq}
RABBITMQ_PORT=${RABBITMQ_PORT:-15672}
RABBITMQ_USER=${RABBITMQ_USER:-guest}
RABBITMQ_PASS=${RABBITMQ_PASS:-guest}

# --- Submissions Configuration ---
SUBMISSION_EXCHANGE_NAME="submission_exchange"
SUBMISSION_QUEUES=( # Each entry is "QUEUE_NAME:ROUTING_KEY"
    "submission_normal_queue:normal_submission"
    "submission_premium_queue:premium_submission"
)
MAX_PRIORITY=10

# --- Winner Trigger Configuration ---
DELAYED_EXCHANGE_NAME="delayed_winner_exchange" # For x-delay functionality
WINNER_TRIGGER_QUEUES=(
    "competition_winner_generation_queue:winner_trigger"
)

# --- Wait for RabbitMQ Management Plugin to be Ready ---
echo "--- Waiting for RabbitMQ management plugin on ${RABBITMQ_HOST}:${RABBITMQ_PORT} to be ready ---"
until rabbitmqadmin -H "${RABBITMQ_HOST}" -P "${RABBITMQ_PORT}" -u "${RABBITMQ_USER}" -p "${RABBITMQ_PASS}" -q list exchanges > /dev/null 2>&1; do
    echo "RabbitMQ management API not ready yet, waiting... (will retry in 3 seconds)"
    sleep 3
done
echo "RabbitMQ management API is ready. Proceeding with topology setup."

# --- Declare Submission Exchange ---
echo "Declaring submission exchange: ${SUBMISSION_EXCHANGE_NAME} (type: direct, durable: true)"
rabbitmqadmin -H "${RABBITMQ_HOST}" -P "${RABBITMQ_PORT}" -u "${RABBITMQ_USER}" -p "${RABBITMQ_PASS}" \
    declare exchange name="${SUBMISSION_EXCHANGE_NAME}" type=direct durable=true || true

# --- Declare Submission Queues and Bind them to the Submission Exchange ---
for Q_DEF in "${SUBMISSION_QUEUES[@]}"; do
    IFS=':' read -r QUEUE_NAME ROUTING_KEY <<< "$Q_DEF"

    echo "Declaring queue: ${QUEUE_NAME} (durable: true, x-max-priority: ${MAX_PRIORITY})"
    rabbitmqadmin -H "${RABBITMQ_HOST}" -P "${RABBITMQ_PORT}" -u "${RABBITMQ_USER}" -p "${RABBITMQ_PASS}" \
        declare queue name="${QUEUE_NAME}" durable=true \
        arguments='{"x-max-priority": '$MAX_PRIORITY'}' || true

    echo "Binding queue '${QUEUE_NAME}' to exchange '${SUBMISSION_EXCHANGE_NAME}' with routing key '${ROUTING_KEY}'"
    rabbitmqadmin -H "${RABBITMQ_HOST}" -P "${RABBITMQ_PORT}" -u "${RABBITMQ_USER}" -p "${RABBITMQ_PASS}" \
        declare binding source="${SUBMISSION_EXCHANGE_NAME}" destination="${QUEUE_NAME}" destination_type=queue routing_key="${ROUTING_KEY}" || true
done

###### Winner Trigger #######

echo "Declaring Delayed Exchange: ${DELAYED_EXCHANGE_NAME} (type: x-delayed-message, durable: true)"
rabbitmqadmin -H "${RABBITMQ_HOST}" -P "${RABBITMQ_PORT}" -u "${RABBITMQ_USER}" -p "${RABBITMQ_PASS}" \
    declare exchange name="${DELAYED_EXCHANGE_NAME}" type=x-delayed-message durable=true arguments='{"x-delayed-type": "direct"}' || true

# Loop through all winner trigger queues and declare + bind them
for Q_DEF in "${WINNER_TRIGGER_QUEUES[@]}"; do
    IFS=':' read -r QUEUE_NAME ROUTING_KEY <<< "$Q_DEF"

    echo "Declaring Winner Trigger Queue: ${QUEUE_NAME} (durable: true)"
    rabbitmqadmin -H "${RABBITMQ_HOST}" -P "${RABBITMQ_PORT}" -u "${RABBITMQ_USER}" -p "${RABBITMQ_PASS}" \
        declare queue name="${QUEUE_NAME}" durable=true || true

    echo "Binding queue '${QUEUE_NAME}' to delayed exchange '${DELAYED_EXCHANGE_NAME}' with routing key '${ROUTING_KEY}'"
    rabbitmqadmin -H "${RABBITMQ_HOST}" -P "${RABBITMQ_PORT}" -u "${RABBITMQ_USER}" -p "${RABBITMQ_PASS}" \
        declare binding source="${DELAYED_EXCHANGE_NAME}" destination="${QUEUE_NAME}" destination_type=queue routing_key="${ROUTING_KEY}" || true
done

echo "--- RabbitMQ topology setup complete ---"
