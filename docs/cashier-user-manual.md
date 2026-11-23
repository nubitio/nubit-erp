# Manual del Usuario — eFact POS

**Versión:** 1.0  
**Mercado:** Perú  
**Roles:** Cajero · Supervisor · Administrador

---

## Índice

1. [Caja registradora](#1-caja-registradora)
2. [Punto de Venta (POS)](#2-punto-de-venta-pos)
3. [Registro de ventas](#3-registro-de-ventas)
4. [Anular una venta](#4-anular-una-venta)
5. [Comprobantes electrónicos (SUNAT)](#5-comprobantes-electrónicos-sunat)
6. [Notas de crédito](#6-notas-de-crédito)
7. [Comprobante rechazado por SUNAT](#7-comprobante-rechazado-por-sunat)
8. [Inventario y stock](#8-inventario-y-stock)
9. [Clientes](#9-clientes)
10. [Catálogo y precios](#10-catálogo-y-precios)
11. [Compras e ingresos](#11-compras-e-ingresos)
12. [Reportes y exportaciones](#12-reportes-y-exportaciones)
13. [Estados y códigos de referencia](#13-estados-y-códigos-de-referencia)
14. [Errores frecuentes y soluciones](#14-errores-frecuentes-y-soluciones)

---

## 1. Caja registradora

El cajero abre, mueve y cierra su caja **sin salir del POS**. La supervisión (historial de turnos, cajas, categorías y control del equipo) está en **Finanzas › Cajas y turnos** y es de los roles con permisos de supervisión.

### 1.1 Abrir una caja

1. En el POS, pulsa **Abrir caja** (barra superior).
2. Si la sucursal tiene más de una caja libre, elige cuál. Si hay una sola, el diálogo dice cuál se abrirá.
3. Ingresa el **efectivo inicial**: el dinero contado con el que empieza el turno (cero o más).
4. Confirma. El sistema registra la apertura y la caja queda en estado **Abierta**.

**Reglas del sistema:**
- Solo puede haber **un turno abierto por caja**. Si todas las cajas de la sucursal están ocupadas, el diálogo muestra quién tiene cada una y desde qué hora; pide a esa persona que cierre su turno, o a un supervisor que lo cierre desde Finanzas › Cajas y turnos.
- Las ventas se registran en **tu** turno: sin caja abierta, el POS no deja cobrar.

### 1.2 Ver las ventas del turno

Menú **⋯** del POS → **Ventas del turno**. Lista las ventas de tu turno abierto, la más reciente primero:

- Hora, número de comprobante y método(s) de pago (en un pago mixto se ve cada método con su importe).
- Total y estado del comprobante: aceptado, rechazado o pendiente de envío a SUNAT. Las ventas **anuladas** aparecen tachadas y no suman.
- El resumen de arriba cuenta las ventas válidas y su total.
- El botón de la impresora **reimprime** el comprobante.

Úsalo cuando el conteo no cuadre: busca la venta con el método equivocado, un cobro duplicado o un vuelto mal dado. Solo ves **tu** turno; el registro de ventas de toda la sucursal es de supervisión (ver sección 3).

**Ventas por método y a crédito.** Bajo el resumen ves cuánto se vendió con cada método (Efectivo, Yape, Plin, tarjeta…). Una **venta a crédito** cuenta como vendida pero **no es dinero todavía**: aparece aparte como *A crédito · por cobrar* y no está en ningún método ni en el efectivo de la caja. Cuando el cliente pague, el cobro entra en el turno en que se cobre.

### 1.3 Movimientos de caja (ingresos y egresos manuales)

Menú **⋯** → **Movimientos y reporte X**:

- **Reporte X:** corte parcial (apertura, ventas por método y a crédito, ingresos, egresos y saldo en caja). No cierra el turno. Siempre muestra los datos al momento de abrirlo. El saldo en caja solo cuenta el efectivo: Yape, tarjeta y crédito no entran al cajón.
- **Registrar un ingreso o egreso:** elige el motivo, el monto y una descripción. Requiere el permiso `cash.movement`, que el rol Cajero por defecto tiene.
  - **Ingresos:** *Reposición de caja* (sencillo que deja el supervisor) y *Otros ingresos*.
  - **Egresos:** *Gasto de caja chica* (bolsas, cinta, una propina), *Pago a proveedor* (el que cobra en efectivo en la puerta) y *Retiro de efectivo* (pasar el excedente a la caja fuerte).
  - Solo aparecen los motivos que decide una persona. Las ventas, los cobros de crédito, las devoluciones y las diferencias de cierre los registra el sistema: no se pueden elegir a mano porque se contarían dos veces. El administrador puede añadir motivos en Finanzas › Cajas y turnos › Categorías.
- Saldo en caja = apertura + ingresos − egresos + ventas en efectivo del turno.

### 1.4 Cerrar una caja

1. Menú **⋯** → **Cerrar caja**.
2. El cierre muestra las **ventas del turno por método**, con el número de operaciones y el importe de cada uno (lo vendido a crédito, aparte y como informativo), y el **cuadre de efectivo** (apertura + ventas en efectivo + ingresos − egresos = esperado).
3. **Efectivo:** cuenta el cajón (por billetes y monedas o ingresando el importe). El sistema calcula el faltante o sobrante.
4. **Pagos digitales (tarjeta, Yape, transferencia…):** no se cuentan en el cajón. Contrasta cada importe con tu terminal, aplicación o banco y márcalo como verificado.
5. Si hay diferencia, explica el motivo; es obligatorio para poder cerrar.
6. En el cierre puedes abrir **Ver ventas del turno** para buscar el origen de una diferencia, y **Guardar conteo y seguir vendiendo** si aún no terminas: el conteo queda en este equipo.
7. Confirma en el diálogo. La sesión pasa a **Cerrada**.

**Importante:** no se pueden eliminar ni modificar movimientos una vez cerrada la sesión.

---

## 2. Punto de Venta (POS)

Accede desde el menú lateral → **POS** (`/pos`). Esta es la pantalla principal de ventas rápidas.

### 2.1 Antes de vender

La caja debe estar abierta. Si está cerrada, el botón **COBRAR** aparece como **"Caja cerrada"** y está deshabilitado.

### 2.2 Buscar y agregar productos

**Búsqueda por nombre o SKU:**
- Escribe en el campo de búsqueda o presiona `F2` para enfocarlo.
- La búsqueda ignora tildes (ej. "cafe" encuentra "café").
- Filtra también por categoría usando las pestañas de categorías.

**Búsqueda por código de barras:**
- Con un lector de barras conectado: escanea directamente. El sistema detecta la secuencia de caracteres rápida (< 80 ms entre teclas) y busca el producto por código exacto.
- Si hay una sola coincidencia, agrega automáticamente al carrito.
- Si no hay coincidencia y el carrito tiene productos, intenta pasar al cobro.

**Agregar al carrito:**
- Clic en el botón `+` de la tarjeta del producto.
- Los productos con unidad de peso (kg, lb) inician con cantidad 0.1 y pueden leer el peso de una balanza conectada.

### 2.3 Gestionar el carrito

- **Cambiar cantidad:** Clic en el número de cantidad para editarlo en línea, o usa los botones `−` y `+`. Con el teclado, `F8` edita la cantidad de la última línea agregada (escribe el número y `Enter`).
- **Eliminar línea:** Reduce la cantidad a 0 con el botón `−` o usa el ícono de papelera.
- **Modificar precio (si está habilitado):** Clic en el precio de la línea (requiere permiso `ui.pos.allowLinePriceOverride`).
- **Limpiar carrito:** Tecla `Esc` → confirma en el diálogo.

**Carrito guardado automáticamente:**
- El carrito se guarda en memoria local del navegador. Si cierras accidentalmente el POS, al volver verás una notificación con la opción de restaurar el borrador.

### 2.4 Cliente

- Por defecto se usa **Cliente General** (RUC 00000000).
- Para cambiar: haz clic en **"Cambiar"** o presiona `F3`.
- Para crear un cliente nuevo sin salir de la venta: botón con el ícono de persona junto al selector (requiere permiso de crear clientes) o `Shift+F3`. Escribe el RUC/DNI y usa **Consultar** para completar el nombre. El cliente queda seleccionado en la venta.
- Busca por nombre, RUC o DNI. Se muestran hasta 8 resultados.
- El tipo de comprobante (boleta/factura) se determina automáticamente por el RUC del cliente:
  - RUC de 11 dígitos → **Factura**
  - DNI de 8 dígitos o sin documento → **Boleta**

### 2.5 Descuentos

- Selecciona un descuento global desde los botones de porcentaje: `Ninguno`, `5%`, `10%`, `15%`, `20%`.
- El descuento se aplica sobre el subtotal total del carrito.

### 2.6 Método de pago

- Selecciona el método con los botones de radio (se muestran los primeros 3; haz clic en **"Más"** para ver todos).
- **Efectivo:** se habilita el campo de monto recibido. Ingresa el billete/moneda entregada:
  - Botón **Exacto**: rellena automáticamente el total exacto.
  - Botones **+5**, **+10**, **+20**: suma ese valor al monto recibido.
  - El **Vuelto** o **Falta** se calcula en tiempo real.
  - No puedes cobrar si el monto recibido es menor al total.

### 2.7 Cobrar

1. Haz clic en **COBRAR S/ X.XX** o presiona `F9` (dentro del cobro, `F9` o `Enter` confirman).
2. El sistema envía la venta al servidor (`POST /api/sales`).
3. **Resultado en línea:** Muestra el código de la venta, el vuelto y opciones de impresión.
4. **Resultado sin conexión:** La venta se guarda en la cola local y se sincroniza automáticamente cuando hay internet.

**Comprobante electrónico automático:**
- Si la configuración `AUTO_INVOICE` está activa, el sistema genera y envía el comprobante a SUNAT automáticamente al guardar la venta.

### 2.8 Venta offline

- El POS sigue funcionando sin internet.
- Las ventas quedan en cola (`mode: queued`) en el almacén local del navegador (IndexedDB).
- Al recuperar la conexión, las ventas se sincronizan automáticamente.
- El punto junto a **Ventas en espera** indica la conexión (verde conectado, ámbar sincronizando, rojo sin conexión). Cuando hay ventas por sincronizar o con error aparece el botón **Ventas pendientes: N**, desde donde puedes reintentar las fallidas.

### 2.9 Ventas en espera

Para atender a otro cliente sin perder la venta actual: botón **En espera** del carrito o `F4`. La venta se guarda con su cliente y se vacía el carrito. Para recuperarla: **Ventas en espera** en la barra superior o `Shift+F4`. Si el carrito actual tiene productos, no se pierde: pasa a la lista.

### 2.10 Atajos de teclado

Presiona `?` (fuera de un campo de texto) para ver esta lista en pantalla.

| Tecla | Acción |
| --- | --- |
| `F2` o `/` | Enfocar el buscador de productos |
| Lector de barras + `Enter` | Agregar el producto por código |
| `F8` | Editar la cantidad de la última línea |
| `Esc` | Vaciar el carrito (con confirmación) |
| `F3` | Seleccionar cliente |
| `Shift+F3` | Crear cliente nuevo |
| `F9` o `Enter` | Cobrar; dentro del cobro, confirmar |
| `F4` | Poner la venta en espera |
| `Shift+F4` | Ver las ventas en espera |
| `?` | Mostrar la lista de atajos |
| `Ctrl/⌘+K` | Asistente de IA (si está habilitado) |

Los atajos no actúan mientras escribes en un campo de texto ni cuando hay un diálogo abierto.

---

## 3. Registro de ventas

Accede desde el menú → **Ventas** → **Registro de ventas** (`/sale`).

> **Quién lo ve:** el Registro de ventas muestra las ventas de toda la sucursal y requiere el permiso `sales.view` (Supervisor y Administrador). El rol **Cajero** por defecto no lo tiene: su menú es solo el POS, y desde allí imprime, consulta el estado y envía por correo los comprobantes de **sus propias** ventas (permiso `sales.view_own`). Anular una venta (`sales.void`) también es de supervisión.

### 3.1 Lista de ventas

Columnas visibles por defecto: Fecha, Cliente, Comprobante, Método de pago, Total, Estado.

**Acciones del toolbar:**
- **Nueva venta** (`+ Nueva venta`): abre el formulario de creación.
- **Acciones ▼** (requiere seleccionar una fila): Vista previa, Imprimir, Duplicar, Anular.

**Expandir una fila:** haz clic en `▶` para ver las líneas de la venta sin abrir el formulario completo.

### 3.2 Crear una venta

1. Haz clic en **+ Nueva venta**.
2. Completa el encabezado:

   | Campo | Obligatorio | Comportamiento |
   |---|---|---|
   | Fecha | Sí | Por defecto hoy |
   | Almacén | Sí | Se preselecciona si solo hay uno |
   | Cliente | Sí | Por defecto "Cliente General"; al elegir cliente con tarifa asignada, actualiza precios |
   | Método de pago | Sí | Por defecto el primero disponible |
   | Moneda | No | PEN por defecto; USD habilita el campo de tipo de cambio |
   | Emitir comprobante | No | Switch; activo si `AUTO_INVOICE` está configurado |
   | Nota | No | Texto libre |

3. Agrega líneas de detalle:

   | Campo | Comportamiento |
   |---|---|
   | Producto | Al seleccionar: rellena precio según tarifa, qty=1 |
   | Cantidad | Si la política es `STRICT`, valida stock disponible en el almacén elegido |
   | Precio unitario | Editable; actualiza el total de línea |
   | Descuento % | 0–100; calcula descuento en monto |
   | Total neto | Solo lectura (qty × precio − descuento) |

4. El **Total** del encabezado se actualiza automáticamente = suma de netos × (1 − descuento global %).
5. Haz clic en **Guardar**.

### 3.3 Duplicar una venta

1. Selecciona la venta en la lista.
2. Menú **Acciones ▼** → **Duplicar**.
3. Se abre el formulario con los mismos datos (cliente, líneas, método de pago). La fecha cambia a hoy.
4. Modifica lo que necesites y guarda.

---

## 4. Anular una venta

**Roles permitidos:** Administrador, Supervisor. Los cajeros no pueden anular ventas.

### 4.1 Desde la lista de ventas

1. Selecciona la venta en la lista.
2. Haz clic en **Acciones ▼** → **Anular**.
3. Ingresa el **motivo de anulación** (obligatorio, no puede estar vacío).
4. Opcionalmente marca **"Restaurar stock"** si quieres devolver las unidades al inventario.
5. Confirma.

### 4.2 Qué ocurre al anular

- El estado de la venta cambia de `COMPLETED` a `VOIDED`.
- Si el comprobante electrónico ya fue emitido y aceptado por SUNAT, la anulación de la venta **no cancela automáticamente el comprobante en SUNAT**. Para eso debes generar una **Nota de crédito** (ver sección 6).
- Si marcaste "Restaurar stock", el sistema revierte los movimientos de inventario de esa venta.

### 4.3 Ventas que no se pueden anular

- Ventas ya en estado `VOIDED`.
- El sistema retorna el error correspondiente si hay restricciones de negocio (ej. comprobante en proceso de envío a SUNAT).

---

## 5. Comprobantes electrónicos (SUNAT)

### 5.1 Generar un comprobante para una venta existente

1. En la lista de ventas, selecciona la venta (debe estar en estado `COMPLETED` y sin comprobante previo).
2. Haz clic en **Acciones ▼** → **Emitir comprobante**.
3. El sistema genera el XML UBL 2.1, firma con el certificado digital del tenant y envía a SUNAT de forma asíncrona.

**Tipos de documento generados automáticamente:**
- Cliente con RUC de 11 dígitos → **Factura (01)**
- Cliente con DNI o sin documento → **Boleta de venta (03)**

### 5.2 Ver comprobantes de una venta

1. En la lista de ventas, selecciona la venta.
2. Haz clic en **Acciones ▼** → **Ver comprobantes**.
3. Se muestra la lista de documentos tributarios asociados (puede haber más de uno si hubo reemplazos).

### 5.3 Módulo de comprobantes (`/invoice`)

Muestra todos los comprobantes del tenant. Columnas:
- Fecha de emisión, RUC, Cliente, Tipo (01/03), Serie, Número, Total
- **Estado SUNAT:** Pendiente / Aceptado / Rechazado / Anulado
- Mensaje SUNAT (descripción del resultado)
- Datos de detracción (código y monto, si aplica)

**Acciones por fila:**
- **Enviar a SUNAT:** envía el XML firmado al servicio web de SUNAT.
- **Consultar CDR:** consulta el estado actual del CDR en SUNAT.
- **Imprimir / Vista previa.**
- **Enviar por correo** (si está configurado).

### 5.4 Ciclo de vida de un comprobante

```
Generado (XML firmado)
  → Enviado a SUNAT (async)
    → ACEPTADO (CDR confirmado)
    → RECHAZADO (error SUNAT)
      → [Corregir y reemplazar] → nuevo comprobante PENDIENTE
```

### 5.5 Detracción

Para ventas de alto valor que requieren detracción:
- Los campos de detracción (código, porcentaje, monto, medio de pago, valor referencial) se calculan automáticamente al generar el comprobante.
- Se muestran en la pestaña **Detracción** del formulario del comprobante.

---

## 6. Notas de crédito

Usa las notas de crédito para anular o corregir comprobantes **ya aceptados por SUNAT**.

### 6.1 Crear una nota de crédito

1. Navega a **Contabilidad** → **Notas de crédito** (`/credit-note`).
2. Haz clic en **Nueva nota de crédito**.
3. Selecciona el **comprobante relacionado** (la factura o boleta que quieres anular/corregir).
4. Elige el **motivo** (códigos SUNAT estándar, ej. "Anulación de la operación").
5. Agrega una **descripción del motivo**.
6. Guarda.

### 6.2 Enviar la nota de crédito a SUNAT

1. Selecciona la nota en la lista.
2. **Acciones ▼** → **Enviar a SUNAT**.
3. Puedes también **Consultar CDR** para verificar el estado de aceptación.

---

## 7. Comprobante rechazado por SUNAT

Cuando SUNAT rechaza un comprobante (estado `RECHAZADO`), puedes reemplazarlo:

1. En la lista de ventas, selecciona la venta con comprobante rechazado.
2. Haz clic en **Acciones ▼** → **Reemplazar rechazado**.
3. El sistema crea un nuevo comprobante corregido para la misma venta y retorna su ID.
4. El nuevo comprobante queda en estado `PENDIENTE` listo para reenviar a SUNAT.

**Errores SUNAT comunes:**

| Código | Descripción | Acción sugerida |
|---|---|---|
| 0109 | RUC del emisor no existe en SUNAT | Verifica el RUC del tenant en configuración |
| 0111 | RUC del emisor no está activo | Activa el RUC en SUNAT |
| 0112 | RUC del emisor está de baja | Contacta a tu contador |
| 0156 | Comprobante ya registrado anteriormente | El comprobante ya existe en SUNAT; no reenviar |
| 0160 | Serie no corresponde al tipo de comprobante | Verifica series en Configuración → Series de comprobante |
| 2075 | Monto de IGV incorrecto | Revisa los cálculos de los ítems |
| 2800 / 2801 | Error en firma digital / Certificado vencido | Renueva el certificado digital en Configuración → Certificado |
| 3000 | Comprobante rechazado por SUNAT | Revisa el mensaje específico y usa "Reemplazar rechazado" |

---

## 8. Inventario y stock

### 8.1 Políticas de stock negativo

El comportamiento al vender sin stock suficiente depende de la configuración del tenant:

| Política | Comportamiento |
|---|---|
| `STRICT` | Bloquea la venta si el stock es ≤ 0. Error: "Stock insuficiente". |
| `WARNING_ONLY` | Permite la venta pero muestra una advertencia. |
| `ALLOW_NEGATIVE` | Permite la venta sin restricciones ni avisos. |

### 8.2 Ver stock de un producto

- **Desde el asistente:** pregunta "¿Cuánto stock tiene el producto X?" — el asistente usa la herramienta `product_stock` y devuelve el stock por almacén.
- **Desde el catálogo:** navega al producto y revisa la pestaña de stock.

### 8.3 Alertas de stock mínimo

- Si un producto tiene `stock_mínimo` configurado y el stock actual es igual o menor, aparece en las alertas de stock.
- El asistente puede consultarlas con `stock_alert`, que también calcula los **días estimados hasta agotamiento** según la velocidad de venta de los últimos 30 días.

### 8.4 Transferencias entre almacenes

Solo disponible si el tenant tiene habilitada la capacidad `inventory.multi_warehouse`. En modo **Tienda única**, el selector de almacén está oculto.

### 8.5 Ingresos de mercadería

Navega a **Compras** → **Ingresos** (`/inbound` o `/purchases`):
1. Selecciona el proveedor y el almacén de destino.
2. Agrega las líneas de productos con cantidades y precios de compra.
3. El total se calcula automáticamente.
4. Guarda para registrar el ingreso y actualizar el stock.

---

## 9. Clientes

### 9.1 Consulta automática de RUC y DNI

Al ingresar el número de RUC o DNI en el campo de cliente, el sistema consulta automáticamente:
- **RUC (11 dígitos):** base de datos SUNAT → devuelve razón social y dirección fiscal.
- **DNI (8 dígitos):** RENIEC → devuelve nombre completo.

Rutas de consulta: `GET /api/consults/ruc/{id}` y `GET /api/consults/dni/{id}`.

### 9.2 Cliente general

El sistema tiene un **cliente general** (RUC 00000000) configurado como cliente anónimo por defecto. Se usa cuando el comprador no entrega su documento de identidad.

### 9.3 Tarifas por cliente

Cada cliente puede tener una **lista de precios (tarifa)** asignada. Al seleccionar ese cliente en una venta, los precios de los productos se actualizan automáticamente según esa tarifa.

---

## 10. Catálogo y precios

### 10.1 Tipos de producto

| Tipo | Descripción |
|---|---|
| `GOOD` | Bien físico (puede llevar control de inventario) |
| `SERVICE` | Servicio (no descuenta stock) |

### 10.2 Comportamiento de inventario

| Valor | Descripción |
|---|---|
| `TRACKED` | Producto con control de stock (descuenta al vender) |
| `NOT_TRACKED` | Sin control de stock (servicios, productos exentos) |
| `COMPOSITE` | Receta/compuesto (explota en sus componentes al vender) |

### 10.3 Listas de precios (tarifas)

- Permite tener diferentes precios por segmento de cliente (mayorista, minorista, etc.).
- Cada lista aplica un **multiplicador** sobre el precio base del producto.

### 10.4 Series de comprobantes

- Cada sucursal tiene series configuradas para cada tipo de comprobante:
  - `F001`, `F002`, ... → Facturas (tipo 01)
  - `B001`, `B002`, ... → Boletas (tipo 03)
- El número correlativo se asigna automáticamente y de forma secuencial con bloqueo de concurrencia para evitar duplicados.

---

## 11. Compras e ingresos

### 11.1 Registrar una compra a proveedor

1. Navega a **Compras** (`/purchases` o `/inbound`).
2. Clic en **Nueva compra**.
3. Campos del encabezado:

   | Campo | Obligatorio |
   |---|---|
   | Fecha | Sí |
   | Almacén | Sí |
   | Proveedor | Sí |
   | Total | Calculado automáticamente |

4. Agrega líneas de producto con cantidad y precio unitario.
5. Guarda. El stock del almacén seleccionado se incrementa.

---

## 12. Reportes y exportaciones

### 12.1 Acceso

Navega a **Ventas** → **Consulta de ventas** (`/sale-consult`).

Requiere la función `excel_export` habilitada en el plan del tenant.

### 12.2 Tipos de reporte disponibles

| Reporte | Contenido |
|---|---|
| `detailed-sales` | Ventas con detalle de ítems |
| `sales-by-line` | Ventas agrupadas por línea |
| `sale-by-product` | Ventas agrupadas por producto |
| `accounting-report` | Reporte contable |
| `payment-method-daily` | Ventas por método de pago y día |
| `daily-summary` | Resumen diario de ventas |
| `voided-sales` | Ventas anuladas |
| `credit-notes` | Notas de crédito emitidas |

### 12.3 Filtros de reporte

- **Rango de fechas:** `Desde` y `Hasta` en formato DD/MM/AAAA.
- **Filtros de grilla:** los filtros activos en la grilla se incluyen automáticamente en el reporte.
- El reporte se descarga como archivo `.xlsx`.

---

## 13. Estados y códigos de referencia

### Estados de venta

| Estado | Descripción |
|---|---|
| `COMPLETED` | Venta registrada y activa |
| `VOIDED` | Venta anulada |

### Estados de comprobante SUNAT

| Estado | Descripción |
|---|---|
| `PENDIENTE` | Generado, pendiente de envío a SUNAT |
| `ENVIADO` | Enviado, esperando respuesta |
| `ACEPTADO` | Aceptado por SUNAT con CDR positivo |
| `RECHAZADO` | Rechazado por SUNAT (ver código de error) |
| `ANULADO` | Anulado mediante baja o nota de crédito |

### Tipos de documento SUNAT

| Código | Tipo |
|---|---|
| `01` | Factura electrónica |
| `03` | Boleta de venta electrónica |
| `07` | Nota de crédito electrónica |
| `08` | Nota de débito electrónica |

### Tipos de movimiento de caja

| Tipo | Descripción |
|---|---|
| `OPENING` | Saldo inicial al abrir el turno |
| `CASH_IN` | Ingreso manual (no venta) |
| `CASH_OUT` | Egreso manual |
| `SALE_IN` | Ingreso por venta en efectivo |

---

## 14. Errores frecuentes y soluciones

### "La caja debe estar abierta"
La venta o el POS requieren una sesión de caja activa. Ve a **Caja** (`/cashbox`) y abre la caja antes de vender.

### "Esta caja ya tiene un turno abierto"
Solo puede haber una sesión abierta por caja. Busca la sesión activa en el panel de cajas.

### "Debe indicar el motivo de anulación"
Al anular una venta, el campo **Motivo** es obligatorio. No puede estar vacío.

### "Stock insuficiente" (política STRICT)
El almacén seleccionado no tiene suficiente stock del producto. Opciones:
- Reduce la cantidad solicitada.
- Cambia al almacén correcto.
- Registra un ingreso de mercadería primero.

### "El comprobante ya fue registrado anteriormente" (SUNAT 0156)
SUNAT ya tiene ese número de serie y correlativo. No reenviar. Consulta el CDR desde el módulo de comprobantes.

### "El número de serie no corresponde al tipo de comprobante" (SUNAT 0160)
La serie `F001` solo aplica a facturas (tipo 01) y `B001` solo a boletas (tipo 03). Verifica la configuración de series en **Configuración** → **Series de comprobantes**.

### "Certificado digital vencido o no válido" (SUNAT 2801)
El certificado digital del tenant ha vencido. Carga un nuevo certificado en **Configuración** → **Certificado SUNAT**.

### "No se pudo obtener respuesta del asistente"
Error de red o timeout. Intenta de nuevo. Si persiste, verifica la conectividad a internet.

### El POS muestra ventas pendientes de sincronización
Hay ventas en la cola offline. Cuando se restaure la conexión a internet, se sincronizarán automáticamente. No cierres el navegador mientras hay ventas pendientes.

### "Cliente no encontrado" (herramienta del asistente)
El RUC, DNI o ID ingresado no existe en la base de datos de clientes del tenant. Verifica el número o crea el cliente desde **Clientes**.
