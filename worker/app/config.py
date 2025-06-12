import os
from dotenv import load_dotenv

# Load environment variables from .env file
# This line is for local development when running Python scripts directly.
# In Docker/Kubernetes, these variables are typically injected by the environment.
load_dotenv()

# RabbitMQ / Celery Broker Configuration
RABBITMQ_HOST = os.getenv('RABBITMQ_HOST', 'rabbitmq') # 'rabbitmq' is the service name in docker-compose
RABBITMQ_USER = os.getenv('RABBITMQ_USER', 'guest')
RABBITMQ_PASSWORD = os.getenv('RABBITMQ_PASSWORD', 'guest')
RABBITMQ_PORT = os.getenv('RABBITMQ_PORT', '5672')
# RABBITMQ_VHOST = os.getenv('RABBITMQ_VHOST', '/') # Virtual host, typically '/'
MAX_PRIORITY = 10

# Redis Configuration
REDIS_HOST = os.getenv('REDIS_HOST', 'redis') # 'redis' is the service name in docker-compose
REDIS_PORT = os.getenv('REDIS_PORT', '6379')
REDIS_CELERY_DB = os.getenv('REDIS_CELERY_DB', '1') # Redis database number

# PostgreSQL Configuration
POSTGRES_HOST = os.getenv('POSTGRES_HOST', 'database') # 'database' is the service name in docker-compose
POSTGRES_PORT = os.getenv('POSTGRES_PORT', '5432')
POSTGRES_DB = os.getenv('POSTGRES_DB', 'app') # Default DB name from your docker-compose
POSTGRES_USER = os.getenv('POSTGRES_USER', 'app') # Default user from your docker-compose
POSTGRES_PASSWORD = os.getenv('POSTGRES_PASSWORD', '!ChangeMe!') # Default password

# Celery Broker URL (used by Celery to connect to RabbitMQ)
CELERY_BROKER_URL = os.getenv('MESSENGER_TRANSPORT_DSN', f"amqp://{RABBITMQ_USER}:{RABBITMQ_PASSWORD}@{RABBITMQ_HOST}:{RABBITMQ_PORT}/")
# CELERY_BROKER_URL = f"amqp://{RABBITMQ_USER}:{RABBITMQ_PASSWORD}@{RABBITMQ_HOST}:{RABBITMQ_PORT}{RABBITMQ_VHOST}"

# Celery Backend URL (used by Celery to store task results in Redis)
CELERY_RESULT_URL = f"redis://{REDIS_HOST}:{REDIS_PORT}/{REDIS_CELERY_DB}"

# Database URL for SQLAlchemy (used to connect to PostgreSQL)
DATABASE_URL = f"postgresql://{POSTGRES_USER}:{POSTGRES_PASSWORD}@{POSTGRES_HOST}:{POSTGRES_PORT}/{POSTGRES_DB}"