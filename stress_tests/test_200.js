import http from 'k6/http';
import { check } from 'k6';

export const options = {
  // Using an 'per-vu-iterations' executor
  scenarios: {
    single_request_per_user: {
      executor: 'per-vu-iterations',
      vus: 100,          // Total number of virtual users (unique users)
      iterations: 10,      // Each virtual user will perform exactly 1 iteration (1 request)
      maxDuration: '1m',  // Max duration for the scenario (should finish much faster)
    },
  },
  insecureSkipTLSVerify: true, // Skip SSL cert errors (useful for localhost HTTPS)
  thresholds: {
    'http_req_duration': ['p(95)<500'], // 95th percentile of response times should be below 500ms
    'http_req_failed': ['rate<0.01'],  // Less than 1% of requests should fail
  },
};

export default function () {
  // Each of the 1000 VUs will execute this function exactly once.
  // No unique data needed for the URL itself, as it's the same for all.

  const targetURL = 'https://symfony.localhost/'; // *** IMPORTANT: Replace with the actual URL you want to test ***

  console.log(`VU ${__VU}: Making request to ${targetURL}`);

  const res = http.get(targetURL);

  check(res, {
    'is status 200': (r) => r.status === 200,
  });

  // No sleep here, as each user makes one request and then exits.
}