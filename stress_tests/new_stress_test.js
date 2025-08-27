import http from 'k6/http';
import { check, sleep } from 'k6';
import encoding from 'k6/encoding';

// --- Test Configuration ---
export const options = {
  insecureSkipTLSVerify: true,
  scenarios: {
    // A scenario that simulates a sudden spike
    spike_test: {
      executor: 'ramping-vus', // Ramp up VUs quickly to simulate a spike
      startVUs: 0,
      stages: [
        { duration: '10s', target: 50 },  // Ramp up to 50 VUs in 10 seconds
        { duration: '20s', target: 50 },  // Hold at 50 VUs for 20 seconds
        { duration: '10s', target: 0 },   // Ramp down to 0 VUs in 10 seconds
      ],
      tags: { test_type: 'spike_test' },
    },
  },
};

// The main function that each virtual user will execute repeatedly.
export default function () {
  const competitionId = 364;
  const baseUrl = 'https://blaze-competition.demo';

  // Generate unique data for each iteration
  const uniqueEmail = `user-${__VU}-${__ITER}@test.com`;
  const uniquePhoneNumber = `69${Math.floor(10000000 + Math.random() * 90000000)}`;

  // --- Step 1: Initial Form Submission ---
  const submitUrl = `${baseUrl}/competition/${competitionId}/submit`;
  const submitPayload = {
    'submission[email]': uniqueEmail,
    'submission[phoneNumber]': uniquePhoneNumber,
    'submission[competition_id]': competitionId,
  };
  
  const submitRes = http.post(submitUrl, submitPayload);
  const step1Check = check(submitRes, { 'Step 1 - Initial submission success': (r) => r.status === 200 || r.status === 302 });

  // --- Step 2: Visit the Verification Page ---
  const identifierObject = {
    competition_id: competitionId,
    email: uniqueEmail,
  };
  const verificationIdentifier = encoding.b64encode(JSON.stringify(identifierObject));
  
  const verifyUrl = `${baseUrl}/verify/${verificationIdentifier}`;
  console.log(verifyUrl, uniquePhoneNumber);
  const verifyPageRes = http.get(verifyUrl);
  const step2Check = check(verifyPageRes, { 'Step 2 - Verification page loads': (r) => r.status === 200 });

  // --- Step 3: Submit the Verification Token (Phone Number) ---
  const verifyPayload = { 'verification_token[token]': uniquePhoneNumber };
  const finalRes = http.post(verifyUrl, verifyPayload, { cookies: verifyPageRes.cookies });
  const step3Check = check(finalRes, { 'Step 3 - Verification is successful': (r) => r.status === 200 || r.status === 302 });
  
  if (!step1Check || !step2Check || !step3Check) {
    console.error(
      `Test failed for user ${uniqueEmail}. Statuses: Submit=${submitRes.status}, VerifyPage=${verifyPageRes.status}, FinalPost=${finalRes.status}`
    );
  }

  // Sleep is good practice in spike tests to simulate some delay between user actions
  // and prevent the VUs from looping too quickly while the system is under stress.
  sleep(1);
}
