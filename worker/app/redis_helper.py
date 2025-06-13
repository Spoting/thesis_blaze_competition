import redis
import datetime
from . import config

def get_redis_client():
    """Establishes and returns a Redis client connection."""
    try:
        r = redis.Redis.from_url(config.SYMFONY_REDIS_URL, decode_responses=True)
        r.ping()
        print(f"[{datetime.datetime.now()}] Successfully connected to Redis.")
        return r
    except redis.exceptions.ConnectionError as e:
        print(f"[{datetime.datetime.now()}] Could not connect to Redis: {e}")
        # In a real application, you might want more robust error handling
        # or propagate the exception to trigger a task retry.
        raise # Re-raise the exception to be handled by the calling task

def set_key(key: str, value: str, ex: int = None, px: int = None, nx: bool = False, xx: bool = False):
    """
    Sets the string value of a key.
    :param key: The key to set.
    :param value: The value to set.
    :param ex: Set the specified expire time, in seconds.
    :param px: Set the specified expire time, in milliseconds.
    :param nx: Only set the key if it does not already exist.
    :param xx: Only set the key if it already exist.
    """
    redis_client = None
    try:
        redis_client = get_redis_client()
        result = redis_client.set(key, value, ex=ex, px=px, nx=nx, xx=xx)
        print(f"[{datetime.datetime.now()}] Redis: Set key '{key}' with value '{value}'. Result: {result}")
        return result
    except Exception as e:
        print(f"[{datetime.datetime.now()}] Redis ERROR: Could not set key '{key}': {e}")
        raise # Re-raise to allow Celery task retry

def get_key(key: str):
    """
    Get the string value of a key.
    :param key: The key to get.
    :return: The value of the key, or None if the key does not exist.
    """
    redis_client = None
    try:
        redis_client = get_redis_client()
        value = redis_client.get(key)
        print(f"[{datetime.datetime.now()}] Redis: Fetched key '{key}'. Value: '{value}'")
        return value
    except Exception as e:
        print(f"[{datetime.datetime.now()}] Redis ERROR: Could not get key '{key}': {e}")
        raise # Re-raise to allow Celery task retry

def increment_key(key: str, amount: int = 1):
    """
    Increments the integer value of a key by the specified amount.
    If the key does not exist, it is set to 0 before performing the operation.
    """
    redis_client = None
    try:
        redis_client = get_redis_client()
        new_value = redis_client.incr(key, amount)
        print(f"[{datetime.datetime.now()}] Redis: Incremented key '{key}' by {amount}. New value: {new_value}")
        return new_value
    except Exception as e:
        print(f"[{datetime.datetime.now()}] Redis ERROR: Could not increment key '{key}': {e}")
        raise # Re-raise to allow Celery task retry

def decrement_key(key: str, amount: int = 1):
    """
    Decrements the integer value of a key by the specified amount.
    If the key does not exist, it is set to 0 before performing the operation.
    """
    redis_client = None
    try:
        redis_client = get_redis_client()
        new_value = redis_client.decr(key, amount)
        print(f"[{datetime.datetime.now()}] Redis: Decremented key '{key}' by {amount}. New value: {new_value}")
        return new_value
    except Exception as e:
        print(f"[{datetime.datetime.now()}] Redis ERROR: Could not decrement key '{key}': {e}")
        raise # Re-raise to allow Celery task retry