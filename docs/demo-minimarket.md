# Demo: minimarket de barrio

Un tenant de demostración para pequeños negocios. La historia está hecha para responder, en pantalla,
lo que un dueño se pregunta: cuánto vendo, cuánto gano, qué debo reponer, qué no se mueve, quién me
debe y si alguien de mi equipo merece una conversación.

No es aleatorio: la misma historia se genera igual cada noche (`MinimarketStory` es determinista).
Todo se calcula respecto a «hoy», así que el demo nunca envejece.

## Cómo levantarlo

```bash
# Tenant nuevo (el RUC 20000000001 apunta a SUNAT beta)
docker compose exec -T web php bin/console app:tenant:provision minimarket \
  --domain=minimarket.localhost --create-user --setup \
  --admin-user=admin --admin-password='Admin.3000' \
  --company-ruc=20000000001 --company-name='Minimarket Demo' \
  --company-address='Av. Demo 123' --branch-name='Principal' \
  --provisioning-url='mysql://root:<clave>@database:3306/?serverVersion=10.7.1-MariaDB&charset=utf8mb4'

# Datos (≈ 50 s)
docker compose exec -T web php bin/console app:demo:seed --tenant=minimarket --story=minimarket --no-interaction
```

Para **resetearlo**: `app:tenant:wipe --tenant=minimarket --force --wipe-catalog` y volver a sembrar.
El seed es atómico (una sola transacción): si falla, no deja un negocio a medias. Se niega a correr
sobre un tenant que ya tiene datos.

> En desarrollo, el usuario de base de datos del tenant debe usar la misma contraseña que `DATABASE_URL`
> (`TENANT_DB_PASS` vacío en `.env` deja el usuario sin contraseña y las migraciones fallan con
> «Access denied»). Si pasa: `ALTER USER '<tenant>'@'%' IDENTIFIED BY '<clave>'` y repetir el aprovisionamiento,
> que es idempotente.

## Accesos

| Quién | Usuario | Rol | Ve «Control» |
|---|---|---|---|
| Dueño | `demo@empresa.pe` | Super Administrador | Sí |
| Cajera (turno mañana) | `maria.cajera` | Cajero | No |
| Cajero (turno tarde) | `luis.cajero` | Cajero | No |

Contraseña: la de `--password` (por defecto `Demo.2026!`).

## La historia (45 días)

- **Ventas**: ~2.250 en 89 turnos de caja (≈ S/ 1.000 al día), boletas casi todas, y facturas a dos negocios.
  La caja de María de **hoy está abierta**, lista para vender en el POS. Luis no ha entrado todavía.
- **El negocio crece**: los últimos días venden ~20 % más que los primeros, y la mañana de hoy se genera
  un poco por encima del día anterior completo, para que el Inicio diga «▲ vs ayer» y «▲ vs mes ant.»
  y no abra con una flecha roja.
- **María es la referencia**: casi no anula, descuenta poco, su caja cierra dentro de monedas.
- **Luis cambia en las últimas tres semanas**: anula ~12 % de sus ventas, da descuentos de 10–15 % en
  ~40 % de ellas, cierra con faltante siete veces (S/ 9 a S/ 28) y retira efectivo con motivos vagos
  («Varios», «Gasto»). Nada lo llama ladrón: es el patrón que Control de caja muestra para conversar.
  Antes de esas tres semanas ambos están en orden (lo comprueba un test).
- **Stock**: seis productos bajo mínimo (leche, aceite, Inca Kola, huevos, queso, shampoo), tres que no
  se mueven (mermelada, esponjas, té) y el pan como producto de mayor volumen.
- **Por cobrar** (S/ 651): Carlos Mendoza debe S/ 234 vencidos hace 8 a 23 días; Rosa y el restaurante
  El Buen Sabor están al día.
- **SUNAT**: comprobantes aceptados, una factura rechazada (RUC del receptor inexistente) y la última
  venta de hoy pendiente de confirmación. Cuatro notas de crédito (tres de Luis, una de María).
- **Compras y gastos**: tres compras de reposición (dos por pagar) y el alquiler y la luz de los dos
  últimos meses, para que «cuánto gano» muestre utilidad neta además de bruta (~S/ 1.500 en noviembre).

## Dónde está el código

- `src/Setup/Demo/Minimarket/` — historia (`MinimarketStory` y colaboradores, sin base de datos) y
  escritura (`MinimarketDemoSeeder` y colaboradores).
- `src/Setup/Command/SeedPresentationDemoCommand.php` — opción `--story`.
- `tests/Setup/Demo/Minimarket/` — la historia es estable, nadie vende fuera de su turno, los productos
  muertos nunca se venden, y **Control de caja marca a Luis y deja en paz a María** en 7, 21 y 30 días.

Para cambiar la historia, edita las constantes de `ShiftEvents` (días de faltantes, retiros, notas de
crédito) y `BasketFactory` (probabilidades). Los tests dicen si Control de caja deja de contarla.

## Plan del demo

Todos los planes muestran la navegación completa del día a día (configuración del catálogo, movimientos de
inventario, notas de crédito, Administración): ya no existe un nivel «tienda simple». El plan solo decide
a qué da derecho: `app:demo:seed --story=minimarket` pasa el tenant a `basic` (`MinimarketPlan`, que usa el
mismo `TenantService::changePlan` del panel de control e invalida las cachés) porque incluye exportación a
Excel y reportes avanzados, y no activa lo que una sola tienda no compra (varios almacenes, lotes, API).
Los ajustes del negocio (empresa, series, impresión) están en el menú del avatar → Ajustes.

## Menú del demo

El menú de cada rol es el conjunto de filas de `menu_rol`, y un tenant nuevo hereda todo el del ERP.
`MinimarketMenu` quita lo que esta historia no usa: Restaurante, Cotizaciones, Cumplimiento (impuestos y
PLE), Suscripciones, Planilla y, dentro de Finanzas, tesorería, conciliación, gastos de personal, activos
fijos y contabilidad. El dueño ve: Inicio, Punto de venta, Caja, Facturación, Clientes, Catálogo, Compras
y Finanzas (cuentas por pagar y por cobrar, resultado). Para mostrar otro módulo, quita su id de
`MinimarketMenu::HIDDEN`; un test verifica que los ids existan y que no queden hijos huérfanos.
Ojo: `app:menu:sync` (despliegue) vuelve a dar todos los ítems a todos los roles; después de ejecutarlo
hay que resetear el demo.

## Revisado en el navegador

Inicio, Control de caja (escritorio, móvil y detalle por persona), Punto de venta (caja abierta de
María), Ventas, Cuentas por cobrar, Reorden, Cajas y Resultado del negocio, con el dueño; y la cajera
sin la pestaña Control. Sin errores de consola ni respuestas 4xx. El Inicio cachea 2 minutos: justo
después de un reset puede mostrar el estado anterior hasta que expire.

## Limitaciones conocidas

- **Kardex vacío.** Las ventas ajustan el stock actual, pero no se escriben movimientos de inventario
  (`stock_movements`). Hay que decidir cómo representar el signo de la cantidad antes de sembrarlos.
- **Las ventas de «hoy» llevan horas del turno completo** (7:00–14:00) aunque el reset ocurra de
  madrugada. El dashboard no distingue la hora; si se quisiera «en vivo», haría falta un segundo
  proceso que reparta las ventas durante el día.
- **Clientes ocasionales aparecen como «Cliente»** en Top clientes (así lo muestra la aplicación para
  boletas sin cliente) y encabezan la lista.
- Los comprobantes no tienen líneas en `tax_document_line`: se imprimen desde los ítems de la venta,
  como en el demo anterior.
- El reset diario programado, el flag `is_demo` y el registro de prospectos siguen pendientes
  (ver el plan del demo).
