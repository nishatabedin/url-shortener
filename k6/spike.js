import http from 'k6/http';
import { check, sleep } from 'k6';

const baseUrl = __ENV.K6_BASE_URL || 'http://localhost:8082';
const apiKey = __ENV.K6_API_KEY || 'local-dev-key';

export const options = {
  stages: [
    { duration: '30s', target: 5 },
    { duration: '30s', target: 50 },
    { duration: '1m', target: 50 },
    { duration: '30s', target: 0 },
  ],
};

export default function () {
  const payload = JSON.stringify({
    original_url: `https://example.com/spike/${__VU}/${__ITER}`,
    expires_in_days: 1,
  });

  const res = http.post(`${baseUrl}/api/v1/shorten`, payload, {
    headers: {
      'Content-Type': 'application/json',
      'X-API-Key': apiKey,
      'Idempotency-Key': `spike-${__VU}-${__ITER}`,
    },
  });

  check(res, {
    'shorten created': (r) => r.status === 201,
  });

  sleep(0.2);
}
