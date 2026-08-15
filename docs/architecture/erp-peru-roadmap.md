# Plan: de POS/facturación a ERP completo para el mercado peruano

**Estado:** propuesto para implementación incremental
**Fecha:** 2026-07-21
**Alcance:** backend `efact`, web (React), control plane SaaS. Android POS solo donde se indique explícitamente.

## Resumen ejecutivo

eFact es hoy un SaaS de POS + facturación electrónica SUNAT con inventario, caja, compras básicas y libros de ventas/compras. Para convertirse en un ERP completo para Perú necesita un **núcleo contable** (plan de cuentas, asientos, libros electrónicos completos) del que todo lo demás (ventas, compras, caja, planillas, activos, tesorería) sea *emisor de hechos económicos*, más los módulos que hoy no existen: Contabilidad, Tesorería/Bancos, Activos Fijos, Planillas (RRHH) y, más adelante, CRM y Costeo/Presupuestos.

La decisión central de este plan es no romper lo que ya funciona: `Sales`, `Billing`, `Inventory`, `CashRegister`, `Purchasing`, `Taxes` y `Reporting` no dejan de ser la fuente de verdad de sus propios hechos (venta, comprobante, movimiento de stock, movimiento de caja, compra). Lo que se agrega es un contexto `Accounting` que **escucha** esos hechos vía Messenger (mismo mecanismo de ADR-001) y genera asientos contables derivados, en vez de que cada módulo aprenda a contabilizar por su cuenta.

## Diagnóstico actual

Lo que ya existe y es reutilizable tal cual:

- Bounded contexts limpios (`ADR-001`) con capa `Domain/Application/Infrastructure/Api` (`ADR-002`).
- Ledgers append-only e inmutabilidad de documentos fiscales ya como principio de diseño (`ADR-003`) — el mismo principio que necesita un libro diario contable.
- Multi-tenant database-per-tenant con control plane separado, `TenantSettings` y `PlanFeature`/`TenantFeatureOverride` para entitlements comerciales.
- Arquitectura de capabilities/vertical presets ya diseñada (`multivertical-capability-plan.md`) — es el mecanismo correcto para activar módulos de ERP por plan/vertical sin condicionales dispersos.
- `Reporting/Ple` ya exporta libros electrónicos de Ventas y Compras (formato SUNAT).
- `Taxes` ya tiene queries de detracciones y retenciones/percepciones (falta el flujo de emisión/registro completo).
- `Purchasing` ya tiene `Purchase`, `PurchaseItem`, recepción y anulación — base para Cuentas por Pagar.
- `Contacts` ya unifica `Customer`/`Supplier` — el ADR ya prevé "empleados con asignación de roles", pero la entidad `Employee` no existe todavía.
- `Organization` ya modela `Company`/`Branch` — base para multi-compañía y centros de costo.

Lo que falta por completo (no es refactor, es construir contexto nuevo):

| Brecha | Por qué importa en Perú |
|---|---|
| Plan de cuentas y asientos contables (Libro Diario/Mayor) | Sin esto no hay estados financieros ni Libro Diario/Mayor electrónico exigido por SUNAT a partir de ciertos ingresos |
| Libro de Inventarios y Balances, Libro de Activos Fijos | PLE hoy solo cubre Ventas y Compras |
| Planillas / PLAME | Obligación laboral-tributaria independiente de PLE; requiere maestro de empleados, cálculo de AFP/ONP/EsSalud/CTS/gratificaciones |
| Activos fijos y depreciación | Requerido para EEFF y Libro de Activos Fijos |
| Tesorería: cuentas bancarias, conciliación, CxC/CxP con antigüedad de saldos, flujo de caja | Hoy solo existe caja chica (`CashRegister`) y compras/ventas puntuales, sin vista de cobranza/pago pendiente |
| Multi-moneda con tipo de cambio SUNAT | Necesario para EEFF y para empresas con operaciones en USD |
| Estados financieros (Balance General, Estado de Resultados, Flujo de Efectivo) | Se derivan del libro mayor; hoy no hay libro mayor |
| CRM (prospectos, oportunidades, seguimiento comercial) | Fuera del alcance fiscal, pero es lo que diferencia "facturación" de "ERP" para PyMEs que crecen |
| Costeo/centros de costo/presupuestos | Necesario para reporting gerencial, no solo fiscal |

## Decisión de arquitectura

Extender el modelo existente en vez de crear uno paralelo:

```text
Accounting = libro contable derivado de eventos de dominio de otros contextos
Payroll, Treasury, FixedAssets = nuevos bounded contexts que también emiten eventos hacia Accounting
Capability/VerticalPreset = mecanismo único para activar módulos ERP por plan y por giro
```

### Contabilidad como consumidor de eventos, no como reescritura

`Accounting` no debe convertirse en un dios que sabe de ventas, compras y planillas. Cada contexto emisor (`Sales`, `Billing`, `Purchasing`, `CashRegister`, `Payroll`, `FixedAssets`, `Treasury`) publica eventos de dominio ya existentes o nuevos (p. ej. `InvoiceIssued`, `PurchaseReceived`, `CashSessionClosed`, `PayslipGenerated`, `AssetDepreciated`). `Accounting` los consume y aplica **reglas de asiento contable configurables por tenant** (mapeo cuenta ↔ evento), generando el asiento. Esto respeta ADR-001: ningún contexto llama directamente a servicios de `Accounting`.

```php
final readonly class ChartOfAccounts { /* PCGE: plan contable general empresarial */ }

final readonly class JournalEntry
{
    // append-only, igual que stock_movements/cash_movements (ADR-003)
    // corrección = nuevo asiento con reversingEntryId, nunca UPDATE/DELETE
}

final class AccountingPostingRuleResolver
{
    public function resolve(DomainEventName $event, Tenant $tenant): PostingRule;
}
```

### Modelo de datos nuevo (alto nivel)

```text
chart_of_accounts        - cuentas PCGE por tenant (seed estándar + personalización)
journal_entries          - append-only, header
journal_entry_lines      - débito/crédito por cuenta, append-only
posting_rules            - mapeo evento de dominio -> líneas contables, versionado
fiscal_periods           - apertura/cierre de periodo contable

employees                - extiende Contacts (rol "employee" sobre partner unificado)
payroll_runs             - corrida de planilla por periodo
payslips                 - boleta de pago por empleado, inmutable tras emisión
payroll_concepts         - conceptos remunerativos/no remunerativos configurables

fixed_assets             - activo, fecha de alta, método/vida útil
depreciation_schedules   - append-only, una línea por periodo depreciado

bank_accounts            - cuentas bancarias del tenant
bank_transactions        - movimientos importados/conciliados
receivables / payables   - vistas derivadas de Billing/Purchasing + estado de cobro/pago
```

Igual que en el plan multivertical: si el dato afecta invariantes transaccionales o reporting fiscal crítico, va en tabla tipada; si es configuración liviana, va en JSON validado por schema (p. ej. `posting_rules.config_json`).

## Integración con capabilities y planes comerciales

Los módulos de ERP se activan igual que las capabilities operativas ya diseñadas, no con un flag nuevo por módulo:

| Capability nueva | Bounded context | Depende de |
|---|---|---|
| `accounting.general_ledger` | `Accounting` | — |
| `accounting.electronic_books` (Diario/Mayor/Inv. y Balances) | `Accounting` + `Reporting` | `accounting.general_ledger` |
| `payroll.core` | `Payroll` | `contacts.employees` |
| `payroll.plame_export` | `Payroll` | `payroll.core` |
| `treasury.bank_accounts` | `Treasury` | — |
| `treasury.bank_reconciliation` | `Treasury` | `treasury.bank_accounts`, `accounting.general_ledger` |
| `fixed_assets.tracking` | `FixedAssets` | `accounting.general_ledger` |
| `crm.pipeline` | `Contacts` (extensión) o `CRM` propio si crece | — |

Esto permite vender "eFact POS" (hoy), "eFact + Contabilidad", "eFact ERP completo" como planes distintos vía `PlanFeature`/`TenantFeatureOverride`, sin bifurcar código por vertical. También habilita nuevos `VerticalPreset` orientados a servicios profesionales o comercio con contabilidad tercerizada (donde solo se activa `billing.sunat` + `accounting.general_ledger`, sin POS/inventario).

## Roadmap recomendado

### Fase 0: Preparación (antes de tocar Accounting)

- Completar Fase 1–3 del `multivertical-capability-plan.md` (catálogo de capabilities, resolver, presets versionados) — es el mecanismo que activará cada módulo ERP.
- Definir catálogo estándar de eventos de dominio salientes de `Sales`, `Billing`, `Purchasing`, `CashRegister` (los que ya deberían existir para Messenger según ADR-001; auditar cobertura real).

### Fase 1: Núcleo contable

- Crear contexto `Accounting` (Domain/Application/Infrastructure/Api).
- `ChartOfAccounts` con seed PCGE estándar peruano, personalizable por tenant.
- `JournalEntry`/`JournalEntryLine` append-only, con `reversingEntryId` para correcciones (mismo patrón que `stock_movements`).
- `PostingRuleResolver` + reglas por defecto para: emisión de comprobante (`Billing`), compra recibida (`Purchasing`), cierre de caja (`CashRegister`).
- Reporte de Balance de Comprobación (trial balance) como primer read-model en `Reporting`.
- Tests: asiento correcto por cada evento, partida doble balanceada, fail-closed ante evento sin regla mapeada.

### Fase 2: Libros electrónicos ampliados

- Extender `Reporting/Ple` con exportadores de Libro Diario y Libro Mayor (reutiliza `PleWriter`/`PleZipService`, formato SUNAT).
- Libro de Inventarios y Balances derivado de `Inventory` + `Accounting` cerrado de periodo.
- Cierre de periodo contable (`fiscal_periods`) con validación de periodo abierto/cerrado antes de aceptar nuevos asientos.

### Fase 3: Tesorería y Cuentas por Cobrar/Pagar

- Contexto `Treasury`: `bank_accounts`, `bank_transactions`, conciliación manual/asistida.
- Vistas de CxC (facturas emitidas no cobradas) y CxP (compras no pagadas) con antigüedad de saldos, derivadas de `Billing`/`Purchasing` — sin duplicar la fuente de verdad.
- Multi-moneda: tipo de cambio SUNAT diario, conversión en reportes, sin alterar la moneda de emisión del comprobante (que ya es un invariante fiscal).
- Flujo de caja proyectado (Reporting) combinando CxC/CxP/caja.

### Fase 4: Activos fijos

- Contexto `FixedAssets`: alta de activo, método de depreciación (línea recta como default legal), calendario de depreciación append-only.
- Evento `AssetDepreciated` posteado a `Accounting` en cada cierre de periodo.
- Libro de Activos Fijos (PLE) reutilizando la infraestructura de Fase 2.

### Fase 5: Planillas (RRHH)

- Extender `Contacts` con rol `employee` sobre el partner unificado (ya previsto en el ADR) en vez de crear un maestro de personas paralelo.
- Contexto `Payroll`: conceptos remunerativos/no remunerativos configurables, cálculo de AFP/ONP/EsSalud/CTS/gratificación/vacaciones según reglas vigentes, `payroll_runs` y `payslips` inmutables tras emisión.
- Exportador PLAME (formato SUNAT/Sunat-MTPE).
- Evento `PayslipGenerated` posteado a `Accounting`.

### Fase 6: Estados financieros y costeo

- Balance General, Estado de Resultados y Estado de Flujo de Efectivo como read-models en `Reporting`, derivados del libro mayor cerrado.
- Centros de costo (extensión de `Organization`) y presupuestos básicos por centro de costo/periodo.

### Fase 7: CRM comercial

- Empezar como extensión ligera de `Contacts` (prospecto = partner sin rol cliente todavía + pipeline de oportunidad).
- Separar a bounded context `CRM` propio solo si el pipeline crece más allá de seguimiento simple (mismo criterio de "tres verticales con necesidad repetida" del plan multivertical, aplicado aquí a "tres flujos comerciales distintos").

### Fuera de alcance por ahora

- Manufactura/producción (BOM, órdenes de producción): no hay señal de demanda validada; no empezar hasta confirmarla con al menos un tenant real.
- Proyectos/horas facturables: mismo criterio — esperar demanda.
- Restaurante ya está planificado como bounded context aparte en `multivertical-capability-plan.md`; no se duplica aquí.

## Guardrails

- `Accounting` nunca es la fuente de verdad de ventas, compras, stock o caja — solo consume sus eventos. Si `Accounting` necesita un dato que su evento no trae, se enriquece el evento, no se le da acceso directo al repositorio de otro contexto.
- Los asientos contables son append-only; una corrección es un asiento reversante, nunca UPDATE/DELETE (mismo principio que ADR-003).
- No mezclar `employee` con `Customer`/`Supplier` como entidades separadas: usar el partner unificado de `Contacts` con asignación de rol, tal como ya especifica el ADR de arquitectura.
- No activar módulos ERP con flags sueltos en `TenantSettings`: usar capabilities + entitlements, igual que el resto de la plataforma.
- Cada módulo nuevo sigue la estructura de 4 capas de ADR-002 desde el día uno; no se admite "vamos a ordenarlo después".
- Periodo contable cerrado bloquea nuevos asientos retroactivos salvo apertura explícita auditada.
- No se contabiliza en la moneda de reporting sin conservar la moneda original del comprobante fiscal.

## Criterios de aceptación por fase

- Fase 1: un tenant con `accounting.general_ledger` activo ve un Balance de Comprobación cuadrado generado 100% desde eventos, sin digitación manual de asientos operativos.
- Fase 2: el tenant puede exportar Libro Diario y Libro Mayor en formato PLE válido para un periodo cerrado.
- Fase 3: CxC/CxP y flujo de caja proyectado no requieren registrar nada dos veces respecto a `Billing`/`Purchasing`.
- Fase 4: el Libro de Activos Fijos se genera sin intervención manual tras el cierre de periodo.
- Fase 5: PLAME se genera y valida contra el formato SUNAT vigente para al menos un caso con AFP y uno con ONP.
- Fase 6: Estado de Resultados y Balance General cuadran contra el Balance de Comprobación de Fase 1 sin ajustes manuales ocultos.
- En todas las fases: los flujos POS/facturación/inventario/caja existentes no cambian su semántica fiscal ni operativa.
