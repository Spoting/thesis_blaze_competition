import http from 'k6/http';
import { check, group } from 'k6';
import encoding from 'k6/encoding';
import { Trend } from 'k6/metrics';

// Define the base URL for the application
const BASE_URL = 'https://symfony.localhost';
// const BASE_URL = 'https://blaze-competition.demo';
const COMPETITION_ID = 323;

// Custom trend metric for measuring end-to-end user journey time
// const endToEndTime = new Trend('end_to_end_journey_time');

// Set up the stress test options.
// The `insecureSkipTLSVerify: true` option is set to handle self-signed SSL certificates.
export let options = {
    insecureSkipTLSVerify: true,
    // Define multiple scenarios to run different types of tests.
    scenarios: {
        one_shot: {
            executor: 'per-vu-iterations',
            vus: 400,             // total concurrent users you want to try
            iterations: 1,        // each user does 1 iteration
            maxDuration: '1m',    // safety limit
            exec: 'default',
        },
    },
    thresholds: {
        http_req_duration: ['p(95)<2000'], // 95% of requests < 1s
        http_req_failed: ['rate<0.05'],    // <5% errors
    },

};

export default function () {
    const startTime = Date.now();
    // Generate unique data for each user and iteration.
    const uniqueEmail = `user-${__VU}-${__ITER}@test.com`;
    // Using a more realistic phone number length (e.g., 10 digits)
    const uniquePhoneNumber = `123456789${__ITER}`;

    // --- Step 1: Landing Page GET ---
    group('1. Visit Landing Page', function () {
        const res = http.get(BASE_URL);
        check(res, {
            'Landing page status is 200': (r) => r.status === 200,
        });
    });

    // --- Step 2: Competition Form GET ---
    group('2. Visit Competition Form', function () {
        const res = http.get(`${BASE_URL}/competition/${COMPETITION_ID}/submit`);
        check(res, {
            'Form page status is 200': (r) => r.status === 200,
        });
    });

    // --- Step 3: Submit Competition Form POST ---
    let identifier;
    group('3. Submit Form', function () {
        // Generate a valid birth date in YYYY-MM-DD format.
        const now = new Date();
        const year = now.getFullYear() - 25; // An age of 25 is safe for most validations
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const birthDate = `${year}-${month}-${day}`;


        // Prepare the form data payload.
        const formData = {
            'submission[email]': uniqueEmail,
            'submission[phoneNumber]': uniquePhoneNumber,
            'submission[birthDate]': birthDate,
            'submission[competition_id]': COMPETITION_ID,
        };

        const res = http.post(
            `${BASE_URL}/competition/${COMPETITION_ID}/submit`,
            formData,
            { headers: { 'Content-Type': 'application/x-www-form-urlencoded' } }
        );

        // Based on analysis, the server responds with 200 and renders the next page directly.
        // We check for a 200 status and a known string from the verification page.
        const isSuccessful = check(res, {
            'Submission post status is 200': (r) => r.status === 200,
            'Response body contains verification form title': (r) => r.body.includes('Verify Your Submission'),
        });

        if (!isSuccessful) {
            console.error(`Submission POST failed with status ${res.status}. Response body: ${res.body.substring(0, 500)}...`);
        }

        // The identifier is part of the redirect URL's query parameters.
        const redirectUrl = res.headers['Location'];
        if (redirectUrl) {
            identifier = redirectUrl.split('identifier=')[1];
        } else {
            // Fallback: Manually create the identifier if the redirect is not captured.
            const identifierObject = {
                competition_id: COMPETITION_ID,
                email: uniqueEmail,
            };
            identifier = encoding.b64encode(JSON.stringify(identifierObject));
        }
    });

    // --- Step 4: Verification Page GET ---
    group('4. Visit Verification Page', function () {
        const res = http.get(`${BASE_URL}/verify?identifier=${identifier}`);
        check(res, {
            'Verification page status is 200': (r) => r.status === 200,
        });
    });

    // --- Step 5: Verify Token POST ---
    group('5. Submit Verification Token', function () {
        // Prepare the verification form data.
        const verificationFormData = {
            'verification_token[token]': uniquePhoneNumber, // Using the unique phone number
            'verification_token[submit]': '',
        };

        const postRes = http.post(
            `${BASE_URL}/verify?identifier=${identifier}`,
            verificationFormData,
            { headers: { 'Content-Type': 'application/x-www-form-urlencoded' } }
        );

        // Based on analysis, the server responds with 200 and renders the success page directly.
        // We check for a 200 status and a known string from the success page.
        const isSuccessful = check(postRes, {
            'Verification post status is 200': (r) => r.status === 200,
            'Response body contains success message title': (r) => r.body.includes('Submission Confirmed!'),
        });

        if (!isSuccessful) {
            console.error(`Verification POST failed with status ${postRes.status}. Response body: ${postRes.body.substring(0, 500)}...`);
        }
    });

    // --- Step 6: Submission Success Page GET ---
    group('6. Visit Success Page', function () {
        // This step is no longer needed since the previous step directly loads the success page.
        // It's commented out to prevent redundant requests.
        const res = http.get(`${BASE_URL}/submission-success`);
        check(res, {
            'Success page status is 200': (r) => r.status === 200,
        });
    });

    // const endTime = Date.now();
    // endToEndTime.add(endTime - startTime);
}
