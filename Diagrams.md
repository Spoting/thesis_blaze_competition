# User Submission & Verification Flow


## Activity Diagram
invite view/comment:
https://online.visual-paradigm.com/share.jsp?id=3434333637392d31




## Sequence Diagram
invite view/comment: 
https://www.mermaidchart.com/app/projects/30d18021-2b74-4799-801b-ea5c5a38e028/diagrams/c353c5a7-5bf8-44b4-aeb5-81a006723a4d/share/invite/eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJkb2N1bWVudElEIjoiYzM1M2M1YTctNWJmOC00NGI0LWFlYjUtODFhMDA2NzIzYTRkIiwiYWNjZXNzIjoiQ29tbWVudCIsImlhdCI6MTc1MDE0NjM5Mn0.zRCRn5aPKWkul4P0DJEh61NWcosEQJFrajGcXqrxICI

public view:
https://www.mermaidchart.com/app/projects/30d18021-2b74-4799-801b-ea5c5a38e028/diagrams/c353c5a7-5bf8-44b4-aeb5-81a006723a4d/version/v0.1/edit


```mermaid
sequenceDiagram
    participant PU as Public User
    participant WA as WebApp (Symfony)
    participant RED as Redis
    participant RMQ_E as RabbitMQ (Email Queue)
    participant ME as Mailer Service
    participant PUE as Public User Email
    participant RMQ_S as RabbitMQ (Submission Queue)
    participant SW as Submission Workers (Low, Medium, High)
    participant PG as PostgreSQL
    participant MER as Mercure

    PU->>WA: Submit Competition Form (email, form_data)
    activate WA
    WA->>RED: SET TokenKey 'token:{email}' (token)  (ttl=2min)
    WA->>RED: SET SubmissionLockKey ({competition_id}_{email}:{form_data, competition_end_date} (ttl=2min)
    WA->>RMQ_E: Publish Message: SendVerificationEmail(email, token)
    deactivate WA

    RMQ_E-->>ME: Deliver Message
    activate ME
    ME->>PUE: Send Email with Token (Link)
    deactivate ME

    PUE->>PU: Email Received
    PU->>WA: Enter Token in Verify Form (email, token)
    activate WA
    WA->>RED: GET TokenEmail & Validate based on Email.
    alt Token Valid
        WA->>RED: DEL 'TokenKey'
        WA->>RED: PERSIST 'SubmissionLockKey' TTL=competition_end_date
        WA->>WA: Identify Message Priority based on CompetitionEndDate <br /> to route Message to corresponding Submission Queue ( Low, Med, High ).<br /> In case of High priority, we also calculate 'x-max-priority'
        WA->>RMQ_S: Publish Message: ProcessSubmission(email, form_data)
        WA->>RED: INCR CompCount 'competition:{competition_id}:submission:count'
    else Token Invalid
        WA-->>PU: Display Error: Invalid Token
    end
    deactivate WA

    RMQ_S-->>SW: Deliver Message
    activate SW
    SW->>PG: INSERT INTO Submissions (email, form_data, competition_id)
    alt Success
        SW->>ME: Send Notification Email: Submission Confirmed
        ME-->>PUE: Notification Sent
        SW->>MER: Publish Event: UpdateCompetitionCounters(competition_id, redis_counter, db_count)
        MER->>WA: Update Counter DashBoards
    else False
        SW->>RED: DECR CompCount 'competition:{competition_id}:submission:count'
        SW->>ME: Send Notification Email: Submission Failed
        ME-->>PUE: Notification Sent
    end
    deactivate SW
```

# Competition Management & Automation Flow


## Activity Diagram:
invite view/comment:
https://online.visual-paradigm.com/share.jsp?id=3434333637392d34


## Sequence Diagram: 
invite view/comment:
https://www.mermaidchart.com/app/projects/30d18021-2b74-4799-801b-ea5c5a38e028/diagrams/81f9dd5e-33ed-4532-9517-55e3191c7399/share/invite/eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJkb2N1bWVudElEIjoiODFmOWRkNWUtMzNlZC00NTMyLTk1MTctNTVlMzE5MWM3Mzk5IiwiYWNjZXNzIjoiQ29tbWVudCIsImlhdCI6MTc1MDE0Nzg3NH0.jLnhi6j36n3sN7RrzNBqocX2vDyvtz0i_H6hu0YDTvQ

public view:
https://www.mermaidchart.com/app/projects/30d18021-2b74-4799-801b-ea5c5a38e028/diagrams/81f9dd5e-33ed-4532-9517-55e3191c7399/version/v0.1/edit

```mermaid
sequenceDiagram 
    participant OU as Organizer User
    participant WA as WebApp (Symfony)
    participant PG as PostgreSQL
    participant RMQ_D as RabbitMQ (x-delay Exchange)
    participant CSW as Competition Status Worker
    participant WGW as Winner Generation Worker
    participant MER as Mercure
    participant RED as Redis

    OU->>WA: Create/Update Competition (details: start_date, end_date, competition_id)
    activate WA
    WA->>PG: INSERT/UPDATE Competition (competition_details)
    PG-->>WA: Success
    WA->>RMQ_D: Publish x-delay Message: UpdateStatus (type: "start", competition_id) @delay CompetitionStartTime
    WA->>RMQ_D: Publish x-delay Message: UpdateStatus (type: "end", competition_id) @delay CompetitionEndTime
    WA->>RMQ_D: Publish x-delay Message: WinnerGenerationTrigger (competition_id) @delay CompetitionEndTime + GracePeriod
    deactivate WA

    RMQ_D-->>CSW: Deliver Message: UpdateStatus (type: "start", competition_id)
    activate CSW
    CSW->>PG: UPDATE Competition SET status = 'Active' WHERE id = competition_id
    PG-->>CSW: Success
    CSW->>MER: Publish Event: CompetitionStarted(competition_id)
    MER->>WA: Updates Public/Admin Pages
    deactivate CSW

    RMQ_D-->>CSW: Deliver Message: UpdateStatus (type: "end", competition_id)
    activate CSW
    CSW->>PG: UPDATE Competition SET status = 'Ended' WHERE id = competition_id
    PG-->>CSW: Success
    CSW->>MER: Publish Event: CompetitionEnded(competition_id)
    MER->>WA: Updates Public/Admin Pages
    deactivate CSW

    RMQ_D-->>WGW: Deliver Message: WinnerGenerationTrigger (competition_id)
    activate WGW
    WGW->>PG: SELECT count(id) FROM Submissions WHERE competition_id = competition_id
    PG-->>WGW: Submission Count Processed
    WGW->>RED: GET CompCount
    RED-->>WGW: Submissions Count Submitted
    alt Processed >= Submitted
        RED-->>WGW: Submission List
        WGW->>PG: SELECT id FROM Submissions WHERE competition_id = competition_id
        WGW->>WGW: Apply Random Winner Logic
        WGW->>PG: UPDATE Competition SET status = 'WinnersDeclared' WHERE id = competition_id
        WGW->>PG: INSERT INTO Winners (submission_id,competition_id,...)
        PG-->>WGW: Success
        WGW->>MER: Publish Event: WinnersDeclared(competition_id, winner_list)
    else Processed < Submitted
        WGW->>RMQ_D: NACK Winner Message and Retry in 15 seconds
    end
    deactivate WGW
```


## Notes:

A) For Email Notifications:

SW->>ME: Send Notification Email: Submission Confirmed

SW->>ME: Send Notification Email: Submission Failed

We could ideally add them to Email Queue, which a Symfony Worker Consumes. 

If we use Celery as Submission Workers, the effort will be considerable higher since we need to serialize Celery Message to be Acceptable for Symfony Messenger.


B) For Mercure Event:

SW->>MER: Publish Event: UpdateCompetitionCounters(competition_id, redis_counter, db_counter)


We could also use another Queue/Worker so we won't slow down Submission Worker.

If we use Celery, the same technical difficulties apply as A.

We can achieve the same result, but not as efficiently, by using a cronjob at Symfony side to send the Event. 
The idea is, to store a second RedisKey 'competition_id:CountInterval' and the cronjob will check the Interval. If the condition is passed, we will query the count of actual processed Submissions from DB and finally Publish the Message.

