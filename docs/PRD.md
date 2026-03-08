---

# PRD — eFact SaaS

**Versión:** v1.0
**Autor:** Johan Guerreros
**Producto:** eFact POS + Facturación Electrónica
**Tipo:** Multi-tenant SaaS
**Mercado objetivo:** Perú

---

# 1. Visión del Producto

**eFact** es una plataforma SaaS multi-tenant que permite a pequeñas y medianas empresas en Perú gestionar:

* Punto de venta (POS)
* Facturación electrónica SUNAT
* Inventario
* Control de caja
* Reportes comerciales

El objetivo es ofrecer una **alternativa moderna, simple y económica** a los sistemas POS tradicionales, con soporte offline y sincronización automática.

---

# 2. Problema

Las pequeñas empresas en Perú enfrentan varios problemas:

### Sistemas actuales

* POS antiguos instalables
* Interfaces complejas
* Costos elevados
* Mala integración con SUNAT
* Sin modo offline

### Problemas operativos

Negocios como:

* restaurantes
* minimarkets
* farmacias
* tiendas retail

necesitan:

* vender rápido
* emitir comprobantes SUNAT
* controlar inventario
* ver reportes simples

pero los sistemas existentes son:

* lentos
* caros
* difíciles de usar
* dependientes de internet

---

# 3. Objetivo del Producto

Crear una plataforma SaaS que permita a un negocio:

* empezar a vender en **menos de 30 minutos**
* operar **incluso sin internet**
* emitir **facturación electrónica SUNAT**
* gestionar inventario y caja
* escalar a múltiples sucursales

---

# 4. Usuarios

## 1 — Dueño del negocio

Perfil:

* dueño de restaurante
* dueño de minimarket
* pequeño comercio

Necesita:

* ver ventas
* ver utilidades
* controlar inventario
* revisar reportes

---

## 2 — Cajero / vendedor

Necesita:

* vender rápido
* buscar productos
* cobrar
* emitir boletas/facturas

---

## 3 — Administrador

Necesita:

* gestionar productos
* controlar inventario
* abrir/cerrar caja
* revisar reportes

---

# 5. Propuesta de Valor

eFact ofrece:

### 1 — POS rápido

* optimizado para tablets Android
* búsqueda rápida de productos
* interfaz simple

### 2 — Offline First

El sistema funciona sin internet:

* ventas se guardan localmente
* sincronización automática cuando vuelve internet

---

### 3 — Facturación electrónica SUNAT

Soporte para:

* Boleta electrónica
* Factura electrónica
* Nota de crédito
* Nota de débito
* Resumen diario
* Comunicación de baja

---

### 4 — Inventario integrado

Control de:

* stock por almacén
* movimientos de inventario
* compras
* ajustes

---

### 5 — Multi-tenant SaaS

Cada empresa tiene:

* su propio tenant
* datos aislados
* configuración independiente

---

# 6. Funcionalidades Principales

## 6.1 Autenticación

Usuarios pueden:

* iniciar sesión
* cambiar contraseña
* gestionar sesiones

Roles:

* Admin
* Cajero
* Supervisor

---

# 6.2 Punto de Venta

Funciones principales:

* buscar producto
* agregar producto
* modificar cantidad
* aplicar descuentos
* seleccionar cliente
* elegir método de pago

Métodos de pago:

* efectivo
* tarjeta
* mixto

---

# 6.3 Gestión de Caja

Flujos:

Abrir caja

```
usuario → abrir caja
ingresar monto inicial
estado = abierta
```

Cerrar caja

```
calcular ventas
comparar efectivo
generar reporte de cierre
```

---

# 6.4 Productos

Gestión de:

* nombre
* SKU
* código de barras
* precio
* costo
* impuestos

Tipos de producto:

* stockable
* servicio

---

# 6.5 Inventario

Operaciones:

* compra de productos
* ajuste de inventario
* transferencia entre almacenes
* consumo por venta

Modelo técnico:

```
stock_movements (ledger)
stock_balances (materialized)
```

---

# 6.6 Facturación electrónica

Documentos soportados:

| Tipo         | SUNAT |
| ------------ | ----- |
| Boleta       | 03    |
| Factura      | 01    |
| Nota crédito | 07    |
| Nota débito  | 08    |

Flujo:

```
venta
→ generar XML UBL
→ firmar con certificado
→ enviar a SUNAT
→ recibir CDR
```

Estados:

```
PENDING
ACCEPTED
REJECTED
```

---

# 6.7 Clientes

Gestión de:

* nombre
* DNI
* RUC
* dirección

Consulta automática de RUC.

---

# 6.8 Reportes

Reportes principales:

Ventas:

* por día
* por producto
* por vendedor

Inventario:

* stock actual
* movimientos

Caja:

* apertura
* cierre
* diferencias

---

# 7. Arquitectura

## Backend

Tecnologías:

* PHP 8
* Symfony
* API Platform
* MariaDB

Arquitectura:

```
Multi-tenant
DB per tenant
```

---

## Frontend Web

Tecnologías:

* React
* Vite
* PWA

---

## Android POS

Tecnologías:

* Kotlin
* Jetpack Compose
* Clean Architecture
* Offline first

---

# 8. Arquitectura Offline

Cliente POS:

```
local DB
→ ventas locales
→ sync queue
→ servidor
```

Servidor:

```
API
→ procesar sync
→ reconciliar inventario
```

---

# 9. Seguridad

Medidas:

* autenticación JWT
* HTTPS
* certificados digitales SUNAT
* auditoría de eventos

---

# 10. Pricing

Ejemplo de planes:

| Plan     | Precio   |
| -------- | -------- |
| Free     | limitado |
| Basic    | S/29     |
| Business | S/69     |
| Pro      | S/129    |

---

# 11. Métricas de éxito

Objetivo inicial:

Primer año:

* 50 clientes activos

KPIs:

* MRR
* número de ventas procesadas
* uptime
* retención mensual

---

# 12. Roadmap

### Fase 1

MVP

* POS
* inventario
* boletas
* reportes básicos

---

### Fase 2

* facturas
* notas de crédito
* multi-almacén
* clientes

---

### Fase 3

* vertical restaurantes
* integraciones pagos
* dashboard analítico

---

# 13. Riesgos

Principales riesgos:

SUNAT cambios regulatorios

Mitigación:

* arquitectura desacoplada de tax engine

---

Problemas de conectividad

Mitigación:

* offline first

---

# 14. Diferenciadores

eFact se diferencia por:

* offline first
* Android POS optimizado
* SaaS multi-tenant
* integración SUNAT nativa
* costo bajo

---

# 15. Integraciones Futuras

Posibles integraciones:

* pasarelas de pago
* SUNAT APIs
* contabilidad
* ecommerce

---

# 16. Alcance Inicial

Vertical inicial recomendada:

**restaurantes pequeños**

porque:

* alto volumen
* necesidad de POS rápido
* inventario simple

---
