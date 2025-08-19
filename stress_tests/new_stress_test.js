import http from 'k6/http';
import { check, sleep } from 'k6';
import { randomIntBetween } from 'k6/crypto'; // 1. Import the helper


// --- Test Configuration ---
export const options = {
  insecureSkipTLSVerify: true,
  scenarios: {
    // A single scenario named 'form_submission_load'
    form_submission_load: {
      executor: 'constant-arrival-rate', // Start new iterations at a constant rate
      rate: 200,          // The target number of iterations to start
      timeUnit: '2s',      // The interval for the rate (i.e., 1000 iterations per 1 second)
      duration: '30s',     // How long to maintain this rate
      preAllocatedVUs: 400, // Number of VUs to pre-allocate. Must be high enough to handle the rate.
      maxVUs: 600,        // A hard limit on the number of VUs that can be running
    },
  },
};

// The main function that each virtual user will execute repeatedly.
export default function () {
  const competitionId = 457;
  const baseUrl = 'https://symfony.localhost';

  // Generate unique data for each iteration
  const uniqueEmail = `user-${__VU}-${__ITER}@test.com`;

  const uniquePhoneNumber = `user-${__VU}-iter-${__ITER}-${Date.now()}`;
  // const uniquePhoneNumber = `69${getRandomInt(10000000, 99999999)}`;

  // --- Step 1: Initial Form Submission ---
  const submitUrl = `${baseUrl}/competition/${competitionId}/submit`;
  const submitPayload = {
    'submission[email]': uniqueEmail,
    'submission[phoneNumber]': uniquePhoneNumber,
    'submission[competition_id]': competitionId,
  };
  const submitRes = http.post(submitUrl, submitPayload);
  check(submitRes, { 'Step 1 - Initial submission redirects successfully': (r) => r.status === 200 });

  // --- Step 2: Visit the Verification Page ---
  const verifyUrl = `${baseUrl}/verify/${uniqueEmail}`;
  const verifyPageRes = http.get(verifyUrl);
  check(verifyPageRes, { 'Step 2 - Verification page loads successfully': (r) => r.status === 200 });

  // --- Step 3: Submit the Verification Token (Phone Number) ---
  const verifyPayload = { 'verification_token[token]': uniquePhoneNumber };
  const finalRes = http.post(verifyUrl, verifyPayload, { cookies: verifyPageRes.cookies });
  const step3Check = check(finalRes, { 'Step 3 - Verification submission is successful': (r) => r.status === 200 });
  
    // --- ADDED: Log details if the check fails ---
  if (!step3Check) {
    console.error(
      `Step 3 failed for user ${uniqueEmail}. Status: ${finalRes.status}. Body: ${finalRes.body}`
    );
  }
  // The sleep is less critical in arrival-rate scenarios but can still be useful
  // to prevent a single VU from immediately looping if an iteration finishes fast.
  // sleep(1);
}