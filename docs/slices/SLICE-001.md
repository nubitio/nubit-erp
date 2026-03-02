# SLICE-001 — `publicReceiptUrl` debe retornar URL absoluta en `/api/sales`

| Field        | Value                                      |
|--------------|--------------------------------------------|
| id           | SLICE-001                                  |
| status       | done                                       |
| created_at   | 2026-03-02                                 |
| updated_at   | 2026-03-02 (done)                          |
| author       | agt-architect                              |

---

## 1. Purpose & Scope

### In scope
- Corregir el campo `publicReceiptUrl` en el response de `GET /api/sales` y
  `POST /api/sales` (endpoint v2 que usa `SaleRecord`) para que siempre
  devuelva una **URL absoluta** (ej. `https://app.example.com/receipt/eyJ...`)
  en lugar de una URL relativa (ej. `/receipt/eyJ...`).

### Out of scope
- Cambios en el endpoint legacy `Sale` / `TaxDocument` (ya resuelto por
  `PublicReceiptUrlNormalizer` con `ABSOLUTE_URL`).
- Cambios en la lógica de renderizado del receipt.
- Cambios en la autenticación o autorización.

---

## 2. Root-cause analysis

Hay **dos rutas de código** que producen `publicReceiptUrl` para `SaleRecord`:

| Ruta | Archivo | Problema |
|------|---------|----------|
| Enrich-on-read (GET collection & item) | `SaleRecordRepository::enrichWithTaxDocuments()` (línea 111) | Llama a `ReceiptPayload::publicUrl()` que hardcodea `'/receipt/' . payload` — siempre relativa |
| Enrich-after-write (POST response) | `SaleV2Processor::process()` → también llama `enrichWithTaxDocuments()` | Misma causa raíz |
| `ReceiptPayload::publicUrl()` | `src/Core/Util/ReceiptPayload.php` (línea 22) | Construye la URL como path relativo sin acceso al request/router |
| `PublicReceiptUrlNormalizer` | `src/Serializer/PublicReceiptUrlNormalizer.php` | Genera URL absoluta correctamente, pero sólo aplica a `Sale` y `TaxDocument`, **no** a `SaleRecord` |

El `PublicReceiptUrlNormalizer` ya posee el `UrlGeneratorInterface` con
`ABSOLUTE_URL` pero **no normaliza `SaleRecord`**, por lo que su URL es
establecida por el repositorio antes de la serialización y no es sobreescrita.

---

## 3. Domain context

- **Bounded context primario:** Sales / POS
- **Bounded context secundario:** E-Invoicing (TaxDocument / Receipt)
- **Entidades involucradas:** `SaleRecord`, `ReceiptPayload`

---

## 4. User story

> Como consumidor de la API (cliente POS, aplicación móvil, etc.),  
> cuando proceso o consulto una venta con comprobante,  
> quiero recibir en `publicReceiptUrl` una URL absoluta y navegable directamente,  
> para poder mostrarla como enlace o QR sin tener que concatenar el host en el cliente.

---

## 5. API surface (antes → después)

```jsonc
// ANTES (incorrecto)
{
  "publicReceiptUrl": "/receipt/eyJ0eXBlIjoiMDMiLCJpZCI6IjQwY2FjNjQ3..."}
}

// DESPUÉS (correcto)
{
  "publicReceiptUrl": "https://app.efact.pe/receipt/eyJ0eXBlIjoiMDMiLCJpZCI6IjQwY2FjNjQ3..."}
}
```

No hay cambios en la firma del endpoint ni en otros campos.

---

## 6. Preferred solution

Extender `PublicReceiptUrlNormalizer` para que también soporte `SaleRecord`:

1. Añadir `SaleRecord` a `supportsNormalization()` y a `getSupportedTypes()`.
2. Añadir un branch en `buildPayload()` que extraiga el `taxDocumentId` y
   `sunatStatus`/tipo desde el `SaleRecord` ya enriquecido.
3. Sobreescribir el campo `publicReceiptUrl` en el array normalizado con la
   URL absoluta generada por `UrlGeneratorInterface::ABSOLUTE_URL`.

> **Alternativa descartada:** inyectar `UrlGeneratorInterface` en
> `SaleRecordRepository`. Los repositorios no deben depender de la capa HTTP;
> la generación de URLs absolutas pertenece a la capa de serialización.

---

## 7. Domain events

| Evento | Producido por | Consumido por |
|--------|--------------|---------------|
| _(ninguno nuevo)_ | — | — |

Este slice es puramente correctivo en capa de serialización; no introduce
nuevos eventos de dominio.

---

## 8. Data requirements

No se requieren migraciones de base de datos ni cambios en el esquema.

---

## 9. Dependent artifacts

| Artifact | Tipo | Estado |
|----------|------|--------|
| `PublicReceiptUrlNormalizer` | Código existente | En producción |
| `SaleRecord` (entity) | Código existente | En producción |
| `ReceiptPayload` | Código existente | En producción |

---

## 10. Sub-tasks

| # | Descripción | Agente |
|---|-------------|--------|
| T1 | Extender `PublicReceiptUrlNormalizer` para soportar `SaleRecord` | `agt-backend` |
| T2 | Añadir/actualizar test unitario de `PublicReceiptUrlNormalizer` para `SaleRecord` | `agt-backend` |
| T3 | Añadir test funcional/E2E que verifique que `publicReceiptUrl` es absoluta en el response de `POST /api/sales` y `GET /api/sales/{id}` | `agt-qa` |

---

## 11. Acceptance criteria

1. `GET /api/sales/{id}` devuelve `publicReceiptUrl` con esquema
   (`https://` o `http://`) y host — nunca un path relativo.
2. `POST /api/sales` (crear venta con `emitInvoice: true`) devuelve
   `publicReceiptUrl` con URL absoluta.
3. `GET /api/sales` (colección) devuelve `publicReceiptUrl` absoluta en
   cada ítem que tenga comprobante.
4. La URL generada es navegable: `GET <publicReceiptUrl>` devuelve `200 OK`.
5. Los tests unitarios de `PublicReceiptUrlNormalizer` pasan con cobertura
   del nuevo branch `SaleRecord`.
6. `make check` (lint + static analysis + tests) pasa en verde.

---

## 12. Open questions / risks

| # | Pregunta | Riesgo |
|---|----------|--------|
| Q1 | ¿El campo `taxDocumentId` en `SaleRecord` ya contiene el `tax_document_id` (UUID) o el `movimiento_id`? Verificar en `enrichWithTaxDocuments()` línea 107. | Bajo — el código ya lo asigna correctamente como `tax_document_id`. |
| Q2 | ¿Qué tipo (`document_type`) debe usarse para construir el payload cuando se lee desde `SaleRecord`? `SaleRecord` no expone `documentType` directamente; sólo `taxDocumentId`. | Medio — puede necesitarse exponer `documentType` como campo transient en `SaleRecord`, o recuperarlo en el normalizer desde otro mecanismo. |
| Q3 | La URL del receipt se genera en dos momentos: (a) al enriquecer en el repositorio y (b) al normalizar. ¿Se debe limpiar el valor del repositorio para evitar confusión? | Bajo — el normalizer sobreescribirá el valor antes de enviarlo al cliente. |
