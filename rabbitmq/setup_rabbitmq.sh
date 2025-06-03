#!/bin/bash
set -e # Exit immediately if a command exits with a non-zero status

# --- Configuration for rabbitmqadmin ---
# These variables define how rabbitmqadmin connects to your RabbitMQ instance.
# They default to values suitable for Docker Compose's default RabbitMQ setup.
RABBITMQ_HOST=${RABBITMQ_HOST:-rabbitmq} # Default to 'rabbitmq' service name in Docker Compose
RABBITMQ_PORT=${RABBITMQ_PORT:-15672}  # RabbitMQ Management API port
RABBITMQ_USER=${RABBITMQ_USER:-guest}
RABBITMQ_PASS=${RABBITMQ_PASS:-guest}

# --- Wait for RabbitMQ Management Plugin to be Ready ---
# It's crucial that RabbitMQ is fully up and its management API is responsive
# before we try to declare exchanges/queues/bindings.
echo "--- Waiting for RabbitMQ management plugin on ${RABBITMQ_HOST}:${RABBITMQ_PORT} to be ready ---"
until rabbitmqadmin -H "${RABBITMQ_HOST}" -P "${RABBITMQ_PORT}" -u "${RABBITMQ_USER}" -p "${RABBITMQ_PASS}" -q list exchanges > /dev/null 2>&1; do
    echo "RabbitMQ management API not ready yet, waiting... (will retry in 3 seconds)"
    sleep 3
done
echo "RabbitMQ management API is ready. Proceeding with topology setup."

# --- Declare Exchange ---
EXCHANGE_NAME="submission_exchange"
echo "Declaring exchange: ${EXCHANGE_NAME} (type: direct, durable: true)"
# 'durable=true' means the exchange will survive a broker restart.
# '|| true' makes the command idempotent; it won't fail if the exchange already exists.
rabbitmqadmin -H "${RABBITMQ_HOST}" -P "${RABBITMQ_PORT}" -u "${RABBITMQ_USER}" -p "${RABBITMQ_PASS}" \
    declare exchange name="${EXCHANGE_NAME}" type=direct durable=true || true

# --- Declare Queues and Bind them to the Exchange ---
# We use an array for queues, storing "QUEUE_NAME:ROUTING_KEY" pairs.
QUEUES=(
    "submission_high_priority_queue:high"
    "submission_medium_priority_queue:medium"
    "submission_low_priority_queue:low"
)

for Q_DEF in "${QUEUES[@]}"; do
    # Split the string into QUEUE_NAME and ROUTING_KEY
    IFS=':' read -r QUEUE_NAME ROUTING_KEY <<< "$Q_DEF"

    # Declare the queue
    echo "Declaring queue: ${QUEUE_NAME} (durable: true, default: Quorum Queue in RabbitMQ 4.x)"
    # 'durable=true' ensures the queue survives restarts and messages are not lost.
    # In RabbitMQ 4.x, queues are Quorum Queues by default unless specified otherwise.
    rabbitmqadmin -H "${RABBITMQ_HOST}" -P "${RABBITMQ_PORT}" -u "${RABBITMQ_USER}" -p "${RABBITMQ_PASS}" \
        declare queue name="${QUEUE_NAME}" durable=true || true

    # Bind the queue to the exchange
    echo "Binding queue '${QUEUE_NAME}' to exchange '${EXCHANGE_NAME}' with routing key '${ROUTING_KEY}'"
    rabbitmqadmin -H "${RABBITMQ_HOST}" -P "${RABBITMQ_PORT}" -u "${RABBITMQ_USER}" -p "${RABBITMQ_PASS}" \
        declare binding source="${EXCHANGE_NAME}" destination="${QUEUE_NAME}" destination_type=queue routing_key="${ROUTING_KEY}" || true
done

# --- Declare Failed Messages Queue ---
FAILED_QUEUE_NAME="failed_messages"
echo "Declaring failed messages queue: ${FAILED_QUEUE_NAME} (durable: true)"
rabbitmqadmin -H "${RABBITMQ_HOST}" -P "${RABBITMQ_PORT}" -u "${RABBITMQ_USER}" -p "${RABBITMQ_PASS}" \
    declare queue name="${FAILED_QUEUE_NAME}" durable=true || true

echo "--- RabbitMQ topology setup complete ---"