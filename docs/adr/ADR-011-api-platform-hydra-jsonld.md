# ADR-011 — API Platform with Hydra/JSON-LD as the API Layer

## Status

Accepted

## Date

2026-04-02

---

## Context

eFact requires a REST API consumed by three clients:
1. **React web POS** — single-page app, heavy CRUD for products, sales, customers, etc.
2. **Android POS** (`efact-app`) — Kotlin Retrofit client syncing sales and fetching product catalog.
3. **External AI clients** — MCP clients and the AI assistant calling read endpoints.

The API must support:
- **CRUD operations** for ~20 entity types with consistent pagination, filtering, and sorting.
- **Custom operations** (invoice generation, SUNAT send, void sale) beyond standard CRUD.
- **OpenAPI documentation** auto-generated from annotations.
- **Mercure real-time push** triggered automatically on write operations.
- **DevExtreme grid integration** — the web frontend's DevExtreme data grids need server-side filtering, sorting, and pagination in a specific format.
- **Serialization groups** — different fields exposed on `GET /api/products` (list) vs `GET /api/products/{id}` (detail).
- **Hypermedia** — IRIs (URLs) as identifiers; related entities referenced by IRI.

**Options evaluated:**

| Option | Notes |
|--------|-------|
| **Custom Symfony controllers + FOSRestBundle** | Full control; significant boilerplate for each endpoint; no auto-generated docs; no built-in Mercure integration |
| **API Platform 4 (chosen)** | Convention-over-configuration CRUD; Hydra/JSON-LD hypermedia; auto OpenAPI; Mercure integration via `mercure: true`; custom State Providers and Processors for non-standard operations |
| **GraphQL only** | Flexible but complex for mobile clients; DevExtreme grid does not natively speak GraphQL |

---

## Decision

Use **API Platform 4.1** as the API layer, with **JSON-LD/Hydra** as the primary hypermedia format and **OpenAPI 3.1** auto-generated docs at `/api/docs`.

### Resource Definition Pattern

Standard CRUD resources use API Platform's `#[ApiResource]` annotation directly on Doctrine entities:

```php
#[ApiResource(
    shortName: 'Product',
    normalizationContext: ['groups' => ['product:read']],
    denormalizationContext: ['groups' => ['product:write']],
    filters: [DevExtremeFilter::class, SearchFilter::class],
    mercure: true,
    paginationEnabled: true,
    paginationItemsPerPage: 25,
)]
#[ORM\Entity]
#[ORM\Table(name: 'producto')]
class Product { ... }
```

Non-entity API operations (auth, dashboard, AI assistant, SUNAT operations) use standalone `#[ApiResource]` classes (ApiResource DTOs in `src/ApiResource/`):

```php
// src/ApiResource/AssistantResource.php
#[ApiResource(
    shortName: 'Assistant',
    operations: [
        new Post(
            uriTemplate: '/assistant',
            processor: AssistantProcessor::class,
        ),
    ],
)]
class AssistantResource { ... }
```

### Custom Operations

Custom operations (beyond GET/POST/PUT/DELETE) are implemented via API Platform State Processors:

```php
// POST /api/sales/{id}/invoice → InvoiceProcessor
// POST /api/sales/{id}/void → VoidSaleProcessor
// POST /api/tax-documents/{id}/send → SendInvoiceProcessor
// GET /api/tax-documents/{id}/consult → ConsultInvoiceProvider
```

### DevExtreme Filter

A custom `DevExtremeFilter` translates DevExtreme's grid filter format (e.g. `filter=["name","contains","shoe"]`) into Doctrine QueryBuilder expressions. This allows the web frontend's data grids to use server-side filtering without custom API endpoints per resource.

### Serialization Groups

```php
#[Groups(['product:read'])]          // exposed on GET /api/products
#[Groups(['product:read', 'product:write'])]  // exposed on GET + POST/PUT/PATCH
#[Groups(['sale:read', 'sale_item:read'])]    // nested in sale response
```

### Mercure Integration

```php
#[ApiResource(mercure: true)]
```

With `mercure: true`, API Platform automatically publishes an SSE update to the Mercure hub after every successful write operation (POST/PUT/PATCH/DELETE). The topic is the resource's IRI (e.g. `/api/products/uuid-123`). See ADR-006 for the SSE subscription architecture.

### Hydra/JSON-LD Response Format

```json
{
  "@context": "/api/contexts/Product",
  "@id": "/api/products/uuid-123",
  "@type": "Product",
  "id": "uuid-123",
  "name": "Coca-Cola 500ml",
  "salePrice": "3.50",
  "category": "/api/categories/5"
}
```

IRIs as identifiers mean the Android app and web frontend can follow links to related resources without hardcoding URL patterns.

### SmartCrud SDUI (see ADR-014)

The web frontend's SmartCrud system (declarative CRUD pages) consumes `GET /api/docs.json` (the OpenAPI spec) to auto-generate form fields, eliminating manual field declarations. This is an extension of the API Platform + Hydra decision — the schema is the single source of truth for both API validation and UI generation.

---

## Consequences

### Positive

- **Automatic OpenAPI docs** — `GET /api/docs` provides a browsable API explorer with all endpoints, schemas, and operations.
- **Mercure integration** — `mercure: true` is a one-line addition to any resource; no custom event dispatch code needed for standard CRUD.
- **Consistent REST contract** — All CRUD resources follow the same URL pattern, pagination format, filter syntax, and serialization conventions.
- **DevExtreme server-side operations** — `DevExtremeFilter` enables grid features (filtering, sorting, pagination) without per-resource custom endpoints.
- **SmartCrud schema inference** — The OpenAPI spec from API Platform drives automatic UI generation (ADR-014, ADR-017).
- **Android/web same API** — No separate "mobile API" needed; the same API Platform endpoints serve both clients.

### Negative

- **Hydra verbosity** — JSON-LD responses include `@context`, `@id`, `@type` on every object. For simple mobile clients, this overhead is unneeded. (Mitigation: JSON-LD can be suppressed with `Accept: application/json` header.)
- **API Platform learning curve** — State Providers, Processors, custom operations, and the Hydra context system require deeper understanding than simple controllers.
- **DTO complexity for custom operations** — Non-CRUD operations (assistant, SUNAT send) require dedicated DTO classes in `src/ApiResource/` which adds boilerplate.
- **Filter proliferation** — DevExtremeFilter, SearchFilter, OrderFilter, etc. must be registered per resource; forgetting a filter means a grid feature silently stops working.

---

## Related ADRs

- ADR-006 — Mercure (`mercure: true` is an API Platform feature)
- ADR-013 — UTC timezone contract (dates in API responses must follow the UTC rule)
- ADR-014 — SmartCrud SDUI (consumes API Platform's Hydra/OpenAPI metadata)
- ADR-017 — SmartCrud canonical field contract (extends ADR-014)
