# SLICE-014: SmartCrud Real-time Updates via Mercure — grid auto-refresh cuando otro usuario modifica un recurso

**Status**: approved
**Priority**: P1
**Created**: 2026-03-18
**Updated**: 2026-03-19
**Author**: spec-writer

---

## Overview

Este slice introduce actualizaciones en tiempo real en los grids de SmartCrud mediante el protocolo Mercure (Server-Sent Events). Cuando un usuario modifica, crea o elimina un recurso desde cualquier sesión, todos los demás usuarios que tengan abierta la misma página verán el grid actualizarse automáticamente sin necesidad de recargar la página. La solución se basa en el patrón establecido por `api-platform/admin` y se integra de forma no intrusiva en el sistema SmartCrud existente (21+ páginas).

## Problem Statement

Actualmente, cuando el Usuario A modifica un producto y el Usuario B tiene abierta la misma lista de productos, el grid del Usuario B no refleja el cambio hasta que recarga manualmente la página. En entornos multi-usuario (cajero + administrador, múltiples sucursales), esto genera inconsistencias visibles: precios desactualizados, stock incorrecto, registros que parecen existir pero ya fueron eliminados. El mecanismo de refresh actual (event bus RxJS `SUCCESS`) solo funciona dentro de la misma sesión de navegador.

## Goals

- Instalar y configurar la infraestructura Mercure (hub + bundle Symfony) en el entorno Docker del proyecto.
- Hacer que API Platform publique automáticamente al hub Mercure tras cada operación de escritura (POST/PATCH/DELETE) en los recursos con `mercure: true`.
- Implementar el descubrimiento automático de la URL del hub Mercure desde el header `Link` de las respuestas API, sin configuración manual en el frontend.
- Implementar la suscripción SSE en `SmartCrudPage` de forma que el DevExtreme DataGrid llame a `refresh()` en menos de 500 ms tras recibir un evento Mercure.
- Garantizar degradación graceful: si Mercure no está disponible, el sistema sigue funcionando con el mecanismo de refresh existente (event bus RxJS) sin errores en consola.
- Proveer un mecanismo opt-in/opt-out por recurso mediante la opción `mercure` en `defineResource`.

## Non-Goals (Out of Scope)

- **Sin autenticación de topics**: No se implementa JWT de suscriptor ni topics privados por usuario/rol. El hub opera en modo público para este slice.
- **Sin migración de notificaciones de usuario**: El sistema `useNotifications` (polling cada 30 s) no se migra a Mercure en este slice.
- **Sin actualizaciones en el formulario de edición abierto**: Si un usuario tiene abierto el `DxDialog` de edición de un registro, no recibirá actualizaciones en tiempo real del formulario; solo el grid de fondo se refresca.
- **Sin presencia de usuarios**: No se implementa indicador de "Fulano está editando este registro".
- **Sin topics privados por usuario o rol**: Todos los suscriptores reciben todos los eventos del topic de colección.
- **Sin migración del cliente Android POS**: Este slice es exclusivo del frontend web React.
- **Sin soporte offline-first para eventos SSE**: Los eventos perdidos durante desconexión no se recuperan (solo reconexión automática nativa del browser).

---

## Acceptance Criteria

### AC-1: Header Link con URL del hub presente en respuestas GET
**Given** que el backend está levantado con `symfony/mercure-bundle` configurado y el hub Mercure está corriendo
**When** se hace `GET /api/products` (o cualquier endpoint de colección con `mercure: true`)
**Then** la respuesta HTTP incluye el header `Link: <https://<host>/.well-known/mercure>; rel="mercure"`
**And** el valor de la URL del hub es accesible desde el frontend vía `response.headers['link']`

---

### AC-2: Publicación automática al hub tras PATCH
**Given** que existe un producto con IRI `/api/products/1`
**When** se hace `PATCH /api/products/1` con un body válido (e.g. `{"name": "Nuevo nombre"}`)
**Then** el hub Mercure recibe un mensaje SSE con `topic=/api/products/1` (IRI absoluto o relativo según configuración)
**And** el `data` del mensaje SSE contiene el JSON-LD del recurso actualizado (incluyendo `@id` y `@type`)
**And** la respuesta HTTP del PATCH devuelve `200 OK` con el recurso actualizado

---

### AC-3: Publicación automática al hub tras DELETE
**Given** que existe un producto con IRI `/api/products/1`
**When** se hace `DELETE /api/products/1`
**Then** el hub Mercure recibe un mensaje SSE con `topic=/api/products/1`
**And** el `data` del mensaje SSE contiene un objeto con al menos `{"@id": "/api/products/1", "@type": "Product"}` (o data vacía según configuración de API Platform)
**And** la respuesta HTTP del DELETE devuelve `204 No Content`

---

### AC-4: Descubrimiento automático de la URL del hub (hub discovery)
**Given** que el frontend realiza la primera llamada autenticada a la API (cualquier endpoint)
**When** la respuesta incluye el header `Link` con `rel="mercure"`
**Then** el hook `useMercureHub` extrae la URL del hub y la almacena en el contexto React (`MercureProvider`)
**And** la URL queda disponible para todos los hooks `useMercureSubscription` sin configuración manual en ningún archivo de entorno del frontend
**And** si el header `Link` no está presente (hub no configurado), `useMercureHub` almacena `null` y no lanza ninguna excepción

---

### AC-5: SmartCrudPage abre EventSource al topic de colección al montarse
**Given** que `SmartCrudPage` está configurado con `defineResource({ apiUrl: 'api/products' })` (sin `mercure: false`)
**And** que `useMercureHub` ha descubierto la URL del hub
**When** el componente `SmartCrudPage` se monta
**Then** se abre exactamente un `EventSource` suscrito al topic `<MERCURE_PUBLIC_URL>?topic=<host>/api/products`
**And** el `EventSource` está en estado `OPEN` (readyState === 1) dentro de los primeros 2 segundos

---

### AC-6: Grid se refresca al recibir un evento Mercure
**Given** que `SmartCrudPage` está montado y mostrando la lista de productos
**And** que el `EventSource` está en estado `OPEN`
**When** el hub Mercure emite un evento SSE para el topic `/api/products/{id}` (cualquier id)
**Then** el DevExtreme DataGrid llama a `gridRef.current?.instance().refresh()` en menos de 500 ms tras recibir el evento
**And** el grid muestra los datos actualizados tras completar el re-fetch del `CustomStore`
**And** no se muestra ningún spinner de carga bloqueante (el refresh es transparente para el usuario)

---

### AC-7: Cleanup del EventSource al desmontar el componente
**Given** que `SmartCrudPage` está montado con un `EventSource` en estado `OPEN`
**When** el usuario navega a otra página (el componente se desmonta)
**Then** el `EventSource` se cierra (`eventSource.close()` es llamado en el cleanup del `useEffect`)
**And** no quedan listeners activos ni referencias al `EventSource` anterior (sin memory leaks verificable con DevTools)
**And** no se producen errores en consola relacionados con el EventSource cerrado

---

### AC-8: Degradación graceful cuando Mercure no está disponible
**Given** que la variable de entorno `MERCURE_URL` no está configurada en el backend, o el hub Mercure está caído
**When** `SmartCrudPage` se monta
**Then** el hook `useMercureSubscription` detecta que la URL del hub es `null` y no intenta abrir ningún `EventSource`
**And** el grid funciona normalmente con el mecanismo de refresh existente (event bus RxJS `SUCCESS`)
**And** no aparece ningún error en la consola del navegador relacionado con Mercure
**And** no aparece ningún mensaje de error en la UI para el usuario final

---

### AC-9: Reconexión automática del EventSource tras pérdida de conexión
**Given** que `SmartCrudPage` está montado con un `EventSource` en estado `OPEN`
**When** la conexión de red se interrumpe temporalmente (simulable con DevTools → Network → Offline → Online)
**Then** el browser reconecta automáticamente el `EventSource` (comportamiento nativo del protocolo SSE, sin código adicional)
**And** tras la reconexión, el grid vuelve a recibir actualizaciones en tiempo real
**And** no se duplican los event listeners tras la reconexión

---

### AC-10: Opt-out por recurso mediante `defineResource({ mercure: false })`
**Given** que un recurso se define con `defineResource({ apiUrl: 'api/products', mercure: false })`
**When** `SmartCrudPage` se monta con ese recurso
**Then** NO se abre ningún `EventSource` para ese recurso
**And** el grid funciona normalmente con el mecanismo de refresh existente (event bus RxJS)
**And** la opción `mercure` es `true` por defecto cuando no se especifica (opt-in implícito si el hub está disponible)

---

## Technical Notes

### Infraestructura (INFRA)

**Decisión arquitectónica (OQ-3 cerrada)**: Se usa el **módulo Mercure integrado en FrankenPHP/Caddy**, no un servicio Docker standalone. FrankenPHP ya incluye el módulo Mercure de Caddy en su binario — solo hay que activarlo en el `Caddyfile`. Esto elimina un servicio extra, evita CORS, y la publicación PHP→hub es local (sin salir de la red del contenedor).

- **Hub Mercure**: módulo nativo de Caddy activado en el `Caddyfile` existente (`docker/frankenphp/Caddyfile`). No se añade ningún servicio Docker nuevo.
- **Activación en Caddyfile** (bloque global + directiva de ruta):
  ```caddyfile
  {
      frankenphp { ... }
      mercure {
          publisher_jwt {$MERCURE_JWT_SECRET}
          subscriber_jwt {$MERCURE_JWT_SECRET}
      }
  }

  route {
      # ... config existente ...
      mercure   # ← antes del php_server
      php_server { ... }
  }
  ```
- **Variables de entorno requeridas**:
  - `MERCURE_URL`: URL interna del hub usada por Symfony para publicar. En FrankenPHP integrado: `http://localhost/.well-known/mercure` (loopback dentro del mismo contenedor).
  - `MERCURE_PUBLIC_URL`: URL pública accesible desde el browser: `https://<dominio>/.well-known/mercure`.
  - `MERCURE_JWT_SECRET`: secreto compartido para firmar los JWT de publicación (backend → hub). Mínimo 256 bits.
- **Dev**: en `docker-compose.yml` el servicio `web` ya expone el puerto 80; el hub queda disponible en `http://localhost:8080/.well-known/mercure`. No se añade ningún servicio nuevo al compose.
- **Sin CORS**: el hub está en el mismo dominio/origen que la app, por lo que el browser no necesita preflight para el `EventSource`.

### Backend (API)

- Instalar `symfony/mercure-bundle` vía Composer.
- Añadir `mercure: true` al atributo `#[ApiResource]` de las entidades que deben publicar eventos (e.g. `Product`, `Category`, `Supplier`, etc.).
- API Platform publica automáticamente al hub tras cada operación de escritura exitosa; no se requiere código adicional en controladores ni event listeners.
- El header `Link` con la URL del hub se añade automáticamente a todas las respuestas de los recursos con `mercure: true`.
- API Platform publica al topic del **IRI del item** (e.g. `/api/products/1`), no al topic de colección. El frontend debe suscribirse al topic de colección o usar wildcards según soporte del hub.

### Frontend (UI)

**Nuevos archivos en `assets/core/mercure/`:**

| Archivo | Responsabilidad |
|---------|----------------|
| `MercureManager.ts` | Singleton que gestiona el ciclo de vida de los `EventSource` (crear, reusar, cerrar). Patrón idéntico a `manager.ts` de `api-platform/admin`. |
| `useMercureHub.ts` | Hook que descubre la URL del hub leyendo el header `Link` de las respuestas via interceptor Axios. Almacena la URL en el contexto `MercureProvider`. |
| `useMercureSubscription.ts` | Hook que suscribe al topic de un recurso y ejecuta un callback (e.g. `gridRef.refresh()`) al recibir un evento. Gestiona el cleanup en el `useEffect`. |
| `MercureProvider.tsx` | React context provider que inicializa `MercureManager` con la URL del hub y la expone a los hooks descendientes. Se monta en `EfactApp.tsx`. |

**Integración en `SmartCrudPage`:**

```tsx
// Pseudocódigo — no prescriptivo de implementación
const { gridRef } = useCrudPage(...);
useMercureSubscription(resource.apiUrl, () => {
  gridRef.current?.instance().refresh();
});
```

**Descubrimiento del hub:**
- Un interceptor Axios (response interceptor) lee el header `Link` de la primera respuesta que lo contenga y llama a `setMercureHubUrl(url)` del contexto.
- El interceptor se registra una sola vez en la inicialización de `EfactApp.tsx`.

**Suscripción al topic (OQ-1 cerrada — estrategia de topics):**
- API Platform publica al **IRI del item** (e.g. `/api/products/1`). El hub Mercure de Caddy/FrankenPHP **soporta wildcards** de topic usando URI Templates (RFC 6570).
- El frontend se suscribe al topic de **colección con wildcard**: `https://host/api/products/{id}` — esto captura cualquier cambio en cualquier item de la colección.
- Adicionalmente, para capturar POSTs (nuevos recursos), se suscribe también al topic de colección: `https://host/api/products`.
- API Platform debe configurarse para publicar también al topic de colección en operaciones POST. Esto se logra con la opción `topics` en el atributo `mercure`:
  ```php
  #[ApiResource(mercure: ['topics' => ['@=iri(object)', '@=iri(object, referenceType=UrlGeneratorInterface::ABS_URL, suffix="")']])]
  ```
  O más simple: publicar al IRI del item y dejar que el frontend use el wildcard `{id}` para capturar creates/updates/deletes.
- **Estrategia final**: el frontend abre **un solo `EventSource`** con el topic wildcard `<host>/api/<resource>/{id}`. El hub entrega todos los eventos de items de esa colección.

**DevExtreme CustomStore:**
- `gridRef.current?.instance().refresh()` hace un re-fetch completo de la página actual del `CustomStore`. Este es el comportamiento correcto para colecciones paginadas.
- El refresh no interrumpe la edición en curso en el grid (DevExtreme maneja esto internamente).

### Compatibilidad con el event bus RxJS existente

- `useMercureSubscription` es **aditivo**: no reemplaza el event bus RxJS. Ambos mecanismos coexisten.
- El event bus RxJS sigue siendo el mecanismo de refresh para operaciones realizadas por el propio usuario en la misma sesión.
- Mercure añade el refresh para operaciones realizadas por otros usuarios en otras sesiones.

---

## Dependencies

| Spec/Sistema | Tipo | Notas |
|---|---|---|
| REFAC-002 | `depends-on` | Alias `@core` debe estar limpio antes de añadir nuevos módulos en `assets/core/` |
| `symfony/mercure-bundle` | integración externa | Requiere Symfony 6+ (compatible con Symfony 7). Versión recomendada: `^0.3` |
| `dunglas/mercure` (Docker) | infraestructura externa | Hub Mercure. Imagen Docker oficial. |
| API Platform 4.1 | integración interna | Soporte nativo de `mercure: true` en `#[ApiResource]`. Ya instalado. |
| DevExtreme DataGrid | integración interna | `gridRef.current?.instance().refresh()` — API estable. Ya instalado. |
| Axios (interceptors) | integración interna | Para leer el header `Link` de las respuestas. Ya instalado. |
| `EfactApp.tsx` | integración interna | Punto de montaje de `MercureProvider`. |

---

## Test Plan Reference

Ver `docs/tests/SLICE-014-test-plan.md` para escenarios de test detallados.

---

## ADR References

| ADR | Decisión relevante |
|---|---|
| ADR-014 | SmartCrud SDUI con Hydra metadata — contexto de la arquitectura SmartCrud sobre la que se integra Mercure |

---

## Open Questions

- [x] **OQ-1: Estrategia de topic para colecciones** — ✅ **CERRADA**: El hub Mercure de Caddy/FrankenPHP soporta URI Templates (RFC 6570) como wildcards. El frontend se suscribe a `<host>/api/<resource>/{id}` para capturar todos los eventos de items de la colección.

- [x] **OQ-2: Entidades con `mercure: true`** — ✅ **CERRADA**: `mercure: true` en **todas** las entidades con `#[ApiResource]` (45 entidades). Opt-out explícito si se necesita excluir alguna en el futuro.

- [x] **OQ-3: Entorno de producción** — ✅ **CERRADA**: Se usa el módulo Mercure integrado en FrankenPHP/Caddy. No se añade ningún servicio Docker standalone. La instancia Mercure standalone existente en producción puede deprecarse.

- [ ] **OQ-4: Autenticación futura** — ¿Se planea en un slice posterior restringir los topics Mercure por tenant (multi-tenant)? Si sí, el diseño actual (modo público) debe ser extensible para añadir JWT de suscriptor sin refactoring mayor. — @spex-architect — informativo, no bloquea este slice.

- [ ] **OQ-5: Comportamiento en DELETE** — API Platform puede publicar en DELETE un payload vacío o un objeto con solo `@id`/`@type`. ¿El frontend debe manejar el caso de payload vacío de forma especial (e.g. eliminar la fila del grid localmente en lugar de hacer refresh)? — @spex-frontend — antes de implementar `useMercureSubscription`. **Decisión por defecto**: siempre hacer `gridRef.refresh()` independientemente del payload (comportamiento uniforme, sin optimizaciones locales en este slice).

---

## Change Log

| Fecha | Autor | Cambio |
|---|---|---|
| 2026-03-18 | spec-writer | Borrador inicial — 10 ACs, arquitectura completa, open questions documentadas |
| 2026-03-19 | spex-architect | Aprobado — OQ-1 cerrada (wildcards URI Template en Caddy), OQ-3 cerrada (Mercure integrado en FrankenPHP, sin servicio standalone), OQ-5 decisión por defecto (siempre refresh), status → approved |
| 2026-03-19 | spex-architect | OQ-2 cerrada — mercure: true en todas las entidades con #[ApiResource] (45 entidades). Todas las open questions resueltas. |
