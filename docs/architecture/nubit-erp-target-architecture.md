# Nubit ERP — Arquitectura objetivo y plan de convergencia

**Estado:** objetivo acordado — punto de partida para el trabajo de convergencia
**Fecha:** 2026-09-05
**Alcance:** backend `efact` (`src/`), frontend (`assets/`), paquetes `nubitio/*`, control plane SaaS
**Relacionado:** [ADR-001](../adr/ADR-001-modular-monolith-strict-bounded-contexts.md), [ADR-013](../adr/ADR-013-feature-gating-entitlements-vs-capabilities.md), [nubit-backend-extraction-plan.md](./nubit-backend-extraction-plan.md), [multivertical-capability-plan.md](./multivertical-capability-plan.md), [platform-boundary-map.md](./platform-boundary-map.md)

---

## 1. Destino (resumen del objetivo)

Nubit es una **plataforma ERP modular multivertical**. Verticales previstas: retail, farmacia,
restaurante, hotel, distribución. El núcleo ERP se reutiliza sin acoplarse a reglas de industria.

Estilo: **monolito modular** con bounded contexts explícitos, un solo despliegue, fronteras
por namespace. Repo: **monorepo modular**. Extracción a servicios sólo con justificación
operativa concreta (escala, aislamiento de fallos, equipos separados, regulación).

### Capas y dirección de dependencia

```
Vertical  →  ERP  →  Platform
```

Nunca `ERP → Vertical` ni `Platform → ERP`. Las verticales no dependen entre sí salvo módulo
compartido explícito.

| Capa | Contenido |
|---|---|
| **Platform Core** | Identity, Authn/Authz, Tenancy, Audit, Configuration, Notifications, Feature Management |
| **ERP Core** | Catalog, Customers, Suppliers, Inventory, Purchasing, Sales, Invoicing, Payments, Reporting |
| **Commerce** | POS, Pricing, Promotions |
| **Verticals** | Pharmacy, Restaurant, Hospitality, … (sólo reglas de su industria) |

Los productos comerciales (Nubit Retail / Farmacia / Restaurante / Hotel) se definen por
**composición de capacidades**, no por ramas de código.

### Reglas arquitectónicas obligatorias

| ID | Regla |
|---|---|
| AR-001 | Platform no depende de ERP ni de verticales |
| AR-002 | ERP no depende de ninguna vertical |
| AR-003 | Una vertical puede depender de ERP y Platform |
| AR-004 | Una vertical no depende directamente de otra vertical (salvo módulo compartido explícito) |
| AR-005 | Ningún módulo accede directamente a tablas de otro módulo |
| AR-006 | Comunicación entre módulos: interfaces, application services, eventos o contratos públicos |
| AR-007 | Las entidades del ERP Core no contienen propiedades específicas de una vertical |
| AR-008 | Prohibidas las dependencias circulares entre módulos |
| AR-009 | Los contratos públicos se mantienen estables (semver al publicarse) |
| AR-010 | Cada módulo debe poder probarse de forma aislada |

Extensión de comportamiento por composición: el módulo genérico define la capacidad
(`Inventory.StockMovement`, `DefaultStockPolicy`), la vertical define la especialización
(`Pharmacy.BatchStockMovement`, `PharmacyStockPolicy`) vía entidades propias, contratos y eventos.

Persistencia: BD compartida inicial, cada módulo dueño lógico de sus tablas con prefijo
(`inventory_*`, `sales_*`, `pharmacy_*`, …). Multi-tenant: todo lleva `tenantId`.

Autorización: permisos por capacidad (`inventory.read`, `pharmacy.batch.manage`), roles agrupan permisos.

Observabilidad: cada operación relevante emite log estructurado con
`tenant_id · module · operation · entity_id · user_id · timestamp · correlation_id`.

---

## 2. Estado actual de efact (2026-09-05)

### 2.1 Ya alineado con el destino

| Área | Estado en efact |
|---|---|
| Monolito modular + bounded contexts | ~20 contextos bajo `src/<Context>` (ADR-001) |
| Comunicación entre contextos | Symfony Messenger domain events + **una** excepción síncrona documentada (Sales↔Billing↔Inventory↔CashRegister para el camino venta→stock→CPE→caja) |
| Platform Core portable | Extraído a Composer: `nubitio/platform`, `nubitio/api-platform`, `nubitio/admin-bundle` (v0.15); contratos de tenant, feature gates, quotas en `Nubit\Platform\` |
| Multi-tenancy | Database-per-tenant + BD de control plane separada (ADR-007); `TenantConnection` conmuta por subdominio |
| Entitlements vs Capabilities (concepto) | ADR-013; `TenantRuntimeConfigProvider`, `TenantSettings`, seam `VerticalPresetInterface` con `RetailPreset` y `PharmacyPreset` |
| RBAC por capacidad | `PermissionCatalog` en código + `role_permission`; voter prefijo `perm:`, super admin `*` |
| Ledgers append-only / CPE inmutables | ADR-003 (`stock_movements` verificado; `cash_movements` con guard DB desde #318) |
| API pública de integración + webhooks | ADR-017 (`integration_*`) |
| Guard de frontera Platform | `scripts/architecture/check-platform-boundary.php` en CI + pre-push |

### 2.2 Brechas frente al destino

| # | Brecha | Evidencia | Impacto |
|---|---|---|---|
| G1 | **No existe capa de verticales.** Pharmacy sólo es un `VerticalPreset` (settings/menús/seeds), sin bounded context propio (`Medicine`, `Batch`, `Expiration`, `Prescription`). No hay Restaurant ni Hospitality. | `src/Settings/Vertical/Preset/PharmacyPreset.php` es lo único "farmacia" | Alto — es el núcleo del destino multivertical |
| G2 | **AR-001…AR-010 no se verifican por tooling.** El guard actual sólo protege las rutas de extracción Platform/Core; no comprueba dirección Vertical→ERP, ni prohíbe ERP→Vertical, ni ciclos. ADR-001 lo reconoce explícitamente como "enforcement gap". | `check-platform-boundary.php` `$checkedPaths` | Alto — sin esto la arquitectura se erosiona en cada PR |
| G3 | **Catálogo de capabilities de 1ª clase incompleto.** Falta `CapabilityKey` enum, `CapabilityCatalog` con metadata (owner, dependencias, entitlement requerido, schema, defaults), `TenantCapabilityResolver` (fail-closed), tablas `tenant_capabilities` y `tenant_profile`. Hoy las capabilities se derivan, no se resuelven desde catálogo versionado. | `multivertical-capability-plan.md` Fase 1 sin implementar | Alto — habilita G1 sin `if vertical ===` |
| G4 | **Sin separación física `apps/` · `modules/` · `verticals/`.** Todo plano bajo `src/`. No hay mecanismo de composición de "producto" más allá de `VerticalPreset`. | árbol `src/` | Medio — reconciliable, ver §3 decisión D1 |
| G5 | **Propiedad de datos por módulo no forzada; naming de tablas inconsistente.** Mezcla legacy español (`producto`, `cliente`, `almacen`, `comprobante`, `movimiento`) y prefijado por contexto (`inventory_movements`, `payroll_*`, `bank_*`, `integration_*`). Namespace legacy `App\Entity\` aún referenciado. | `migrations/`, lista forbidden del guard | Medio — bloquea AR-005 verificable |
| G6 | **Módulos no declaran sus dependencias** (`dependsOn`). ~~No hay manifiesto por contexto.~~ Resuelto por NB-2: `architecture/contexts.yaml` + test de consistencia. | `architecture/contexts.yaml` | Cerrado |
| G7 | **Sin namespacing de API por vertical** (`/api/pharmacy/*`, `/api/restaurant/*`). | rutas API actuales | Bajo — sigue a G1 |
| G8 | **Puntos de extensión no sistematizados.** Existen seams sueltos (ADR-014 descuentos, interfaces `SaleInvoicing*`), pero no un registro `StockPolicy` / `PriceCalculator` / `TaxCalculator` / `PaymentProvider` con default + override por vertical. | `src/Sales`, `src/Taxes` | Medio — habilita especialización sin tocar core |
| G9 | **Observabilidad de correlación entre módulos sin verificar.** OpenTelemetry auto-instrumentado, pero no confirmado el log estructurado por operación con `module` + `correlation_id` propagado por el bus de eventos. | `composer.json` open-telemetry/* | Bajo |
| G10 | **`Taxes` es delgado y compartido** (consumido por Billing/Payroll). El destino lo trata como utilidad fiscal, no dominio — alineado, pero su frontera con Billing no está declarada como contrato. | ADR-001 nota sobre `Taxes` | Bajo |

---

## 3. Decisiones a sentar

**Resueltas en [ADR-018](../adr/ADR-018-nubit-multivertical-target-architecture.md) (2026-09-05).**

| ID | Decisión | Resolución |
|---|---|---|
| **D1** | ¿efact ES el monorepo Nubit, o un consumidor de paquetes publicados? | efact **es** el monorepo Nubit (`apps/erp` conceptual). Los verticales nacen **dentro de `src/`** como bounded contexts. `nubitio/*` sigue igual; nada vuelve al repo. El árbol físico `apps/`/`modules/`/`verticals/` queda como ilustración, no como movimiento requerido. |
| **D2** | ¿ERP Core se extrae a paquete `nubitio/erp-core`? | **No en este horizonte.** Un solo consumidor; extraer congela contratos sin beneficio. Revisar sólo si aparece una segunda app first-party. |
| **D3** | Motor de verticales: ¿preset-only o bounded context + capabilities? | **Capabilities de 1ª clase** como unidad de comportamiento (catálogo versionado + `TenantCapabilityResolver` fail-closed). `VerticalPreset` sólo empaqueta, sin lógica. Prohibido `if vertical === …` en servicios de ERP Core / Commerce. |
| **D4** | Naming de tablas legacy español | **Congelar legacy** (`producto`, `cliente`, …); no renombrado masivo (riesgo en código fiscal/migraciones). Toda tabla nueva `<context>_*`. Legacy registrado en el manifiesto `ownsTables` (NB-13). |

---

## 4. Backlog de convergencia

Seguimiento en GitHub: épica [nubitio/efact#326](https://github.com/nubitio/efact/issues/326),
sub-issues #327–#347 (NB-1 = #327 … NB-21 = #347), en el project board #1.

IDs `NB-n`. Cada tarea: **Objetivo · Entregable · Criterio de aceptación**. Orden = dependencia.

### Fase 0 — Sentar fronteras y tooling (habilita todo lo demás)

**NB-1 · Verificador de reglas AR-001…AR-010** — hecho (commit tras #327)
Objetivo: convertir las reglas en un check de CI, no sólo el guard Platform actual.
Entregable: `phparkitect.php` (capas derivadas de `architecture/contexts.yaml`, ver NB-2); `phparkitect-baseline.json` (violaciones pre-existentes, sólo puede reducirse); `composer check:architecture`; job en `ci-main.yml` + `scripts/quality-check.sh` (pre-push).
Nota: se eligió PHPArkitect sobre Deptrac porque deptrac 3.0 empaqueta php-parser 4.x y no parsea la sintaxis PHP 8.4 `new Foo()->method()` usada en todo el repo.
Criterio: el CI falla ante un import `src/Inventory` → `src/Pharmacy`; `main` verde con baseline.

**NB-2 · Manifiesto de dependencias por contexto** — hecho (PR de #328)
Objetivo: cada contexto declara `layer`, `dependsOn`, `publicContracts`, `ownsTables`.
Entregable: `architecture/contexts.yaml` (fuente única de verdad, 25 contextos); `phparkitect.php` deriva su mapa de capas de ahí; `tests/Architecture/ContextManifestTest.php` cruza manifiesto ↔ código (completitud bidireccional, `dependsOn` ⊇ refs reales, `ownsTables` ⊇ tablas de entidades y sin dueño doble, contratos existen).
Criterio: `contexts.yaml` cubre todos los contextos bajo `src/`; test verde; quitar un `dependsOn` o una tabla real hace fallar el test.

**NB-3 · Registrar el mapa de capas en ADR-018**
Objetivo: elevar este documento a ADR aceptada, con D1–D4 resueltas.
Entregable: `docs/adr/ADR-018-nubit-multivertical-target-architecture.md`; `CONTEXT.md` enlaza a él.
Criterio: D1–D4 con decisión escrita y alternativas rechazadas.

### Fase 1 — Capabilities de 1ª clase (multivertical-plan Fase 1)

**NB-4 · Catálogo operativo de capabilities** — hecho (#349)
Objetivo: `CapabilityKey` enum + `CapabilityDefinition` (key, label, owner context, deps, requiresEntitlement, configKeys, safeDefaults) + `CapabilityCatalog` versionado (`VERSION`).
Entregable: `src/Settings/Capability/`; `CapabilityCatalogTest` (paridad enum↔catálogo, sin ciclos, `requiresEntitlement` en `FeatureCatalog`, `ownerContext` en `architecture/contexts.yaml`, `safeDefaults ⊆ configKeys`).
Criterio: catálogo cubre las 20 capabilities ya implícitas (`inventory.multi_warehouse`, `inventory.lot_tracking`, `cash.sessions`, `billing.sunat`, `catalog.weighted_items`, `hardware.scale`, …).

**NB-5 · Estado operacional por tenant** — hecho (PR de #331)
Objetivo: tablas `tenant_capabilities` y `tenant_profile`.
Entregable: migración `Version20260906040000` + entidades `TenantCapability`/`TenantProfile` + repos; enum `CapabilitySource` (preset|manual|migration|support); `VerticalPresetApplier` graba el perfil y habilita `desiredCapabilities()` con los `safeDefaults` del catálogo, respetando overrides manual/support.
Criterio: `doctrine:migrations:migrate` verde (109 migraciones); mapping válido; aplicar un preset deja el perfil y las capabilities poblados.

**NB-6 · `TenantCapabilityResolver` fail-closed** — hecho (PR de #332)
Objetivo: resolución única: existencia → entitlement comercial → dependencias → fila del tenant / `defaultEnabled` → falla cerrado.
Entregable: `src/Settings/Capability/TenantCapabilityResolver` (`isEnabled()`, `config()`, `require()`, `all()`) + `CapabilityNotAvailableException`; `defaultEnabled` en `CapabilityDefinition`/`CapabilityCatalog`; `TenantRuntimeConfigProvider` deriva su bloque `capabilities` del resolver (misma forma de salida; los 2 valores de config derivados de settings quedan overlaid hasta NB-7).
Criterio: `TenantCapabilityResolverTest` (existencia, entitlement, dependencia incumplida, override de fila, `config` filtrado, `require`); `TenantRuntimeConfigProviderTest` sigue verde; `GET /api/tenant/runtime-config` expone `capabilities` con `enabled`+`config`.

**NB-7 · Migrar flags dispersos a capabilities** — hecho (PR de #333)
Objetivo: mapear `requireCashRegister`→`cash.sessions`, `autoInvoice`→`billing.sunat`, `negativeStockPolicy`→`inventory.stock_tracking`, `printMethod`→`hardware.printing`.
Entregable: `SettingsCapabilitySync` (espejo desde `TenantSettings` a la config de las 4 filas), llamado en `TenantSettingsService::save()` y `VerticalPresetApplier`; migración `Version20260906050000` que rellena las filas por tenant; `TenantRuntimeConfigProvider` deriva `capabilities` sólo del resolver (overlay temporal eliminado). `TenantSettings` conserva columnas y getters como compat.
Criterio: `/api/tenant/runtime-config` mantiene la salida; consumidores de dominio (SaleProcessor, políticas de stock) siguen leyendo `TenantSettings` en modo compat; sin ramificación por `vertical`.

### Fase 2 — Presets versionados y onboarding

**NB-8 · `PresetApplier` con dry-run** — hecho (PR de #334)
Objetivo: poder previsualizar el efecto de aplicar un preset y confirmar que re-aplicar no cambia nada.
Entregable: `VerticalPresetPlanner` (read-only) + `VerticalPresetPlan` VO (diff de settings campo→campo y de capabilities: `enable`/`config`/`noop`/`skip-manual`/`unknown`, + warehouse); `tenant:apply-preset --dry-run` imprime el diff en tablas; el aplicador registra auditoría estructurada (settings cambiados, capabilities habilitadas, warehouse) en el log del comando. Respeta overrides `manual`/`support` (los reporta como `skip-manual`).
Criterio: `VerticalPresetPlannerTest` (fresh tenant, re-apply no-op, override manual, warehouse); `tenant:apply-preset <key> --dry-run` no persiste.
Nota: los presets siguen declarando sólo `settings` + `desiredCapabilities`; menús/seeds/uiDefaults declarativos en el `VerticalPresetInterface` quedan como seguimiento (no bloquean).

**NB-9 · `--vertical=<key>` en provisioning + selección de giro en onboarding** — hecho (PR de #335)
Frontend: el wizard `TenantOnboardingGate` ya tenía el paso de selección de giro (aplica `POST /api/tenant/apply-preset`) — sin cambios.
Backend: `app:tenant:provision --vertical=<key>` corre `tenant:apply-preset` tras `--setup` (warning, no aborta); `--vertical` fluye por `ProvisionCommandRequest` → `ProvisionCommandRunner` → `TenantProvisioningService::run(vertical: …)` (default `''`, sin cambios para llamadores actuales).
Criterio: un operador provisiona `app:tenant:provision acme --setup --vertical=pharmacy` sin tocar BD; el wizard sigue siendo la fuente de verdad post-provisión.

### Fase 3 — Primera vertical como bounded context: Pharmacy

**NB-10 · `src/Pharmacy` bounded context** — hecho (#355 + follow-up)
Objetivo: primera vertical como bounded context real, no un preset.
Entregable: `src/Pharmacy/{Entity,Repository,Service,State,Controller,Enum,Policy,MessageHandler}` — `PharmacyProduct` (composición: `productId` int, `activeIngredient`/`sanitaryRegistration`/`requiresPrescription`/`controlled`/`controlledSubstanceCode`, `Catalog.Product` intacto), `PharmacyBatch` (metadata de lote; cantidad en `stock_lots`), `PharmacyBatchMovement` (ledger FEFO de #357), `Prescription` (+ `PrescriptionInput`/`Processor`), `ExpiryControl` enum, `PharmacyCatalogService`, `PharmacyExpiryReportController`; migraciones `Version20260906060000`/`070000`/`Version20260907090000` (sin FK cross-context); `architecture/contexts.yaml` gana `Pharmacy` (`layer: Vertical`, `dependsOn: [Catalog, Inventory, Sales]`) → capa `Vertical_Pharmacy` de PHPArkitect activa; API Platform `/api/pharmacy/{products,batches,prescriptions}` + `/api/pharmacy/expiry-report`.
Criterio: PHPArkitect verde (Pharmacy→ERP permitido, ERP→Pharmacy prohibido — `PharmacyBoundaryTest`); `Catalog.Product` sin campos de farmacia (AR-007, verificado por test).

**NB-11 · `PharmacyStockValidationPolicy` (validación de vencimiento)** — hecho (PR de #337)
Objetivo: Pharmacy especializa el seam de validación de stock sin que Inventory/Sales lo sepan.
Entregable: `App\Pharmacy\Policy\PharmacyStockValidationPolicy` **decora** `StockValidationPolicyInterface` (de Inventory) vía `#[AsDecorator]` — Sales sigue inyectando la interfaz, el contenedor mete el decorator. Delega en el inner (chequeo de stock negativo) y encima aplica `ExpiryControl`: `BLOCK` rechaza si todos los lotes están vencidos, `WARN` avisa; producto sin perfil de farmacia o sin lotes registrados pasa igual. `Pharmacy.dependsOn` = `[Catalog, Inventory]` (Vertical→ERP permitido).
Seguimiento (#337): **selección de lote FEFO en la deducción** — va con NB-12 (reacción a `SaleConfirmed` que registra el lote descontado); `PharmacyCatalogService::nextUsableBatch/fefoBatches` ya es el seam.
Criterio: `PharmacyStockValidationPolicyTest` (block/warn/passthrough/inner-block); 511 tests de Sales+Inventory sin regresión; PHPArkitect verde.

**NB-12 · Reacción por evento Pharmacy ↔ core** — hecho (PR de #338)
Objetivo: Pharmacy reacciona a la confirmación de venta; `Sales` no importa `Pharmacy`.
Entregable: `App\Sales\Event\SaleConfirmed` (+ `SaleConfirmedLine`) — value object **definido en Sales**, despachado por `SaleProcessor` tras persistir una venta nueva (try/catch, no fatal). `App\Pharmacy\MessageHandler\RecordBatchDrawdownOnSaleConfirmed` (`#[AsMessageHandler]`, en la vertical) escribe una fila append-only en `pharmacy_batch_movements` por línea de producto de farmacia, eligiendo el lote FEFO no vencido (fallback: el más antiguo). Idempotente por `saleId`. `Pharmacy.dependsOn` = `[Catalog, Inventory, Sales]`.
Seguimiento: split de una línea entre varios lotes (necesita cantidades por lote de Inventory `stock_lots`).
Criterio: `PharmacyBoundaryTest` — nada fuera de `src/Pharmacy` importa `App\Pharmacy` (incluye `src/Sales`); `RecordBatchDrawdownOnSaleConfirmedTest` (FEFO, skip no-farmacia/NONE, idempotencia, fallback); 284 tests de Sales sin regresión.

### Fase 4 — Endurecer propiedad de datos (AR-005)

**NB-13 · Inventario de propiedad de tablas**
Entregable: `architecture/contexts.yaml` `ownsTables` completo; test que detecta una tabla sin dueño o con dos dueños.

**NB-14 · Naming de tablas (según D4)**
Entregable: plan de migración legacy español → prefijo por contexto, o congelación documentada.
Criterio: tablas nuevas siempre `<context>_*`; check en revisión de migraciones.

**NB-15 · Guard de acceso cruzado a tablas**
Objetivo: test/arquitectura que falla si un repositorio de contexto A referencia una tabla de contexto B.
Criterio: CI falla ante `Restaurant` escribiendo `inventory_*`.

### Fase 5 — Puntos de extensión sistemáticos (G8)

**NB-16 · Registro de seams del core**
Objetivo: catalogar y normalizar `SaleValidator`, `PriceCalculator`, `TaxCalculator`, `InvoiceProcessor`, `StockPolicy`, `PaymentProvider` con default + resolución por capability/vertical.
Entregable: interfaces en el contexto dueño + `#[AsExtensionPoint]` o tag de servicio + doc.
Criterio: añadir una regla de vertical = registrar una implementación, cero cambios en el core.

### Fase 6 — Verticales siguientes

**NB-17 · Ferretería y Farmacia (capabilities)** — multi-unidad, ubicación, stock mínimo, reposición, variantes; lotes/vencimiento/FEFO/alertas.
**NB-18 · `src/Restaurant` bounded context** — mesas, áreas, pedidos abiertos, comandas, modificadores, cocina; integra con `Sales` sólo al cierre/cobro; no contamina `SaleProcessor`.
**NB-19 · `src/Hospitality` bounded context** — habitaciones, tarifas, reservas, huésped, estancia, check-in/out; integra con `Billing`.

### Fase 7 — Observabilidad y contratos

**NB-20 · Log estructurado por operación** con `tenant_id · module · operation · entity_id · user_id · correlation_id`; `correlation_id` propagado por el stamp de Messenger entre contextos.
**NB-21 · Contract tests entre módulos** — validan las interfaces públicas declaradas en los manifiestos (NB-2); parte de la suite.

---

## 5. Criterios de "destino alcanzado" (v1 multivertical)

1. Un tenant nuevo se provisiona como retail / farmacia / ferretería sin cambios manuales de BD.
2. Las capabilities efectivas son visibles por API y auditables por soporte.
3. PHPArkitect verifica AR-001…AR-010 en CI (`composer check:architecture`); `main` verde.
4. Existe al menos una vertical (`Pharmacy`) como bounded context propio, con `Catalog.Product` libre de campos de farmacia.
5. El comportamiento del POS/core se decide por runtime-config, nunca por `if vertical === …`.
6. Cada contexto declara `dependsOn`, contratos públicos y `ownsTables`; ningún módulo toca tablas de otro.
7. Las ventas existentes conservan su semántica fiscal y de inventario.

---

## 6. Historial

| Fecha | Evento |
|---|---|
| 2026-09-05 | Documento creado: destino multivertical Nubit + análisis de estado efact + backlog NB-1…NB-21 |
