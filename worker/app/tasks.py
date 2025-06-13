from .celery import app
import time
import datetime
import json
# from . import config
from . import db_helper
from . import redis_helper


@app.task(bind=True)
def process_normal_submission(self, payload_json: str):
    """Processes a normal submission."""
    try:
        payload = json.loads(payload_json)
        submission_id = payload.get("competitionId")
        data = payload.get("formData")

        print(f"[{datetime.datetime.now()}] Worker {self.request.hostname} - "
              f"Processing normal submission {submission_id} with data: '{payload}'")
        time.sleep(2)
        
        query = f"select * from competition LIMIT 1;"
        result = db_helper.execute_db_query(query, fetch_one=True)
        print(f"DB Query Result: {result}")
        
        print(f"[{datetime.datetime.now()}] Worker {self.request.hostname} - "
              f"Finished normal submission {submission_id}")
        return f"Normal submission {submission_id} processed successfully."
    except Exception as e:
        print(f"[{datetime.datetime.now()}] Error processing normal submission: {e}")
        raise self.retry(exc=e, countdown=5, max_retries=3)

@app.task(bind=True)
def process_premium_submission(self, payload_json: str):
    """Processes a premium submission."""
    try:
        payload = json.loads(payload_json)
        submission_id = payload.get("competitionId")
        data = payload.get("formData")

        print(f"[{datetime.datetime.now()}] Worker {self.request.hostname} - "
              f"Processing premium submission {submission_id} with data: '{payload}'")
        time.sleep(5)
        print(f"[{datetime.datetime.now()}] Worker {self.request.hostname} - "
              f"Finished premium submission {submission_id}")
        return f"Premium submission {submission_id} processed successfully."
    except Exception as e:
        print(f"[{datetime.datetime.now()}] Error processing premium submission: {e}")
        raise self.retry(exc=e, countdown=10, max_retries=5)

@app.task(bind=True)
def trigger_winner_generation(self, payload_json: str):
    """Triggers winner generation for a competition."""
    try:
        payload = json.loads(payload_json)
        competition_id = payload.get("competitionId")

        print(f"[{datetime.datetime.now()}] Worker {self.request.hostname} - "
              f"Triggering winner generation for competition {competition_id} (Delayed Task)")
        time.sleep(3)
        
        # --- Redis Usage Example ---
        redis_key = f"count_competition_submittions_{competition_id}"
        count = redis_helper.get_key(redis_key)
        print(f"[{datetime.datetime.now()}] {redis_key} : {count} in Redis.")

        
        print(f"[{datetime.datetime.now()}] Worker {self.request.hostname} - "
              f"Winner generation completed for competition {competition_id}")
        return f"Winner generation for competition {competition_id} triggered successfully."
    except Exception as e:
        print(f"[{datetime.datetime.now()}] Error triggering winner generation: {e}")
        raise self.retry(exc=e, countdown=15, max_retries=3)
