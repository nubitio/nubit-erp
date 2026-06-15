# Plan de arquitectura multivertical

**Estado:** propuesto para implementacion incremental  
**Fecha:** 2026-06-15  
**Alcance:** backend `efact`, web POS, Android POS y control plane SaaS

## Resumen ejecutivo

eFact debe evolucionar de un POS/facturacion con verticales iniciales a una plataforma multivertical mantenible. La decision central es separar dos conceptos que suelen mezclarse:

- **Capabilities:** comportamientos operativos reutilizables, como venta por peso, caja obligatoria, stock por lote, balanza, multi-almacen o facturacion SUNAT.
- **Vertical presets:** plantillas comerciales/versionadas que combinan capabilities, defaults, menus y seeds para un giro especifico, como carniceria, ferreteria, farmacia o restaurante.

La plataforma debe vender y operar verticales, pero el codigo debe componerse por capabilities. Esto evita duplicar logica por vertical y mantiene un camino claro para activar nuevas combinaciones sin llenar el dominio de condicionales `if vertical === ...`.

## Diagnostico actual

El sistema ya tiene bases correctas para una arquitectura enterprise:

- `Sales`, `Inventory`, `Catalog`, `CashRegister`, `Billing`, `Settings` y `ControlPlane` ya existen como bounded contexts.
- El aislamiento multi-tenant es database-per-tenant, con control plane separado.
- `TenantSettings` ya concentra configuracion operativa tipada del tenant.
- El control plane ya tiene `PlanFeature`, `TenantFeatureOverride` y `TenantFeatureChecker` para entitlements comerciales.
- Inventario ya usa ledger canonico (`stock_movements`) y read model (`stock_items`) con idempotencia.
- POS web ya tiene offline/outbox, codigo de barras, impresion local y soporte inicial de balanza via agente local.

El riesgo principal es que las nuevas verticales agreguen flags y campos directamente en `TenantSettings`, `Product`, `SaleRecord` o componentes POS. Ese camino funcionaria a corto plazo, pero crearia una matriz de comportamiento dificil de testear y operar.

## Decision de arquitectura

Adoptar un modelo hibrido:

```text
VerticalPreset = capabilities + defaults + menus + seeds + UI config
```

Los presets son una herramienta de onboarding, soporte y empaquetado comercial. Las capabilities son la unidad real de comportamiento del sistema.

Ejemplos:

| Vertical | Capabilities base |
|---|---|
| Retail generico | `sales.core`, `billing.sunat`, `cash.sessions`, `inventory.stock_tracking`, `hardware.barcode_scanner` |
| Carniceria | Retail + `catalog.weighted_items`, `hardware.scale` |
| Ferreteria | Retail + `inventory.multi_warehouse`, `catalog.multi_unit_sales`, `catalog.product_locations` |
| Farmacia | Retail + `inventory.lot_tracking`, `inventory.expiry_tracking`, `catalog.regulated_metadata` |
| Restaurante | `restaurant.tables`, `restaurant.orders`, `restaurant.kitchen_flow`, `sales.core`, `billing.sunat`, `cash.sessions` |

Restaurante debe ir al final porque no es solo una variante del POS actual. Introduce pedidos abiertos, mesas, comandas, cocina, modificadores y cierre posterior de venta. Debe tratarse como bounded context propio que integra con `Sales`, no como un preset grande dentro del flujo actual.

## Modelo conceptual

### Entitlements comerciales

`PlanFeature` y `TenantFeatureOverride` deben seguir representando derechos comerciales: lo que el plan permite usar.

Ejemplos: `excel_export`, `multi_warehouse`, `lot_tracking`, `advanced_reports`, `api_access`.

### Capabilities operativas

Agregar un catalogo operacional versionado:

```php
enum CapabilityKey: string
{
    case SalesCore = 'sales.core';
    case BillingSunat = 'billing.sunat';
    case CashSessions = 'cash.sessions';
    case InventoryStockTracking = 'inventory.stock_tracking';
    case InventoryLotTracking = 'inventory.lot_tracking';
    case InventoryExpiryTracking = 'inventory.expiry_tracking';
    case CatalogWeightedItems = 'catalog.weighted_items';
    case CatalogMultiUnitSales = 'catalog.multi_unit_sales';
    case HardwareScale = 'hardware.scale';
    case HardwareBarcodeScanner = 'hardware.barcode_scanner';
    case RestaurantTables = 'restaurant.tables';
    case RestaurantKitchenFlow = 'restaurant.kitchen_flow';
}
```

Cada capability debe tener metadata:

- key canonica
- nombre y descripcion
- bounded context propietario
- dependencias
- si requiere entitlement comercial
- config schema
- defaults seguros

### Estado operacional por tenant

Agregar almacenamiento tenant-local:

```text
tenant_capabilities
- id
- capability_key
- enabled
- config_json
- source: preset|manual|migration|support
- preset_key nullable
- created_at
- updated_at
```

Agregar perfil singleton:

```text
tenant_profile
- id
- vertical_key nullable
- preset_version
- applied_at
- locked_fields_json
```

`tenant_capabilities` no reemplaza todos los settings. `TenantSettings` puede seguir conteniendo configuracion tipada general, pero la logica nueva debe consultar un resolvedor de runtime y no flags dispersos.

## Runtime config

Crear un resolvedor unico:

```php
final class TenantCapabilityResolver
{
    public function isEnabled(string|CapabilityKey $key): bool;

    /** @return array<string, mixed> */
    public function config(string|CapabilityKey $key): array;

    public function require(string|CapabilityKey $key): void;
}
```

Resolucion esperada:

1. Validar que la capability exista en el catalogo.
2. Validar entitlement comercial si la capability lo requiere.
3. Leer estado tenant-local.
4. Fusionar defaults del catalogo, preset aplicado y overrides manuales.
5. Fallar cerrado ante claves desconocidas o dependencias incumplidas.

Exponer al frontend:

```text
GET /api/tenant/runtime-config
```

Respuesta esperada:

```json
{
  "vertical": "butcher",
  "capabilities": {
    "sales.core": { "enabled": true, "config": {} },
    "catalog.weighted_items": { "enabled": true, "config": { "precision": 3 } },
    "hardware.scale": { "enabled": true, "config": { "required": false } }
  },
  "settings": {
    "requireCashRegister": true,
    "autoInvoice": true,
    "negativeStockPolicy": "STRICT"
  },
  "ui": {
    "pos": {
      "showScaleButton": true,
      "quantityPrecision": 3
    }
  }
}
```

El POS debe cachear este runtime config para operar offline y refrescarlo al iniciar sesion, cambiar sucursal o detectar nueva version.

## Presets verticales

Crear `VerticalPresetCatalog` con presets declarativos y versionados. Un preset no debe contener logica de negocio; solo describe el estado inicial deseado.

Ejemplo conceptual:

```php
final readonly class VerticalPreset
{
    public function __construct(
        public string $key,
        public string $label,
        public int $version,
        /** @var array<string, bool|array<string, mixed>> */
        public array $capabilities,
        /** @var array<string, mixed> */
        public array $tenantSettings,
        /** @var list<string> */
        public array $menus,
        /** @var array<string, mixed> */
        public array $seedData,
        /** @var array<string, mixed> */
        public array $uiDefaults,
    ) {}
}
```

Ejemplo carniceria:

```text
butcher
- sales.core
- billing.sunat
- cash.sessions requireOpenSession=true
- inventory.stock_tracking negativeStockPolicy=STRICT
- catalog.weighted_items precision=3
- hardware.scale required=false stableWeightTimeoutMs=12000
- hardware.barcode_scanner
```

El aplicador de presets debe ser idempotente:

- soportar dry-run
- mostrar diff antes de aplicar
- no borrar overrides manuales sin confirmacion explicita
- registrar auditoria de capabilities, settings, menus y seeds aplicados
- guardar `preset_key` y `preset_version`

## Integracion con POS

El POS no debe inferir todo por vertical. Debe resolver comportamiento por capabilities:

| Comportamiento | Decision runtime |
|---|---|
| Mostrar boton de balanza | `hardware.scale` enabled |
| Permitir cantidad decimal por peso | `catalog.weighted_items` enabled y unidad de peso |
| Intentar lectura automatica al agregar producto | `hardware.scale.config.autoReadOnAdd` |
| Requerir caja abierta | `cash.sessions.config.requireOpenSession` |
| Auto emitir comprobante | `billing.sunat.config.autoInvoice` |
| Bloquear stock negativo | `inventory.stock_tracking.config.negativeStockPolicy` |

La heuristica actual por unidad (`KGM`, `GRM`, etc.) es util, pero debe convertirse en una regla subordinada a `catalog.weighted_items`.

## Producto y catalogo

No agregar campos por cada vertical directamente en `Product`. Usar esta regla:

- Si el dato afecta invariantes transaccionales, stock, impuestos o reporting critico, crear tabla tipada.
- Si el dato es metadata liviana de UI/defaults, usar atributos JSON validados por schema.

Posibles extensiones:

```text
product_profile
- product_id
- profile_key: weighted|pharmacy|hardware|restaurant
- attributes_json
```

Tablas tipadas futuras:

- `product_expiry_policy`
- `product_regulatory_metadata`
- `product_location`
- `product_variant`
- `restaurant_modifier_group`

## Roadmap recomendado

### Fase 1: Fundacion runtime

- Crear `CapabilityKey`, `CapabilityDefinition`, `CapabilityCatalog` y `TenantCapabilityResolver`.
- Crear tablas `tenant_capabilities` y `tenant_profile`.
- Crear endpoint `/api/tenant/runtime-config`.
- Agregar tests de resolucion, dependencias y fail-closed.

### Fase 2: Presets versionados

- Crear `VerticalPresetCatalog`.
- Crear `PresetApplier` con dry-run y auditoria.
- Agregar `--vertical=<key>` a provisioning/setup.
- Exponer seleccion de giro en onboarding.

### Fase 3: Migracion de flags existentes

- Mapear `requireCashRegister` a `cash.sessions`.
- Mapear `autoInvoice` a `billing.sunat`.
- Mapear `negativeStockPolicy` a `inventory.stock_tracking`.
- Mapear `printMethod` a `hardware.printing`.
- Mantener backward compatibility solo mientras frontend y API terminan de migrar.

### Fase 4: POS capability-aware

- Consumir `/api/tenant/runtime-config`.
- Cachear runtime config para offline.
- Gatear balanza, cantidades decimales, caja, auto factura y menu por capabilities.
- Cubrir con Vitest las decisiones de UI y calculo por peso.

### Fase 5: Primer vertical real: carniceria

- Aplicar preset `butcher`.
- Validar venta por kg, lectura de balanza, cantidad manual, ticket y descuento de stock decimal.
- Registrar en venta si la cantidad vino de balanza o entrada manual cuando sea requerido por soporte/auditoria.

### Fase 6: Ferreteria y farmacia

- Ferreteria: multi-unidad, ubicacion, stock minimo, reposicion y variantes.
- Farmacia: lotes, vencimiento, FEFO, alertas y bloqueo de vencidos.

### Fase 7: Restaurante como bounded context

- Crear `Restaurant` separado.
- Modelar mesas, areas, pedidos, comandas, modificadores y cocina.
- Integrar con `Sales` solo al cierre/cobro.
- Evitar contaminar `SaleProcessor` con estados de mesa/pedido abierto.

## Guardrails

- No usar `vertical_key` para ramas de negocio dentro de servicios centrales.
- No agregar nuevos flags sueltos a `TenantSettings` si representan behavior reusable.
- No usar `PlanFeature` como storage operacional de verticales.
- No duplicar pantallas por vertical si la diferencia es configuracion de campos, acciones o defaults.
- No introducir plugins dinamicos complejos hasta que existan al menos tres verticales con necesidades repetidas y medibles.
- Todo comportamiento activado por capability debe tener tests de backend y frontend cuando afecte venta, stock, caja o facturacion.

## Criterios de aceptacion

- Un tenant nuevo puede elegirse como retail, carniceria o ferreteria sin cambios manuales de base de datos.
- Las capabilities efectivas son visibles por API y auditables por soporte.
- El POS cambia comportamiento por runtime config, no por hardcodes de vertical.
- Las ventas existentes no cambian su semantica fiscal ni de inventario.
- Restaurante queda explicitamente fuera del flujo POS actual hasta tener bounded context propio.
