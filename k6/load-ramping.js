import http from 'k6/http';
import { check, sleep } from 'k6';

const baseUrl = __ENV.K6_BASE_URL || 'http://localhost:8082';
const apiKey = __ENV.K6_API_KEY || 'local-dev-key';

export const options = {
  stages: [
    { duration: '2m', target: 5 },
    { duration: '5m', target: 25 },
    { duration: '2m', target: 0 },
  ],
};

export default function () {
  const payload = JSON.stringify({
    original_url: `https://example.com/${__VU}/${__ITER}`,
    expires_in_days: 30,
  });

  const res = http.post(`${baseUrl}/api/v1/shorten`, payload, {
    headers: {
      'Content-Type': 'application/json',
      'X-API-Key': apiKey,
      'Idempotency-Key': `ramp-${__VU}-${__ITER}`,
    },
  });

  check(res, {
    'shorten created': (r) => r.status === 201,
  });

  sleep(0.5);
}
