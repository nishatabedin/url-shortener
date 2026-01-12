import http from 'k6/http';
import { check, sleep } from 'k6';

const baseUrl = __ENV.K6_BASE_URL || 'http://localhost:8082';
const apiKey = __ENV.K6_API_KEY || 'local-dev-key';

export const options = {
  vus: 1,
  duration: '30s',
};

export default function () {
  const health = http.get(`${baseUrl}/healthz`);
  check(health, {
    'healthz ok': (r) => r.status === 200,
  });

  const payload = JSON.stringify({
    original_url: 'https://example.com',
    expires_in_days: 7,
  });

  const res = http.post(`${baseUrl}/api/v1/shorten`, payload, {
    headers: {
      'Content-Type': 'application/json',
      'X-API-Key': apiKey,
      'Idempotency-Key': `smoke-${__ITER}`,
    },
  });

  check(res, {
    'shorten created': (r) => r.status === 201,
  });

  sleep(1);
}
