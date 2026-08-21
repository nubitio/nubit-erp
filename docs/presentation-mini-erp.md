# Guión de presentación — eFact Mini ERP

Recorrido de 20–25 minutos para un comprador. Usa un tenant dedicado sembrado con `app:demo:seed`.

## Acceso

- URL: `http://demo.localhost:8080` (el subdominio resuelve el tenant; `localhost` no activa planilla/finanzas)
- Usuario: `demo@empresa.pe`
- Contraseña: la definida al sembrar (`Demo.2026!` por defecto; cámbiala en una demo real)

## Historia que cuentas

Una comercializadora peruana opera el día a día en un solo sistema: vende en caja, emite a SUNAT, repone stock, cobra y paga, lleva planilla y deja asientos listos para PLE.

## Recorrido recomendado

1. **Login** — Mini ERP, no solo facturación: POS + SUNAT, compras/tesorería, contabilidad y planilla.
2. **Inicio** — KPIs con ventas de hoy y del mes, por cobrar / por pagar, stock crítico y top clientes. El dashboard no debe verse en ceros.
3. **Punto de venta** — Caja ya abierta. Cobra un producto (agua, lapicero o mouse) y muestra el ticket. Vuelve al dashboard para el movimiento del día.
4. **Facturación** — Comprobantes F001/B001 aceptados. Una parte a crédito alimenta CxC.
5. **Compras** — Recepción de mercadería + una orden de compra abierta.
6. **Inventario** — Stock, reorden (tinta, lapiceros e Inca Kola están bajo mínimo a propósito).
7. **Finanzas** — Cuentas por cobrar, cuentas por pagar, conciliación (cuentas BCP/Interbank), reportes, contabilidad (asientos del mes).
8. **Cotizaciones** — Borrador, enviada y aceptada.
9. **Planilla** — Seis empleados, tasas AFP y una corrida calculada / pagada.
10. **Cumplimiento** — Impuestos y exportación PLE (ventas, compras, diario/mayor si el periodo tiene asientos).

## Qué no mostrar

- Tenant de desarrollo con datos `PROD-000001` o `cliente1@test.com`.
- Pantallas vacías de planilla, compras o contabilidad: si aparecen, el seed no corrió en ese tenant.
- Barra de depuración Symfony o el badge `dev` en login.

## Cómo rearmar la demo

```bash
docker compose exec -T web php bin/console app:tenant:provision demo \
  --domain=demo.localhost --create-user --setup \
  --admin-user=admin --admin-password='Admin.3000' \
  --company-ruc=20000000001 --company-name='Demo SAC' \
  --company-address='Av. Demo 123' --branch-name='Principal'

docker compose exec -T web php bin/console app:demo:seed --tenant=demo --no-interaction
```

Si el tenant ya tiene productos o ventas, el seed se detiene a propósito. Crea otro tenant o limpia en un entorno controlado.
