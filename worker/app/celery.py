from celery import Celery
import app.config as config

app = Celery('python_worker', broker=config.BROKER_URL)
app.conf.update(
    task_routes={
        'app.tasks.*': {'queue': 'default'},
    },
    task_track_started=True,
    task_time_limit=30,
)