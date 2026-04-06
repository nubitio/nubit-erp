# ADR-007 — AI Assistant and MCP Agent Integration

## Status

Accepted

## Date

2026-04-02

---

## Context

eFact supervisors and business owners need to query their business data in natural language — "¿Cuáles son mis productos con stock bajo esta semana?" or "¿Cuántas facturas están pendientes de envío a SUNAT?" — without navigating complex report screens. Additionally, exposing eFact's business data to external AI agents (e.g. Claude Desktop, GPT plugins) via a standard protocol would create an extensibility surface for power users.

Two integration points are needed:
1. **In-app AI assistant** — A chat interface within the eFact web POS that answers tenant-specific business questions.
2. **MCP endpoint** — A Model Context Protocol endpoint that allows external AI clients to access the same tools.

**Technology choices evaluated:**

| Option | Notes |
|--------|-------|
| Custom API endpoint + raw OpenAI SDK calls | Full control, no dependencies, but requires reimplementing tool-calling loop, streaming, error handling, context management |
| `symfony/ai-bundle` | Official Symfony AI integration; abstracts provider selection, tool registration, streaming; designed for Symfony DI integration |
| LangChain PHP | Less mature, smaller community, less idiomatic Symfony |

For the MCP endpoint:
| Option | Notes |
|--------|-------|
| Custom MCP implementation | Full control but significant protocol implementation work |
| `symfony/mcp-bundle` | Official Symfony MCP bundle; integrates with the same tool registry as `symfony/ai-bundle` |

---

## Decision

Use **`symfony/ai-bundle`** for the in-app AI assistant and **`symfony/mcp-bundle`** for the external MCP endpoint.

### Architecture

```
POST /api/assistant
  → AssistantResource (API Platform)
  → symfony/ai-bundle AiAgent
  → GPT-4o-mini (OpenAI)
  → Tool calls dispatched to registered tools
  → Tool results returned to model
  → Final response streamed back to client
```

```
/mcp  (HTTP, GET/POST per MCP protocol)
  → symfony/mcp-bundle McpServer
  → Same tool registry
  → External AI clients (Claude Desktop, etc.)
```

### Registered Tools

All tools are Symfony services tagged with the AI bundle's tool tag. They are **tenant-scoped** — they use the active tenant's entity manager and the JWT branch context.

| Tool | Capability |
|------|-----------|
| `SaleTools` | Query sales by date range, total, status; summarize daily/weekly sales |
| `ProductTools` | Query product catalog, prices, stock levels |
| `CashRegisterTools` | Query cash session status, opening/closing amounts |
| `DocumentationTools` | RAG over eFact user documentation using TF-IDF `KnowledgeIndex` |
| `ApiReadTool` | Generic read-only API calls against the tenant's API Platform resources |
| `StockAlertTool` | Query products below minimum stock threshold |
| `SunatStatusTool` | Query pending/rejected/accepted SUNAT invoice counts |
| `CustomerIntelligenceTool` | Customer purchase history, top customers, average ticket |

### RAG (Retrieval-Augmented Generation)

`DocumentationTools` uses `symfony/ai-bundle`'s `KnowledgeIndex` with TF-IDF retrieval to answer "how do I..." questions about eFact usage without sending documentation to the LLM on every query.

### Multi-tenancy Safety

Tools run within the tenant HTTP request context. The active tenant's database connection is already switched by `TenantListener` before the AI assistant endpoint is reached. Tools must only query the active tenant's entity manager — they do not accept a tenant ID parameter and cannot be routed to a different tenant's data.

### Model Selection

GPT-4o-mini is chosen for the in-app assistant because:
- Tool-calling is reliable with this model.
- Cost is low for high-frequency assistant queries.
- Response time is acceptable for a chat interface (~1-3s).

The model can be swapped via `symfony/ai-bundle`'s provider abstraction without changing tool code.

---

## Consequences

### Positive

- **Natural language business queries** — Supervisors can ask "¿Qué productos vendí más ayer?" without learning report interfaces.
- **Same tools for in-app and external clients** — `symfony/mcp-bundle` reuses the same tool registry, so MCP clients get identical capabilities.
- **Tenant isolation preserved** — All tool calls are scoped to the active tenant by the existing request context; no special tenant handling needed inside tools.
- **Extensible** — New tools are Symfony services with a tag; adding a new capability requires one class, not wiring in multiple places.

### Negative

- **Bundle maturity** — `symfony/ai-bundle` and `symfony/mcp-bundle` are experimental (`dev-main` required); API changes in these bundles may require adaptation work.
- **LLM cost** — Every assistant query incurs OpenAI API cost. High-volume tenants with frequent assistant use add to platform costs.
- **Hallucination risk** — The AI may provide inaccurate business answers if tool results are ambiguous. Tools must return structured data and clear error messages.
- **No streaming in current implementation** — If the assistant endpoint is synchronous (not streaming), large responses block the HTTP worker. Streaming should be added if response latency becomes an issue.
- **MCP security surface** — The `/mcp` endpoint must be protected by the same JWT firewall as `/api`. External MCP clients must authenticate before accessing tools.

---

## Related ADRs

- ADR-003 — JWT auth (MCP endpoint must be protected by the same JWT firewall)
- ADR-001 — Multi-tenancy (tools are tenant-scoped by the existing TenantListener context)
