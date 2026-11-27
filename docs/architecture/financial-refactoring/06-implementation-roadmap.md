# 06 · Hoja de ruta de implementación

Complejidad: S (≤2 días) · M (≤1 semana) · L (≤3 semanas) · XL (>3 semanas). Son estimaciones gruesas, sin medir velocidad del equipo. Cada fase termina desplegable y verificable. Commits convencionales; para repos con `main` protegido, PR (ver convenciones de `AGENTS.md`).

## Estado de implementación (2026-11-27)

Fase 0 implementada en el árbol de trabajo (sin commit). Verificado: 618 pruebas PHPUnit de Sales/AP/Treasury/Billing en verde, PHPStan limpio en los archivos tocados, `finance:audit` ejecutado contra el tenant `demo`, SQL de la migración probado en una base temporal.

| Tarea | Estado | Notas |
| --- | --- | --- |
| T0.1 locks en cobros | Hecho | `lockOpenReceivablesForCustomer` (FOR UPDATE + refresh), `refresh(PESSIMISTIC_WRITE)` en cobro individual; `ApplyAdvanceService` ahora transaccional con candado por cliente (`CustomerAccountLock`). **Sin prueba de concurrencia real** (ver T0.11) |
| T0.2 locks en AP | Hecho | pago y reversión releen bajo lock |
| T0.3 idempotencia | Parcial | índice único en migración (solo en tenants limpios), recheck de la clave bajo lock, 409 reintentable, métrica de clave ausente. **Falta**: clave en anticipos, aplicación de anticipo y pago a proveedor |
| T0.4 ledger sin carrera | Hecho | `CustomerAccountLock` dentro de `CustomerAccountLedger::record` |
| T0.5 guarda SupplierBill | Hecho | `SupplierBillUpdateProcessor` |
| T0.6 cuota SUNAT | Hecho (código) | `SaleCreditTermsProvider`; **falta validar en la beta de SUNAT** |
| T0.7 audit/verify | `finance:audit` hecho | `finance:verify` programado y reglas de Treasury pendientes |
| T0.8 NC con efecto | Hecho (operativo) | `CreditNoteReceivableAdjuster`; **sin asiento contable** y solo ventas a crédito |
| T0.9 moneda homogénea | Hecho | `CollectionPlanGuard` |
| T0.10 CHECK de saldos | Hecho | migración `Version20261127100000`, condicionada a datos limpios |
| T0.11 batería de concurrencia | Pendiente | requiere MariaDB con esquema migrado (la BD `demo_test` local no lo tiene) |

Fases 1 a 4 no iniciadas.

## Fase 0 · Estabilización (sin cambios de modelo)

| ID | Tarea | Justificación | Alcance / archivos | Dep. | Datos | Aceptación | Pruebas | Riesgo | Tam. |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| T0.1 | Locks + relectura en flujos de cobro | G-01 | `CollectOnAccountService`, `SalePaymentService::registerPayment`, `ApplyAdvanceService`, `SaleRecordRepository` (nuevo `findOpenReceivablesForCustomerForUpdate`) | — | — | Dos cobros paralelos sobre la misma factura: uno gana y el otro recibe 409/422 con saldo actualizado; `paid_total+balance_due=total` | Concurrencia (7) | Deadlocks → orden por id y timeout | M |
| T0.2 | Lock en pago a proveedor y reversión | G-01 | `SupplierPaymentService` | — | — | Igual que T0.1 para AP | Concurrencia AP | — | S |
| T0.3 | Idempotencia obligatoria y única | G-02 | Header en `CollectOnAccountProcessor`, `ReceiveAdvanceProcessor`, `ApplyAdvanceProcessor`, `SaleCollectPaymentProcessor`, `SupplierBillPayProcessor`; frontend envía clave; migración `UNIQUE` | T0.7 (limpiar duplicados) | `UNIQUE` en `customer_account_entries`, `idempotency_key` en `supplier_payment` | Mismo request ×2 (secuencial y paralelo) = 1 efecto, misma respuesta | Idempotencia, E2E doble clic | Clientes viejos sin header → modo tolerante con métrica | M |
| T0.4 | Saldo acumulado sin carrera | G-03 | `CustomerAccountLedger::record` (lock por cliente) como paso intermedio | T0.1 | — | Prueba de 20 inserciones concurrentes: `running_balance` consistente | Concurrencia | — | S |
| T0.5 | Guarda de `SupplierBill` | G-12 | Processor de `Patch`; test que confirme o descarte el hallazgo primero | — | — | Con pagos, `amount/currency/dueDate/supplier` inmutables (422) | Unit + API | Romper edición legítima: permitir solo `note`/`category` | S |
| T0.6 | Cuota SUNAT coherente (versión mínima) | G-05 | `BillCreateUseCase` (cuota por saldo a crédito, vencimiento = `due_date` interno); verificar norma | D-02 | — | XML: monto neto pendiente = `balance_due` al emitir; fecha = `due_date`; contado sin cuotas | Unit + golden XML + `golden-path-fiscal.spec` | Rechazo SUNAT: probar en beta | M |
| T0.7 | `finance:audit` y `finance:verify` | G-07 | Nuevo comando en `Sales`/`Treasury` (reutiliza `TreasuryGaps`); CSV | — | — | Reglas R1–R10 de `05`; salida por tenant | Integración con BD de prueba | Consultas pesadas → por lotes | M |
| T0.8 | Efecto financiero mínimo de NC | G-04 | `CreditNoteService` → `AppliesAdjustment` temporal: reduce `balance_due` (hasta el saldo) + ledger; el excedente se registra como saldo a favor (ADVANCE tipo `credit_note`) y evento contable | D-01 | ledger | NC sobre pendiente baja el saldo; sobre pagada crea saldo a favor; sin movimiento de dinero | Unit + E2E `credit-note-issue` ampliado | Decisión de negocio; GL (`EconomicEvent` nuevo tipo) | L |
| T0.9 | Guarda de moneda homogénea | G-15 | `CollectOnAccountPlanner` | — | — | Plan mezclando monedas → 422 | Unit | — | S |
| T0.10 | `CHECK` de saldos | G-01/07 | Migración condicionada a `finance:audit` limpio | T0.7 | CHECK | `balance_due>=0` impuesto en BD | Migración | Falla si hay datos sucios | S |
| T0.11 | Batería de concurrencia/idempotencia | G-23 | `tests/…/Concurrency` con procesos paralelos o dos conexiones | — | — | Corre en CI nocturno | — | Flaky → diseñar con barreras | M |

Paralelo: T0.1/T0.2/T0.5/T0.9 son independientes; T0.7 antes de T0.3/T0.10; T0.8 y T0.6 esperan decisiones. **Entregable de fase**: versión sin sobreaplicación ni duplicados y con auditoría de datos.

## Fase 1 · Núcleo financiero

| ID | Tarea | Alcance | Dep. | Aceptación | Tam. |
| --- | --- | --- | --- | --- | --- |
| T1.1 | Esquema `ar_*` y entidades (receivable, installment, collection, tender, allocation, adjustment) | migración, entidades, `ContextManifest` | F0 | Arquitectura verde; tablas vacías | M |
| T1.2 | Kernel compartido: `AllocationPlanner` (estrategias vencimiento/emisión/manual), `AgingCalculator` único | `Efact/Domain/Finance` | — | Ejemplo A800/B1200/C2400/D1400 con 2500 → 800/1200/500/0 | M |
| T1.3 | `OpenReceivable` desde `SalesWriteGateway` (cuotas) | puerto Sales→AR | T1.1 | Venta a crédito crea receivable+cuota(s) | M |
| T1.4 | `RegisterCollection` (multi-medio, exceso→anticipo, idempotencia, lock) detrás de `finance.receivables_v2` | casos de uso; los endpoints actuales delegan | T1.1–T1.3, T0.* | Escenarios 4–8 | L |
| T1.5 | `ReverseCollection` y reversión por asignación | + Treasury `reverse` + evento | T1.4 | Escenario 11; saldos restaurados; trazabilidad | M |
| T1.6 | Backfill AR + `shadow` + verify | `05` §4 | T1.1, T0.7 | `finance:verify` sin diferencias en piloto | L |
| T1.7 | Cuotas en ventas (UI y POS opcional) y XML por cuota | Billing + frontend | T1.3 | Escenario 3 | L |
| T1.8 | AP: `ap_installment`, `RegisterPayment` global multi-medio, reversión | espejo AR | T1.2 | Escenarios 17, 19 | L |
| T1.9 | Compras → `OpenPayable` (condición de pago, unicidad `purchase_id`) | Purchasing + AP | T1.8 | Escenarios 15–16 | M |
| T1.10 | Estado de cuenta y aging a fecha de corte (cliente y proveedor) | servicio único + endpoints | T1.4, T1.8 | Escenario 26: mismo reporte a una fecha pasada tras nuevos cobros | M |
| T1.11 | UI: `SettleDialog`, AP sobre `OpenItemsPage`, cartera por cuota | frontend | T1.4, T1.8 | E2E de AR/AP | L |
| T1.12 | Permisos nuevos y migración de roles | Identity | — | Nadie pierde acceso; POS no cobra | S |

Secuencia estricta: T1.1 → T1.3 → T1.4 → T1.5; T1.2 en paralelo a T1.1; T1.8 puede seguir a T1.2 en paralelo con T1.4–T1.6. **Entregable**: cobro/pago global multi-medio con reversión y estados de cuenta confiables en tenants piloto.

## Fase 2 · Control financiero

T2.1 anticipos de cliente con exceso y uso con lock (M) · T2.2 anticipos a proveedor (M) · T2.3 `credit_policy` + límites en POS y autorización (L) · T2.4 aging/morosidad y utilización de crédito (M) · T2.5 programación de pagos (M) · T2.6 Resumen con flujo proyectado por cuotas (M) · T2.7 NC definitiva sobre `ar_adjustment` y reembolsos (L) · T2.8 cierre de periodos/fechas (M, D-05) · T2.9 retiro de ruta vieja y de `sale_payments` como aplicaciones (L).

## Fase 3 · Automatización

Recordatorios ligados a vencimientos (existe `CollectionReminder`), promesas de pago, conciliación sugerida, refinanciamiento, órdenes de pago y autorizaciones simples, proyección de liquidez (cada una M–L).

## Fase 4 · Avanzado

Compensación AR/AP, factoring y letras, diferencias de cambio y multimoneda avanzada, autorizaciones multinivel, integración contable por cuenta (XL en conjunto; solo con demanda demostrada).

## Orden recomendado

T0.7 → T0.1/T0.2/T0.5/T0.9 (paralelo) → T0.3/T0.4/T0.10 → decisiones D-01/D-02 → T0.6/T0.8 → T0.11 → Fase 1 según dependencias.
