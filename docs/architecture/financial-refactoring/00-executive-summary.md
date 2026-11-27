# 00 · Resumen ejecutivo: auditoría del dominio financiero

Fecha: 2026-11-27 · Repositorio: `nubit-erp/nubit-erp` (HEAD `f97a649bb`) · Alcance: solo análisis y diseño; no se cambió código.

Convención de evidencia en todos los documentos: **[V]** hecho verificado leyendo código, **[I]** inferencia (no ejecutada ni probada), **[R]** recomendación.

## 1. Estado general

El dominio financiero **no parte de cero ni está mal planteado**: ya existe un contexto `Treasury` con ledger append-only e idempotente, un ledger de cuenta corriente del cliente, una tabla de aplicaciones cobro→venta (`customer_payment_applications`), cobro global FIFO/explícito, anticipos y su aplicación, y el ADR-021 que fija la dirección. Lo que falta es **endurecer la integridad** (concurrencia, idempotencia, consistencia con SUNAT) y **completar el modelo** (cuotas, multi-medio, reversión de cobranzas, pagos globales a proveedor, notas de crédito con efecto financiero).

Las dos mitades están en niveles distintos de madurez:

| Área | Madurez | Resumen |
| --- | --- | --- |
| Tesorería (`src/Treasury`) | Alta | Ledger con índice único `(source_type, source_id, leg)`, reversión por fila correctora, suspense, backfill/verify. Es opcional por capability. |
| Cobranzas (`Sales`) | Media | N:M cobro↔venta ya modelado, pero sin bloqueo, sin cuotas, un solo medio por cobro, sin reversión. |
| Cuentas por pagar (`AccountsPayable`) | Baja | Un pago = una factura, sin ledger, sin anticipos, sin idempotencia ni bloqueo, sin vínculo operativo con Compras. |
| Notas de crédito (`Billing`) | Crítica | No tienen efecto financiero alguno [V por grep, ver G-04]. |
| Consistencia SUNAT↔interno | Crítica | La cuota enviada a SUNAT está fija en emisión+1 mes y por el total, sin relación con el saldo ni el vencimiento interno. |

## 2. Principales hallazgos (detalle y evidencia en `02-gap-analysis.md`)

1. **P0, sobreaplicación concurrente.** `CollectOnAccountService::collect()` y `ApplyAdvanceService::apply()` cargan las ventas abiertas *fuera* de la transacción y sin lock; `SalePaymentService::persistPaymentRow()` valida el saldo sobre la entidad en memoria. Solo `ExternalBankCollection` usa `PESSIMISTIC_WRITE`. `SaleRecord.versionNo` es una columna manual, no `@ORM\Version`. Dos cobros simultáneos pueden dejar `balance_due` y `paid_total` incorrectos. Igual en `SupplierPaymentService`.
2. **P0, idempotencia incompleta.** El `Idempotency-Key` es opcional y el replay se consulta antes de la transacción, sin índice único en `customer_account_entries`. Cobro individual, anticipos, aplicación de anticipo y pago a proveedor no tienen idempotencia (el id de aplicación es `Uuid::v4()` nuevo en cada llamada).
3. **P0, notas de crédito sin efecto financiero.** `CreditNoteService`, `CreditNoteIssueController` y `NoteCreateUseCase` no tocan `balance_due`, ledger, tesorería ni `EconomicEvent`. Una NC sobre factura pendiente deja la deuda intacta; sobre factura pagada no genera saldo a favor ni devolución.
4. **P0, discrepancia con SUNAT.** `BillCreateUseCase` (línea ~176) fija una única cuota a `fechaEmision + 1 mes` por el **monto total**, mientras que el vencimiento interno es `issued + defaultPaymentTermDays` (`SalesWriteGateway` ~183) y el saldo a crédito puede ser menor que el total (pago inicial). La RS 193-2020/SUNAT exige monto neto pendiente y fechas/montos reales de cuotas.
5. **P1, sin cuotas, sin multi-medio, sin exceso.** Una venta tiene un único `due_date`; un cobro admite un solo medio; el exceso se rechaza (`assertFullyApplied`), no se convierte en anticipo.
6. **P1, sin reversión de cobranzas de clientes.** No existe flujo; solo se anula una venta sin pagos aplicados.
7. **P1, doble fuente de verdad.** `SaleRecord.balanceDue` + `sale_payments` + ledger + aplicaciones; el propio docblock de `CustomerAccountLedger` lo admite. No hay `CHECK` ni comando que los reconcilie.
8. **P1, no se pueden reconstruir saldos a fecha de corte.** El aging del estado de cuenta usa `balance_due` actual y antigüedad desde emisión, no desde vencimiento.
9. **P1, Compras no genera obligación.** `PurchaseProcessor` solo emite un `EconomicEvent`; `SupplierBill.purchase` es manual y opcional; no hay condición de pago en compras.
10. **P1, moneda.** `collect()` toma la moneda de la primera venta del plan (`$plan[0]`); no valida homogeneidad.

## 3. Riesgos críticos

Saldos incorrectos silenciosos por concurrencia; cobros duplicados por reintentos; diferencia entre lo informado a SUNAT (y por tanto a factoring/negociación de facturas) y el saldo interno; deuda que no baja tras una NC; imposibilidad de corregir un cobro mal registrado; reportes históricos no confiables.

## 4. Fortalezas a conservar

`Money` con BigDecimal y strings decimales; `wrapInTransaction` + `EconomicEvent` síncrono en la misma transacción; Treasury (clave única, `reverse()`, suspense, `treasury:verify`); patrón de `ExternalBankCollection` (lock + idempotencia por referencia + replay comparado) como **referencia a generalizar**; tabla de aplicaciones N:M; planner FIFO puro y testeado; cobro en efectivo de venta de turno anterior se registra en la sesión abierta actual (no se recontabiliza como venta); pruebas de arquitectura (`TableOwnership`, `CrossContextTableWrite`, `LedgerFiscalImmutability`); E2E de AR, AP y Treasury.

## 5. Recomendación general

Evolución incremental, sin reescritura y sin contabilidad de partida doble nueva (ya existe `Accounting`). En orden:

1. **Fase 0 (estabilización, ~3–4 semanas):** locks + idempotencia obligatoria + índices únicos + comando `finance:verify` + corrección de cuota SUNAT + efecto financiero mínimo de NC + guardas en `SupplierBill` + pruebas de concurrencia.
2. **Fase 1 (núcleo):** cabecera de cobranza/pago + medios (tenders) + asignaciones (allocations) + cuotas + reversión + pago global a proveedor + estado de cuenta a fecha de corte.
3. **Fase 2 (control):** exceso→anticipo, anticipos a proveedor, política de crédito, aging único, programación de pagos.
4. Fases 3–4 según demanda real.

Compartir **primitivas** entre AR y AP (planner de asignación, aging, value objects), **no** un agregado genérico `Obligation`.

## 6. Alcance propuesto

Dentro: Sales/AR, AccountsPayable, Purchasing (solo vínculo con obligación), Billing (solo cuotas SUNAT y notas), Treasury (integración), Reporting financiero, UI de AR/AP/Resumen. Fuera: contabilidad completa, factoring/letras (fase 4), integración bancaria.

## 7. Decisiones pendientes (ver `08-open-decisions.md`)

D-01 qué hacer con una NC sobre factura ya cobrada; D-02 modelo de cuotas SUNAT en ventas con pago inicial; D-03 ¿AR se extrae a un contexto propio?; D-04 multimoneda en un mismo cobro; D-05 política de periodos cerrados; D-06 obligatoriedad de Treasury; D-07 tratamiento de detracción/retención en el saldo.

## 8. Límites de esta auditoría

No se ejecutaron pruebas ni se consultó una base con datos reales: no se midió el volumen de inconsistencias históricas. No se leyeron línea a línea `BankReconciliation`, `Accounting`, `Payroll` ni el frontend completo. Los escenarios 22, 27, 29 y 30 se evalúan parcialmente (ver `02-gap-analysis.md` §3).
