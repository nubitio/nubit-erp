# 04 · Diseño funcional objetivo

Reutiliza lo existente: drawer y diálogos de AR (#409/#508), `OpenItemsPage(side)` y componentes compartidos propuestos en ADR-021 P3 (`AgingStrip`, `PartyDrawer`, `SettleDialog`, `MoneyCell`, `StatusBadge`, `PeriodSelector`). No se crean pantallas nuevas donde baste una pestaña o acción contextual.

## 1. Navegación

Grupo **Finanzas**: Resumen · Por cobrar · Por pagar · Caja y bancos · (Contabilidad, Más) — ya acordado en ADR-021 P1. Por cobrar/Por pagar son la misma pantalla con el lado invertido. Cajeros siguen en "Caja".

## 2. Flujos comerciales y financieros

| Flujo | Comercial | Financiero |
| --- | --- | --- |
| Venta contado | venta + CPE | cobro en el POS → Treasury |
| Venta crédito | venta + CPE con cuotas reales | `OpenReceivable` con cuotas; sin movimiento de dinero |
| Pago inicial + saldo | venta + CPE (monto neto pendiente = saldo) | cobro inicial + receivable por el saldo |
| Cobranza | — | `RegisterCollection` (no es venta) |
| NC | NC en Billing | `ApplyAdjustment`; si hubo pago: saldo a favor o reembolso (D-01) |
| Compra crédito | compra + recepción | `OpenPayable` por la condición de pago |
| Pago a proveedor | — | `RegisterPayment` |

## 3. Pantallas

### 3.1 Por cobrar (cartera)

- **Objetivo**: saber quién debe y cuánto, y cobrar. **Usuario**: dueño/supervisor, cajero con permiso.
- **Información**: KPIs deuda total / vencida / por vencer (una línea por moneda); grilla por cliente (deuda, vencida, próximo vencimiento, saldo a favor, límite utilizado); vista alternativa por documento/cuota; drawer del cliente con documentos pendientes, historial de cobranzas, anticipos, estado de cuenta.
- **Acciones**: Cobrar, Registrar anticipo, Aplicar anticipo, Revertir cobranza, Estado de cuenta (PDF/Excel), Recordatorio, Promesa de pago (F3).
- **Filtros**: cliente, sucursal, moneda, vencimiento (rango/bucket), estado de gestión.
- **Permisos**: `receivables.view`, `receivables.collect`, `receivables.reverse` (ver §6).
- **Vacío/error**: vacío = "Sin deudas pendientes" + CTA a ventas; error con reintento sin solapar paginación (corrige hallazgo UI de ADR-021).
- **Validaciones**: importe > 0; moneda homogénea; exceso solo con confirmación a anticipo; no superar saldo de cuota; clave idempotente generada al abrir el diálogo.
- **Interacciones**: Treasury (cuentas), Billing (NC), Sales (venta origen), Reportes.

### 3.2 Flujo de cobranza (8 pasos del encargo)

1. Seleccionar cliente (o abrir desde su fila). 2. Se muestra su deuda por cuota. 3. Ingresar importe recibido. 4. Agregar medios (uno por defecto; "+ otro medio"), cuenta sugerida por método (mapeo de Treasury), editable. 5. Distribución automática *por vencimiento más antiguo* (estrategia seleccionable: más antigua por vencimiento / por emisión / manual). 6. Edición manual por cuota con total restante visible y bloqueo si Σ ≠ importe (o diferencia → "saldo a favor"). 7. Confirmar (botón deshabilitado durante el envío; la clave idempotente evita duplicados). 8. Constancia de cobranza (imprimible/WhatsApp) y saldo actualizado.

Cobro por documento = mismo diálogo abierto desde la fila con la asignación prefijada.

### 3.3 Por pagar

Espejo: cartera por proveedor, vista por documento/cuota, drawer del proveedor, **Pagar** (global o por documento, multi-medio, cuentas de Treasury), historial, anticipos (F2), **programación** (marcar cuotas "para pagar el viernes", F2), estado de cuenta, agenda. Permisos: `payables.view/pay/reverse/schedule/authorize`. Retira `/supplier-bill/:id/pay` (pasa a diálogo) cuando AP migre a `OpenItemsPage`.

### 3.4 Caja y bancos

Resumen de disponibilidad por cuenta y moneda; gestión de cuentas (existente, `treasury.manage`); movimientos con origen enlazado; cajas y turnos (existente); transferencias (`treasury.transfer`); conciliación (existente); cierres. Cobranzas y pagos se ven como movimientos con enlace a la cobranza.

### 3.5 Resumen financiero

Por cobrar, por pagar, vencido (ambos lados), disponibilidad, próximos 7/30 días, flujo proyectado (cuotas AR/AP por fecha + saldo actual). Tarjetas que enlazan a las listas filtradas. Ya existe `FinanceSummaryQuery`; se extiende con cuotas.

## 4. Estado de cuenta (cliente y proveedor)

Dos vistas con parámetro `asOf`: (a) **Movimientos cronológicos**: saldo anterior, cargos, abonos, ajustes, saldo acumulado, saldo final (ventana SQL sobre `ar_*`); (b) **Pendientes**: documento, emisión, vencimiento, importe original, liquidado *a la fecha de corte*, saldo, días de atraso. Reconstrucción a corte: importe liquidado = Σ allocations con `applied_on ≤ asOf` y no revertidas **a esa fecha** (`reversed_at > asOf` o nulo).

## 5. Reportes

| Reporte | Fuente | Nota |
| --- | --- | --- |
| Cartera por cliente / proveedor | cuotas | por moneda |
| Documentos / cuotas pendientes | `ar_installment` / `ap_installment` | — |
| Aging | servicio único, buckets configurables, **por vencimiento** | reemplaza 4 implementaciones |
| Estado de cuenta | §4 | — |
| Historial de cobranzas / pagos | collections / payments + tenders | — |
| Agenda de vencimientos | cuotas | próximas y vencidas |
| Flujo realizado / proyectado | Treasury / cuotas + programaciones | — |
| Utilización de crédito | `credit_policy` vs deuda | F2 |
| Morosidad | aging + gestión | F2 |

Reconciliación: cada reporte incluye un total que debe igualar `finance:verify` (Σ cuotas = Σ ventas a crédito − allocations − ajustes; Σ tenders = Σ movimientos de Treasury).

## 6. Permisos

| Capacidad | Permiso propuesto | Hoy |
| --- | --- | --- |
| Consultar cartera | `receivables.view` / `payables.view` | `sales.view`, `accounts_payable.view` |
| Registrar cobranza | `receivables.collect` | `sales.collect` |
| Registrar pago | `payables.pay` | `accounts_payable.pay` |
| Modificar aplicaciones / revertir | `receivables.reverse`, `payables.reverse` | inexistente / `pay` |
| Ajustes y castigos | `receivables.adjust` | inexistente |
| Autorizar crédito / exceder límite | `credit.authorize`, `credit.override` | inexistente |
| Programar / autorizar / ejecutar pagos | `payables.schedule`, `payables.authorize`, `payables.execute` | inexistente |
| Reportes financieros | `finance.reports` | mezclado |
| Otras sucursales | `finance.all_branches` | ver `BranchContext` |

Compatibilidad: migración de roles asigna los nuevos permisos a quien hoy tiene el equivalente; **nadie obtiene permisos financieros por tener POS/ventas** (`sales.create` no implica `receivables.collect`). Negocio pequeño: el rol Administrador tiene todos; empresas: se separan.

## 7. Casos de uso alternativos

Cobro con caja cerrada (efectivo) → mensaje y bloqueo (actual); cobro bancario sin turno → permitido; reintento → misma constancia (HTTP 200 con `replayed=true`); conflicto de concurrencia → "El saldo cambió, revisa la distribución" y se recalcula; reversión parcial por asignación; cobro en moneda distinta a la cuota → rechazo claro (D-04).

## 8. Flujo de pagos a proveedores

Mismo diálogo `SettleDialog(side=payable)`: proveedor → deuda por cuota → importe → medios/cuentas → distribución (vencimiento más cercano) → confirmar → constancia. Efectivo exige caja abierta (D-B del PRD de Treasury).
