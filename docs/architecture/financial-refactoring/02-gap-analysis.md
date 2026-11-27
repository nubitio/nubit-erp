# 02 · Matriz de brechas

Severidad/prioridad: **P0** integridad, pérdida de datos o seguridad · **P1** comportamiento incorrecto en operaciones principales · **P2** limitaciones y mantenibilidad · **P3** capacidades futuras. Evidencia: **[V]** verificado, **[I]** inferencia a confirmar con una prueba.

## 1. Brechas

| ID | Hallazgo | Evidencia | Actual | Esperado | Impacto funcional / técnico | Sev. | Recomendación |
| --- | --- | --- | --- | --- | --- | --- | --- |
| G-01 | Sobreaplicación por concurrencia [V] | `CollectOnAccountService::collect` (carga `findOpenReceivablesForCustomer` fuera de `wrapInTransaction`, sin lock); `SalePaymentService::persistPaymentRow` valida `getBalanceDue()` de la entidad en memoria; `ApplyAdvanceService::apply` lee `availableAdvance` sin lock; `SupplierPaymentService::registerPayment` idem. `SaleRecord::versionNo` es columna manual (no `@ORM\Version`). Único lock: `ExternalBankCollection` | Dos usuarios cobrando la misma deuda leen el mismo saldo y ambos escriben `paid_total/balance_due` calculados sobre el valor viejo (lost update) o duplican | Una sola operación gana; la otra ve el saldo actualizado | Saldos erróneos, deuda negativa o doble cobro; no detectable sin reconciliación | P0 | `SELECT … FOR UPDATE` de las ventas/facturas objetivo (orden por id) dentro de la transacción, releer y recalcular el plan tras el lock; `CHECK balance_due >= 0`. Generalizar el patrón de `ExternalBankCollection` |
| G-02 | Idempotencia parcial [V] | `CollectOnAccountProcessor::collectionIdFor` devuelve `?string` (header opcional); replay vía `entries->findCollectionApplications` antes de la transacción; sin índice único sobre `customer_account_entries(source, source_id)` (migración `20260909130000`); `ApplyAdvanceService`: `Uuid::v4()`; `registerPayment`, `ReceiveAdvanceService`, `SupplierPaymentService`: sin clave | Reintento sin header = segundo cobro; dos reintentos paralelos con header pueden pasar el replay a la vez | Reintento idempotente garantizado por unicidad en BD | Cobros/pagos duplicados | P0 | Header obligatorio en los flujos de dinero (o derivarlo en el frontend), tabla/columna `idempotency_key` con índice único, insertar la cabecera primero y manejar el conflicto como en `TreasuryMovementRecorder::record` |
| G-03 | `running_balance` con carrera [V] | `CustomerAccountLedger::record` → `entries->currentBalance()` (último id) y luego insert; sin lock | Dos entradas concurrentes parten del mismo saldo previo | Saldo acumulado consistente | Estado de cuenta incorrecto | P0 | Bloquear una fila ancla por cliente (p. ej. `cliente` `FOR UPDATE`) al escribir; o dejar de persistir `running_balance` y calcularlo con ventana (preferible, ver 03) |
| G-04 | Notas de crédito sin efecto financiero [V por grep] | `Billing/Service/CreditNoteService.php`, `Controller/CreditNoteIssueController.php`, `Sunat/Domain/NoteCreateUseCase.php`: cero coincidencias de `balance|paidTotal|Treasury|refund|EconomicEvent`; solo `CreditNoteStockReverser` | NC emitida: la deuda no cambia, no hay saldo a favor, devolución ni asiento | NC sobre pendiente reduce saldo; sobre pagada genera saldo a favor/reembolso; parcial reduce el saldo y el excedente a favor | Deuda inflada, cartera falsa, reportes no cuadran con SUNAT | P0 | Concepto `Adjustment` (ver 03) aplicado en la misma transacción que la NC; decisión D-01 |
| G-05 | Cuota SUNAT inconsistente con la deuda [V] | `BillCreateUseCase.php` ~132-135 y ~176-181: `FormaPagoCredito(voucherSaleAmount)` y una `Cuota` a `voucherDate +1 month` por `voucherSaleAmount`; vs `SalesWriteGateway` ~181-184 (`due_date = issued + defaultPaymentTermDays`) y `balanceDue` ≤ total con pago inicial | XML: una cuota por el total a +1 mes sin importar plazo, pago inicial ni vencimiento interno | RS 193-2020/SUNAT: *monto neto pendiente* y fecha/monto de cada cuota, coherentes con la deuda interna | Comprobante contradice la cartera; riesgo ante SUNAT, factoring y conformidad del adquirente | P0 | Generar cuotas desde el cronograma interno (`installment`), monto neto = saldo a crédito; verificar norma vigente antes de implementar (§4) |
| G-06 | Sin reversión de cobranzas de clientes [V] | Archivos de `Sales` con "reverse": solo ledger/void/repositorio de entradas; ningún servicio ni endpoint de reversión de cobro; `customer_payment_applications` sin estado | Un cobro mal registrado no se puede corregir; la venta con pagos no se puede anular | Reversión total y por aplicación, con contra-movimiento de Treasury y evento contable | Operaciones de soporte manuales sobre BD | P1 | `ReverseCollection` (fila correctora + `reversed_at/by/reason`) |
| G-07 | Doble fuente de verdad del saldo [V] | Docblock de `CustomerAccountLedger` ("per-sale debt still lives on `SaleRecord.balanceDue`"); AP idem; sin comando de reconciliación | `balance_due`, `sale_payments`, aplicaciones y ledger pueden divergir | Una fuente (aplicaciones/ajustes) y el saldo derivado verificable | Deriva silenciosa | P1 | `finance:verify`; saldo en cabecera como caché actualizado bajo lock y verificado |
| G-08 | Sin cuotas ni cronograma [V] | Un `due_date` en `sale_record` y `supplier_bill`; sin tablas de cuotas | No se pueden representar ventas en cuotas ni aplicar a nivel de cuota | `installment` con vencimiento, importe y saldo | Escenario 3 no soportado; aging impreciso | P1 | Tabla de cuotas (ver 03/05) |
| G-09 | Un medio de pago por cobranza; exceso rechazado [V] | Firma de `collect()` (un `PaymentMethod`); `assertFullyApplied` | No hay efectivo+transferencia en un cobro; exceso → 422 con texto "aún no disponible" | Tenders múltiples; exceso → anticipo | Escenarios 7 y 8 | P1 | `collection_tender`; exceso a ADVANCE |
| G-10 | FIFO por emisión, no por vencimiento [V] | `findOpenReceivablesForCustomer` `orderBy issuedDate…`; `CollectOnAccountPlanner::fifo` | Distribución por emisión | Por vencimiento más antiguo (configurable) | Cuando hay cuotas, difiere | P2 | Estrategia en el planner |
| G-11 | AP: sin pago global, anticipos, cronograma ni orden de pago [V] | `SupplierPaymentService` (un bill por pago); sin ledger de proveedor | Pagar 5 facturas = 5 operaciones | Pago global con asignaciones y medios | Escenarios 17, 18 | P1 | Espejo de AR (ver 03) |
| G-12 | `SupplierBill` editable tras pagos [I] | `Patch` con `perm:accounts_payable.create`; `amount` en grupo `supplier_bill:write`; sin processor en Patch | Cambiar `amount` no recalcula `balance_due`; también `dueDate`, `currency` con pagos aplicados | Importe/moneda inmutables con pagos; cambios por ajuste | Saldo corrupto | P0/P1 (confirmar con test) | Processor de Patch que bloquee campos financieros con pagos |
| G-13 | Compras no crean obligación [V] | `PurchaseProcessor` solo `EconomicEvent PURCHASE`; `SupplierBill.purchase` nullable, sin unicidad; el processor de bill omite el evento si hay compra | Compra al crédito = compra + factura manual; posible doble registro o ninguno | Condición de pago en compra genera obligación; 3-way matching futuro | Escenarios 15, 16 | P1 | Vincular con unicidad `purchase_id`; condición de pago en `Purchase` |
| G-14 | Aging no reconstruible y con tres reglas [V] | `AgingCalculator` (statement): edad desde fecha de emisión con `balance_due` actual; AP en PHP; AR en SQL y TS | Reporte histórico usa saldo actual; criterios distintos entre pantallas | Aging por vencimiento, a fecha de corte, calculado una vez en backend | Escenario 26, reportes no confiables | P1 | Un único servicio de aging que use aplicaciones con fecha ≤ corte |
| G-15 | Moneda de la cobranza tomada de la primera venta [V] | `collect()`: `$currency = $plan[0]['sale']->getCurrency()`; `bookAccountingEvent` con esa moneda | Un cobro mezclando PEN y USD se registra todo en la moneda de la primera | Rechazar o separar por moneda | Escenario 30 | P1 | Validar homogeneidad en el planner |
| G-16 | `SalePayment` mezcla pago al vender y aplicación de cobro [V] | `CollectOnAccountService::applyPlan` crea `sale_payments` por aplicación | Reportes de medios de pago pueden contar cobros como pagos de venta | Cobranza ≠ pago de venta | Conceptual; riesgo de doble conteo | P2 | Introducir `allocation` y que `sale_payments` quede para el momento de la venta |
| G-17 | Permisos gruesos [V] | `sales.collect` cubre cobrar, anticipo y aplicar; `accounts_payable.pay` cubre pagar **y** revertir; `Patch` usa `.create`; no hay permisos de revertir, ajustar, autorizar, ver otras sucursales | Separación de funciones imposible | Catálogo del §10 del encargo | Control interno | P1 | Nuevos permisos (ver 04) |
| G-18 | Sin cierre de periodos ni límites de fecha [V/I] | Sin lógica de periodo fuera de Payroll; `paidAt` llega como dato del cliente (revisar DTO) | Cobros/reversiones con fecha arbitraria sobre meses cerrados | Política de fechas/periodos | Reportes históricos inestables | P2 | Decisión D-05 |
| G-19 | Ledgers sin protección de BD [V] | `customer_account_entries`/`customer_payment_applications` sin triggers; `ON DELETE CASCADE` en aplicaciones; `treasury_movement` solo convención (ADR-003 lo admite) | Un DELETE/UPDATE manual rompe la trazabilidad | Append-only reforzado (REVOKE o triggers) | Auditoría | P2 | Revisar tras Fase 1 |
| G-20 | Treasury opcional e histórico no migrado [V] | `TreasuryEnabledRecorder`; PRD Treasury §4 "does not repair collections, advances, payroll or cash movements" | Con la capability apagada, bancos/wallets no se registran | Registro siempre, o aviso claro | Escenarios 21–23 incompletos | P2 | D-06; backfill ampliado |
| G-21 | Sin política de crédito, promesas, disputas, castigos, refinanciamiento [V] | No existen campos/tablas (`creditLimit` sin coincidencias en Contacts/Sales) | Venta a crédito sin límite | Límite y bloqueo/autorización | Riesgo comercial | P2 | Fase 2 |
| G-22 | Compensación AR/AP, factoring, letras [V: no existen] | — | — | — | — | P3 | Fase 4 |
| G-23 | Sin pruebas de concurrencia/idempotencia concurrente [V] | Búsqueda en `tests/Sales`, `tests/Treasury`, `tests/AccountsPayable` | — | Batería ver 07 | Regresiones invisibles | P1 | Fase 0 |
| G-24 | Detracción/retención no afectan el saldo [I] | `BillCreateUseCase` arma `Detraction` pero el saldo interno es el total | El cliente "debe" el total aunque parte vaya a la cuenta del BN | Saldo neto vs. detracción separados (D-07) | Cartera sobrestimada | P2 | D-07 |

### Correcciones durante la implementación

- **G-02**: el frontend (`CollectPaymentDialog`, `useIdempotencyKey`) ya envía `Idempotency-Key` en el cobro global; la brecha es del lado servidor (opcional, sin unicidad) y de los demás flujos.
- **G-04 ampliada**: `TaxDocumentProcessor::voidSaleIfCreditNoteAnnuls` marca la venta `VOIDED` con una NC de anulación sin pasar por `SaleVoidUseCase` (sin guarda de pagos aplicados, sin `ReceivableVoidReverser`, sin reversión en Treasury). Ahora el ajuste de saldo convierte lo ya cobrado en saldo a favor, pero **la reversión de dinero en Treasury sigue sin hacerse en ese camino**.
- **G-12 confirmada** leyendo código: `initializeBalanceDue` es solo `PrePersist`; un PATCH de `amount` no recalculaba nada.

## 2. Fortalezas verificadas (no tocar sin motivo)

Treasury idempotente por clave única y con reversión por fila correctora; `Money` decimal; cobro de deuda de turno anterior en el turno actual (resuelve `findOpenCashSessionIdForUser`, no la sesión original); cobro bancario sin turno (método no efectivo no exige sesión); anulación de venta bloqueada si hay pagos; reglas de arquitectura automatizadas.

## 3. Escenarios de referencia (30)

Leyenda de prioridad = prioridad de implementación. "Pruebas existentes" son las que vi por nombre; no las ejecuté.

| # | Escenario | Actual | Brecha | Datos / riesgo | Pruebas existentes → faltantes | Prio. |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | Venta al contado | Funciona (`SalesWriteGateway`, `formaPago=1`) | Ninguna relevante | — | POS/E2E → — | — |
| 2 | Crédito sin pago inicial | Funciona; `due_date` único; XML con cuota +1 mes por el total | G-05 | `sale_record`, XML | `pos-credit-sale.spec` → XML vs. deuda | P0 |
| 3 | Pago inicial + cuotas | Pago inicial sí; cuotas no | G-08, G-05 (monto total ≠ pendiente) | `sale_record`, XML | — → todo | P1 |
| 4 | Cobro total de una factura | Funciona | Sin lock/idempotencia (G-01/02) | `sale_payments`, ledger | `SalePaymentsTreasuryTest`, E2E → concurrencia | P0 |
| 5 | Cobro parcial | Funciona | Idem | idem | idem | P0 |
| 6 | Cobro global | Funciona, FIFO/explícito, un evento y un movimiento | G-01/02, orden por emisión | idem | E2E `receivable-collection-statement` → carrera | P0 |
| 7 | Múltiples medios | No soportado | G-09 | — | — → todo | P1 |
| 8 | Cobro > deuda | Rechazado (422) | G-09 | — | — | P1 |
| 9 | Anticipo sin venta | Funciona (`ReceiveAdvanceService`) | Sin idempotencia | ledger ADVANCE, Treasury | — → reintento | P1 |
| 10 | Aplicar anticipo | Funciona (FIFO sobre ADVANCE) | `availableAdvance` sin lock → doble uso (inv. 5) | ledger, aplicaciones | — → concurrencia | P0 |
| 11 | Reversión de cobranza | No existe | G-06 | — | — | P1 |
| 12 | NC sobre factura pendiente | Sin efecto en saldo | G-04 | `balance_due` | `credit-note-issue.spec` (solo emisión) → saldo | P0 |
| 13 | NC sobre factura pagada | Sin saldo a favor ni reembolso | G-04 | — | — | P0/P1 |
| 14 | NC sobre parcialmente pagada | Sin efecto | G-04 | — | — | P0/P1 |
| 15 | Compra al crédito | Compra y factura son independientes | G-13 | `purchase`, `supplier_bill` | `purchases-stock.spec` → vínculo | P1 |
| 16 | Compra con recepción parcial | Existe recepción de OC (`PurchaseOrderReceiptService`); sin devengo por recepción | G-13; 3-way matching ausente | — | — | P2 |
| 17 | Pago global a proveedor | No | G-11 | — | — | P1 |
| 18 | Anticipo a proveedor | No | G-11 | — | — | P2 |
| 19 | Reversión de pago a proveedor | Funciona (`reversePayment`, Treasury, evento) | Mismo permiso que pagar; sin lock | `supplier_payment.reversed_at` | `tests/AccountsPayable` → carrera, permiso | P1 |
| 20 | Cobro de deuda anterior en turno actual | Funciona; entra como cobro del turno | Verificar que reportes de caja no lo cuenten como venta | `cash_movements` | `cash-credit-reconciliation.spec` | P2 |
| 21 | Cobro bancario sin turno | Funciona (métodos no efectivo) si hay cuenta mapeada o suspense | G-20 con Treasury apagado | Treasury | `treasury.spec` | P2 |
| 22 | Venta online liquidada por pasarela | Soportado por `ExternalBankCollection` + `SettleGateway` (clearing) | No auditado a fondo | `treasury_movement` | `ExternalBankReceiptTest` | P2 |
| 23 | Transferencia entre cuentas propias | Funciona (`TransferMoney`, dos patas) | — | — | `TransferAndPositionTest` | — |
| 24 | Dos usuarios liquidando a la vez | Sin protección | G-01 | `sale_record` | Ninguna | P0 |
| 25 | Reintento de cobro confirmado | Solo con header y sin garantía de BD | G-02 | ledger | Replay unit → concurrente | P0 |
| 26 | Estado de cuenta histórico | Movimientos sí; pendientes con saldo actual | G-14 | — | `CustomerStatement` tests → corte | P1 |
| 27 | Entre sucursales | Anticipos con `branch_id`; statement trae origen/destino | Parcial; no auditado a fondo | ledger | — | P2 |
| 28 | Entre tenants | BD por tenant | No auditado (fuera de alcance) | — | tests ControlPlane/Identity | P1 (verificar) |
| 29 | Compensación AR/AP | No existe | G-22 | — | — | P3 |
| 30 | Multimoneda | Documentos con moneda; Treasury rechaza desajuste; sin diferencia de cambio | G-15; sin TC en cobro | — | — | P1/P3 |

## 4. Requisitos tributarios verificados y pendientes

Verificado en fuente oficial (SUNAT, RS 193-2020/SUNAT y anexo 5, https://www.sunat.gob.pe/legislacion/superin/2020/anexo5-193-2020.pdf; portal https://cpe.sunat.gob.pe/informacion_general/preguntas_frecuentes): en operaciones al crédito el comprobante debe consignar la forma de pago crédito, el **monto neto pendiente de pago** (sin retenciones de IGV ni SPOT) y las **fechas de vencimiento y montos por cuota**; el informe SUNAT 070-2023 cita el DU 013-2020 sobre plazo y monto neto pendiente (MIPYME). Esto sustenta G-05.

**Pendiente de validar antes de implementar** (no verificado en esta auditoría): reglas exactas de modificación del cronograma tras emisión; tratamiento de NC sobre facturas al crédito (afecta cuotas); detracción, retención y percepción en el saldo; tipo de cambio aplicable a cobros; anticipos (comprobante de anticipo vs. recibo). Ver `08-open-decisions.md`.
