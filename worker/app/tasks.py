from .celery import app
import redis
import psycopg2
import app.config as config

# Redis client
redis_client = redis.Redis(host=config.REDIS_HOST, port=config.REDIS_PORT, db=config.REDIS_DB)

# DB connection
def get_db_connection():
    return psycopg2.connect(
        host=config.DB_HOST,
        dbname=config.DB_NAME,
        user=config.DB_USER,
        password=config.DB_PASS
    )

@app.task
def process_message(message_id):
    # 1. Lookup data from Redis
    value = redis_client.get(message_id)
    if not value:
        return f"No key {message_id} in Redis"

    # 2. CRUD operation
    conn = get_db_connection()
    cur = conn.cursor()
    cur.execute("INSERT INTO logs (message_id, payload) VALUES (%s, %s)", (message_id, value.decode()))
    conn.commit()
    cur.close()
    conn.close()

    return f"Processed message {message_id}"
