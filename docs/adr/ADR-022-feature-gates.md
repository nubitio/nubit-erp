# ADR-022: Feature Gates — Sistema de Control de Capacidades por Plan

**Status**: Accepted
**Date**: 2026-04-26
**Deciders**: adr-writer, equipo eFact
**Related Specs**: SPEC-005
**Supersedes**: —

---

## Context

eFact es una plataforma multitenant SaaS de facturación electrónica. Hasta ahora, el control de acceso a recursos se limitaba a cuotas contables (número de usuarios, facturas, etc.) gestionadas por `TenantCertificateRepository` y `TenantQuotaService`. No existía un mecanismo formal para habilitar o deshabilitar **capacidades funcionales** (features) por plan de suscripción, ni para conceder excepciones temporales a tenants específicos.

Con la incorporación de planes diferenciados (Starter, Pro, Enterprise) y la necesidad de ofrecer trials de features premium, se requiere un sistema de **Feature Gates** que:

1. Defina qué capacidades están incluidas en cada plan.
2. Permita excepciones por tenant con fecha de expiración.
3. Sea auditable, eficiente en queries y fácil de invalidar en caché.
4. Comunique correctamente al frontend cuándo una acción requiere upgrade de plan.

---

## Decision Drivers

- **Auditabilidad**: debe ser posible reconstruir el historial de qué features tuvo cada tenant y cuándo.
- **Consistencia con el control plane**: el resto del control plane usa DBAL puro (sin Doctrine ORM); las nuevas entidades deben seguir el mismo patrón.
- **Rendimiento**: las comprobaciones de features ocurren en cada request; deben ser sub-milisegundo con caché.
- **Semántica HTTP correcta**: el frontend necesita distinguir "no autorizado" de "requiere upgrade de plan".
- **Seguridad de internals**: los detalles del sistema de billing no deben exponerse a tenants regulares.
- **Invalidación precisa**: cambiar el plan de un tenant debe reflejarse inmediatamente sin afectar a otros tenants.

---

## Considered Options

### Decisión 1 — Modelo de datos
1. **Tablas separadas `plan_features` + `tenant_feature_overrides`** — modelo 3 capas: Limits / Features / Overrides
2. **Flags en tabla `tenants`** — columnas booleanas directamente en la tabla principal
3. **JSON blob en tabla `plans`** — campo `features JSON` en la tabla de planes
4. **Feature flags externos (LaunchDarkly / Unleash)** — servicio externo de feature flags

### Decisión 2 — Código HTTP para feature bloqueada
1. **HTTP 402 Payment Required** — `PaymentRequiredHttpException`
2. **HTTP 403 Forbidden** — `AccessDeniedException`
3. **HTTP 404 Not Found** — ocultar la existencia del recurso
4. **Redirect a upgrade page** — respuesta 302 hacia `/upgrade`

### Decisión 3 — Estrategia de caché
1. **Tag-based cache con `TagAwareCacheInterface`** — tag `tenant_features_{tenantId}`, TTL 300s
2. **Sin caché** — queries directas a BD en cada request
3. **Cache por key con invalidación manual** — `cache.delete("features_{tenantId}")`
4. **Redis pub/sub** — invalidación en tiempo real vía mensajes

### Decisión 4 — Limpieza de overrides expirados
1. **Soft-clean con campo `cleaned_at`** — cron diario 03:15 UTC
2. **DELETE físico** — eliminar filas expiradas
3. **Flag `is_deleted`** — campo booleano de borrado lógico
4. **No limpiar** — dejar acumular filas expiradas

### Decisión 5 — Exposición del campo `source` en `/api/tenant/entitlements`
1. **Condicional: header `X-Debug-Entitlements: 1` + `ROLE_ADMIN`** — solo visible para admins en modo debug
2. **Siempre visible** — exponer `source` a todos los tenants
3. **Nunca visible** — omitir `source` completamente de la API

### Decisión 6 — Capa de persistencia para entidades del control plane
1. **DBAL puro con value objects `final readonly`** — repositorios DBAL, sin Doctrine ORM
2. **Doctrine ORM con entidades mapeadas** — entidades con anotaciones/atributos Doctrine
3. **Eloquent / Active Record** — patrón Active Record

---

## Decision Outcome

### D1 — Modelo 3 capas: Limits / Features / Overrides

**Chosen option**: **Tablas separadas `plan_features` + `tenant_feature_overrides`**

**Rationale**: Las tres capas tienen semánticas distintas y ciclos de vida diferentes. Los *Limits* son contadores (ya existían); las *Features* son capacidades booleanas por plan (cambian con el catálogo de planes); los *Overrides* son excepciones temporales por tenant (tienen `expires_at`). Mezclarlos en una sola tabla o en un JSON blob impediría queries eficientes, índices precisos y auditoría clara. Los flags en `tenants` no escalan al añadir nuevas features. LaunchDarkly introduce una dependencia externa costosa e innecesaria para un SaaS con control total de su infraestructura.

### D2 — HTTP 402 para feature bloqueada

**Chosen option**: **HTTP 402 Payment Required** (`PaymentRequiredHttpException`)

**Rationale**: El recurso *existe* y el usuario *está autenticado*, pero acceder a él requiere un plan de pago superior. HTTP 402 es semánticamente correcto para este caso. HTTP 403 confundiría al frontend (mismo código que "sin permisos de rol"). HTTP 404 oculta información útil. Un redirect 302 rompe clientes API que no esperan redirecciones.

### D3 — Tag-based cache

**Chosen option**: **`TagAwareCacheInterface` con tag `tenant_features_{tenantId}`, TTL 300s**

**Rationale**: Permite invalidar *exactamente* el caché de un tenant al cambiar su plan o sus overrides, sin afectar a otros tenants ni requerir un flush global. Compatible con los adaptadores de Symfony Cache (Redis, Memcached). El TTL de 300s es un safety net; la invalidación activa ocurre en `FeatureGateService::invalidateCache()`.

### D4 — Soft-clean de overrides expirados

**Chosen option**: **Campo `cleaned_at` timestamp, cron diario 03:15 UTC**

**Rationale**: La auditabilidad es un requisito de negocio (¿qué features tuvo este tenant y cuándo?). El DELETE físico destruye ese historial. El runtime ignora overrides expirados comprobando `expires_at < NOW()`, independientemente de `cleaned_at`, por lo que la limpieza es puramente operacional y no afecta a la corrección del sistema. El cron de madrugada minimiza impacto en producción.

### D5 — Campo `source` condicional

**Chosen option**: **Visible solo con `X-Debug-Entitlements: 1` + `ROLE_ADMIN`**

**Rationale**: El campo `source` revela si una feature viene del plan base o de un override, lo que expone internals del sistema de billing. Los tenants regulares no necesitan esta información; el frontend solo necesita saber si la feature está activa. El modo debug con header + rol admin es suficiente para soporte y troubleshooting sin riesgo de fuga de información.

### D6 — DBAL puro para entidades del control plane

**Chosen option**: **`PlanFeature` y `TenantFeatureOverride` como `final readonly` value objects con repositorios DBAL**

**Rationale**: Consistencia con el patrón establecido en el control plane (`TenantCertificateRepository`, `PlanManager`). DBAL ofrece queries explícitas y predecibles, sin lazy loading ni proxies de Doctrine que puedan causar N+1 queries inadvertidos. Los value objects inmutables son más seguros en contextos de caché.

---

## Pros and Cons of the Options

### D1 — Modelo de datos

#### Tablas separadas `plan_features` + `tenant_feature_overrides` ✅

**Pros**:
- Queries eficientes con índices precisos (`plan_id`, `tenant_id`, `feature_key`)
- Ciclos de vida independientes por capa
- Auditoría clara: historial de overrides separado del catálogo de features
- Fácil de extender (añadir `metadata JSON` a overrides sin tocar `plan_features`)

**Cons**:
- Más tablas en el esquema
- JOIN necesario para resolver entitlements completos

---

#### Flags en tabla `tenants`

**Pros**:
- Implementación trivial
- Sin JOINs

**Cons**:
- No escala: cada nueva feature requiere una migración de columna
- No soporta overrides temporales
- Sin historial de cambios

---

#### JSON blob en tabla `plans`

**Pros**:
- Flexible para añadir features sin migraciones

**Cons**:
- No indexable; queries lentas para buscar "todos los tenants con feature X"
- Sin validación de esquema a nivel de BD
- Dificulta overrides por tenant

---

#### Feature flags externos (LaunchDarkly)

**Pros**:
- UI de gestión lista para usar
- Targeting avanzado (% rollout, A/B)

**Cons**:
- Dependencia externa de pago
- Latencia de red en cada evaluación
- Los flags de LaunchDarkly no están ligados al modelo de planes/billing de eFact

---

### D2 — Código HTTP

#### HTTP 402 ✅

**Pros**:
- Semánticamente correcto para "requiere pago"
- Permite al frontend mostrar upgrade modal específico
- Distinguible de 403 (sin permisos de rol)

**Cons**:
- Poco usado en la práctica; algunos clientes HTTP no lo manejan bien
- Requiere documentación explícita en la API

---

#### HTTP 403

**Pros**:
- Ampliamente conocido y soportado

**Cons**:
- Ambiguo: mismo código para "sin rol" y "sin plan"
- El frontend no puede distinguir si debe mostrar upgrade modal o mensaje de permisos

---

#### HTTP 404

**Pros**:
- Oculta la existencia del recurso (seguridad por oscuridad)

**Cons**:
- Confuso para el desarrollador: el recurso sí existe
- Dificulta el debugging

---

#### Redirect 302

**Pros**:
- Funciona bien en contextos web tradicionales

**Cons**:
- Rompe clientes API REST
- No aplicable a endpoints JSON

---

### D3 — Caché

#### Tag-based cache ✅

**Pros**:
- Invalidación precisa por tenant
- Compatible con Redis y Memcached vía Symfony Cache
- TTL como safety net independiente de la invalidación activa

**Cons**:
- Requiere adaptador `TagAwareCacheInterface` (no todos los backends lo soportan)
- Ligera complejidad adicional vs cache por key simple

---

#### Sin caché

**Pros**:
- Simplicidad máxima
- Siempre datos frescos

**Cons**:
- Query a BD en cada request; inaceptable para endpoints de alta frecuencia

---

#### Cache por key con invalidación manual

**Pros**:
- Compatible con cualquier backend de caché

**Cons**:
- Propenso a errores: fácil olvidar invalidar la key correcta
- Sin garantía de consistencia si hay múltiples instancias

---

#### Redis pub/sub

**Pros**:
- Invalidación en tiempo real

**Cons**:
- Complejidad operacional significativa
- Overkill para un TTL de 5 minutos

---

### D4 — Limpieza de overrides

#### Soft-clean con `cleaned_at` ✅

**Pros**:
- Historial completo de overrides preservado
- El runtime no depende de `cleaned_at` para corrección
- Cron de bajo impacto

**Cons**:
- La tabla crece con el tiempo (mitigado por el cron diario)
- Dos campos temporales (`expires_at`, `cleaned_at`) pueden confundir

---

#### DELETE físico

**Pros**:
- Tabla siempre limpia

**Cons**:
- Pérdida irreversible del historial de overrides
- No auditable

---

#### Flag `is_deleted`

**Pros**:
- Patrón conocido de soft delete

**Cons**:
- Semánticamente incorrecto: un override expirado no está "borrado", está expirado
- Confunde con el soft-delete general de la aplicación (ver ADR-016)

---

#### No limpiar

**Pros**:
- Sin cron, sin complejidad operacional

**Cons**:
- La tabla crece indefinidamente
- Degrada el rendimiento de queries con el tiempo

---

### D5 — Campo `source`

#### Condicional (header + ROLE_ADMIN) ✅

**Pros**:
- No expone internals de billing a tenants regulares
- Disponible para soporte y debugging cuando se necesita

**Cons**:
- Requiere documentación del header especial
- Ligera complejidad en el serializador

---

#### Siempre visible

**Pros**:
- API más simple

**Cons**:
- Expone si un tenant tiene un override especial (información sensible de billing)

---

#### Nunca visible

**Pros**:
- Máxima privacidad

**Cons**:
- Imposibilita el debugging de entitlements sin acceso directo a BD

---

### D6 — Capa de persistencia

#### DBAL puro con value objects ✅

**Pros**:
- Consistencia con el control plane existente
- Queries explícitas y predecibles
- Value objects inmutables seguros para caché
- Sin riesgo de N+1 queries por lazy loading

**Cons**:
- Más código boilerplate que Doctrine ORM
- Sin migrations automáticas de Doctrine

---

#### Doctrine ORM

**Pros**:
- Menos boilerplate
- Migrations automáticas

**Cons**:
- Inconsistente con el patrón del control plane
- Riesgo de lazy loading inadvertido en contextos de caché

---

## Consequences

### Positive
- El sistema de Feature Gates es auditable: el historial de overrides se preserva indefinidamente.
- La invalidación de caché es precisa por tenant; cambiar el plan de un tenant no afecta a otros.
- El frontend puede distinguir "sin permisos de rol" (403) de "requiere upgrade de plan" (402) y mostrar el UI apropiado.
- Las entidades del control plane son consistentes: DBAL + value objects `final readonly`.
- El campo `source` en `/api/tenant/entitlements` permite debugging sin exponer internals a tenants regulares.

### Negative
- Se añaden dos tablas al esquema del control plane (`plan_features`, `tenant_feature_overrides`).
- El cron de soft-clean es un componente operacional adicional que debe monitorizarse.
- El uso de HTTP 402 requiere documentación explícita en la API y manejo en el cliente frontend.
- `TagAwareCacheInterface` requiere un backend compatible (Redis recomendado; Memcached con limitaciones).

### Risks
- **Acumulación de overrides expirados**: si el cron falla repetidamente, la tabla crece. — Mitigación: alertas en SigNoz si el cron no ejecuta en 25h; el rendimiento no se degrada porque el runtime filtra por `expires_at`.
- **Cache stale tras cambio de plan**: si `invalidateCache()` no se llama correctamente, un tenant puede ver features incorrectas hasta que expire el TTL. — Mitigación: tests de integración que verifican invalidación; TTL máximo de 300s como safety net.
- **Confusión entre `expires_at` y `cleaned_at`**: desarrolladores podrían asumir que `cleaned_at IS NULL` significa "override activo". — Mitigación: documentación en el repositorio; el método `isActive()` del value object encapsula la lógica correcta.

---

## Implementation Notes

- `FeatureGateService::check(string $featureKey, int $tenantId): bool` es el punto de entrada único para todas las comprobaciones de features.
- El atributo PHP `#[RequiresFeature('excel_export')]` en controllers delega a `FeatureGateService` y lanza `PaymentRequiredHttpException` si la feature no está activa.
- La resolución de entitlements sigue el orden: Override activo > Feature del plan > `false`.
- Los repositorios DBAL deben usar `$this->connection->fetchAllAssociative()` con parámetros nombrados; nunca interpolación de strings.
- El cron de soft-clean debe registrarse en `config/services.yaml` como `messenger:consume` o como comando Symfony Scheduler con expresión `0 3 * * *` (03:00 UTC, con jitter de 15 min → 03:15 UTC efectivo).
- Al añadir una nueva feature al catálogo, insertar en `plan_features` para todos los planes relevantes en la misma migración.

---

## References

- [RFC 7231 — HTTP 402 Payment Required](https://datatracker.ietf.org/doc/html/rfc7231#section-6.5.2) — semántica del código de estado
- [Symfony Cache — Tag-based Invalidation](https://symfony.com/doc/current/components/cache/cache_invalidation.html) — documentación oficial
- ADR-002 — Dual Database / Control Plane: establece el patrón DBAL para el control plane
- ADR-016 — Soft Delete con SQLFilter: contexto del patrón de borrado lógico en eFact
- ADR-019 — Control Plane Bounded Context: límites del contexto donde viven estas entidades
