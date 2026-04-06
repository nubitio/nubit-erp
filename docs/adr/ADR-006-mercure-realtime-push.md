# ADR-006 — Mercure for Real-Time Server-Sent Events

## Status

Accepted

## Date

2026-04-02

---

## Context

The eFact web POS requires real-time UI updates for several scenarios:

1. **Dashboard KPIs** — Sales total, pending invoices count, and stock alerts update as transactions are processed.
2. **Invoice status** — When a background worker sends an invoice to SUNAT and receives acceptance/rejection, the UI must reflect the new status without the user manually refreshing.
3. **Inventory alerts** — Low-stock notifications must appear in real time.
4. **Multi-tab / multi-device** — A supervisor monitoring the dashboard on one tab should see sales completed by a cashier on another tab/device reflected live.
5. **User notifications** — `UserNotification` events (stock alert, invoice status change) must be pushed to the relevant user's UI immediately.

**Options evaluated:**

| Technology | Notes |
|------------|-------|
| **Polling** | Simple but wasteful; N clients × poll interval = constant DB load regardless of change frequency |
| **WebSocket (e.g. Ratchet, Swoole)** | Bidirectional; overkill for primarily server-to-client push; complex persistent connection management under PHP-FPM; stateful server required |
| **Server-Sent Events (SSE) with custom endpoint** | Unidirectional (server→client); simple; but requires long-lived HTTP connections which PHP-FPM handles poorly (holds worker process) |
| **Mercure** | SSE-based protocol built on top of an HTTP/2 hub; publish/subscribe with JWT authorization; FrankenPHP ships a built-in Mercure hub; `symfony/mercure-bundle` provides first-class integration |

---

## Decision

Use **Mercure** for all real-time server-to-client push, leveraging FrankenPHP's built-in Mercure hub.

### Integration Pattern

**Backend publishes updates via Mercure topics** using API Platform's `mercure: true` annotation on resources:

```php
#[ApiResource(
    // ...
    mercure: true,  // API Platform automatically publishes updates to Mercure on write operations
)]
class SaleRecord { ... }
```

Resources with `mercure: true`:
- `Product`, `SaleRecord`, `TaxDocument`, `CashSession`, `UserNotification`, `VoucherSeries`, `Category`, `PaymentMethod`, `Customer`, `Branch`, `Warehouse`, `Purchase`, `StockItem`, `StockLot`

For custom events (e.g. SUNAT status change triggered by a background worker), the backend publishes to the Mercure hub directly via the `HubInterface`:

```php
$this->hub->publish(
    new Update(
        topics: ["/api/tax-documents/{$id}"],
        data: $serializer->serialize($taxDocument, 'json'),
        private: true,
    )
);
```

**Frontend subscribes** using the `EventSource` API (or the `@mercure/react` hook):

```ts
const eventSource = new EventSource(
  `${mercureUrl}?topic=${encodeURIComponent('/api/tax-documents/' + id)}`,
  { withCredentials: true }
);
eventSource.onmessage = (e) => {
  const updated = JSON.parse(e.data);
  queryClient.setQueryData(['tax-document', id], updated);
};
```

### Authorization

Mercure subscriptions are authorized via a JWT cookie set by the backend at login. The Mercure hub validates this JWT on the SSE connection handshake. Private topics (e.g. `/api/user-notifications`) are only accessible to the specific user's JWT.

### FrankenPHP Integration

Because FrankenPHP ships Mercure as a built-in module (via Caddy), there is no separate Mercure service to deploy. The `Caddyfile` enables the Mercure module:

```
{
  order mercure before respond
}

:443 {
  mercure {
    publisher_jwt {env.MERCURE_JWT_SECRET}
    subscriber_jwt {env.MERCURE_JWT_SECRET}
  }
  php_server
}
```

---

## Consequences

### Positive

- **Zero polling overhead** — Clients receive updates only when data changes; no wasted DB queries.
- **No separate service** — FrankenPHP's built-in Mercure hub eliminates a deployment dependency.
- **API Platform native integration** — `mercure: true` on a resource annotation means write operations automatically publish updates; minimal custom code.
- **Standards-based** — SSE is a W3C standard; the browser's `EventSource` API handles reconnection automatically.
- **Scales to multi-device** — Multiple tabs, multiple cashier terminals, and the supervisor dashboard all subscribe independently and receive the same updates.

### Negative

- **Server-to-client only** — Mercure is unidirectional. Client-to-server operations still go through the REST API.
- **SSE limitations** — Some corporate proxies and older browsers may interfere with long-lived SSE connections. The web POS targets modern browsers (Chrome, Firefox, Edge), so this is an acceptable constraint.
- **Topic granularity** — Publishing too broadly (e.g. `/api/sales` for all sales) causes every client to receive updates for every sale. Topic design (per-resource IRI) is important for efficiency.
- **Private topic JWT management** — Mercure's JWT is separate from the API JWT. Key rotation must be coordinated across both.

---

## Related ADRs

- ADR-004 — FrankenPHP (built-in Mercure hub)
- ADR-011 — API Platform (the `mercure: true` annotation is an API Platform feature)
