/* global __ENV, __VU */

import http from 'k6/http';
import { check, sleep } from 'k6';

const base = (__ENV.BASE_URL || 'http://localhost:8080').replace(/\/$/, '');
const token = __ENV.AUTH_TOKEN || '';
const tenantHeader = __ENV.TENANT_HEADER || '';

export const options = {
  scenarios: {
    reads: { executor: 'constant-arrival-rate', rate: 20, timeUnit: '1s', duration: '30s', preAllocatedVUs: 10, maxVUs: 40 },
    bounded_import: { executor: 'constant-arrival-rate', exec: 'bounded_import', rate: 1, timeUnit: '1s', duration: '30s', preAllocatedVUs: 2, maxVUs: 5, startTime: '5s' },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    'http_req_duration{workload:read}': ['p(95)<750'],
    'http_req_duration{workload:import}': ['p(95)<1500'],
  },
};

function headers() {
  const result = { Authorization: `Bearer ${token}`, Accept: 'application/json' };
  if (tenantHeader) result['X-Tenant'] = tenantHeader;
  return result;
}

export default function () {
  const response = http.get(`${base}/api/products?itemsPerPage=20`, { headers: headers(), tags: { workload: 'read' } });
  check(response, { 'read is not a server error': (r) => r.status < 500 });
  sleep(0.1);
}

export function bounded_import() {
  const rows = Array.from({ length: 250 }, (_, index) => ({ ruc: `k6-${__VU}-${index}`, name: `Load ${index}`, amount: '1.00' }));
  const response = http.post(`${base}/api/v1/onboarding/receivables`, JSON.stringify({ rows }), { headers: { ...headers(), 'Content-Type': 'application/json' }, tags: { workload: 'import' } });
  check(response, { 'import is bounded': (r) => r.status === 200 || r.status === 422 });
}
