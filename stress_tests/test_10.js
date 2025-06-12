import http from 'k6/http';
import { sleep, check } from 'k6';

export const options = {
  vus: 10,      // Number of virtual users (changed from 200 to 10)
  duration: '10s', // How long the test should run with these 10 users
  insecureSkipTLSVerify: true, // Skip SSL cert errors (useful for localhost HTTPS)
  thresholds: {
    'http_req_duration': ['p(95)<500'], // 95th percentile of response times should be below 500ms
    'http_req_failed': ['rate<0.01'],  // Less than 1% of requests should fail
  },
};

export default function () {
  const res = http.get('https://symfony.localhost/'); // *** IMPORTANT: Replace with the actual URL you want to test ***

  check(res, {
    'is status 200': (r) => r.status === 200,
  });
  // sleep(1); // Simulate some user think time
}