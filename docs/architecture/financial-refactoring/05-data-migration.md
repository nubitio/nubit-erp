# 05 · Evolución de datos y migración

Principios: sin big bang; aditivo primero; tablas viejas intactas hasta el corte; cada paso reversible o verificable; **no se inventan distribuciones históricas** que los datos no justifiquen. Estilo strangler como ADR-004 y el PRD de Treasury.

## 1. Cambios de esquema (todos aditivos hasta Fase 1-fin)

Fase 0 (sobre tablas existentes):
- `customer_account_entries`: `UNIQUE(source, source_id, entry_type)` **tras** detectar y resolver duplicados (§3). Nota: una cobranza global usa una sola entrada con `source='on_account_collect'` y el mismo `source_id` solo una vez, por lo que el índice es válido; `sale_payment` usa el id del pago.
- `sale_record`: `CHECK (balance_due >= 0 AND paid_total >= 0)`; `supplier_bill` ídem (MariaDB aplica CHECK desde 10.2.1).
- `supplier_payment`: columna `idempotency_key` nullable + `UNIQUE`.
- `ar_*` aún no.

Fase 1 (tablas nuevas, prefijo por contexto):
`ar_receivable`, `ar_installment`, `ar_collection`, `ar_collection_tender`, `ar_allocation`, `ar_adjustment`, `ap_installment`, `ap_payment` (cabecera), `ap_payment_tender`, `ap_allocation`. `supplier_bill` y `sale_record` se conservan. Declarar dueños en `ContextManifest`/`TableOwnershipTest`.

Fase 2: `ar_advance` (o reutilizar entradas ADVANCE), `credit_policy`, `ap_advance`, `ap_schedule`. Fase 3+: promesas, órdenes de pago, etc.

## 2. Mapeo actual → objetivo

| Actual | Objetivo | Regla |
| --- | --- | --- |
| `sale_record` con `balance_due>0` / crédito | `ar_receivable` + cuotas | Una cuota con `due_date` actual y `amount = total − pagos al emitir` si no hay cronograma |
| `sale_payments` de ventas a crédito aplicados por cobranza | `ar_allocation` | Vía `customer_payment_applications.sale_payment_id` |
| `customer_account_entries` PAYMENT con `source='on_account_collect'` | `ar_collection` (cabecera por `source_id`) | Una cabecera por `collectionId` |
| `customer_account_entries` PAYMENT `source='sale_payment'` | `ar_collection` de 1 allocation | `source_id` = id del pago |
| Entradas ADVANCE | `ar_advance` / cobranza con exceso | Sin cambios de dinero |
| `customer_payment_applications` | `ar_allocation` | Copiar con `applied_at`; marcar `origin='backfill'` |
| `supplier_bill` | `ap_installment` (1 cuota) | `due_date` actual |
| `supplier_payment` | `ap_payment` + 1 allocation | `reversed_at` → estado `reversed` |
| Movimientos Treasury existentes | sin cambio; nueva clave `collection_tender` desde el corte | Convivencia de claves (`sale_payment` histórico, `collection_tender` nuevo) |

## 3. Inventario y detección de inconsistencias (antes de activar)

Comando `finance:audit --tenant=<t> [--from]` (solo lectura, **Fase 0**), con salida CSV por regla:

| Regla | Consulta |
| --- | --- |
| R1 saldo de venta | `sale_record.total − Σ sale_payments.amount ≠ balance_due` |
| R2 aplicaciones vs pagos | Σ `customer_payment_applications.applied_amount` por `sale_payment_id` ≠ `sale_payments.amount` |
| R3 ledger vs ventas | saldo ledger del cliente ≠ Σ `balance_due` de sus ventas abiertas − anticipos disponibles |
| R4 entradas duplicadas | `GROUP BY source, source_id, entry_type HAVING COUNT>1` |
| R5 cobros huérfanos | `sale_payments` de crédito sin aplicación ni entrada |
| R6 AP | `amount − Σ pagos no revertidos ≠ balance_due`; status incoherente |
| R7 negativos | `balance_due < 0` |
| R8 tesorería | cobros/pagos no-efectivo sin movimiento (reutiliza `TreasuryGaps`) |
| R9 moneda | aplicaciones cuya moneda ≠ la de la venta |
| R10 XML vs saldo | cuota SUNAT ≠ `due_date`/saldo (solo informe) |

Cada violación se clasifica: **reparable determinísticamente** (R2 con un único candidato), **requiere decisión** (R1, R3) o **informativa**. Las no determinísticas pasan a un procedimiento de conciliación manual: lista exportable → revisión del cliente/soporte → `finance:adjust` registra un ajuste explícito con motivo (no edita filas históricas).

## 4. Backfill (Fase 1)

1. Crear `ar_receivable` + cuota única por cada venta a crédito (abiertas **y** cerradas con aplicaciones, para poder reconstruir saldos).
2. Reconstruir allocations solo donde exista `customer_payment_applications` (evidencia directa). Para pagos legacy sin aplicación (ventas cobradas por la ruta antigua), crear una cobranza de 1 allocation **solo si** `sale_payments` apunta a una venta de crédito (evidencia: FK dura). Marcar `origin='backfill'`, `confidence='exact'|'inferred'`.
3. Casos sin evidencia (R1/R3 no explicados): **no** crear allocations; registrar `ar_adjustment(kind='opening_reconciliation')` con el delta y el motivo, de modo que el saldo coincida sin fingir una distribución. Aparecen en un informe "saldos sin historia".
4. Idempotente (`origin`+clave natural), reanudable, por lotes y por tenant, con `--dry-run`.

## 5. Compatibilidad

- API: `POST /sales/collect-on-account` y `/sales/customer-advances*` se mantienen; internamente delegan en `RegisterCollection`. Respuesta conserva campos existentes (`collectionId`, `applications[]`) y agrega `tenders`, `replayed`. Sin `Idempotency-Key`: en Fase 0 se acepta pero se registra métrica; se hace obligatorio con la versión siguiente del contrato (anunciar; las integraciones externas ya usan clave determinista).
- `sale_record.balance_due/paid_total` se siguen escribiendo (desde AR) para POS, listados y `SalesGridVirtualFields`, hasta que todo lea de AR.
- Webhooks/integraciones: eventos actuales no cambian; los nuevos son aditivos (ADR-017).
- Doble escritura Fase 1: la ruta nueva y la vieja producen el mismo `balance_due`; `finance:verify` compara.

## 6. Activación progresiva

Capability por tenant `finance.receivables_v2` (como `treasury.accounts`), ya soportada por `TenantRuntimeConfig`. Orden: tenants internos/demo → piloto → resto. Estados: `off` (ruta vieja) → `shadow` (doble escritura y comparación, el usuario ve lo viejo) → `on` (lectura de nuevo) → retiro.

## 7. Rollback

- Fase 0: migraciones con `down()` (índices y CHECK). Un CHECK que falle bloquea la migración: por eso §3 va primero.
- Fase 1: apagar capability = vuelve la ruta vieja (que sigue escribiendo `balance_due`). Las tablas nuevas permanecen. Después de `on`, una cobranza creada solo en la ruta nueva existe también en `sale_payments` (doble escritura) hasta el retiro, por lo que el rollback no pierde datos. Retiro de la ruta vieja = punto de no retorno, con backup previo.

## 8. Validación posterior y monitoreo

`finance:verify` (programado diario y tras cada backfill): reglas R1–R9 en modo fallo + invariantes nuevas (Σ allocations ≤ cuota, Σ tenders = Σ allocations + exceso, cada tender con movimiento Treasury). Alertas: cualquier violación, `suspense ≠ 0`, cobranzas con conflicto de idempotencia, latencia de lock. Métricas: cobros por minuto, `replayed`, `lock_wait_timeout`.

## 9. Riesgos y controles

| Riesgo | Control |
| --- | --- |
| CHECK/UNIQUE falla por datos sucios | `finance:audit` previo; migración condicionada |
| Backfill inventa historia | solo evidencia directa; resto = ajuste explícito rotulado |
| Divergencia durante doble escritura | `shadow` + `verify` diario + corte por tenant |
| Locks largos | transacciones cortas, orden de bloqueo por id, timeout y mensaje al usuario |
| Volumen de tablas por tenant | índices `(customer_id, due_date)`, `(installment_id)`; validar con `docs/capacity-budgets.md` |
| Integraciones asumen respuesta actual | campos aditivos, versión de contrato, `PublicContractSnapshotTest` |
