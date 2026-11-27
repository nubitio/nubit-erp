# 08 · Decisiones abiertas y supuestos

## Resolución (2026-11-27)

Todas las decisiones se tomaron con la opción recomendada. La tabla inferior conserva el análisis.

| ID | Decisión tomada |
| --- | --- |
| D-01 | NC sobre factura cobrada genera **saldo a favor**; el reembolso es una acción explícita |
| D-02 | Cuotas SUNAT por el **saldo neto pendiente** con el vencimiento interno |
| D-03 | Extraer AR de Sales **tras la Fase 1** |
| D-04 | **Una moneda por cobro**; diferencia de cambio en Fase 4 |
| D-05 | **Reversión con fecha actual**; cierre contable cuando Contabilidad esté activa |
| D-06 | Tesorería **obligatoria al final de la Fase 1**, con cuentas por defecto y suspense |
| D-07 | **Diferido**: validar con contador antes de la Fase 2; hoy el saldo es el total |
| D-08 | Distribución por **vencimiento más antiguo** (emisión y manual como opciones) |
| D-09 | Idempotency-Key **gradual con métrica**, luego obligatoria con fecha de corte |
| D-10 | Anticipos a proveedor en **Fase 2**; 3-way matching en **Fase 3** |

Con D-01 y D-02 resueltas, T0.6 y T0.8 del roadmap ya no están bloqueadas.

| ID | Decisión | Alternativas | Pros / contras | Recomendación | Impacto |
| --- | --- | --- | --- | --- | --- |
| D-01 | NC sobre factura ya cobrada (total o parcial) | (a) saldo a favor del cliente (anticipo), aplicable o reembolsable; (b) reembolso automático; (c) solo reducir saldo y dejar excedente sin registrar | (a) flexible, no mueve dinero solo; (b) rígido; (c) pierde dinero adeudado al cliente | (a), con reembolso como acción explícita | T0.8, T2.7; GL requiere evento nuevo |
| D-02 | Cuotas informadas a SUNAT en ventas con pago inicial | (a) cuota por el saldo neto pendiente con vencimiento interno; (b) mantener +1 mes | (a) coherente con RS 193-2020; (b) incumple | (a); validar con contador/SUNAT beta | T0.6, T1.7 |
| D-03 | ¿Extraer AR de `Sales` a `Receivables`? | (a) ahora; (b) tras Fase 1 cuando AP sea segundo consumidor; (c) nunca | (a) coste alto sin necesidad; (b) menos churn | (b) | Namespaces, `ContextManifest` |
| D-04 | Cobro con múltiples monedas | (a) una moneda por cobro; (b) conversión con TC | (a) simple y consistente con ADR-021 dec. 3 | (a); diferencias de cambio en F4 | T0.9 |
| D-05 | Periodos cerrados | (a) sin cierre; (b) cierre contable (bloquea fechas ≤ cierre); (c) solo reversión con fecha actual | (b) integridad vs. fricción | (c) ya en Fase 1; (b) cuando Contabilidad esté activa | T2.8 |
| D-06 | ¿Treasury obligatorio? | (a) opcional (hoy); (b) obligatorio con cuenta suspense | (b) un solo registro de dinero; migración y fricción para negocios pequeños | (b) al final de Fase 1, con auto-seed de cuentas | G-20 |
| D-07 | Detracción/retención/percepción en el saldo | (a) saldo = total y detracción como cobro especial; (b) saldo neto y detracción aparte | requiere criterio contable | **Validar con especialista**; no implementar antes | G-24 |
| D-08 | Estrategia de distribución por defecto | vencimiento más antiguo (recomendado, pedido por el encargo) vs. emisión (actual) | cambia comportamiento actual | Vencimiento; avisar en release notes | T1.2 |
| D-09 | Idempotency-Key obligatoria | inmediata vs. gradual | gradual protege integraciones | Gradual con métrica y fecha de corte | T0.3 |
| D-10 | Anticipo a proveedor y 3-way matching | F2/F3 vs. ahora | se pide poco por pequeños comercios | F2 anticipos, F3 matching | Roadmap |

## Supuestos a validar con usuarios/contadores

- Que pequeños comercios aceptan el flujo de un solo diálogo sin saber de "cuotas".
- Que la distribución por vencimiento es la esperada por distribuidoras.
- Que castigos, promesas y disputas son necesarios antes de factoring.
- Reglas SUNAT no verificadas aquí: modificación de cronograma tras emisión, NC en facturas al crédito, retenciones/percepciones, TC de cobros, comprobante de anticipos.
- Volumen real de inconsistencias históricas: solo conocido tras `finance:audit` en tenants reales.
