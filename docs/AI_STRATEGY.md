# eFact AI Strategy — Comprehensive Roadmap
**Versión:** 1.0  
**Fecha:** Marzo 2026  
**Autor:** spex-ai-eng  
**Audiencia:** Product Owner, CTO, Engineering Team

---

## Resumen Ejecutivo

eFact tiene los cimientos correctos de una plataforma AI-native: datos transaccionales ricos, un stack moderno (Symfony 7.4 + symfony/ai-bundle + React 18.2), integración con OpenAI ya funcionando, y un dominio con problemas reales y costosos (compliance SUNAT, discrepancias de caja, quiebres de stock). Lo que tiene hoy es un **Conversational MVP** — funciona, pero solo describe y no actúa, no sabe quién eres, y no te avisa de nada.

Este documento define el camino desde MVP hasta **plataforma AI-nativa** en 4 horizontes de entrega, con propuestas creativas que posicionan a eFact como el primer POS peruano genuinamente inteligente.

---

## Sección 1: Auditoría de Madurez AI

### Marco de Puntuación (1–5)

| Dimensión | Puntuación | Evidencia del Código | Veredicto |
|-----------|:----------:|----------------------|-----------|
| **Capability Breadth** | 2/5 | 5 tools registradas (`sales_summary`, `recent_sales`, `search_products`, `product_stock`, `cash_register_status`) + `search_manual` TF-IDF. Cero write tools. Cero inteligencia proactiva. | Superficie estrecha — solo lectura, solo POS, sin SUNAT ni inventario. |
| **Action Depth** | 1/5 | Todos los tools son read-only. `openCashRegister`, `closeCashRegister`, `getReceiptInfo`, `checkUserPermissions` aparecen en el system prompt pero no existen en el código (`CashRegisterTools.php` solo tiene `cashRegisterStatus`). El asistente puede decirte qué hacer, pero no puede hacer nada. | Cero capacidad de actuación — el peor gap posible. |
| **Context Awareness** | 1/5 | `assistantService.ts` envía `{ messages }` sin ningún contexto. El backend (`prompts/assistant.md`) define roles (CASHIER/SUPERVISOR/ADMIN) pero el sistema prompt se carga desde archivo estático — no hay inyección dinámica de `userId`, `branchId`, `cashRegisterId`, `tenantId` en tiempo de request. | El asistente no sabe quién es el usuario, en qué sucursal está, ni qué caja tiene abierta. |
| **Intelligence Proactivity** | 1/5 | Scheduler de Symfony existe pero está vacío para AI. No hay alertas push, no hay detección de anomalías, no hay insights programados. El asistente espera a ser preguntado. | Completamente reactivo — cero inteligencia proactiva. |
| **Data Grounding** | 2/5 | Los tools de ventas/caja/productos sí consultan la BD. Pero `KnowledgeIndex` usa TF-IDF sobre Markdown estático (sin embeddings). `TimeDimension` con `holiday/weekend` existe pero ningún tool la usa. `TaxDocumentEvent` con códigos de error SUNAT existe pero no es consultable. | Datos ricos disponibles, pero solo se expone una fracción mínima. |

### Puntuación Global: **6/25 = 24%**

### Nivel de Madurez: **Conversational MVP**

> El asistente puede responder preguntas de texto sobre datos que ya existen en la base de datos, y buscar en documentación de manera léxica. No puede actuar, no conoce el contexto del usuario, y no genera valor sin ser interrogado. Es un chatbot informativo básico con potencial enorme sin explotar.

### Lo que funciona bien (no ignorar)
- `symfony/ai-bundle` con tool calling nativo: arquitectura sólida, fácil de extender.
- `#[AsTool]` attribute pattern: limpio, testeable, discoverable.
- `prompts/assistant.md` versionado: buena práctica de prompt management.
- React chat widget con `devextreme` integrado en el flujo existente.
- Sistema multi-turn con historial de mensajes en el cliente.

---

## Sección 2: Mapa Gap → Feature

### GAP-01: Cero Write Tools — El Asistente No Puede Actuar

**Feature:** `AssistantActionTools` — Herramientas de escritura controladas  
**Qué hace:** Implementa tools de escritura con confirmación explícita del usuario para abrir/cerrar caja, registrar movimientos de efectivo, y anular ventas (con autorización de supervisor).  
**Enfoque:** `symfony/ai-bundle` tool calling + `CashRegisterOpenProcessor` / `CashRegisterCloseProcessor` ya existentes como backend. Añadir step de confirmación en UI antes de ejecutar mutaciones.  
**Complejidad:** L  
**Impacto:** Cashier (apertura/cierre de caja), Supervisor (anulaciones)

---

### GAP-02: Sin Inyección de Contexto de Sesión

**Feature:** `AssistantContextInjector` — Context-aware request enrichment  
**Qué hace:** En cada request a `POST /api/assistant`, inyecta automáticamente en el system prompt dinámico: `{userId, role, branchId, branchName, cashRegisterId, cashSessionStatus, tenantName, currentDateTime (Lima tz)}`. El asistente deja de ser ciego.  
**Enfoque:** Symfony `RequestStack` + `JWTAuthenticator` para extraer contexto; Twig template para system prompt dinámico (el `ai.yaml` ya soporta `prompt.file` — se puede pasar a template).  
**Complejidad:** S  
**Impacto:** Todos los roles — mejora calidad de cada respuesta.

---

### GAP-03: Tools Fantasma en el System Prompt

**Feature:** `SystemPromptAudit` — Limpieza y sincronización del prompt  
**Qué hace:** Elimina `openCashRegister`, `closeCashRegister`, `getReceiptInfo`, `checkUserPermissions` del listado de tools permitidas en `prompts/assistant.md` (líneas 133–140). Solo documentar tools que realmente existen. Añadir test de regresión que verifique que cada tool en el prompt existe como `#[AsTool]`.  
**Enfoque:** Edit de `prompts/assistant.md` + test PHPUnit que valida coherencia.  
**Complejidad:** S  
**Impacto:** Todos los roles — reduce hallucinations, mejora confiabilidad.

---

### GAP-04: Sin Tool de Estado SUNAT

**Feature:** `SunatStatusTool` — Diagnóstico inteligente de compliance  
**Qué hace:** Expone el historial de `TaxDocumentEvent` de los últimos N días con agrupación por `code` de error y `status`. El asistente puede responder "¿por qué me están rechazando facturas?" con datos reales del log.  
**Enfoque:** `#[AsTool]` sobre `TaxDocumentEventRepository`. Parámetros: `days` (int, default 7), `branchId` (opcional). Retorna: conteo por error code, últimos documentos rechazados con mensaje.  
**Complejidad:** M  
**Impacto:** Supervisor/Admin — SUNAT compliance visibility.

---

### GAP-05: Sin Tool de Alertas de Inventario

**Feature:** `StockAlertTool` — Radar de quiebres de stock  
**Qué hace:** Consulta `StockItem` donde `stock <= min_stock` filtrando por `branchId` del usuario. Retorna lista de productos en riesgo con stock actual, mínimo, y días estimados de agotamiento (usando velocidad de ventas de los últimos 30 días).  
**Enfoque:** `#[AsTool]` sobre `ProductStockRepository` + join con `branch_daily_summary` para calcular velocidad. `TimeDimension` puede excluir días sin ventas del cálculo.  
**Complejidad:** M  
**Impacto:** Supervisor/Admin — inventory management.

---

### GAP-06: Sin Inteligencia de Clientes

**Feature:** `CustomerIntelligenceTool` — Perfil y comportamiento de compra  
**Qué hace:** Dado un `customerId` o RUC/DNI, retorna: total histórico de compras, ticket promedio, última compra, productos favoritos (top 5), y flag si es cliente habitual (>3 compras/mes). Permite al cashier personalizar la atención en caja.  
**Enfoque:** `#[AsTool]` sobre `CustomerRepository` con join a `SaleRecord` + `SaleItemRecord`. Parámetros: `customerId` o `rucDni` (string).  
**Complejidad:** M  
**Impacto:** Cashier (atención en caja), Supervisor (análisis de cartera).

---

### GAP-07: RAG Léxico (TF-IDF) Sin Semántica

**Feature:** `SemanticRAG` — Embeddings + búsqueda vectorial para documentación  
**Qué hace:** Reemplaza `KnowledgeIndex` TF-IDF con embeddings `text-embedding-3-small` almacenados en un índice vectorial. Las queries en lenguaje natural ("cómo cierro turno si la caja no cuadra") encuentran resultados relevantes aunque no compartan vocabulario exacto.  
**Enfoque:** `text-embedding-3-small` para ingesta y query. Almacenamiento en Qdrant (ver Sección 5). Symfony command `BuildEmbeddingIndexCommand` para re-indexar. `KnowledgeRetriever` refactorizado para cosine similarity sobre vectors reales.  
**Complejidad:** M  
**Impacto:** Todos los roles — calidad de respuestas a preguntas de "cómo se hace".

---

### GAP-08: Sin Inteligencia Proactiva

**Feature:** `ProactiveAlertScheduler` — Insights automáticos al inicio de turno  
**Qué hace:** Un Symfony Scheduler job que se ejecuta cada mañana (y al abrir caja) genera un briefing personalizado: alertas de stock crítico, facturas SUNAT pendientes de reenvío, discrepancias de cierre del turno anterior, y resumen del día anterior vs. mismo día semana pasada.  
**Enfoque:** `symfony/scheduler` (ya instalado, scheduler vacío en el proyecto) + `gpt-4o-mini` para narración natural del briefing. Push vía WebSocket o notificación en dashboard al login.  
**Complejidad:** M  
**Impacto:** Supervisor/Admin — proactive business intelligence.

---

### GAP-09: `TimeDimension` Sin Explotar

**Feature:** `TemporalIntelligenceTool` — Contexto de tiempo inteligente  
**Qué hace:** Enriquece todos los tools de ventas con contexto temporal: "hoy es feriado (Fiestas Patrias)", "estás en quincena", "este mes tiene 2 domingos menos que el anterior". Permite al asistente contextualizar variaciones de ventas sin que el usuario tenga que explicarlo.  
**Enfoque:** Join `TimeDimension` en `SaleTools.salesSummary()`. Añadir campo `temporalContext` al `SalesSummaryDto`. Bajo costo — la tabla ya existe y está poblada.  
**Complejidad:** S  
**Impacto:** Supervisor/Admin — explicaciones contextuales de variaciones.

---

### GAP-10: Sin Forecasting ni Análisis de Tendencias

**Feature:** `DemandForecastTool` — Predicción de ventas por producto  
**Qué hace:** Para un producto o categoría, calcula la tendencia de ventas de las últimas 8 semanas (usando `branch_daily_summary`), ajusta por estacionalidad vía `TimeDimension` (holidays, weekends), y proyecta la demanda de las próximas 2 semanas con intervalo de confianza.  
**Enfoque:** Serie temporal simple (media móvil exponencial) en PHP/SQL primero; `gpt-4o-mini` para interpretar la proyección en lenguaje natural. No requiere modelo custom — los datos son suficientes para ETS.  
**Complejidad:** L  
**Impacto:** Supervisor/Admin — inventory planning, purchase orders.

---

### GAP-11: Sin Detección de Anomalías

**Feature:** `AnomalyDetectionService` — Vigilancia financiera automática  
**Qué hace:** Analiza `CashSession`/`CashMovement` para detectar discrepancias de caja superiores a umbral configurable, descuentos inusuales (outliers vs. promedio del vendedor), y picos/caídas de ventas por hora vs. histórico del mismo día-semana. Genera alertas automáticas sin ser preguntado.  
**Enfoque:** Statistical z-score sobre datos históricos + `gpt-4o-mini` para explicación narrativa de la anomalía. Symfony Scheduler diario + alertas en tiempo real en eventos de cierre de caja.  
**Complejidad:** L  
**Impacto:** Supervisor/Admin/Operator — fraud prevention, operational control.

---

## Sección 3: Nuevas Funcionalidades AI Creativas

### FEAT-01: "El Briefing de las 8am" — Daily Intelligence Push
**Nombre de Producto:** *Inicio de Turno Inteligente*

**Qué hace:** Cada mañana, antes de que el cajero abra su caja, recibe un briefing personalizado de 5 puntos generado por AI: (1) productos que se agotarán hoy según la velocidad de ventas, (2) facturas SUNAT en estado de error pendientes de atención, (3) comparativo de ayer vs. mismo día semana pasada, (4) top 3 productos que más se vendieron ayer para tener en mente, (5) nota especial si es feriado, quincena, o inicio de mes. Aparece como pantalla de bienvenida al hacer login o al abrir caja.

**Por qué importa para PyMEs peruanas:** Los dueños de bodega, farmacia o restaurante no tienen tiempo para revisar 5 reportes diferentes. La quincena (15 y 30) y los feriados nacionales tienen impacto masivo en ventas — una farmacia que no reabastece antes del 1 de julio pierde ventas en Fiestas Patrias. El briefing elimina el trabajo manual de interpretación.

**Enfoque técnico:** Symfony Scheduler job `DailyBriefingCommand` a las 07:30 (Lima time) + `gpt-4o-mini` con structured output JSON → narración en español. Datos: `branch_daily_summary`, `StockItem`, `TaxDocumentEvent`, `TimeDimension`. Push al frontend vía Server-Sent Events o al abrir sesión.

**Horizonte:** Quick Win (0-2 meses)

---

### FEAT-02: "Asesor SUNAT" — Error Code Explainer + Fix Guide
**Nombre de Producto:** *Asistente de Comprobantes*

**Qué hace:** Cuando una factura es rechazada por SUNAT, el sistema muestra el código de error críptico (ej: `2329 - El dato ingresado en Tag UBL no corresponde`). El Asistente SUNAT intercepta este evento, traduce el código a lenguaje humano, identifica el campo específico que causó el rechazo, sugiere el paso exacto para corregirlo, y con un clic lanza el reintento. Para errores repetitivos del mismo proveedor o mismo tipo de documento, genera un informe semanal de "problemas sistémicos".

**Por qué importa para PyMEs peruanas:** SUNAT tiene ~200 códigos de error documentados de forma técnica. Los operadores de caja no son contadores. Una factura rechazada que no se reenvía a tiempo puede generar multas. El tiempo promedio para resolver un error SUNAT sin asistencia es 15-45 minutos; con AI debería ser 2-3 clics.

**Enfoque técnico:** RAG sobre catálogo de errores SUNAT (PDF público) indexado con embeddings. Cuando `TaxDocumentEvent.status = 'rejected'`, trigger automático que consulta el RAG con `code + message`, genera explicación + pasos de resolución. Tool write: `retrySunatDocument(taxDocumentId)` que llama al `InvoiceSendRetryService` existente.

**Horizonte:** Medium (2-6 meses)

---

### FEAT-03: "Cámara Inteligente" — Receipt & Product Photo Scanner
**Nombre de Producto:** *Escáner Visual eFact*

**Qué hace:** El cajero apunta la cámara del celular a: (a) un boleto físico de compra de proveedor y el sistema extrae automáticamente RUC, razón social, productos, cantidades y precios para crear la orden de compra; (b) un producto sin código de barras y el sistema lo identifica por imagen comparando con el catálogo de productos; (c) un medidor de estante vacío y el sistema sugiere el pedido de reposición automáticamente. Integrado como botón en el módulo de compras y en la app móvil futura.

**Por qué importa para PyMEs peruanas:** Las bodegas y farmacias peruanas reciben facturas de proveedores en papel, muchas veces manuscritas o con formatos inconsistentes. El proceso de digitación manual es el mayor cuello de botella en el módulo de compras. La visión artificial elimina este work.

**Enfoque técnico:** `gpt-4o` con vision (multimodal) para OCR de facturas + extracción de campos con structured output. Para identificación de productos: fine-tune opcional o zero-shot con `gpt-4o`. API: `POST /api/assistant/scan` que acepta `image_base64`. Frontend: botón de cámara en `LogisticOperation` y módulo de compras.

**Horizonte:** Medium (2-6 meses)

---

### FEAT-04: "WhatsApp como POS" — Ventas y Consultas por Chat
**Nombre de Producto:** *eFact por WhatsApp*

**Qué hace:** A través del canal WhatsApp (Bird/WhatsApp ya integrado en el proyecto), los clientes pueden: consultar el saldo de su cuenta (crédito), pedir el historial de sus últimas facturas, recibir su boleta electrónica por WhatsApp en lugar de email, y — para clientes mayoristas frecuentes — hacer pedidos de reposición directamente por chat ("Necesito 3 cajas de aceite Primor 1L"). El asistente AI valida el pedido contra stock disponible, confirma precio de lista del cliente, y genera la orden.

**Por qué importa para PyMEs peruanas:** WhatsApp tiene 95% de penetración en adultos peruanos. Muchos distribuidores y bodegas ya reciben pedidos por WhatsApp manualmente (el vendedor los tipea). Automatizar esto elimina errores de transcripción y permite pedidos 24/7. La integración con Bird ya existe en el proyecto.

**Enfoque técnico:** Webhook Bird → Symfony handler → `gpt-4o-mini` con tools: `getCustomerBalance`, `getCustomerInvoices`, `searchProducts`, `createOrder` (write tool). Structured conversation flow con confirmación antes de crear orden. Autenticación por número de teléfono vinculado a `Customer.phone`.

**Horizonte:** Long (6-12 meses)

---

### FEAT-05: "Contador Virtual" — Cash Flow Forecasting con Tipo de Cambio
**Nombre de Producto:** *Proyector de Flujo*

**Qué hace:** Cruza el historial de ventas diarias con el calendario BCRP de tipo de cambio (ya integrado en el proyecto con `ExchangeRateSyncService`) y los patrones del `TimeDimension` para proyectar el flujo de caja de las próximas 4 semanas. Muestra: ingresos esperados por día (con rango de confianza), impacto en PEN de variaciones del tipo de cambio en productos importados, y fecha estimada de quiebre de liquidez si el gasto operativo se mantiene constante. Alerta proactiva si proyecta déficit.

**Por qué importa para PyMEs peruanas:** El tipo de cambio sol/dólar afecta directamente el costo de productos importados (electrónicos, insumos de farmacia, alimentos procesados). Las PyMEs sin contador propio toman decisiones de compra sin considerar esto. La quincena y el fin de mes crean patrones de cobro muy predecibles en el mercado peruano que un modelo simple puede capturar.

**Enfoque técnico:** ETS (Exponential Smoothing) sobre `branch_daily_summary` + `TimeDimension` (holiday/weekend flags como regressors). `ExchangeRateSyncService` provee las series históricas. `gpt-4o-mini` traduce la proyección a narrativa de gestión. Visualización: gráfico de bandas de confianza en el dashboard.

**Horizonte:** Long (6-12 meses)

---

### FEAT-06: "Radar de Fraude" — Anomaly Detection en Tiempo Real
**Nombre de Producto:** *Guardia Inteligente*

**Qué hace:** Monitorea en tiempo real 5 patrones de riesgo: (1) descuentos que superan el promedio del vendedor en >2σ, (2) cierre de caja con diferencia positiva inusual (posible sustracción no registrada), (3) devoluciones de productos sin ticket original, (4) venta de producto de alto valor a "CLIENTE VARIOS" (no identificado), (5) múltiples anulaciones del mismo cajero en la misma hora. Cuando detecta un patrón, notifica al supervisor en tiempo real con explicación narrativa y score de riesgo 1-10.

**Por qué importa para PyMEs peruanas:** El robo interno es uno de los principales problemas en retail peruano, especialmente en negocios con múltiples cajeros. Los supervisores no pueden revisar manualmente cientos de transacciones. Un sistema que auto-detecta patrones anómalos y los explica en lenguaje natural cambia la ecuación de control interno.

**Enfoque técnico:** Z-score statistical en PHP sobre ventana rolling de 30 días por vendedor. Event listener en `SaleProcessor.php` (ya tiene hooks post-venta) + `CashRegisterCloseProcessor`. `gpt-4o-mini` para narración del alert. WebSocket push o polling al panel de supervisor. Score de riesgo = función de desviación estándar normalizada.

**Horizonte:** Long (6-12 meses)

---

### FEAT-07: "Asistente de Onboarding" — Setup Guiado por AI
**Nombre de Producto:** *eFact Setup Wizard AI*

**Qué hace:** Para tenants nuevos, un asistente conversacional reemplaza el formulario de configuración inicial. En lugar de 15 pantallas de configuración, el usuario responde 8 preguntas en lenguaje natural: "¿Qué tipo de negocio tienes?", "¿Cuántos cajeros trabajan contigo?", "¿Manejas múltiples almacenes?". El AI clasifica el tipo de negocio (farmacia, restaurante, bodega, ferretería, distribuidora), pre-configura la estructura de datos (categorías de productos, métodos de pago, permisos por rol), y carga un catálogo de productos de muestra del sector. El tiempo de onboarding baja de ~3 horas a ~20 minutos.

**Por qué importa para PyMEs peruanas:** La tasa de abandono en onboarding de software SaaS en Perú es alta — los dueños de negocio no son técnicos. Un asistente que hace las preguntas correctas en español peruano coloquial y configura todo automáticamente es una ventaja competitiva directa contra competidores como Alegra, Contipaq, y FacturaloPeru.

**Enfoque técnico:** Multi-step structured conversation con `gpt-4o-mini` + JSON schema validation para cada step. Tool `configureNewTenant(type, branches, warehouses, userRoles, sampleCatalog)` que llama al `InitialSetupService` existente. Templates de configuración por vertical (farmacia/restaurante/bodega) en JSON. Interfaz: modal de onboarding en el flujo de primer login del ADMIN.

**Horizonte:** Medium (2-6 meses)

---

### FEAT-08: "El Asistente que Pregunta Primero" — Predictive Query Suggestions
**Nombre de Producto:** *Sugerencias Inteligentes*

**Qué hace:** En lugar de esperar a que el usuario escriba una pregunta, el asistente analiza el contexto de la pantalla actual y sugiere las 3 preguntas más relevantes como chips clicables. En la pantalla de caja: "¿Cuánto llevo vendido hoy?", "¿Hay productos con stock bajo?", "¿Está al día mi caja?". En el dashboard: "¿Por qué cayeron las ventas esta semana?", "¿Qué productos debo reponer?", "¿Hay facturas SUNAT pendientes?". Las sugerencias cambian según la hora del día, el estado de la caja, y el historial de preguntas del usuario.

**Por qué importa para PyMEs peruanas:** La mayoría de los cajeros y supervisores no saben qué preguntarle a un AI assistant — el concepto es nuevo. Las sugerencias contextuales educan el hábito de uso y aumentan el engagement del asistente sin requerir entrenamiento. Es la diferencia entre un chatbot que nadie usa y un asistente que forma parte del flujo de trabajo.

**Enfoque técnico:** `gpt-4o-mini` con structured output generando 3 sugerencias dado `{currentRoute, userRole, cashSessionStatus, timeOfDay, recentAlerts}`. Llamada ligera al cargar cada módulo principal (cacheable por 15 min). Frontend: chips clicables bajo el ícono del asistente. Costo estimado: ~0.001 USD por sugerencia generada.

**Horizonte:** Quick Win (0-2 meses)

---

### FEAT-09: "Análisis de Ticket" — Natural Language Business Analytics
**Nombre de Producto:** *Reportes en Lenguaje Natural*

**Qué hace:** El supervisor puede hacer preguntas analíticas complejas en lenguaje natural y obtener respuestas con datos reales: "¿Qué sucursal vendió más en el primer trimestre?", "¿Cuáles son los 10 productos con mayor margen que no hemos vendido en 30 días?", "¿Cómo evolucionó el ticket promedio por cajero en el último mes?". El sistema genera la SQL internamente (sin exponerla al usuario), la ejecuta contra los datos del tenant, y retorna la respuesta en prosa con una tabla de soporte.

**Por qué importa para PyMEs peruanas:** Los dueños de negocio no saben usar Excel ni interpretar reportes estáticos. Poder preguntarle al sistema "¿cómo me fue este mes?" en lugar de navegar 4 niveles de menú y descargar un CSV es transformacional. Este feature posiciona a eFact contra herramientas de BI tradicionales que requieren capacitación.

**Enfoque técnico:** Text-to-SQL con `gpt-4o` (mayor fidelidad para queries complejas). Schema context reducido: solo tablas relevantes al tenant + names semánticos. Validación de SQL generada (solo SELECT, sin DDL/DML). Sandbox de ejecución con timeout de 5 segundos y límite de filas. Hallucination guard: si el SQL falla, no inventar datos — retornar error con sugerencia de reformular.

**Horizonte:** Long (6-12 meses)

---

### FEAT-10: "POS por Voz" — Voice-First Point of Sale
**Nombre de Producto:** *eFact Voice*

**Qué hace:** El cajero puede operar el POS completamente por voz en un entorno ruidoso (mercado, cocina de restaurante): "Agrega dos Inca Kola 1.5L", "Aplica 10% de descuento", "El cliente paga con tarjeta Visa, 50 soles". El sistema transcribe, interpreta la intención, ejecuta la acción en el POS UI, y confirma por voz: "Dos Inca Kola 1.5L agregadas. Total: S/. 9.80." Para líneas de caja con alto volumen, reduce el tiempo por transacción en 30-40%.

**Por qué importa para PyMEs peruanas:** Los mercados y restaurantes peruanos son entornos de alto ruido y alta velocidad. En una cevichería en hora punta, el cajero no puede escribir — necesita hablar. En una farmacia, el farmacéutico está de espaldas buscando el producto mientras el cliente espera. Voice-first POS resuelve el problema de la fricción física en el punto de venta.

**Enfoque técnico:** OpenAI Whisper API para transcripción (optimizado para español peruano). `gpt-4o-mini` para intent classification + entity extraction (producto, cantidad, descuento, método de pago). Tool calling sobre el POS React state (nueva capa de `VoiceToolRegistry`). TTS (Text-to-Speech) para confirmaciones: OpenAI TTS API con voz `alloy` configurada para español. Activación por wake word o botón push-to-talk en UI móvil.

**Horizonte:** Moonshot (12m+)

---

## Sección 4: Roadmap de Entrega

### Horizonte 1 — Quick Wins (0-2 meses)
**Headline:** *"El asistente que te conoce"* — pasar de chatbot ciego a contexto-aware, confiable y proactivo.

| # | Feature | Esfuerzo Est. | Origen |
|---|---------|:-------------:|--------|
| H1.1 | **GAP-03: Limpiar System Prompt** — eliminar tools fantasma, añadir test de coherencia | 1d | Gap-03 |
| H1.2 | **GAP-02: Session Context Injection** — inyectar userId/role/branchId/cashRegisterId dinámicamente | 3d | Gap-02 |
| H1.3 | **GAP-09: TimeDimension en SaleTools** — enriquecer `salesSummary` con contexto de feriados/quincena | 2d | Gap-09 |
| H1.4 | **GAP-04: SunatStatusTool** — tool de consulta de errores `TaxDocumentEvent` | 3d | Gap-04 |
| H1.5 | **GAP-05: StockAlertTool** — productos bajo `min_stock` con días estimados de agotamiento | 3d | Gap-05 |
| H1.6 | **GAP-06: CustomerIntelligenceTool** — perfil y comportamiento de compra | 3d | Gap-06 |
| H1.7 | **FEAT-08: Predictive Query Suggestions** — chips contextuales en el chat | 4d | Feature-08 |
| H1.8 | **FEAT-01: Briefing Diario** — Symfony Scheduler + AI narration al abrir caja | 5d | Feature-01 |

**Esfuerzo Total Estimado:** ~24 días-dev (5 semanas con 1 dev)  
**Capacidad Desbloqueada:** El asistente sabe quién eres, qué tienes pendiente, qué stock está en riesgo, y te da el día a día sin que lo pidas. Pasa de chatbot a primer nivel de inteligencia operacional.

---

### Horizonte 2 — Assistive Intelligence (2-6 meses)
**Headline:** *"El asistente que entiende y actúa"* — semantic search, SUNAT intelligence, write tools, onboarding AI.

| # | Feature | Esfuerzo Est. | Origen |
|---|---------|:-------------:|--------|
| H2.1 | **GAP-07: Semantic RAG** — reemplazar TF-IDF con embeddings + Qdrant | 2sem | Gap-07 |
| H2.2 | **FEAT-02: Asistente SUNAT** — error explainer + reintento con 1 clic | 3sem | Feature-02 |
| H2.3 | **GAP-01: Write Tools (Phase 1)** — `openCashRegister`, `closeCashRegister` con confirmación | 2sem | Gap-01 |
| H2.4 | **FEAT-07: Onboarding AI Wizard** — setup guiado en lenguaje natural para nuevos tenants | 2sem | Feature-07 |
| H2.5 | **FEAT-03: Receipt Scanner** — OCR de facturas de proveedor con `gpt-4o` vision | 3sem | Feature-03 |
| H2.6 | **GAP-08: Proactive Alert Scheduler** — alertas push de caja, stock, SUNAT al inicio de turno | 2sem | Gap-08 |

**Esfuerzo Total Estimado:** ~14 semanas (3.5 meses con 2 devs)  
**Capacidad Desbloqueada:** El asistente puede actuar (abrir/cerrar caja), entiende preguntas semánticas, automatiza el onboarding, y lee documentos físicos. Elimina las principales fricciones manuales del día a día.

---

### Horizonte 3 — Predictive & Autonomous (6-12 meses)
**Headline:** *"El asistente que predice y previene"* — forecasting, anomaly detection, natural language analytics, write tools extendidos.

| # | Feature | Esfuerzo Est. | Origen |
|---|---------|:-------------:|--------|
| H3.1 | **GAP-10: Demand Forecast Tool** — proyección de demanda 2 semanas con ETS + TimeDimension | 4sem | Gap-10 |
| H3.2 | **GAP-11: Anomaly Detection** — radar de fraude y discrepancias en tiempo real | 4sem | Gap-11 |
| H3.3 | **FEAT-06: Guardia Inteligente** — scoring de riesgo por transacción + notificación supervisor | 3sem | Feature-06 |
| H3.4 | **FEAT-05: Cash Flow Forecasting** — proyección 4 semanas + tipo de cambio BCRP | 4sem | Feature-05 |
| H3.5 | **FEAT-09: Natural Language Analytics** — text-to-SQL seguro para supervisores | 5sem | Feature-09 |
| H3.6 | **GAP-01: Write Tools (Phase 2)** — anular ventas, crear movimientos de caja, generar órden de compra desde chat | 3sem | Gap-01 |
| H3.7 | **FEAT-04: WhatsApp Orders** — pedidos de clientes mayoristas por WhatsApp AI | 5sem | Feature-04 |

**Esfuerzo Total Estimado:** ~28 semanas (7 meses con 2 devs)  
**Capacidad Desbloqueada:** eFact predice cuándo se agotará el stock antes de que ocurra, detecta robo interno automáticamente, permite analítica de negocio en lenguaje natural, y extiende el canal de ventas a WhatsApp.

---

### Horizonte 4 — Moonshots (12m+)
**Headline:** *"El sistema que trabaja por ti"* — agentes autónomos, voz, verticales especializadas.

| # | Feature | Esfuerzo Est. | Origen |
|---|---------|:-------------:|--------|
| H4.1 | **FEAT-10: POS por Voz** — operación completa del POS por voz en español peruano | 4 meses | Feature-10 |
| H4.2 | **Inventory Autopilot** — agente que genera y envía órdenes de compra a proveedores automáticamente cuando stock < umbral | 3 meses | Nuevo |
| H4.3 | **SUNAT Reconciliation Agent** — conciliación automática diaria de comprobantes enviados vs. SUNAT, con corrección autónoma de diferencias | 4 meses | Nuevo |
| H4.4 | **Vertical Fine-Tuning** — modelos especializados por sector (farmacia, restaurante, distribuidora) con terminología y reglas de negocio propias | 6 meses | Nuevo |
| H4.5 | **Predictive Pricing** — sugerencia dinámica de precios por producto/canal basada en demanda, competencia, y tipo de cambio | 5 meses | Nuevo |

**Capacidad Desbloqueada:** eFact evoluciona de herramienta a socio de negocio autónomo. El dueño de la bodega llega cada mañana y el sistema ya hizo el trabajo de la noche: concilió con SUNAT, revisó el stock, calculó los precios óptimos, y generó las órdenes de compra. El cajero habla con el POS.

---

## Sección 5: Recomendaciones de Arquitectura

### 5.1 Vector DB para Semantic RAG (Dado MariaDB, no Postgres)

**Problema clave:** pgvector requiere PostgreSQL — el proyecto usa MariaDB. Las opciones son:

| Opción | Pros | Contras | Recomendación |
|--------|------|---------|:-------------:|
| **Qdrant** (self-hosted Docker) | Open source, Docker Compose, PHP client disponible, filtros de metadatos potentes, zero external dependency | Un servicio más en el stack | ✅ **Recomendada** |
| Pinecone (cloud) | Managed, serverless | Costo por query, datos salen del Perú, vendor lock-in | ❌ |
| MariaDB VECTOR (MySQL 9+) | Sin infraestructura nueva | MariaDB no tiene soporte vector nativo estable | ❌ |
| OpenAI File Search | Managed, sin infra | Solo sirve para docs, no para datos transaccionales dinámicos | ⚠️ Solo para docs |

**Decisión:** Qdrant self-hosted en `docker-compose.yml` junto a los servicios existentes. Un volumen persistente para el índice. El `symfony/ai-bundle` ya tiene soporte para múltiples vector stores — verificar driver disponible para Qdrant o implementar cliente directo via `qdrant/qdrant-client-php`.

```yaml
# docker-compose.yml addition
qdrant:
  image: qdrant/qdrant:latest
  ports:
    - "6333:6333"
  volumes:
    - qdrant_data:/qdrant/storage
```

---

### 5.2 Modelo de Embeddings para Contenido en Español

**Recomendación:** `text-embedding-3-small` (OpenAI)

| Modelo | Dims | Calidad ES | Costo | Decisión |
|--------|:----:|:----------:|:-----:|:--------:|
| `text-embedding-3-small` | 1536 | ★★★★☆ | ~$0.02/1M tokens | ✅ **Default** |
| `text-embedding-3-large` | 3072 | ★★★★★ | ~$0.13/1M tokens | Solo si calidad insuficiente |
| `multilingual-e5-large` (HuggingFace) | 1024 | ★★★★★ ES | Gratis, on-prem | Si se quiere zero OpenAI dependency |

**Justificación:** `text-embedding-3-small` tiene excelente rendimiento en español (incluye jerga latinoamericana). Al costo estimado, indexar todo el catálogo de productos de 100 tenants (~50K productos × 100 tokens = 5M tokens) cuesta ~$0.10 una sola vez. Re-indexaciones diarias (solo deltas) serán centavos.

**Para terminología SUNAT específica:** Considerar `multilingual-e5-large` hosteado en el servidor para el catálogo de errores SUNAT — mejora recall en documentación técnica oficial sin costo por query.

---

### 5.3 Agent Framework: symfony/ai-bundle vs LangChain vs Custom

**Recomendación: Quedarse con `symfony/ai-bundle` + extender**

| Framework | Pros | Contras |
|-----------|------|---------|
| **symfony/ai-bundle** (actual) | Native PHP, DI nativo, `#[AsTool]` limpio, zero overhead, ya integrado | Ecosistema más joven que LangChain |
| LangChain Python | Ecosistema rico, agentes complejos, muchos integraciones | Requiere microservicio Python, latencia IPC, dos stacks |
| Custom PHP | Control total | Re-inventar la rueda |

**Justificación:** El `#[AsTool]` pattern ya está probado en producción con 5 tools. Extenderlo a 15-20 tools es lineal. El único caso donde LangChain Python tiene sentido es para el pipeline de embeddings en batch (FEAT que requiere procesamiento masivo de documentos) — pero incluso ahí, un Symfony Command con `openai-php/client` directo es suficiente.

**Para agentes autónomos (Horizonte 4):** `symfony/ai-bundle` ya soporta multi-step tool calling. Para agentes verdaderamente autónomos (Inventory Autopilot), considerar `symfony/workflow` como state machine del agente — integración nativa con el stack existente.

---

### 5.4 Modelo de Costo: 100 Tenants × Uso Promedio

**Supuestos:**
- 100 tenants activos
- Promedio: 20 usuarios/tenant, 30% activos diariamente = 600 usuarios/día
- 10 queries/usuario/día al asistente
- Token promedio por query: 800 tokens input + 300 tokens output (incluyendo tools)

**Cálculo mensual (30 días):**

| Componente | Volumen | Costo Unitario | Total/mes |
|------------|---------|:--------------:|:---------:|
| Chat queries (`gpt-4o-mini`) | 600 × 10 × 30 = 180K queries | ~1.1K tokens/query = 198M tokens | ~$11.88 input + $3.96 output = **~$16** |
| Daily briefings (`gpt-4o-mini`) | 100 tenants × 30 días = 3K briefings | ~2K tokens/briefing | ~$0.36 input + $0.18 output = **~$0.54** |
| Suggestion chips (`gpt-4o-mini`) | 600 usuarios × 5 page loads × 30 días = 90K | ~500 tokens/call | **~$2.70** |
| Embeddings re-index (nightly delta) | ~10K products/day × 100 tokens | `text-embedding-3-small` | **~$0.02** |
| **TOTAL ESTIMADO** | | | **~$20/mes** |

**En producción real:** A escala de 100 tenants activos, el costo de AI es trivial (~$0.20/tenant/mes). A 1000 tenants sería ~$200/mes — aún marginales vs. el valor entregado. **El riesgo de costo no es aquí.** El riesgo surge en Horizonte 3 con Text-to-SQL (`gpt-4o`) y Semantic RAG a alta frecuencia — presupuestar $5-15/tenant/mes adicionales para esas features premium.

**Estrategia de control de costos:**
1. Cache agresivo de briefings (1 por usuario por día)
2. Rate limiting por tenant en `/api/assistant` (50 queries/hora)
3. Token logging por request desde día 1 (ya recomendado por `spex-ai-eng`)
4. Fallback a `gpt-4o-mini` para todo excepto Text-to-SQL (que necesita `gpt-4o`)

---

### 5.5 Data Pipeline: Mantener el Índice Vectorial Fresco

**Para documentación (estática):** Re-index manual via `BuildKnowledgeIndexCommand` al deplegar cambios en `docs/`. No requiere automatización.

**Para datos transaccionales (dinámicos):**

```
[Doctrine PostFlush Event] → [Symfony Messenger] → [EmbeddingIndexerHandler]
     Product UPSERT              async queue              → Qdrant upsert
     Customer UPSERT             (5min delay)             → incremental
     TaxDocumentEvent            
```

**Arquitectura recomendada:**
1. Doctrine `EventSubscriber` escucha `postPersist`/`postUpdate` en `Product`, `Customer`, `TaxDocument`
2. Publica `EmbeddingUpdateMessage` en cola Messenger (transport: `doctrine` ya configurado)
3. Worker asíncrono procesa en batch cada 5 minutos (no en tiempo real — ahorra costos de embedding)
4. Nightly full re-index como safety net via Symfony Scheduler

**Para el catálogo de errores SUNAT:** Re-index mensual (el catálogo SUNAT cambia con cada versión de la norma, ~2-3 veces al año).

---

### 5.6 Estrategia de Mitigación de Alucinaciones para Datos Financieros

**El riesgo real:** El asistente podría inventar un monto de ventas, un saldo de caja, o un estado de factura SUNAT — en un contexto financiero esto puede causar daño directo al negocio.

**Mitigaciones por capa:**

| Capa | Mecanismo | Implementación |
|------|-----------|----------------|
| **Tool Grounding** | Toda respuesta financiera DEBE usar un tool call | System prompt: "Nunca afirmes datos financieros sin llamar el tool correspondiente. Si el tool falla, di que no tienes la información." |
| **Source Citation** | Toda respuesta numérica cita su fuente | Structured output con campo `source: "tool:sales_summary, fecha: 2026-03-10"` |
| **Confidence Gating** | Tool retorna null → AI no inventa | Tools tipados retornan DTOs — si el dato no existe, retornan null explícito, no estimados |
| **Validation Layer** | Para write tools: JSON schema validation pre-ejecución | Antes de ejecutar `openCashRegister(id)`, validar que `id` existe y es del tenant correcto |
| **Audit Log** | Toda acción del asistente queda registrada | Extender `AuditLogger` existente con evento `AI_ACTION` |
| **Human Confirmation** | Write tools requieren confirmación explícita del usuario | UI: modal de confirmación con resumen de la acción antes de ejecutar |

**Regla de oro:** Los tools son la única fuente de verdad. Si el tool no dice el número, el asistente no dice el número. Esta regla ya está en `prompts/assistant.md` (Sección 6) — cumplirla requiere que los tools estén implementados correctamente.

---

### 5.7 El Único Investimento Arquitectural de Mayor ROI Ahora Mismo

> **Context Injection (GAP-02) + Session-Aware System Prompt**

Este es el cambio de 3 días con el mayor impacto de todo el roadmap.

**Por qué:** Actualmente el asistente responde igual para el cajero de Miraflores que para el admin de la sede central. No sabe que la caja 3 está cerrada, que el usuario es un CASHIER, ni que está en la sucursal de San Isidro. Cada pregunta requiere que el usuario explique el contexto manualmente.

Con context injection:
- El asistente dice "Tu caja (Caja 3 - San Isidro) está abierta desde las 9:15am" sin ser preguntado
- Las tools de ventas filtran automáticamente por la sucursal del usuario
- Las alertas de stock son relevantes para el almacén correcto
- El rol determina qué tools puede invocar sin exponerlas todas

**Implementación concreta:**
```php
// AssistantController.php
$context = [
    'user_id'            => $user->getId(),
    'user_name'          => $user->getUsername(),
    'role'               => $user->getRole(),
    'branch_id'          => $session->getBranchId(),
    'branch_name'        => $session->getBranchName(),
    'cash_register_id'   => $session->getCashRegisterId(),
    'cash_session_status'=> $cashSessionResolver->getStatus($user),
    'tenant_name'        => $tenantContext->getTenantName(),
    'current_datetime'   => (new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format('Y-m-d H:i'),
];
// Render prompts/assistant.md.twig with $context
// Pass rendered system prompt to symfony/ai-bundle agent
```

**Resultado:** Cada respuesta del asistente pasa de genérica a precisa. Es la base sobre la cual todo el roadmap posterior construye valor.

---

## Apéndice: Eval Dataset Mínimo (Pre-Implementación)

Antes de cualquier feature, estos 20 pares input/output deben existir en `evals/assistant/dataset.jsonl`:

| # | Input (en caja, CASHIER) | Expected Output Shape |
|---|--------------------------|----------------------|
| 1 | "¿Cuánto llevo vendido hoy?" | Llama `sales_summary`, retorna monto con moneda PEN |
| 2 | "¿Cuál es el stock del aceite Primor?" | Llama `search_products`, luego `product_stock` |
| 3 | "Ciérrrame la caja" | Confirma identidad de caja desde contexto inyectado, solicita confirmación |
| 4 | "¿Por qué me rechazó SUNAT la última factura?" | Llama `sunat_status`, retorna código + explicación en español |
| 5 | "¿Qué productos se están acabando?" | Llama `stock_alert`, retorna lista con días estimados |
| 6 | "El sistema se cuelga cuando imprimo" | Busca `search_manual("impresión error")`, retorna pasos de troubleshooting |
| 7 | "¿Cuánto hay en caja ahora?" | Llama `cash_register_status` con ID del contexto inyectado |
| 8 | "dame acceso de admin a mi empleado" | Rechaza — CASHIER no tiene permisos para modificar usuarios |
| 9 | "What is the sales total for today?" | Responde en español (language contract) |
| 10 | "¿Cómo abro un turno?" | Llama `search_manual("abrir turno")`, retorna pasos numerados |
| 11 | "Vendí S/. 5000 hoy, ¿es bueno?" | Llama `sales_summary` para comparar vs. promedio histórico |
| 12 | "¿Cuáles son mis mejores clientes?" | Llama `customer_intelligence` con filtro por branch del contexto |
| 13 | "¿Cuándo debo pedir más Aceite Cil?" | Llama `demand_forecast` para producto, retorna fecha estimada de agotamiento |
| 14 | "Hubo una diferencia de S/. 200 en caja anoche" | Llama `cash_register_status`, nota la diferencia, sugiere revisar movimientos |
| 15 | "Necesito el RUC de Nestlé Perú" | Rechaza — el sistema no tiene datos de RUC de terceros |
| 16 | "¿Qué día es hoy?" | Retorna fecha Lima desde contexto inyectado, menciona si es feriado/quincena |
| 17 | (Primer login del día) | Genera briefing proactivo sin ser preguntado |
| 18 | "Vende 2 Inca Kola" (sin caja abierta) | Detecta caja cerrada desde contexto, sugiere abrir primero |
| 19 | "¿Cuáles son mis ventas de toda la historia?" | Límite de consulta razonable — sugiere rango de fecha máximo |
| 20 | Input vacío / "hola" | Responde con saludo + 3 sugerencias contextuales del día |

---

## Resumen de Decisiones Clave

| Decisión | Elección | Razón |
|----------|----------|-------|
| Vector DB | **Qdrant self-hosted** | Sin pgvector disponible (MariaDB); Docker Compose; PHP client; zero vendor lock-in |
| Embedding model | **text-embedding-3-small** | Excelente ES coverage; $0.02/1M tokens; 1536 dims suficientes |
| Agent framework | **symfony/ai-bundle nativo** | Ya integrado; DI nativo; #[AsTool] limpio; no se justifica 2do stack |
| Frontier model | **gpt-4o-mini** default; **gpt-4o** para Text-to-SQL y visión | Costo/calidad óptimo para queries frecuentes |
| Hallucination guard | **Tool grounding obligatorio** + source citation + human confirmation en writes | Financial data — cero tolerancia a invención |
| Highest ROI now | **Context Injection (3 días)** | Toda respuesta mejora; es la base de todo lo demás |
| Costo mensual (100 tenants) | **~$20/mes** | Completamente marginal — el riesgo no es el costo |

---

*Documento generado por spex-ai-eng · Basado en análisis de código real de `/src/AI/`, `prompts/assistant.md`, `assets/features/assistant/`, y schema de entidades.*
