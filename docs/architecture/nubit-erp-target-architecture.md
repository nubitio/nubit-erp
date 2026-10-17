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

Persistencia y tenancy aprobadas: database-per-tenant con BD de control plane separada (ADR-007); las tablas tenant no requieren `tenant_id`. Cada contexto es dueño lógico de sus tablas según `architecture/contexts.yaml`; las tablas nuevas usan prefijo `<context>_*` y los nombres legacy se congelan según ADR-018 D4.

Autorización: permisos por capacidad (`inventory.read`, `pharmacy.batch.manage`), roles agrupan permisos.

Observabilidad: cada operación relevante emite log estructurado con
`tenant_id · module · operation · entity_id · user_id · timestamp · correlation_id`.

---

## 2. Estado actual de efact (revisado 2026-10-16)

### 2.1 Ya alineado con el destino

| Área | Estado en efact |
|---|---|
| Monolito modular + bounded contexts | Contextos bajo `src/<Context>`; inventario vigente en `architecture/contexts.yaml` y ADR-001 |
| Comunicación entre contextos | Symfony Messenger domain events + **una** excepción síncrona documentada (Sales↔Billing↔Inventory↔CashRegister para el camino venta→stock→CPE→caja) |
| Platform Core portable | Extraído a Composer: `nubitio/platform`, `nubitio/api-platform`, `nubitio/admin-bundle`; contratos de tenant, feature gates y quotas en `Nubit\Platform\` |
| Multi-tenancy | Database-per-tenant + BD de control plane separada (ADR-007); `TenantConnection` conmuta por subdominio |
| Entitlements vs Capabilities | ADR-013/018; catálogo versionado, `TenantCapabilityResolver`, estado por tenant y sincronización de compatibilidad bajo `src/Settings/Capability/` |
| RBAC por capacidad | `PermissionCatalog` en código + `role_permission`; voter prefijo `perm:`, super admin `*` |
| Ledgers append-only / CPE inmutables | `stock_movements` es inmutable en su API de aplicación. `cash_movements` tiene escritura por inserción en la aplicación, pero sus migraciones de triggers están retiradas/no-op; no se puede afirmar protección DB general. Triggers antiguos podrían permanecer en algunas BD según su historial de migración. CPE: correcciones por documentos relacionados/estados, véase ADR-003. |
| API pública de integración + webhooks | ADR-017 (`integration_*`) |
| Guard de fronteras | Guard Platform/Core en CI + pre-push; PHPArkitect deriva reglas de capa del manifiesto y hay pruebas en `tests/Architecture/` |
| Verticales | `src/Pharmacy` implementa perfiles/productos, lotes, recetas, política de stock y reacción a ventas. `src/Restaurant` implementa áreas/mesas/pedidos y flujo de cocina; cerrar pedido enlaza un `saleId` existente, no crea/cobra una venta por sí mismo. Las pruebas Restaurant son acotadas. Hospitality no tiene contexto en `src/`. |
| Módulos financieros adicionales | `Accounting`, `Payroll`, `FixedAssets`, `BankReconciliation`, `AccountsPayable` y `ExpenseReports` están presentes con flujos funcionales iniciales; su presencia no significa cobertura integral ni certifica cumplimiento regulatorio completo. `Billing/Recurring` tiene planes, suscripciones y generación de ventas recurrentes. |
| Propiedad de datos | `architecture/contexts.yaml` registra `ownsTables` y `externalTables`; hay pruebas de consistencia, ownership, naming y escrituras cruzadas |

### 2.2 Brechas frente al destino

| # | Brecha | Evidencia | Impacto |
|---|---|---|---|
| G1 | **Cobertura de verticales incompleta.** Pharmacy tiene perfiles, lotes, recetas, vencimiento y reacción a ventas. Restaurant tiene áreas/mesas, pedidos, estados de cocina y UI; `CloseOrderProcessor` enlaza el `saleId` recibido y libera la mesa, no ejecuta la venta/cobro. Las pruebas actuales (`tests/Restaurant`) son acotadas. Hospitality no figura en el manifiesto ni bajo `src/`. | `src/Pharmacy`, `src/Restaurant`, `assets/features/restaurant`, `tests/Restaurant`, `architecture/contexts.yaml`; NB-17…NB-19 | Alto — falta madurez verificada y completar verticales objetivo |
| G2 | **Reglas arquitectónicas con cobertura parcial.** PHPArkitect deriva capas del manifiesto y aplica restricciones con baseline; hay pruebas dedicadas de límites, ownership, escrituras cruzadas y contratos. Esto mejora sustancialmente el enforcement, pero no demuestra que las diez reglas AR-001…AR-010 estén verificadas de extremo a extremo ni elimina las violaciones preexistentes del baseline. | `phparkitect.php`, `phparkitect-baseline.json`, `tests/Architecture/` | Alto — mantener CI y reducir baseline |
| G3 | **Capabilities operativas implementadas; presets aún no totalmente declarativos.** Existen catálogo, enum, resolver fail-closed, estado por tenant y sincronización con settings de compatibilidad. El contrato de preset sigue centrado en settings y capabilities; menús, seeds y UI defaults declarativos continúan como seguimiento. | `src/Settings/Capability/`, `VerticalPresetInterface`; NB-8 | Medio — completar el empaquetado declarativo y retirar compatibilidad gradualmente |
| G4 | **Sin separación física `apps/` · `modules/` · `verticals/`.** Los contextos siguen bajo `src/`; es intencional según D1, no una reorganización pendiente. | ADR-018 D1 | Cerrado por decisión |
| G5 | **Propiedad de datos inventariada y escrituras raw-SQL cruzadas comprobadas.** El manifiesto enumera tablas tenant y externas; las pruebas comprueban ownership, naming y escrituras SQL cruzadas con excepciones. El guard no detecta lecturas cruzadas ni reemplaza revisión ORM. Nombres legacy españoles congelados; tablas nuevas `<context>_*`. No fijar un total de tablas aquí: el manifiesto evoluciona con las migraciones. | `architecture/contexts.yaml`, `tests/Architecture/{TableOwnershipTest,CrossContextTableWriteTest,NewTableNamingConventionTest}.php` | Medio — mantener inventario y ampliar cobertura si aparecen escrituras ORM cruzadas |
| G6 | **Dependencias de contexto declaradas.** El manifiesto y sus pruebas de consistencia están implementados; las dependencias registradas reflejan referencias observadas y no constituyen por sí mismas aprobación del acoplamiento. | `architecture/contexts.yaml`, `ContextManifestCodeTest` | Cerrado como inventario; racionalizar acoplamientos sigue siendo trabajo continuo |
| G7 | **API namespaced de Pharmacy y Restaurant implementada.** Pharmacy tiene rutas `/api/pharmacy/...`; Restaurant declara recursos y procesadores para mesas, áreas, pedidos e ítems. Conviene mantener contrato/documentación API y pruebas alineados con las rutas disponibles. | `src/Pharmacy`, `src/Restaurant`, `assets/features/restaurant/api/` | Bajo — fortalecer contrato y pruebas |
| G8 | **Catálogo de extension points y prueba de consistencia implementados; cobertura funcional sigue evolucionando.** `ExtensionPointCatalogTest` comprueba interfaces marcadas, implementación default, estrategia de resolución y presencia en la documentación. La cobertura depende de qué seams del core se hayan registrado en ese catálogo. | `src/Efact/Extension/`, `docs/architecture/extension-points.md`, `tests/Architecture/ExtensionPointCatalogTest.php` | Medio |
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

**NB-1 · Verificador de reglas arquitectónicas** — implementado; cobertura completa AR-001…AR-010 por confirmar
Objetivo: convertir las reglas en un check de CI, no sólo el guard Platform actual.
Entregable: `phparkitect.php` (capas derivadas de `architecture/contexts.yaml`, ver NB-2); `phparkitect-baseline.json` (violaciones pre-existentes, sólo puede reducirse); `composer check:architecture`; job en `ci-main.yml` + `scripts/quality-check.sh` (pre-push).
Nota: se eligió PHPArkitect sobre Deptrac porque deptrac 3.0 empaqueta php-parser 4.x y no parsea la sintaxis PHP 8.4 `new Foo()->method()` usada en todo el repo.
Criterio: el CI falla ante un import `src/Inventory` → `src/Pharmacy`; `main` verde con baseline.

**NB-2 · Manifiesto de dependencias por contexto** — implementado
Objetivo: cada contexto declara `layer`, `dependsOn`, `publicContracts`, `ownsTables`.
Entregable: `architecture/contexts.yaml` (fuente única de verdad, 25 contextos); `phparkitect.php` deriva su mapa de capas de ahí; `tests/Architecture/ContextManifestTest.php` cruza manifiesto ↔ código (completitud bidireccional, `dependsOn` ⊇ refs reales, `ownsTables` ⊇ tablas de entidades y sin dueño doble, contratos existen).
Criterio: `contexts.yaml` cubre todos los contextos bajo `src/`; test verde; quitar un `dependsOn` o una tabla real hace fallar el test.

**NB-3 · Registrar el mapa de capas en ADR-018** — implementado
Objetivo: elevar este documento a ADR aceptada, con D1–D4 resueltas.
Entregable: `docs/adr/ADR-018-nubit-multivertical-target-architecture.md`; `CONTEXT.md` enlaza a él.
Criterio: D1–D4 con decisión escrita y alternativas rechazadas.

### Fase 1 — Capabilities de 1ª clase (multivertical-plan Fase 1) — implementada

**NB-4 · Catálogo operativo de capabilities** — implementado
Objetivo: `CapabilityKey` enum + `CapabilityDefinition` (key, label, owner context, deps, requiresEntitlement, configKeys, safeDefaults) + `CapabilityCatalog` versionado (`VERSION`).
Entregable: `src/Settings/Capability/`; `CapabilityCatalogTest` (paridad enum↔catálogo, sin ciclos, `requiresEntitlement` en `FeatureCatalog`, `ownerContext` en `architecture/contexts.yaml`, `safeDefaults ⊆ configKeys`).
Criterio: catálogo cubre las 20 capabilities ya implícitas (`inventory.multi_warehouse`, `inventory.lot_tracking`, `cash.sessions`, `billing.sunat`, `catalog.weighted_items`, `hardware.scale`, …).

**NB-5 · Estado operacional por tenant** — implementado
Objetivo: tablas `tenant_capabilities` y `tenant_profile`.
Entregable: migración `Version20260906040000` + entidades `TenantCapability`/`TenantProfile` + repos; enum `CapabilitySource` (preset|manual|migration|support); `VerticalPresetApplier` graba el perfil y habilita `desiredCapabilities()` con los `safeDefaults` del catálogo, respetando overrides manual/support.
Criterio: `doctrine:migrations:migrate` verde (109 migraciones); mapping válido; aplicar un preset deja el perfil y las capabilities poblados.

**NB-6 · `TenantCapabilityResolver` fail-closed** — implementado
Objetivo: resolución única: existencia → entitlement comercial → dependencias → fila del tenant / `defaultEnabled` → falla cerrado.
Entregable: `src/Settings/Capability/TenantCapabilityResolver` (`isEnabled()`, `config()`, `require()`, `all()`) + `CapabilityNotAvailableException`; `defaultEnabled` en `CapabilityDefinition`/`CapabilityCatalog`; `TenantRuntimeConfigProvider` deriva su bloque `capabilities` del resolver (misma forma de salida; los 2 valores de config derivados de settings quedan overlaid hasta NB-7).
Criterio: `TenantCapabilityResolverTest` (existencia, entitlement, dependencia incumplida, override de fila, `config` filtrado, `require`); `TenantRuntimeConfigProviderTest` sigue verde; `GET /api/tenant/runtime-config` expone `capabilities` con `enabled`+`config`.

**NB-7 · Migrar flags dispersos a capabilities** — sincronización implementada; consumidores conservan compatibilidad con `TenantSettings`
Objetivo: mapear `requireCashRegister`→`cash.sessions`, `autoInvoice`→`billing.sunat`, `negativeStockPolicy`→`inventory.stock_tracking`, `printMethod`→`hardware.printing`.
Entregable: `SettingsCapabilitySync` (espejo desde `TenantSettings` a la config de las 4 filas), llamado en `TenantSettingsService::save()` y `VerticalPresetApplier`; migración `Version20260906050000` que rellena las filas por tenant; `TenantRuntimeConfigProvider` deriva `capabilities` sólo del resolver (overlay temporal eliminado). `TenantSettings` conserva columnas y getters como compat.
Criterio: `/api/tenant/runtime-config` mantiene la salida; consumidores de dominio (SaleProcessor, políticas de stock) siguen leyendo `TenantSettings` en modo compat; sin ramificación por `vertical`.

### Fase 2 — Presets versionados y onboarding

**NB-8 · Planificación/dry-run del preset** — implementado; preset declarativo completo queda pendiente
Objetivo: poder previsualizar el efecto de aplicar un preset y confirmar que re-aplicar no cambia nada.
Entregable: `VerticalPresetPlanner` (read-only) + `VerticalPresetPlan` VO (diff de settings campo→campo y de capabilities: `enable`/`config`/`noop`/`skip-manual`/`unknown`, + warehouse); `tenant:apply-preset --dry-run` imprime el diff en tablas; el aplicador registra auditoría estructurada (settings cambiados, capabilities habilitadas, warehouse) en el log del comando. Respeta overrides `manual`/`support` (los reporta como `skip-manual`).
Criterio: `VerticalPresetPlannerTest` (fresh tenant, re-apply no-op, override manual, warehouse); `tenant:apply-preset <key> --dry-run` no persiste.
Nota: los presets siguen declarando sólo `settings` + `desiredCapabilities`; menús/seeds/uiDefaults declarativos en el `VerticalPresetInterface` quedan como seguimiento (no bloquean).

**NB-9 · `--vertical=<key>` en provisioning + selección de giro en onboarding** — hecho (PR de #335)
Frontend: el wizard `TenantOnboardingGate` ya tenía el paso de selección de giro (aplica `POST /api/tenant/apply-preset`) — sin cambios.
Backend: `app:tenant:provision --vertical=<key>` corre `tenant:apply-preset` tras `--setup` (warning, no aborta); `--vertical` fluye por `ProvisionCommandRequest` → `ProvisionCommandRunner` → `TenantProvisioningService::run(vertical: …)` (default `''`, sin cambios para llamadores actuales).
Criterio: un operador provisiona `app:tenant:provision acme --setup --vertical=pharmacy` sin tocar BD; el wizard sigue siendo la fuente de verdad post-provisión.

### Fase 3 — Primera vertical como bounded context: Pharmacy — implementada en alcance inicial

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

### Fase 4 — Propiedad de datos (AR-005)

**NB-13 · Inventario de propiedad de tablas** — hecho (#339), inventario evolutivo
Entregable: `architecture/contexts.yaml` `ownsTables` y `externalTables` cubren las tablas del esquema tenant con ownership único; tablas legacy sin código vivo se anotan `# dead` mientras se decide/ejecuta su retiro. El número de tablas cambia con migraciones y no se fija aquí.
Criterio: `tests/Architecture/TableOwnershipTest.php` (KernelTestCase) cruza el esquema vivo del tenant (`information_schema`, tras aplicar `migrations/`) contra el manifiesto — falla ante una tabla sin dueño, una tabla con dos dueños, o una entrada de `ownsTables`/`externalTables` sin tabla viva; se salta si no hay DB de tenant accesible.

**NB-14 · Naming de tablas (según D4)** — decisión implementada; verificador presente
Legacy español congelado, sin renombrado masivo. Tablas nuevas usan `<context>_*`; `NewTableNamingConventionTest` cubre la convención. Mantener el test alineado con migraciones futuras.

**NB-15 · Guard de acceso cruzado a tablas** — implementado para escrituras raw-SQL
`CrossContextTableWriteTest` escanea escrituras `INSERT`/`UPDATE`/`DELETE`/`REPLACE`/`TRUNCATE` contra tablas de otro dueño, incluye auto-test del scanner y exige ADR para excepciones sancionadas. No cubre lecturas cruzadas ni sustituye control de escrituras vía ORM.

### Fase 5 — Puntos de extensión sistemáticos (G8)

**NB-16 · Registro de seams del core** — catálogo y guard implementados; ampliar según se formalicen nuevos seams
Objetivo: catalogar y normalizar `SaleValidator`, `PriceCalculator`, `TaxCalculator`, `InvoiceProcessor`, `StockPolicy`, `PaymentProvider` con default + resolución por capability/vertical.
Entregable: interfaces en el contexto dueño + `#[AsExtensionPoint]` o tag de servicio + doc.
Criterio: añadir una regla de vertical = registrar una implementación, cero cambios en el core.

### Fase 6 — Completar verticales existentes y añadir verticales siguientes

**NB-17 · Completar capacidades verticales** — Pharmacy ya cubre lotes, vencimiento y FEFO inicial; quedan capacidades de ferretería y extensiones de farmacia, como dividir una línea de venta entre varios lotes.
**NB-18 · `src/Restaurant` bounded context** — implementación inicial presente: áreas/mesas, pedidos e ítems, estados de preparación/entrega, envío a cocina, solicitud de cuenta/cierre y UI salón/cocina con Mercure. `CloseOrderProcessor` cierra el pedido con un `saleId` ya proporcionado y libera la mesa; no crea ni liquida la venta. Cobertura de pruebas limitada a entidad/procesador de cierre; falta validar recorridos integrales, modificadores y contrato de integración con Sales.
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
| 2026-09-24 | Estado revisado contra `src/`, `architecture/contexts.yaml` y `tests/Architecture/`; se actualizaron brechas y estado de NB-1…NB-18. |
| 2026-10-16 | Estado contrastado con código y pruebas: se corrige inmutabilidad `cash_movements`, se aclara el límite del cierre Restaurant, se registran módulos financieros presentes y se elimina el total fijo de tablas. |
