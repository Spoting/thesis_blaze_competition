# Things to Prove.

## Email Priority 
- Specify Types Emails and their corresponding priorities.
- Demonstrate:
    - https://symfony.localhost/test-emails
    - (mailer) http://localhost:8026/






# Demo 2 -- Should be in Kubernetes
< 
what it tests, what will it prove, what are the parameters
- A Long to Show Low Submission Queue Usage
- B Normal to Show a Normal flow
- C purpose is to Fail WinnerGeneration
- D Small with Burst

-- For testing Purposes: We Disabled Notification Mails for Failure/Success of Processing of Competition
-- For testing Purposes: 1 in 10 Batches of Submissions will fail.
-- For testing Purposes: Hardcoded C to Fail WinnerGeneration.
-- For testing Purposes: The number of DelayStatusTransitions are small.
>

- PF to Mailer | RabbitMQ

- Login/ Open Dashboard

- Run
```
php bin/console app:demo-scenario-2 --clear-only; php bin/console app:demo-scenario-2 1500;
```
