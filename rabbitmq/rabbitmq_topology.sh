#!/bin/bash
set -e # Exit immediately if a command exits with a non-zero status

RABBITMQ_HOST=${RABBITMQ_HOST:-rabbitmq}
RABBITMQ_PORT=${RABBITMQ_PORT:-15672}
RABBITMQ_USER=${RABBITMQ_USER:-guest}
RABBITMQ_PASS=${RABBITMQ_PASS:-guest}

# --- Dead-Letter Configuration ---
DEAD_LETTER_EXCHANGE_NAME="dead_letter_exchange"
DEAD_LETTER_QUEUE_NAME="dead_letter_queue"
# We'll use a wildcard binding for the dead-letter queue for simplicity,
# and specific routing keys for messages sent to the DLX.
DEAD_LETTER_QUEUE_BINDING_KEY="#"

# --- Submissions Configuration ---
SUBMISSION_EXCHANGE_NAME="submission_exchange"
SUBMISSION_QUEUES=( # Each entry is "QUEUE_NAME:ROUTING_KEY:DEAD_LETTER_ROUTING_KEY_SUFFIX"
    "submission_low_priority_queue:low_priority_submission:submission.low_priority"
    "submission_high_priority_queue:high_priority_submission:submission.high_priority"
)

MAX_PRIORITY=${MESSAGE_QUEUE_MAX_PRIORITY:-5}

# --- Winner Trigger and Competition Status Configuration ---
DELAYED_EXCHANGE_NAME="delayed_competition_status_exchange" # For x-delay functionality
COMPETITION_STATUS_QUEUES=(
    "competition_status_queue:competition_status:competition_status"
    "competition_winner_generation_queue:winner_trigger:winner_trigger"
)

# --- Email Configuration ---
EMAIL_EXCHANGE_NAME="email_exchange"
EMAIL_QUEUES=(
    "email_verification_queue:email_verification:email.verification"
    "email_notification_queue:email_notification:email.notification"
)


# --- Wait for RabbitMQ Management Plugin to be Ready ---
echo "--- Waiting for RabbitMQ management plugin on ${RABBITMQ_HOST}:${RABBITMQ_PORT} to be ready ---"
until rabbitmqadmin -H "${RABBITMQ_HOST}" -P "${RABBITMQ_PORT}" -u "${RABBITMQ_USER}" -p "${RABBITMQ_PASS}" -q list exchanges > /dev/null 2>&1; do
    echo "RabbitMQ management API not ready yet, waiting... (will retry in 3 seconds)"
    sleep 3
done
echo "RabbitMQ management API is ready. Proceeding with topology setup."

# --- Declare Dead-Letter Exchange and Queue ---
echo "Declaring Dead-Letter Exchange: ${DEAD_LETTER_EXCHANGE_NAME} (type: topic, durable: true)"
rabbitmqadmin -H "${RABBITMQ_HOST}" -P "${RABBITMQ_PORT}" -u "${RABBITMQ_USER}" -p "${RABBITMQ_PASS}" \
    declare exchange name="${DEAD_LETTER_EXCHANGE_NAME}" type=topic durable=true || true

echo "Declaring Dead-Letter Queue: ${DEAD_LETTER_QUEUE_NAME} (durable: true)"
rabbitmqadmin -H "${RABBITMQ_HOST}" -P "${RABBITMQ_PORT}" -u "${RABBITMQ_USER}" -p "${RABBITMQ_PASS}" \
    declare queue name="${DEAD_LETTER_QUEUE_NAME}" durable=true || true

echo "Binding Dead-Letter Queue '${DEAD_LETTER_QUEUE_NAME}' to exchange '${DEAD_LETTER_EXCHANGE_NAME}' with routing key '${DEAD_LETTER_QUEUE_BINDING_KEY}'"
rabbitmqadmin -H "${RABBITMQ_HOST}" -P "${RABBITMQ_PORT}" -u "${RABBITMQ_USER}" -p "${RABBITMQ_PASS}" \
    declare binding source="${DEAD_LETTER_EXCHANGE_NAME}" destination="${DEAD_LETTER_QUEUE_NAME}" destination_type=queue routing_key="${DEAD_LETTER_QUEUE_BINDING_KEY}" || true

# --- Declare Submission Exchange ---
echo "Declaring submission exchange: ${SUBMISSION_EXCHANGE_NAME} (type: direct, durable: true)"
rabbitmqadmin -H "${RABBITMQ_HOST}" -P "${RABBITMQ_PORT}" -u "${RABBITMQ_USER}" -p "${RABBITMQ_PASS}" \
    declare exchange name="${SUBMISSION_EXCHANGE_NAME}" type=direct durable=true || true

# --- Declare Submission Queues and Bind them to the Submission Exchange ---
for Q_DEF in "${SUBMISSION_QUEUES[@]}"; do
    IFS=':' read -r QUEUE_NAME ROUTING_KEY DLQ_ROUTING_KEY_SUFFIX <<< "$Q_DEF"
    DLQ_ROUTING_KEY="${DLQ_ROUTING_KEY_SUFFIX}.dead"

    echo "Declaring queue: ${QUEUE_NAME} (durable: true, x-max-priority: ${MAX_PRIORITY}, DLX: ${DEAD_LETTER_EXCHANGE_NAME}, DLRK: ${DLQ_ROUTING_KEY})"
    rabbitmqadmin -H "${RABBITMQ_HOST}" -P "${RABBITMQ_PORT}" -u "${RABBITMQ_USER}" -p "${RABBITMQ_PASS}" \
        declare queue name="${QUEUE_NAME}" durable=true \
        arguments='{"x-max-priority": '$MAX_PRIORITY', "x-dead-letter-exchange": "'${DEAD_LETTER_EXCHANGE_NAME}'", "x-dead-letter-routing-key": "'${DLQ_ROUTING_KEY}'"}' || true

    echo "Binding queue '${QUEUE_NAME}' to exchange '${SUBMISSION_EXCHANGE_NAME}' with routing key '${ROUTING_KEY}'"
    rabbitmqadmin -H "${RABBITMQ_HOST}" -P "${RABBITMQ_PORT}" -u "${RABBITMQ_USER}" -p "${RABBITMQ_PASS}" \
        declare binding source="${SUBMISSION_EXCHANGE_NAME}" destination="${QUEUE_NAME}" destination_type=queue routing_key="${ROUTING_KEY}" || true
done

###### Winner Trigger and Competition Status #######

echo "Declaring Delayed Exchange: ${DELAYED_EXCHANGE_NAME} (type: x-delayed-message, durable: true)"
rabbitmqadmin -H "${RABBITMQ_HOST}" -P "${RABBITMQ_PORT}" -u "${RABBITMQ_USER}" -p "${RABBITMQ_PASS}" \
    declare exchange name="${DELAYED_EXCHANGE_NAME}" type=x-delayed-message durable=true arguments='{"x-delayed-type": "direct"}' || true

# Loop through all competition status queues and declare + bind them
for Q_DEF in "${COMPETITION_STATUS_QUEUES[@]}"; do
    IFS=':' read -r QUEUE_NAME ROUTING_KEY DLQ_ROUTING_KEY_SUFFIX <<< "$Q_DEF"
    DLQ_ROUTING_KEY="${DLQ_ROUTING_KEY_SUFFIX}.dead"

    echo "Declaring Competition Status/Winner Trigger Queue: ${QUEUE_NAME} (durable: true, DLX: ${DEAD_LETTER_EXCHANGE_NAME}, DLRK: ${DLQ_ROUTING_KEY})"
    rabbitmqadmin -H "${RABBITMQ_HOST}" -P "${RABBITMQ_PORT}" -u "${RABBITMQ_USER}" -p "${RABBITMQ_PASS}" \
        declare queue name="${QUEUE_NAME}" durable=true \
        arguments='{"x-dead-letter-exchange": "'${DEAD_LETTER_EXCHANGE_NAME}'", "x-dead-letter-routing-key": "'${DLQ_ROUTING_KEY}'"}' || true

    echo "Binding queue '${QUEUE_NAME}' to delayed exchange '${DELAYED_EXCHANGE_NAME}' with routing key '${ROUTING_KEY}'"
    rabbitmqadmin -H "${RABBITMQ_HOST}" -P "${RABBITMQ_PORT}" -u "${RABBITMQ_USER}" -p "${RABBITMQ_PASS}" \
        declare binding source="${DELAYED_EXCHANGE_NAME}" destination="${QUEUE_NAME}" destination_type=queue routing_key="${ROUTING_KEY}" || true
done

###### Email Verification #######
echo "Declaring email exchange: ${EMAIL_EXCHANGE_NAME} (type: direct, durable: true)"
rabbitmqadmin -H "${RABBITMQ_HOST}" -P "${RABBITMQ_PORT}" -u "${RABBITMQ_USER}" -p "${RABBITMQ_PASS}" \
    declare exchange name="${EMAIL_EXCHANGE_NAME}" type=direct durable=true || true

for Q_DEF in "${EMAIL_QUEUES[@]}"; do
    IFS=':' read -r QUEUE_NAME ROUTING_KEY DLQ_ROUTING_KEY_SUFFIX <<< "$Q_DEF"
    DLQ_ROUTING_KEY="${DLQ_ROUTING_KEY_SUFFIX}.dead"

    echo "Declaring queue: ${QUEUE_NAME} (durable: true, DLX: ${DEAD_LETTER_EXCHANGE_NAME}, DLRK: ${DLQ_ROUTING_KEY})"
    rabbitmqadmin -H "${RABBITMQ_HOST}" -P "${RABBITMQ_PORT}" -u "${RABBITMQ_USER}" -p "${RABBITMQ_PASS}" \
        declare queue name="${QUEUE_NAME}" durable=true \
        arguments='{"x-dead-letter-exchange": "'${DEAD_LETTER_EXCHANGE_NAME}'", "x-dead-letter-routing-key": "'${DLQ_ROUTING_KEY}'"}' || true

    echo "Binding queue '${QUEUE_NAME}' to exchange '${EMAIL_EXCHANGE_NAME}' with routing key '${ROUTING_KEY}'"
    rabbitmqadmin -H "${RABBITMQ_HOST}" -P "${RABBITMQ_PORT}" -u "${RABBITMQ_USER}" -p "${RABBITMQ_PASS}" \
        declare binding source="${EMAIL_EXCHANGE_NAME}" destination="${QUEUE_NAME}" destination_type=queue routing_key="${ROUTING_KEY}" || true
done

echo "--- RabbitMQ topology setup complete ---"