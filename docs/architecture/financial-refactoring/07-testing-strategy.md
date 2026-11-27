# 07 · Estrategia de pruebas

Herramientas existentes: Pest/PHPUnit (`tests/`), pruebas de arquitectura (`tests/Architecture`), Playwright (`e2e/`), Vitest en frontend, Mago/PHPStan. Las pruebas listadas como "nuevas" **no existen hoy** (verificado por búsqueda en `tests/Sales`, `tests/Treasury`, `tests/AccountsPayable`).

## 1. Capas

| Capa | Qué cubre | Ejemplos |
| --- | --- | --- |
| Dominio / unitarias | `AllocationPlanner` (FIFO, vencimiento, manual, redondeo, exceso, moneda), `AgingCalculator` con corte, máquinas de estado, `Money` | ejemplo A/B/C/D del encargo; centavos en 3 cuotas de 100.00/3 con regla de redondeo explícita (última cuota absorbe) |
| Integración | servicios con BD real (MariaDB de CI): cobro → allocations → saldos → Treasury → `EconomicEvent` en una transacción; rollback si Treasury falla | extender `SalePaymentsTreasuryTest`, `TreasuryTestDatabase` |
| Contratos | snapshots de API pública y webhooks (`PublicContractSnapshotTest`); respuesta de `/sales/collect-on-account` conserva campos | — |
| E2E | cobranza global multi-medio, exceso→anticipo, reversión, estado de cuenta, pago global a proveedor, doble clic | ampliar `accounts-receivable`, `accounts-payable`, `receivable-collection-statement`, `credit-note-issue` |
| Concurrencia | dos conexiones/procesos con barrera: mismo saldo, mismo anticipo, misma clave | ver §2 |
| Idempotencia | mismo request secuencial y paralelo; con distinto cuerpo → 409 | `ExternalBankReceiptTest` es el modelo |
| Migración | `finance:audit` sobre fixtures sucios; backfill idempotente y reanudable; rollback de capability | dataset con casos R1–R10 |
| Reconciliación | `finance:verify` como prueba: tras cada escenario E2E, invariantes en 0 | se invoca al final de los specs |
| Multi-tenant | un tenant no ve ni aplica sobre ids de otro (BD por tenant): prueba por intento de acceso con token de tenant A a recurso de B | `tests/Security`, ControlPlane |
| Regresión | POS, ventas, compras, facturación y caja: suites existentes + `golden-path-fiscal`, `pos-credit-sale`, `cash-credit-reconciliation` | no deben cambiar |

## 2. Casos de concurrencia obligatorios (Fase 0)

1. Dos cobros de 100 sobre una factura con saldo 100 → exactamente uno confirma.
2. Cobro global y cobro individual simultáneos sobre la misma factura.
3. Dos aplicaciones del mismo anticipo → saldo disponible nunca negativo.
4. Pago a proveedor ×2 simultáneo.
5. 20 asientos simultáneos en la cuenta de un cliente → `running_balance` monótono coherente.
6. Reversión ×2 de la misma cobranza → una sola contra-fila.
7. Cobro vs. anulación de venta simultáneos.

Implementación: dos conexiones DBAL con transacciones entrelazadas (barreras) para determinismo, más un test de estrés corto en nocturno.

## 3. Cuándo corre cada cosa

| Momento | Suite |
| --- | --- |
| Local (pre-commit/pre-push) | unitarias del dominio financiero, Mago, PHPStan, arquitectura |
| Cada pull request | unitarias + integración financiera + contratos + E2E de AR/AP/Treasury/credit-note + concurrencia determinista + migración sobre fixtures |
| Pre-release | todo lo anterior + regresiones POS/ventas/compras/facturación completas + `finance:audit`/`verify` sobre una copia anonimizada de un tenant real + prueba de rollback de capability |
| Programado (nocturno) | estrés de concurrencia, `finance:verify` en staging, reproducción de backfill, aislamiento multi-tenant |

## 4. Criterio de salida por fase

Fase 0: batería §2 verde y 0 violaciones en `finance:verify` sobre fixtures. Fase 1: mismos escenarios 1–30 del `02-gap-analysis.md` con prueba asociada (cobertura trazable por número de escenario en el nombre del test).
