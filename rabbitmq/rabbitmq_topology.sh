#!/bin/bash
set -e # Exit immediately if a command exits with a non-zero status

# --- Configuration for rabbitmqadmin ---
# These variables define how rabbitmqadmin connects to your RabbitMQ instance.
# Default values are suitable for local development or Docker Compose setups.
# For Kubernetes, ensure these are correctly passed as environment variables
# from Secrets or ConfigMaps.
RABBITMQ_HOST=${RABBITMQ_HOST:-rabbitmq} # Default to 'rabbitmq' service name
RABBITMQ_PORT=${RABBITMQ_PORT:-15672}  # RabbitMQ Management API port
RABBITMQ_USER=${RABBITMQ_USER:-guest}
RABBITMQ_PASS=${RABBITMQ_PASS:-guest}

# --- Queue and Exchange Names ---
MAIN_EXCHANGE_NAME="submission_exchange"
FAILED_MESSAGES_QUEUE_NAME="failed_messages" # As per your 'failed' transport config

# --- Main Queues Configuration (Name:RoutingKey) ---
# Each entry is "QUEUE_NAME:ROUTING_KEY"
MAIN_QUEUES=(
    "submission_normal_queue:normal"
    "submission_premium_queue:premium"
)

# --- Common Queue Arguments ---
# Max priority level for the queues. RabbitMQ supports up to 255, but 10-20 is common.
MAX_PRIORITY=10

# --- Wait for RabbitMQ Management Plugin to be Ready ---
# It's crucial that RabbitMQ is fully up and its management API is responsive
# before we try to declare exchanges/queues/bindings.
echo "--- Waiting for RabbitMQ management plugin on ${RABBITMQ_HOST}:${RABBITMQ_PORT} to be ready ---"
until rabbitmqadmin -H "${RABBITMQ_HOST}" -P "${RABBITMQ_PORT}" -u "${RABBITMQ_USER}" -p "${RABBITMQ_PASS}" -q list exchanges > /dev/null 2>&1; do
    echo "RabbitMQ management API not ready yet, waiting... (will retry in 3 seconds)"
    sleep 3
done
echo "RabbitMQ management API is ready. Proceeding with topology setup."

# --- Declare Main Exchange ---
echo "Declaring main exchange: ${MAIN_EXCHANGE_NAME} (type: direct, durable: true)"
# 'durable=true' means the exchange will survive a broker restart.
# '|| true' makes the command idempotent; it's safe to run multiple times.
rabbitmqadmin -H "${RABBITMQ_HOST}" -P "${RABBITMQ_PORT}" -u "${RABBITMQ_USER}" -p "${RABBITMQ_PASS}" \
    declare exchange name="${MAIN_EXCHANGE_NAME}" type=direct durable=true || true

# --- Declare Main Queues and Bind them to the Main Exchange ---
for Q_DEF in "${MAIN_QUEUES[@]}"; do
    # Split the string into QUEUE_NAME and ROUTING_KEY
    IFS=':' read -r QUEUE_NAME ROUTING_KEY <<< "$Q_DEF"

    # Declare the queue with max priority
    echo "Declaring queue: ${QUEUE_NAME} (durable: true, x-max-priority: ${MAX_PRIORITY})"
    rabbitmqadmin -H "${RABBITMQ_HOST}" -P "${RABBITMQ_PORT}" -u "${RABBITMQ_USER}" -p "${RABBITMQ_PASS}" \
        declare queue name="${QUEUE_NAME}" durable=true \
        arguments='{"x-max-priority": '$MAX_PRIORITY'}' || true

    # Bind the queue to the main exchange
    echo "Binding queue '${QUEUE_NAME}' to exchange '${MAIN_EXCHANGE_NAME}' with routing key '${ROUTING_KEY}'"
    rabbitmqadmin -H "${RABBITMQ_HOST}" -P "${RABBITMQ_PORT}" -u "${RABBITMQ_USER}" -p "${RABBITMQ_PASS}" \
        declare binding source="${MAIN_EXCHANGE_NAME}" destination="${QUEUE_NAME}" destination_type=queue routing_key="${ROUTING_KEY}" || true
done

# --- Declare Failed Messages Queue (for Symfony's 'failed' transport) ---
echo "Declaring Failed Messages Queue: ${FAILED_MESSAGES_QUEUE_NAME} (durable: true)"
rabbitmqadmin -H "${RABBITMQ_HOST}" -P "${RABBITMQ_PORT}" -u "${RABBITMQ_USER}" -p "${RABBITMQ_PASS}" \
    declare queue name="${FAILED_MESSAGES_QUEUE_NAME}" durable=true || true

#############
# --- Winner Trigger Configuration ---
DELAYED_EXCHANGE_NAME="delayed_winner_exchange" # For x-delay functionality
WINNER_TRIGGER_QUEUE_NAME="competition_winner_generation_queue"
WINNER_TRIGGER_ROUTING_KEY="winner_trigger"

# Declare Delayed Exchange for Winner Triggers (requires rabbitmq_delayed_message_exchange plugin)
echo "Declaring Delayed Exchange: ${DELAYED_EXCHANGE_NAME} (type: x-delayed-message, durable: true)"
rabbitmqadmin -H "${RABBITMQ_HOST}" -P "${RABBITMQ_PORT}" -u "${RABBITMQ_USER}" -p "${RABBITMQ_PASS}" \
    declare exchange name="${DELAYED_EXCHANGE_NAME}" type=x-delayed-message durable=true arguments='{"x-delayed-type": "direct"}' || true

# Declare Winner Trigger Queue
echo "Declaring Winner Trigger Queue: ${WINNER_TRIGGER_QUEUE_NAME}" \
     "(durable: true, dead-lettered to ${DLX_EXCHANGE_NAME}/${FAILED_WINNER_TRIGGER_ROUTING_KEY})"
rabbitmqadmin -H "${RABBITMQ_HOST}" -P "${RABBITMQ_PORT}" -u "${RABBITMQ_USER}" -p "${RABBITMQ_PASS}" \
    declare queue name="${WINNER_TRIGGER_QUEUE_NAME}" durable=true \
    # arguments='{"x-dead-letter-exchange": "'$DLX_EXCHANGE_NAME'", "x-dead-letter-routing-key": "'$FAILED_WINNER_TRIGGER_ROUTING_KEY'"}' || true

# Bind Winner Trigger Queue to Delayed Exchange
echo "Binding queue '${WINNER_TRIGGER_QUEUE_NAME}' to delayed exchange '${DELAYED_EXCHANGE_NAME}' with routing key '${WINNER_TRIGGER_ROUTING_KEY}'"
rabbitmqadmin -H "${RABBITMQ_HOST}" -P "${RABBITMQ_PORT}" -u "${RABBITMQ_USER}" -p "${RABBITMQ_PASS}" \
    declare binding source="${DELAYED_EXCHANGE_NAME}" destination="${WINNER_TRIGGER_QUEUE_NAME}" destination_type=queue routing_key="${WINNER_TRIGGER_ROUTING_KEY}" || true


echo "--- RabbitMQ topology setup complete ---"