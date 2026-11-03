# Recordatorios de cobro — propuesta de contrato (borrador)

Estado: **implementado (V1 manual).** Referencia de diseño: Figma `80:2634`
(«Recordatorio de pago (WhatsApp)»). Complementa el plan manual de
[recurring-billing-plan.md](../recurring-billing-plan.md) («V1: recordatorios manuales»).

## Qué existe hoy

| Pieza | Estado |
| --- | --- |
| Teléfono y correo del cliente | `Customer.phone`, `Customer.email` |
| Deuda por cliente | `GET /sales/reports/receivable-customers` (incluye `customerPhone`) |
| Envío de correo | `MailerInterface` + Twig, usado por `InvoiceEmailUseCase` |
| Enlace público del comprobante | `publicReceiptUrl` |
| Integración con WhatsApp | **No existe** |
| Registro de recordatorios enviados | **No existe** |
| Persona de contacto de cobranza | **No existe** («Rosa Quispe · compras» en Figma) |

## Alcance propuesto para V1 (solo manual)

1. **Redactar** el mensaje en el servidor a partir de una plantilla por tono.
2. **WhatsApp «Desde mi WhatsApp»:** el cliente abre `https://wa.me/<tel>?text=…` con el
   mensaje ya escrito. No hay API de WhatsApp Business ni credenciales.
3. **Correo:** el servidor lo envía con el correo existente.
4. **Registrar** cada recordatorio para mostrar «último recordatorio» y evitar repetirlos.

Fuera de V1: recordatorios automáticos (3 días antes, el día, cada 7 días), WhatsApp
Business (plan Pro), plantillas editables («Guardar como plantilla»), edición del mensaje
con vista previa persistente.

## Contrato

### `POST /api/receivables/reminders/preview`

Sin efectos. Devuelve el mensaje y el destinatario resueltos.

```json
// request
{ "saleId": "01a18c84-…", "channel": "whatsapp", "tone": "direct" }

// response
{
  "recipient": { "name": "Cliente CI E2E", "phone": "+51954321678", "email": null,
                 "source": "customer" },
  "subject": null,
  "body": "Hola, le escribimos de Compu Express. La factura F001-00000262 por S/ 845.00 venció el 05/09/2026 y sigue pendiente. ¿Nos confirma la fecha de pago? Puede ver la factura aquí: …",
  "documentUrl": "https://…/receipt/…",
  "daysOverdue": 43,
  "lastReminder": { "channel": "whatsapp", "createdAt": "2026-10-20T15:02:00-05:00" }
}
```

- `channel`: `whatsapp` | `email`. `tone`: `friendly` | `direct` | `final`.
- Errores: `409` si la venta no tiene saldo pendiente; `422` si falta el dato del canal
  (teléfono o correo) con un mensaje que lo diga.

### `POST /api/receivables/reminders`

Registra el recordatorio. Para `email` además lo envía.

```json
{ "saleId": "01a18c84-…", "channel": "email", "tone": "friendly", "body": "…" }
```

- `whatsapp` → se guarda como `prepared` (no se puede saber si el cajero lo envió).
- `email` → `sent` o `failed` con el motivo del transporte.
- `body` es opcional; si falta se usa la plantilla. Se valida longitud máxima.
- Limitación: un recordatorio por comprobante y canal cada 24 h; `409` con la fecha del
  último. Un parámetro `force` explícito la omite.

### `GET /api/receivables/reminders?saleId=…|customerId=…`

Historial paginado, más reciente primero.

## Modelo de datos

`collection_reminder`: `id`, `sale_record_id`, `customer_id`, `channel`, `tone`,
`status` (`prepared` | `sent` | `failed`), `body`, `recipient_phone`, `recipient_email`,
`failure_reason`, `created_by_user_id`, `created_at`. Índice por (`sale_record_id`,
`channel`, `created_at`). Migración nueva de Doctrine.

Opcional, si se quiere la «persona de contacto»: `Customer.collectionContactName`
(texto, nullable). Sin ella el mensaje usa la razón social.

## Permisos

Nuevo permiso `receivables.remind`, o reutilizar `sales.collect`. Cobrar y recordar suelen
coincidir; separar permite que un asistente recuerde sin poder registrar cobros.

## Riesgos

- **Datos personales:** el registro guarda teléfono, correo y texto del mensaje. Definir
  retención y que solo lo vea quien tiene el permiso.
- **WhatsApp «preparado» ≠ «enviado»:** la interfaz debe decir «preparado», no «enviado».
- **Correo masivo:** sin límite por usuario, un error de interfaz podría enviar muchos.
  La regla de 24 h por comprobante lo acota; falta decidir un tope diario por usuario.

## Decisiones tomadas

1. **Correo incluido en V1**, además de WhatsApp manual.
2. **Permiso nuevo `receivables.remind`.** La migración lo concede solo al rol Administrador
   (rol 2); los tenants nuevos lo reciben por el catálogo. Los cajeros no lo tienen.
3. **Persona de contacto opcional** en el cliente (`collectionContactName`, «Contacto de
   cobranza» en el formulario). Sin ella se usa la razón social.
4. **Un recordatorio por comprobante y canal cada 24 h**, con `force: true` para omitirla.
5. **Retención de 12 meses:** `app:receivables:purge-reminders`, programado a diario a las
   03:30 (America/Lima).

## Detalles de implementación

- Las horas que ve el usuario en los mensajes del servidor se escriben en America/Lima.
- La fecha de vencimiento se lee como fecha de calendario, sin convertir de zona horaria.
- El tono amable tutea; los tonos directo y último aviso tratan de «usted».
- La respuesta incluye `whatsappUrl` ya armado (`wa.me/<número>?text=…`).
- Interfaz: botón «Recordar» en cada comprobante con saldo del cajón de Cuentas por cobrar,
  visible solo con el permiso.

## Fuera de V1 (sin cambios)

Recordatorios automáticos, WhatsApp Business, plantillas editables y tope diario por usuario.

