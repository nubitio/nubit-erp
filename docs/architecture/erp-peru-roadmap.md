# Plan: de POS/facturación a ERP completo para el mercado peruano

**Estado:** plan de producto/arquitectura de 2026-07-21; parcialmente materializado y revisado contra el código el 2026-10-16. Las fases siguientes son backlog, no evidencia de que cada módulo esté completo.
**Fecha del plan:** 2026-07-21 · **Última revisión:** 2026-10-16
**Alcance:** backend `efact`, web (React), control plane SaaS. Android POS solo donde se indique explícitamente.

## Resumen ejecutivo

El producto ya no es solo POS/facturación: el código contiene contextos iniciales de `Accounting`, `BankReconciliation`, `FixedAssets`, `Payroll`, `AccountsPayable`, `ExpenseReports` y `Billing/Recurring`, además de ventas, compras, caja e inventario. La brecha es completar y validar la cobertura funcional/fiscal (en especial asientos automáticos completos, cierres/libros/estados financieros, tesorería y ciclos regulatorios), no crear esos contextos desde cero. CRM, costeo/presupuestos y varias obligaciones PLE siguen siendo alcance futuro o por confirmar.

La decisión central de este plan sigue siendo no romper las fuentes de verdad de los contextos operativos. El código ya emite `EconomicEvent` desde ventas, compras, caja, cuentas por cobrar/pagar y anticipos; un handler contable síncrono genera asientos para los tipos soportados. Extender los eventos/reglas y validar cobertura completa es trabajo pendiente; `Accounting` ya existe y no debe crearse como contexto nuevo.

## Diagnóstico contrastado con el código

Lo que ya existe y es reutilizable tal cual:

- Bounded contexts bajo `src/`, con layout pragmático por contexto (`ADR-006`); `ADR-002` fue supersedido y no es la convención implementada.
- Ledgers append-only e inmutabilidad de documentos fiscales ya como principio de diseño (`ADR-003`) — el mismo principio que necesita un libro diario contable.
- Multi-tenant database-per-tenant con control plane separado, `TenantSettings` y `PlanFeature`/`TenantFeatureOverride` para entitlements comerciales.
- Catálogo de capabilities, resolver y estado por tenant implementados. La dirección vigente y sus límites están en ADR-018; `multivertical-capability-plan.md` es una propuesta histórica parcialmente superada.
- `Reporting/Ple` ya exporta libros electrónicos de Ventas y Compras (formato SUNAT).
- `Taxes` ya tiene queries de detracciones y retenciones/percepciones (falta el flujo de emisión/registro completo).
- `Purchasing` y `AccountsPayable` tienen flujos de compra, facturas de proveedor y pagos; profundidad de conciliación/contabilización pendiente de validación.
- `Payroll` mantiene su propio contexto de `Employee`; no asumir que existe un maestro único de personas en `Contacts`.
- `Organization` ya modela `Company`/`Branch` — base para multi-compañía y centros de costo.
- `Accounting` registra eventos económicos y asientos, consulta mayor/balance de comprobación y participa en exportación PLE contable; validar cobertura de reglas, cierres y totalidad PCGE antes de considerarlo libro mayor integral.
- `BankReconciliation` implementa cuentas bancarias, importación de estados y matching; no equivale a un módulo Treasury integral ni a flujo proyectado.
- `FixedAssets` y `Payroll` tienen modelos/servicios y operaciones principales, incluido comando de depreciación y exportación PLAME; la cobertura legal/GA requiere validación funcional independiente.
- `Billing/Recurring` implementa planes, suscripciones, ejecuciones y generación programada/manual; ver [`recurring-billing-plan.md`](../recurring-billing-plan.md).

Brechas funcionales y alcance no confirmado (no asumir contextos ausentes):

> Las fases siguientes conservan la secuencia de diseño original. Para contextos que ya existen, su entregable debe leerse como completar cobertura/integración y no como crearlos de nuevo.

| Brecha | Por qué importa en Perú |
|---|---|
| Integridad contable y cierre de periodos | Ya existe `Accounting` con asientos y consultas; verificar automatización por eventos, reglas PCGE, partida doble, cierres y controles de periodo |
| Libros PLE completos (Diario/Mayor/Inventarios y Balances/Activos) | Hay exportación contable y de activos inicial; verificar formatos, datasets y pruebas contra requisitos SUNAT vigentes |
| Planillas / PLAME | El contexto `Payroll` y exportador PLAME existen; validar casos regulatorios completos, cambios normativos y aceptación de salida |
| Activos fijos y depreciación | `FixedAssets` tiene alta, depreciación y baja; validar ciclo completo, reglas fiscales y Libro de Activos Fijos |
| Tesorería y posición financiera | Hay `BankReconciliation`, CxC/CxP y caja; falta confirmar flujo proyectado, liquidación bancaria y conciliación integral |
| Multi-moneda con tipo de cambio SUNAT | Existe catálogo/tabla de tipos de cambio; validar aplicación contable y tratamiento de moneda funcional en todos los procesos |
| Estados financieros (Balance General, Estado de Resultados, Flujo de Efectivo) | No se verificó un conjunto completo de estados contables cerrados y reconciliables derivados del mayor |
| CRM (prospectos, oportunidades, seguimiento comercial) | Fuera del alcance fiscal, pero es lo que diferencia "facturación" de "ERP" para PyMEs que crecen |
| Costeo/centros de costo/presupuestos | Necesario para reporting gerencial, no solo fiscal |

## Decisión de arquitectura

Extender el modelo existente en vez de crear uno paralelo:

```text
Accounting = contexto existente, con registro contable inicial derivado de hechos económicos
Payroll/FixedAssets = contextos existentes; Treasury integral aún no figura como contexto separado
Capabilities y entitlements = mecanismo actual para resolver comportamiento y acceso comercial
```

### Contabilidad como consumidor de eventos, no como reescritura

`Accounting` no debe convertirse en un dios que sabe de ventas, compras y planillas. Varios emisores ya despachan `EconomicEvent` por el transporte síncrono y `EconomicEventHandler` aplica reglas predeterminadas para tipos soportados. La cobertura de nómina/activos y reglas configurables por tenant no se debe dar por hecha sin verificar los emisores y mapeos concretos. El flujo síncrono es una integración implementada, no una promesa de eventual consistency.

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

### Fase 0: Preparación (fundación ya implementada; mantener)

- Mantener catálogo/resolver/estado de capabilities y revisar presets según ADR-018 y `CONTEXT.md`; no recrear infraestructura ya existente.
- Auditar y mantener el contrato de `EconomicEvent`/transporte síncrono y cobertura de productores/postings. No asumir que eventos de todos los módulos enlistados en el plan están conectados.

### Fase 1: Completar el núcleo contable existente

- Completar el contexto existente `src/Accounting`; no adoptar aquí la estructura DAIA supersedida por ADR-006.
- `ChartOfAccounts` con seed PCGE estándar peruano, personalizable por tenant.
- `JournalEntry`/`JournalEntryLine` append-only, con `reversingEntryId` para correcciones (mismo patrón que `stock_movements`).
- `PostingRuleResolver` + reglas por defecto para: emisión de comprobante (`Billing`), compra recibida (`Purchasing`), cierre de caja (`CashRegister`).
- Reporte de Balance de Comprobación (trial balance) como primer read-model en `Reporting`.
- Tests: asiento correcto por cada evento, partida doble balanceada, fail-closed ante evento sin regla mapeada.

### Fase 2: Libros electrónicos ampliados

- Extender `Reporting/Ple` con exportadores de Libro Diario y Libro Mayor (reutiliza `PleWriter`/`PleZipService`, formato SUNAT).
- Libro de Inventarios y Balances derivado de `Inventory` + `Accounting` cerrado de periodo.
- Cierre de periodo contable (`fiscal_periods`) con validación de periodo abierto/cerrado antes de aceptar nuevos asientos.

### Fase 3: Completar Tesorería y Cuentas por Cobrar/Pagar

- `BankReconciliation` ya cubre cuentas/estados bancarios e importación/matching; evaluar lo que falta para una frontera Treasury y proyección de flujo de caja sin duplicar funcionalidades.
- Vistas de CxC (facturas emitidas no cobradas) y CxP (compras no pagadas) con antigüedad de saldos, derivadas de `Billing`/`Purchasing` — sin duplicar la fuente de verdad.
- Multi-moneda: tipo de cambio SUNAT diario, conversión en reportes, sin alterar la moneda de emisión del comprobante (que ya es un invariante fiscal).
- Flujo de caja proyectado (Reporting) combinando CxC/CxP/caja.

### Fase 4: Completar Activos Fijos

- `FixedAssets` ya existe con activos, depreciación y baja; validar calendario, reversas y reglas fiscales antes de ampliar PLE.
- Evento `AssetDepreciated` posteado a `Accounting` en cada cierre de periodo.
- Libro de Activos Fijos (PLE) reutilizando la infraestructura de Fase 2.

### Fase 5: Completar Planillas (RRHH)

- Evaluar si conviene unificar empleados de `Payroll` con `Contacts`; actualmente `Payroll` tiene su propio modelo de empleado, por lo que no describir la unificación como ya existente ni como migración decidida.
- `Payroll` ya existe con empleados, cálculo/corridas y exportador PLAME; validar y completar conceptos, cumplimiento normativo y reportes bajo revisión especializada.
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
