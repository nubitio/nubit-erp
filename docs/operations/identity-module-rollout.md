# Módulo de identidad: invitaciones, recuperación de contraseña y 2FA

Activa las funciones de identidad de `nubitio/admin-bundle`:

- **Invitaciones por correo**: un administrador invita con un correo y un rol; la persona crea su propia contraseña.
- **Olvidé mi contraseña** (por usuario o correo), con enlace de un solo uso.
- **Verificación en dos pasos (TOTP)**, opcional por usuario, con 10 códigos de recuperación.

**Apagado por defecto.** Con `IDENTITY_MODULE_ENABLED` ausente o en `0` el ERP se comporta exactamente como antes.

## Qué NO hace (a propósito)

- No obliga el 2FA a nadie: `required_for_all=false` y sin roles obligatorios. Cada usuario decide.
- No toca la contraseña ni el flujo de login de quien no activó el 2FA.
- Un usuario con una activación **sin confirmar** (cerró el QR a medias) entra igual que siempre.

## Antes de encender: orden obligatorio

Con el módulo encendido, **cada login consulta `nubit_totp_credential`**. Si esa tabla falta en la base de un inquilino, todos los logins de ese inquilino fallan. Por eso:

1. **Desplegar con el módulo apagado.** La migración `Version20261107020000_identity_module_tables` solo crea tablas (aditiva; nada las lee todavía). Lo mismo `Version20261107000000_user_email_last_login` (`usuario.email`, `usuario.last_login_at`).
2. **Migrar todos los inquilinos**: `php bin/console app:tenants:migrate`.
3. **Comprobar que todos están listos** (sale con código 1 si alguno falta, sirve como compuerta):

   ```bash
   php bin/console app:identity:preflight
   # READY tenant "acme" (users with a second factor: 0)
   # NOT READY — tenant "x" is missing: nubit_totp_credential. Run app:tenants:migrate first.
   ```

4. **Confirmar que hay un worker de Messenger** consumiendo `async`: los correos (invitación y recuperación) salen por esa cola, como el resto de correos del ERP. Sin worker, el enlace nunca llega.
5. **Encender**: poner `IDENTITY_MODULE_ENABLED=1` en el entorno del servicio **web** y reiniciarlo. La caché del contenedor se reconstruye al arrancar (`docker/entrypoint.sh`). No basta con cambiar el valor sin reiniciar.
6. **Verificar** con un inquilino de prueba: iniciar sesión sin 2FA; abrir *Menú de usuario → Verificación en dos pasos*; invitar a un correo propio.

> El flag se lee al compilar el contenedor y **no** acepta `%env()%` en YAML (el bundle lo castearía a verdadero aunque valga `0`). Por eso vive en `config/packages/nubit_admin_identity.php`.

## Si algo sale mal: apagar

Poner `IDENTITY_MODULE_ENABLED=0` (o quitarla) y reiniciar `web`. Efecto inmediato en el login.

Ten presente: con el módulo apagado, **quien ya activó 2FA entra solo con contraseña** (el segundo factor deja de exigirse). Es seguro para el acceso, pero degrada la protección de esas cuentas hasta volver a encenderlo. Las tablas y los datos quedan intactos.

## Un usuario perdió el teléfono y los códigos

La contraseña no se toca ni se evita; solo se quita el segundo factor para que la persona pueda entrar y activarlo de nuevo.

- **Administrador (UI/API)**: `DELETE /api/users/{id}/totp` (permiso `users.edit`; solo un super administrador puede restablecer a otro).
- **Operaciones (sin nadie que pueda entrar)**, sobre **un** inquilino:

  ```bash
  php bin/console app:identity:totp-reset USUARIO --tenant=acme
  ```

  Queda registrado en el log (`Second factor removed by an operator`).

## Datos que conviene saber

| Tema | Detalle |
|---|---|
| Límite de «olvidé mi contraseña» | 5 solicitudes por IP y por identidad cada 15 min; lo que excede se descarta **en silencio** (a propósito: no revela si la cuenta existe). |
| Vigencia | Invitación 7 días; enlace de restablecer 30 min; ambos de un solo uso y guardados solo como hash. |
| Contraseña nueva | Mínimo 8 caracteres (invitación y restablecer). El cambio de contraseña desde el menú sigue pidiendo 6. |
| Cuota de usuarios | Se aplica cuando la persona **acepta** la invitación (al crearse el usuario), no al invitar. |
| Roles invitables | Cualquier rol salvo Super Administrador desde la UI. El API exige ser super administrador para ese rol. |
| Códigos de 2FA | De un solo uso: un código ya usado se rechaza aunque siga dentro de su ventana de 30 s. |
| Sin QR | La activación muestra la clave para escribirla en la app (Google Authenticator, Authy, 1Password). Un QR requiere añadir una librería. |
| Usuarios sin correo | Pueden entrar con usuario y contraseña, pero no usar «olvidé mi contraseña» (se les restablece desde la administración). |

## Verificación automática

`e2e/identity-module.spec.ts` (19 casos: login sin cambios, invitaciones, 2FA con reutilización y códigos de recuperación, desactivación, restablecimiento por administrador, recuperación de contraseña, y las pantallas en el navegador). Requiere el módulo encendido en servidor y ejecutor, y un receptor de correo:

```bash
IDENTITY_MODULE_ENABLED=1 MAILPIT_URL=http://localhost:8026 yarn test:e2e e2e/identity-module.spec.ts
```

Sin la variable, la suite se omite. Todo lo que activa 2FA o cambia una contraseña ocurre sobre usuarios creados por la propia prueba, nunca sobre el usuario compartido de e2e.
