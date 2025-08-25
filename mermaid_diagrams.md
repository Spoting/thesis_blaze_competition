#  4.1 Ροή Υποβολής και Επαλήθευσης Χρήστη
## Sequence Diagram
```mermaid
sequenceDiagram
    participant PU as Public User
    participant WA as WebApp (Symfony)
    participant RED as Redis
    participant RMQ_E as RabbitMQ (Email Queue)
    participant MW as Email Worker
    participant PUE as Public User Email Client
    participant RMQ_S as RabbitMQ (Submission Queue)

    PU->>WA: Submit Competition Form (email, form_data)
    activate WA
    WA->>RED: GET SubmissionLockKey 'competition:{competition_id}:submission:{email}'
    alt SubmissionLockKey Not Found
        WA->>RED: SET VerificationTokenKey 'verification_token:{competition_id + email}' (ttl=2min)
        WA->>RED: SET SubmissionLockKey (ttl=2min)
        WA->>RMQ_E: Publish Message: SendVerificationEmail(email, token)
    else SubmissionLockKey Found
        WA-->>PU: Display Error: Already Submitted
    end
    deactivate WA

    activate MW
    RMQ_E-->>MW: Deliver Message
    MW->>PUE: Send Email with Token (Link)
    deactivate MW

    PUE->>PU: Email Received
    PU->>WA: Enter Token in Verify Form (email, token)
    activate WA
    WA->>RED: GET 'VerificationTokenKey'
    WA->>WA: Validate based on Email and Token
    alt Token-Email Valid
        WA->>RED: DEL 'VerificationTokenKey'
        WA->>RED: PERSIST SubmissionLockKey TTL=competition_end_date
        note over WA: Calculate if SubmissionMessage will be Published <br> to Low Priority Queue <br> or to High Queue with x-max-priority
        WA->>WA: Identify Message Priority/Routing based on CompetitionEndDate.
        WA->>RMQ_S: Publish Message: SubmissionMessage(email, form_data)
        WA->>RED: INCR CompCount 'competition:{competition_id}:submission:count'
    else Token-Email Invalid
        WA-->>PU: Display Error: Invalid Token
    end
    deactivate WA
```

# 4.2 Επεξεργασία Υποβολών (Batch Process, Bulk Insert, DLQ)
## Sequence Diagram
```mermaid
sequenceDiagram

    participant RMQ_S as RabbitMQ (Submission Queue)
    participant SW as Submission Worker
    participant DB as PostgreSQL Database
    participant RED as Redis
    participant RMQ_E as RabbitMQ (Email Queue)
    participant MW as Email Worker
    participant RMQ_DLQ as RabbitMQ Dynamic DLQ

    
    RMQ_S->>SW: Push Messages (Batch: Prefetch=50)

    activate SW
    %% note over SW: Process Batch
    SW->>DB: BEGIN Transaction
    %% activate DB
    %% note over SW: 
    SW->>DB: Raw SQL Bulk INSERT ...<br>ON CONFLICT DO NOTHING
    
    alt No errors during insert
        DB->>SW: COMMIT Transaction
        DB-->>SW: Returns inserted Emails
        note over SW: Identify inserted and duplicate Submissions
        loop For each successfully inserted email
            SW->>RMQ_E: Publish Message: SuccessEmail
            RMQ_E->>MW: Send Notification Email
        end
        loop For each duplicate email
            SW->>RED: DECR SubmissionCount
            SW->>RMQ_E: Publish Message: DuplicateEmail
            RMQ_E->>MW: Send Notification Email
        end
        SW->>RMQ_S: ACK all messages in batch
    else Errors Occurs
        %% deactivate DB
        DB->>SW: ROLLBACK Transaction
        SW->>RMQ_S: NACK all messages in batch
        note over SW: Messenger retries messages...
        loop Max Retries Occurs
            note over SW: WorkerSubscriber is triggered
            %% SW->>RS: DECR SubmissionCount
            %% SW->>RMQ_E: Publish Message: FailedEmail
            %% SW->>RMQ_DLX: Publish Failed Message
            SW->>RED: DECR SubmissionCount 'competition:{competition_id}:submission:count'
            SW->>RED: DEL SubmissionLockKey 'competition:{competition_id}:submission:{email}'
            note over RMQ_DLQ: Store Failed Submissions
            SW->>RMQ_DLQ: Create DLQ dlq_{competition_id} 
            SW->>RMQ_DLQ: Publish Message to DLQ
            SW->>RMQ_E: Publish Message: FailedEmail
            RMQ_E->>MW: Send Notification Email
        end
    end
    deactivate SW
```

# 4.3 Αυτοματοποίηση Κατάστασης Διαγωνισμών
## Sequence
```mermaid
sequenceDiagram
    participant OU as Users

    participant WA as WebApp (Symfony)
    participant CS as Competition Subscriber
    participant PG as PostgreSQL

    participant RMQ_D as RabbitMQ (x-delay Exchange)
    participant CSW as Competition Status Worker
    participant RED as Redis
    participant MER as Mercure
    participant RMQ_E as RabbitMQ (Email Queue)
    participant MW as Email Worker

    OU->>WA: Organizer User <br> Update Competition
    activate WA
    WA->>PG: UPDATE Competition
    PG-->>WA: Success
    deactivate WA
    activate CS
    WA-->>CS: CompetitionSubscriber is triggered

    CS->>CS: shouldDispatchStatusUpdateMessages <br> (start_date|end_date is Updated or new Status='scheduled')
    deactivate CS
    alt shouldDispatchStatusUpdateMessages=true 
        activate CS
        CS->>RMQ_D: Publish x-delay Message: UpdateStatus <br>(type: "start", competition_id) @delay CompetitionStartTime
        CS->>RMQ_D: Publish x-delay Message: UpdateStatus <br>(type: "end", competition_id) @delay CompetitionEndTime
        CS->>RMQ_D: Publish x-delay Message: UpdateStatus <br>(type: "archived", competition_id) @delay CompetitionEndTime + ArchivedPeriod
        CS->>RMQ_D: Publish x-delay Message: WinnerGenerationTrigger <br>(competition_id) @delay CompetitionEndTime + GracePeriod
        deactivate CS
        
        activate CSW
        RMQ_D-->>CSW: Deliver Message: UpdateStatus
        CSW-->>CSW: Validate Status Transitition <br> (isCurrentStatusLowerThanNew && !isStatusTransitionValid)
        alt Transitition Valid 
            CSW->>PG: UPDATE Competition Status
            PG-->>CSW: Success
            CSW-->>CS: CompetitionSubscriber is triggered
            CSW->>RMQ_D: ACK Mesage
            CSW->>RMQ_E: Publish Message: SuccessEmail
            RMQ_E->>MW: Send Notification Email
        else Transitition NOT Valid
            alt CurrentStatusEqualOrHigherThanNew=false
                CSW->>RMQ_D: NACK Mesage and Retry
                note over CSW: Messenger retries messages...
                loop Max Retries Occurs
                note over CSW: WorkerSubscriber is triggered
                    CSW->>RMQ_E: Publish Message: FailedEmail
                    RMQ_E->>MW: Send Notification Email
                end
            else CurrentStatusEqualOrHigherThanNew=true
                CSW->>RMQ_D: NACK Mesage and do NOT Retry
                note over CSW: WorkerSubscriber is triggered
                CSW->>RMQ_E: Publish Message: FailedEmail
                RMQ_E->>MW: Send Notification Email
            end
        end
        deactivate CSW
    end
```

# 4.4 Real Time Ενημερώσεις Competition και Announcements

## Sequence
```mermaid
sequenceDiagram
    participant U as Users
    participant CSW as Competition Status Worker
    participant PG as PostgreSQL
    %% participant WA as WebApp (Symfony)
    participant CS as Competition Subscriber
    participant MER as Mercure
    participant RED as Redis

    alt Organizer User Updates Competition
        U->>PG:  UPDATE Competition
    else Status Transitition StatusUpdateMessage
        CSW->>PG: UPDATE Competition
    end

    activate CS
    PG-->>CS: CompetitionSubscriber is triggered
    CS->>MER: Publish Event: CompetitionListUpdate
    MER-->>U: Real-Time Update Competition List to Public User 


    activate CS
    CS->>CS: Check if Status is Updated
    alt Status Updated
        CS->>RED: addToList global_announcements Key
        CS->>RED: trimList global_announcements Key
        CS->>MER: Publish Event: AnnouncementUpdate
        MER-->>U: Real-Time Update Announcement Sections to Public Users
    end
    deactivate CS
```


# 4.5 Real Time Ενημερώσεις Chart & Καταγραφή Στατιστικών Διαγωνσμού.
## Sequence
```mermaid
sequenceDiagram
    participant U as Orgnizer User
    participant CSW as Competition Status <br> Worker
    participant SC as Scheduler<br>(Cronjob)
    participant PG as PostgreSQL
    %% participant WA as WebApp (Symfony)
    participant CS as Competition Subscriber
    participant MER as Mercure
    participant RED as Redis
    participant RAQ as RabbitMQ DLQ

    alt Organizer User Updates Competition
        U->>PG:  UPDATE Competition
    else Status Transitition StatusUpdateMessage
        CSW->>PG: UPDATE Competition
    end

    activate CS
    PG-->>CS: CompetitionSubscriber is triggered
    CS->>CS: Check if Status is Updated
    alt Status Updated
        CS->>PG: INSERT CompeititionStatusTransistion <br> (currentStatus, newStatus)
        PG-->>CS: Success
        %% CS->>MER: Publish Event: ChartDataUpdate 
        %% MER-->>U: Real-Time Update Competition Chart to Organizer User 
        CS->>MER: Publish Event: ChartStatusAnnotationUpdate
        activate MER
        MER-->>U: Real-Time Update Competition Chart to Organizer User 
        deactivate MER
    end
    deactivate CS
    activate SC
    note over SC: Creates CompetitionStatSnapshot at Interval.
    SC->>RED: GET CompCountKey competition:{competition_id}:submission:count
    RED-->>SC: Return InitiatedSubmissions
    SC->>PG: SELECT count from Submission
    PG-->>SC: Return ProcessedSubmissions
    SC->>RAQ: GET Queue Length of DLQ (competitiond_id)
    RAQ-->>SC: Return FailedSubmissions
    activate PG
    SC->>PG: INSERT CompeititionStatSnapshot <br> (InitiatedSubmissions, ProcessedSubmissions, FailedSubmissions)
    PG-->>SC: Sucess 
    deactivate SC
    
    CS->>MER: Publish Event: ChartDataUpdate 
    activate MER
    MER-->>U: Real-Time Update Competition Chart to Organizer User 
    deactivate MER
```

# 4.6 Δημιουργία Τυχαίων Νικητών
## Sequence
```mermaid
sequenceDiagram
    participant RMQ_S as "RabbitMQ (Status Queue)"
    participant WGW as "Winner Generation Worker"
    participant DB as "PostgreSQL Database"
    participant RED as "Redis"
    participant RMQ_E as "RabbitMQ (Email Queue)"
    participant EW as "Email Worker"

    RMQ_S->>WGW: Deliver Message:<br>WinnerTriggerMessage
    activate WGW
    WGW->>DB: Get processed submissions count
    DB-->>WGW: Return Processed count
    WGW->>RED: Get initiated submissions count
    RED-->>WGW: Return Initiated count
    alt Initiated < Processed
        WGW->>RED: Heal Submission Counter "SET CompCountKey Key (Processed Count)
    end
    note over WGW: Check if all Submissions are Processed
    alt Initiated == Processed
        note over WGW: Execute Reservoir Sampling
        WGW->>DB: Iterate submissions
        activate DB
        DB-->>WGW: Return winning submissions
        deactivate DB
        
        WGW->>DB: BEGIN Transaction
        activate DB
        note over WGW: Create Winner entities
        WGW->>DB: INSERT multiple Winner records
        WGW->>DB: UPDATE Competition status
        DB->>WGW: COMMIT Transaction
        deactivate DB
        loop For each winner
            WGW->>RMQ_E: Publish Message: WinnerEmail
        end
        WGW->>RMQ_E: Publish Message: OrganizerEmail
        RMQ_E->>EW: Send Notification Email
        WGW->>RMQ_S: ACK
    else Initiated > Processed
          WGW->>RMQ_S: NACK Mesage
            note over WGW: Messenger retries messages...
            loop Max Retries Occurs
            note over WGW: WorkerSubscriber is triggered
                WGW->>RMQ_E: Publish Message: FailedEmail
                RMQ_E->>EW: Send Notification Email
            end
    end
    deactivate WGW
```