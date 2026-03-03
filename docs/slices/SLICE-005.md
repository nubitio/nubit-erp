# SLICE-005 — Hardening de seguridad: APP_SECRET, security headers y endpoints públicos

| Field        | Value                                      |
|--------------|--------------------------------------------|
| id           | SLICE-005                                  |
| status       | approved                                   |
| created_at   | 2026-03-02                                 |
| updated_at   | 2026-03-02 (approved)                      |
| author       | agt-architect                              |

---

## 1. Purpose & Scope

### In scope

- **SEC-1:** Hacer obligatorio un `APP_SECRET` no vacío en todos los entornos;
  actualizar la configuración para que `JWTManager` falle con un mensaje claro si
  se arranca sin secret.
- **SEC-2:** Documentar el procedimiento de rotación de secretos expuestos (S3,
  GitHub PAT, Bill API token) y añadir validación de presencia en `.env` con valores
  de placeholder explícitos; actualizar `.env.local.dist` (o equivalente) para
  que nuevos entornos no arranquen con credenciales de ejemplo.
- **SEC-3:** Agregar todos los security headers obligatorios en el `Caddyfile`:
  `Strict-Transport-Security`, `Content-Security-Policy`, `X-Frame-Options`,
  `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`.
- **SEC-4:** Restringir los dos endpoints sensibles en `security.yaml`:
  - `/api/settings` → `ROLE_USER`
  - `/api/sales/report` → `ROLE_USER`
- **Smoke test:** Verificar que todos los cambios anteriores pasan `make check`
  y que la aplicación arranca correctamente en entorno de staging.

### Out of scope

- Migración de JWT de HS256 a RS256 (evaluada en backlog; no bloqueante para v1).
- Rotación del propio `APP_SECRET` en producción mediante secrets manager
  (HashiCorp Vault, AWS SSM, etc.) — ese proceso es operacional, no código.
- Cambios en la lógica de autenticación o en los roles/permisos más allá de
  los dos endpoints específicos listados.
- `/api/setup` — el endpoint no existe o no es relevante; queda fuera de scope.
- Agregar autenticación más robusta al panel `/admin` (HTTP Basic → OAuth2) —
  queda en backlog post-launch.
- Rate limiting o restricción por IP del endpoint `/health`.
- Tests E2E de seguridad completos (eso corresponde a `agt-security` en un slice
  dedicado de hardening).

---

## 2. Domain context

- **Bounded context primario:** Seguridad / Auth (`src/Core/Security/`)
- **Bounded context secundario:** Infraestructura (`docker/frankenphp/Caddyfile`,
  `config/packages/security.yaml`)
- **Entidades involucradas:** `JWTManager`, `security.yaml` (access_control),
  `Caddyfile`

---

## 3. User story

> Como operador del SaaS (DevOps / propietario del sistema),
> quiero que la plataforma efact rechace arrancar con `APP_SECRET` vacío,
> que todos los responses HTTP lleven security headers estándar,
> y que los endpoints `/api/settings` y `/api/sales/report`
> requieran autenticación,
> para que ningún atacante pueda forjar tokens JWT ni acceder a datos sensibles
> del tenant sin credenciales válidas.

---

## 4. API surface

### 4.1 Cambios en `security.yaml` (access_control)

```yaml
# ANTES
- { path: ^/api/settings,      roles: PUBLIC_ACCESS }
- { path: ^/api/sales/report,  roles: PUBLIC_ACCESS }

# DESPUÉS
- { path: ^/api/settings,      roles: ROLE_USER }
- { path: ^/api/sales/report,  roles: ROLE_USER }
```

### 4.2 Security headers en Caddyfile

```
header {
    Strict-Transport-Security  "max-age=31536000; includeSubDomains"
    Content-Security-Policy    "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:"
    X-Frame-Options            "DENY"
    X-Content-Type-Options     "nosniff"
    Referrer-Policy            "strict-origin-when-cross-origin"
    Permissions-Policy         "camera=(), microphone=(), geolocation=()"
}
```

> **Nota sobre CSP:** La directiva `style-src 'unsafe-inline'` es necesaria
> mientras DevExtreme inyecte estilos inline. Deberá evaluarse en un slice
> de hardening posterior. `img-src data:` es necesaria para el logo en
> base64 del recibo.

### 4.3 Arranque con APP_SECRET vacío

- **Antes:** La aplicación arranca; los JWT se firman con `''`; `JWTManager`
  ya lanza `LogicException` — pero solo en el primer uso del servicio.
- **Después:** Un `KernelEvent::BOOT` listener (o `CompilerPass`) verifica
  `APP_SECRET` al arrancar y lanza un error fatal inmediato con mensaje
  descriptivo. En entorno `dev`, se acepta cualquier valor de 8+ chars.
  En entornos `prod`/`staging`, se exige un mínimo de 32 bytes hex.

> **Alternativa más simple (preferida):** Dado que `JWTManager` ya lanza
> `LogicException` en el constructor cuando el secret está vacío, la solución
> es asegurarse de que el servicio se instancie en el arranque (marcarlo como
> `eager: true` o añadir un health check que lo llame). Esto es suficiente
> para detectar el error al hacer deploy, no solo al primer login.

### 4.4 Documentación de rotación (sin cambios en contratos de API)

- Crear `docs/runbooks/rotate-secrets.md` con los pasos para rotar
  S3, GitHub PAT y Bill API token, y para generar un nuevo `APP_SECRET`.
- Actualizar `.env` para que los placeholders de secretos sean `CHANGEME_*`
  en lugar de valores de ejemplo reales.

---

## 5. Domain events

Ninguno nuevo. Este slice es exclusivamente de configuración e infraestructura.

---

## 6. Data requirements

No se requieren migraciones de base de datos.

---

## 7. Dependent artifacts

| Artifact | Tipo | Estado |
|----------|------|--------|
| `docker/frankenphp/Caddyfile` | Config existente | Necesita security headers |
| `config/packages/security.yaml` | Config existente | Necesita restricciones en access_control |
| `src/Core/Security/Auth/JWTManager.php` | Código existente | Lanza `LogicException` en constructor — necesita activación temprana |
| `.env` | Config existente | Placeholders de secretos deben ser no-valores |
| `docs/runbooks/` | Directorio (a crear) | Runbook de rotación de secretos |

---

## 8. Sub-tasks

| # | Descripción | Agente |
|---|-------------|--------|
| T1 | **Security headers en Caddyfile:** Agregar bloque `header { ... }` con HSTS, CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy en `docker/frankenphp/Caddyfile`. | `agt-devops` |
| T2 | **Restringir endpoints en security.yaml:** Cambiar `PUBLIC_ACCESS` → `ROLE_USER` para `/api/settings` y `/api/sales/report`. | `agt-backend` |
| T3 | **Activación temprana de JWTManager:** Añadir un `KernelBootListener` (o usar `warmup` de container) que instancie `JWTManager` al arranque para que el `LogicException` de secret vacío falle en deploy, no en runtime. Actualizar `.env` para que `APP_SECRET` tenga valor placeholder `CHANGEME_generate_with_bin2hex_random_bytes_32`. | `agt-backend` |
| T4 | **Runbook de rotación de secretos:** Crear `docs/runbooks/rotate-secrets.md` con instrucciones paso a paso para rotar S3 key, GitHub PAT y Bill API token, y para generar `APP_SECRET`. Incluir el comando `php -r "echo bin2hex(random_bytes(32));"`. | `agt-devops` |
| T5 | **Tests de regresión de acceso:** Verificar que los dos endpoints restringidos devuelven `401 Unauthorized` sin token y `200 OK` con token válido. | `agt-qa` |
| T6 | **Gate:** `make check` verde; ejecutar `curl -I https://staging.efact.pe` y verificar presencia de todos los headers; verificar que la aplicación no arranca en staging con `APP_SECRET` vacío. | `agt-qa` |

---

## 9. Acceptance criteria

1. Un request a `GET /api/settings` **sin** token JWT devuelve `401 Unauthorized`
   (no `200 OK`).
2. Un request a `GET /api/sales/report` **sin** token JWT devuelve
   `401 Unauthorized`.
3. Un request autenticado (`ROLE_USER`) a `GET /api/settings` devuelve `200 OK`
   (no regresión).
4. Todos los responses HTTP del servidor incluyen los siguientes headers:
   - `Strict-Transport-Security: max-age=31536000; includeSubDomains`
   - `X-Frame-Options: DENY`
   - `X-Content-Type-Options: nosniff`
   - `Content-Security-Policy` con valor no vacío
   - `Referrer-Policy: strict-origin-when-cross-origin`
   - `Permissions-Policy` con valor no vacío
5. Si la aplicación se arranca con `APP_SECRET=` (vacío) en entorno `prod`,
   el proceso de Symfony **no llega a servir requests** — falla en boot con
   mensaje descriptivo que incluye instrucción para generar el secret.
6. El archivo `.env` **no** contiene ningún secret real; todos los campos
   sensibles tienen como valor `""` (vacío) o `CHANGEME_*` como placeholder.
7. Existe `docs/runbooks/rotate-secrets.md` con instrucciones para rotar
   los tres secretos expuestos (S3, GitHub PAT, Bill API) y para generar `APP_SECRET`.
8. `make check` (phpstan, phpcs, phpmd, phpunit) pasa en verde sin nuevas
   advertencias.
9. No hay regresiones en los tests existentes de `JWTAuthenticator` ni de
    los endpoints de autenticación (`/api/auth/login`, `/api/auth/refresh`).

---

## 10. Open questions / risks

| # | Pregunta | Riesgo |
|---|----------|--------|
| Q1 | La CSP con `style-src 'unsafe-inline'` puede ser insuficiente para algunos auditores de seguridad. ¿Se acepta este trade-off para v1 mientras DevExtreme no soporte nonce-based styles? | Bajo — `'unsafe-inline'` es aceptable para v1; el endurecimiento de CSP queda en backlog. |
| Q2 | ¿El bloque `header` del Caddyfile debe excluir los endpoints de `/receipt/{id}` (recibos públicos) de `X-Frame-Options: DENY`? Los recibos pueden ser embebidos en iframe por algunos clientes POS. | Bajo — los recibos son páginas independientes navigables; `DENY` es seguro. Si se necesita embedding, se cambia a `SAMEORIGIN` en un path específico. |
| Q3 | ¿Los secretos `S3_ACCESS_SECRET`, `GITHUB_TOKEN` y `BILL_API_TOKEN` están actualmente en `.env.local` de los entornos de staging/prod o solo en dev? La acción de rotación es urgente si están en prod. | **Alto** — debe verificarse con el equipo de ops antes de hacer deploy de este slice. |
