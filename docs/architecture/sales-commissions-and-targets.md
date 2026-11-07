# Comisiones y metas de vendedores — diseño

**Estado:** propuesta de diseño (2026-11-07). No hay código; las decisiones abiertas están en §7.
**Contexto nuevo propuesto:** `Commissions` (capa ERP Core, ver ADR-018).

## 1. Qué pide el negocio

Un cliente pidió gestión de **metas** y **comisiones** para vendedores. Hoy el ERP solo guarda quién vendió
(`sales.seller_user_id`, `sale_quote.seller_user_id`) y no hay reporte por vendedor, metas ni comisiones.

## 2. Cómo lo resuelven los ERP empresariales

Fuentes: documentación de [NetSuite](https://docs.oracle.com/en/cloud/saas/netsuite/ns-online-help/section_N1146679.html),
[SAP Incentive Management](https://learning.sap.com/courses/sap-incentive-management-administration/incentive-rules_d20f9a35-0455-42fe-a482-25bf46723d5b)
y [Odoo 19](https://www.odoo.com/documentation/19.0/applications/sales/sales/commissions.html).
Los tres coinciden en la misma estructura; cambia el nombre y la profundidad.

| Concepto | NetSuite | SAP ICM | Odoo |
|---|---|---|---|
| Regla de cálculo | *Commission schedule* | Reglas de crédito, medición, incentivo y depósito | *Commission plan* |
| Agrupa y asigna | *Plan*; un vendedor tiene **un solo plan por rango de fechas** | Plan de compensación por posición | Plan por vendedor |
| Base | Ventas totales, cantidad, o % de cuota | Medición configurable | Monto o cantidad, facturado o vendido |
| Escala | **Lineal** (la tasa más alta alcanzada sobre todo) o **marginal** (cada tramo a su tasa) | Tablas de tasas (escalonado) | Tramos por % de meta alcanzada |
| Meta | Cuota por período o acumulada al año | Atingimiento por medición | Meta por mes, trimestre o año |
| Disparador | Facturación; opción de base caja | Transacción acreditada | Factura contabilizada |
| Notas de crédito | Comisión negativa, que no se recupera al vendedor salvo reembolso al cliente | Reglas de reversión | Ajustes manuales |
| **Cálculo vs. pago** | Se calcula y se **paga por separado** (ítem de ingreso en planilla) | **Liquidación separada del cálculo** | Ajustes y pago aparte |
| Recalculo | Automático al cambiar el plan o al facturar | Por reglas, en orden | Manual por ajustes |

Lo que se repite y conviene copiar:

1. **Plan separado de la asignación.** El plan define tramos y base; la asignación lo liga a un vendedor por fechas, sin traslapes.
2. **Dos escalas, no una:** lineal y marginal. Cambiar de una a otra cambia el pago mucho.
3. **El disparador es una decisión del negocio:** facturado o cobrado. Los ERP lo hacen configurable por plan.
4. **Las reversiones son entradas nuevas**, no ediciones. Es lo mismo que ADR-003 exige para stock y caja.
5. **Calcular y pagar son dos pasos.** Entre ambos hay una liquidación que alguien revisa y aprueba.
6. **El pago va por planilla** cuando la comisión es remuneración.

## 3. Lo que ya tiene este ERP

| Necesidad | Dónde está |
|---|---|
| Quién vendió | `sales.seller_user_id`, `sale_quote.seller_user_id` |
| Base facturada | `tax_document` (01, 03, 07, 08) y `sales` |
| Base cobrada | `sale_payments`, `customer_payment_applications` (cobranza de ventas al crédito) |
| Margen | `sale_items.unit_cost_snapshot`, `margin_amount` |
| Reversión | anulación de venta (`/sales/{id}/void`), nota de crédito (`tax_document` 07) |
| Persona → usuario | `employee.user_id` |
| Pago en planilla | contexto `Payroll` (`payslip_entry`, asientos al mayor) |
| Activación por plan comercial | capabilities (`CapabilityKey`) y permisos (`PermissionCatalog`) |

Falta: planes, asignaciones, metas, un registro de comisiones, liquidación y la entrada de la comisión en la boleta.

## 4. Diseño propuesto

### 4.1 Modelo (tablas nuevas con prefijo `commissions_*`, ADR-018 D4)

```text
commissions_plan           nombre, base (INVOICED | COLLECTED | MARGIN), período (MONTH | QUARTER | YEAR),
                           escala (FLAT | LINEAR | MARGINAL), activo
commissions_plan_tier      plan, desde (% de meta o monto), tasa            ← tramos
commissions_assignment     plan, vendedor (user_id), vigente_desde, vigente_hasta   ← sin traslape por vendedor
commissions_target         vendedor, período, monto de meta
commissions_entry          APPEND-ONLY. vendedor, período, origen (venta | cobro | nota de crédito | ajuste),
                           origen_id, base, tasa, monto (con signo), estado (ABIERTA | LIQUIDADA)
commissions_statement      vendedor, período, total, estado (BORRADOR → APROBADA → PAGADA), vínculo a la planilla
```

`commissions_entry` es el libro. Nada se actualiza ni se borra: un recálculo agrega una reversión y una entrada nueva.

### 4.2 Flujo

```text
venta emitida / cobro aplicado / nota de crédito / anulación
  └─ evento de dominio (Messenger)  ──►  Commissions
        1. ¿hay asignación vigente para el vendedor en esa fecha?   no → se ignora
        2. ¿el plan se dispara con este hecho? (INVOICED con la venta, COLLECTED con el cobro)
        3. agrega commissions_entry (con signo)

cierre de período
  └─ por vendedor: atingimiento = base acumulada / meta
        escala LINEAR   → tasa del tramo más alto alcanzado sobre toda la base
        escala MARGINAL → cada tramo a su tasa
        escala FLAT     → una tasa fija
     → entradas de ajuste por la diferencia contra lo ya acumulado
     → commissions_statement en BORRADOR

aprobación de la liquidación
  └─ EconomicEvent COMMISSION_ACCRUAL (gasto contra remuneraciones por pagar)
  └─ la comisión entra a la boleta del mes como concepto variable
```

### 4.3 Reglas por defecto (las que hacen los ERP)

- **Base por defecto: cobrado** para ventas al crédito y facturado para ventas al contado. Es lo que protege al negocio de pagar comisión por deuda que no se cobra.
- **Una venta, un vendedor** en la primera versión. Reparto entre varios vendedores es una fase posterior.
- **Notas de crédito y anulaciones restan en el período en que ocurren**, no reescriben el pasado. Si el saldo del vendedor queda negativo, **se arrastra**; no se descuenta de la planilla sin una regla explícita.
- **Un vendedor, un plan por fecha.** La base de datos lo valida.
- **Moneda:** todo se convierte a soles con el tipo de cambio del comprobante.

### 4.4 Pago: por planilla

En Perú la comisión es remuneración (afecta a AFP/ONP, EsSalud y quinta categoría) y entra al promedio de gratificación y CTS. Por eso el pago correcto es **por planilla**, no por un egreso suelto de caja o banco:

- `payslip_entry` hoy no tiene concepto de ingresos variables. Hay que agregar uno (comisiones) y que el cálculo de aportes lo incluya.
- El medio de pago **no se pregunta**: la planilla se paga como las remuneraciones, por cuenta sueldo (banco).
- El efecto en gratificación y CTS (promedio de comisiones de los últimos seis meses) es un cálculo aparte y delicado: debe revisarlo un contador antes de implementarlo.

### 4.5 Integración y fronteras

- `Commissions` **consume eventos** de Sales/Billing/CashRegister; no los llama. Cumple AR-006.
- `Payroll` consulta las liquidaciones **aprobadas** a través de un contrato público de `Commissions`; `Commissions` no depende de `Payroll`.
- Capability nueva `sales.commissions` (depende de `sales.core`) y permisos `commissions.view`, `commissions.manage_plans`, `commissions.approve`.
- Reportes: avance de meta por vendedor, comisión del período, liquidaciones pendientes. Exportables a Excel como los demás.

## 5. Fases

| Fase | Contenido | Por qué en ese orden |
|---|---|---|
| **1 — Mínimo útil** | Reporte de ventas por vendedor, metas por período y comisión de tasa fija sobre facturado o cobrado | Sin motor de planes; resuelve lo que más piden y valida con el cliente |
| **2 — Planes** | Planes con tramos, escala lineal y marginal, asignaciones, libro de entradas, reversiones, liquidación | Es el diseño de §4 completo, sin planilla |
| **3 — Planilla** | Concepto de comisión en `payslip_entry`, aportes, asiento contable, efecto en gratificación y CTS | Depende de la revisión de un contador |
| **4 — Avanzado** | Reparto entre vendedores, comisión por margen o por producto, jerarquía (gerente) | Solo si un cliente lo pide |

## 6. Riesgos

- **Disputas por reglas:** cada cliente tiene reglas propias. Un plan configurable evita código por cliente, pero hay que acotar lo que se permite.
- **Dinero acumulado y tiempo:** una comisión mal calculada se paga y es difícil de recuperar. Por eso la liquidación se **aprueba** antes de pagar.
- **Notas de crédito después del pago:** el diseño las arrastra como saldo negativo; recuperarlas de la planilla es una decisión laboral, no técnica.
- **Backfill:** las ventas anteriores a la activación no generan comisión salvo que se pida explícitamente.

## 7. Decisiones abiertas

1. ¿La base por defecto es cobrado, facturado o configurable por plan? (propuesta: configurable, con cobrado como defecto)
2. ¿Hay metas por vendedor, por sucursal o por equipo?
3. ¿Se necesita reparto de una venta entre varios vendedores en la primera versión?
4. ¿Qué pasa con una nota de crédito posterior al pago: saldo negativo que se arrastra (propuesta) o descuento en planilla?
5. ¿Un contador valida el tratamiento de la comisión en gratificación y CTS?
