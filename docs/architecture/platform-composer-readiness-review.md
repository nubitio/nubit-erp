# Revisión de readiness Composer para Platform

## 1. Estado actual y alcance

`SPEC-004` deja una frontera **Platform interna** más clara dentro del monolito, pero todavía **no hay paquete Composer publicable** ni compromiso de API externa. El trabajo completado entre `TASK-013` y `TASK-023` estabiliza una ruta de migración: introduce `src/Platform`, mantiene shims en `App\Core`, aísla dependencias efact-specific y añade guardrails para evitar regresiones.

El objetivo actual es preparar la extracción futura sin romper consumidores existentes. Por tanto:

- `src/Platform` es el namespace interno preferente para código nuevo de plataforma.
- `App\Core\...` sigue existiendo como compatibilidad transicional donde ya hay consumidores.
- Las integraciones Symfony/Doctrine/Messenger deben tratarse como bridge/adapters, no como núcleo portable.
- Auth, constantes de negocio, Doctrine efact y políticas tenant app-specific siguen siendo responsabilidad de la aplicación.

## 2. Candidatos para un futuro paquete

### Núcleo portable (`Platform core`)

Contenido candidato para un paquete base, siempre que mantenga dependencias mínimas y no importe `App\...` específico:

- `src/Platform/Tenant/Contract/*`
  - `TenantRegistryInterface`
  - `TenantDescriptorRegistryInterface`
  - `TenantConnectionSwitcherInterface`
  - `TenantBackupRunnerInterface`
- `src/Platform/Tenant/Model/TenantDescriptor`
- `src/Platform/Tenant/Context/TenantContext`
- `src/Platform/Quota/Contract/*`
  - `QuotaEnforcerInterface`
  - `QuotaResourceResolverInterface`
- `src/Platform/Cache/CacheManager`
- `src/Platform/Filesystem/FileManager`, si se acepta Flysystem como dependencia core o se extrae detrás de contrato.
- `src/Platform/RateLimit/RateLimitResult`
- `src/Platform/Exception/*`
- Modelos/value objects genéricos de mensajería, por ejemplo `TenantStamp` y `ActorStamp`, si se decide que depender de Messenger pertenece al core o a un bridge.

### Bridge Symfony / integraciones framework

Contenido candidato para un paquete separado o sub-namespace opcional, no para el núcleo portable puro:

- `src/Platform/Symfony/Console/TenantAwareCommand`
- Middleware Messenger: `TenantStampMiddleware`, `TenantContextMiddleware`.
- Stamps Messenger si se prefiere que todo lo relacionado a Messenger viva fuera del core.
- `src/Platform/Observability/Logging/TenantLogProcessor` por su dependencia de Monolog/OpenTelemetry.
- `src/Platform/Validation/Validator` por su dependencia de Symfony Validator.
- `src/Platform/Http/ApiResponse` por su dependencia de HttpFoundation.
- Listeners HTTP/Console todavía ubicados en `src/Core/EventListener` cuando queden libres de reglas efact.
- Futuros compiler passes, extensión de bundle, configuración DI y aliases de compatibilidad.

## 3. Exclusiones explícitas / adaptadores app-owned

Estos elementos no deben entrar al paquete `Platform` salvo rediseño profundo detrás de contratos genéricos:

- **Auth efact**: moved to `src/Identity/Auth/*`, `src/Identity/Security/*`, and `src/Identity/EventListener/AppSecretBootListener.php`; former Core auth/session paths are removed from production code.
- **Constantes efact**: `App\Efact\Config\EfactConstants` y la fachada legacy `App\Core\Util\Constants`.
- **Doctrine efact**: `src/Efact/Doctrine/*` y fachadas legacy `src/Core/Doctrine/*`.
- **Quota resolver app-owned**: `App\ControlPlane\Billing\Quota\EfactEntityQuotaResourceResolver`, porque conoce entidades como `User`, `Customer`, `Product`, `TaxDocument` y `Branch`.
- **Servicios transicionales de aplicación**: `SettingsService`, `DashboardService`, `CompanyContext`, `LogoProvider`.
- **Exporters**: `PdfExporter` y `XlsExporter` mientras no se pruebe que su API/formato es genérico y no expresa reportes/comprobantes efact.
- **Política tenant DB**: `TenantConnection` actual y el fallback YAML, porque codifican reglas concretas de conexión (`dbname`, `user`, `config/tenants.yaml`).
- **Reglas de dominio/UI**: payloads de comprobantes/recibos, filtros por sucursal, campos virtuales de ventas/documentos y settings SUNAT/CPE/ventas/compras/inventario.

## 4. Estrategia de compatibilidad vigente

La migración conserva compatibilidad por capas:

- **Shims `App\Core`**: contratos y clases legacy siguen disponibles para consumidores existentes. En varios casos extienden o delegan hacia clases `App\Platform`.
- **Aliases DI**: `config/services.yaml` conecta contratos nuevos `App\Platform\...` a servicios legacy o implementaciones existentes para evitar duplicar instancias.
- **Alias Core-primary para `TenantContext`**: `App\Platform\Tenant\Context\TenantContext` aliasa a `App\Core\Context\TenantContext`. Esto es intencional porque el contexto es stateful por request/message y ambos namespaces deben compartir la misma instancia.
- **Fachadas legacy**:
  - Auth legacy permanece fuera de Platform y bloqueada por guardrails.
  - `App\Core\Util\Constants` sigue como fachada deprecated hacia `App\Efact\Config\EfactConstants`.
  - `App\Core\Doctrine\...` sigue como fachada hacia `App\Efact\Doctrine\...` donde aplica.
- **ControlPlane implementa puertos**: Platform define contratos; ControlPlane/app adapters implementan la política concreta.

Esta estrategia permite migrar imports gradualmente sin cambiar comportamiento runtime ni forzar una extracción prematura.

## 5. Dependency guard y pruebas

Comandos relevantes desde la raíz del proyecto:

```bash
composer check:platform-boundary
composer test:platform-boundary-guard
```

En entorno Docker, el mapa de frontera recomienda:

```bash
docker compose exec -T web composer check:platform-boundary
docker compose exec -T web composer test:platform-boundary-guard
docker compose exec -T web php bin/console lint:container
```

Qué prueban:

- `composer check:platform-boundary` escanea candidatos Platform y falla si encuentra imports hacia namespaces efact prohibidos: entidades, DTOs, repositorios, ControlPlane concreto, módulos de negocio, auth legacy, constantes efact y Doctrine efact.
- `composer test:platform-boundary-guard` ejecuta self-tests con fixtures temporales para demostrar casos negativos y positivos del guard sin dejar fixtures productivos.
- `php bin/console lint:container` valida wiring Symfony cuando se cambian aliases, servicios o bindings.

Para este documento no se tocaron archivos PHP, por lo que no es obligatorio ejecutar tests de código. La validación mínima es confirmar que el documento existe y es legible.

## 6. Namespace y nombre de paquete propuestos

No hay decisión final de namespace/paquete. Recomendación por defecto para discusión:

- Package Composer: `efact/platform`
- Namespace PHP futuro: `Efact\Platform\`

Rationale:

- Evita publicar un paquete con namespace `App\...`, que está acoplado al monolito Symfony.
- Mantiene identidad clara del vendor actual.
- Permite un `autoload` limpio y estable al extraer.
- Deja `App\Platform\...` como namespace interno transicional hasta que se haga el rename o subtree split.

Alternativas abiertas:

| Alternativa | Ventaja | Desventaja |
|---|---|---|
| `efact/platform` + `Efact\Platform\` | Default recomendado; simple y coherente. | Si el paquete debe ser open-source genérico, el vendor puede sonar demasiado producto-specific. |
| `efact/php-platform` + `Efact\Platform\` | Más explícito en Packagist. | Nombre más largo sin beneficio claro para consumo interno. |
| `johangm90/efact-platform` + `Efact\Platform\` | Alineado con el package app actual. | Menos organizacional; menos ideal si se formaliza como paquete de empresa. |
| Mantener `App\Platform\` en el paquete | Minimiza cambios de imports iniciales. | No recomendado para publicación; `App\` comunica código de aplicación, no librería. |

## 7. Propuesta de forma del paquete Composer

### Paquete base tentativo

```json
{
  "name": "efact/platform",
  "type": "library",
  "description": "Reusable platform contracts, tenant context, quota ports and infrastructure helpers for Efact applications.",
  "license": "proprietary",
  "require": {
    "php": ">=8.3",
    "psr/cache": "^3.0",
    "psr/log": "^3.0"
  },
  "suggest": {
    "league/flysystem": "Required when using Filesystem\\FileManager.",
    "symfony/cache": "Useful for CacheManager integrations."
  },
  "autoload": {
    "psr-4": {
      "Efact\\Platform\\": "src/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "Efact\\Platform\\Tests\\": "tests/"
    }
  }
}
```

Notas:

- La versión mínima de PHP debe decidirse al extraer. Hoy la app exige `>=8.5`, pero el paquete podría elegir `>=8.3`/`>=8.4` si no usa features más nuevas.
- Dependencias Symfony, Messenger, Monolog, OpenTelemetry, HttpFoundation, Validator y Flysystem deben revisarse una por una: core dependency, `suggest`, o bridge separado.

### Bridge Symfony opcional

Opciones:

1. **Paquete separado**: `efact/platform-symfony` con namespace `Efact\PlatformSymfony\` o `Efact\Platform\Symfony\`.
2. **Subdirectorio en el mismo repo**: monorepo con `packages/platform` y `packages/platform-symfony`.
3. **Mismo paquete, dependencias opcionales**: más simple al inicio, pero puede diluir la frontera portable.

Recomendación inicial: separar el bridge antes de publicar, aunque al principio viva en el mismo repositorio. Esto evita que consumidores del core tengan que instalar Symfony completo.

Source roots propuestos:

```text
packages/platform/src/              # Efact\Platform\*
packages/platform/tests/
packages/platform-symfony/src/      # Efact\Platform\Symfony\* o Efact\PlatformSymfony\*
packages/platform-symfony/tests/
```

## 8. Fases de migración hacia extracción

1. **Estabilizar imports**
   - Migrar consumidores nuevos a `App\Platform\...`.
   - Mantener `App\Core\...` sólo como compatibilidad/deprecation.
   - Asegurar que no entren nuevas dependencias prohibidas al guard.

2. **Separar bridge Symfony**
   - Mover commands, middleware, listeners, validators Symfony, HttpFoundation responses, Monolog/OpenTelemetry y DI hacia un namespace bridge.
   - Definir qué queda en core puro vs bridge.

3. **Eliminar leftovers app-specific**
   - Sacar de Core/Platform auth, settings/dashboard/company/logo/exporters si no son genéricos.
   - Mantener adapters efact en `src/Efact`, `src/Auth` o bounded contexts app-owned.

4. **Agregar test suite local del paquete**
   - Unit tests para contratos/modelos/contexto.
   - Tests de compatibilidad para aliases sólo en la app, no en el paquete portable.
   - Fixtures mínimos sin dependencias del dominio efact.

5. **Extracción por path repository**
   - Crear `packages/platform` y configurar la app para consumirlo con Composer path repository.
   - Validar autoload, CI y runtime sin publicación externa.

6. **CI y release**
   - Pipeline propio con lint, static analysis, PHPUnit y dependency guard package-local.
   - Automatizar tagging y changelog.

7. **Versionado semántico**
   - Declarar API pública inicial.
   - Usar SemVer: breaking changes sólo en majors; nuevos contratos en minors; bugfixes en patches.
   - Documentar política de deprecations para reemplazar shims `App\Core`.

## 9. Riesgos y blockers restantes

- **API pública prematura**: publicar antes de estabilizar contratos puede fijar shapes array-based legacy difíciles de cambiar.
- **Symfony leakage**: clases útiles pero framework-specific pueden forzar dependencias pesadas en el paquete core.
- **Imports legacy persistentes**: si la app sigue usando `App\Core` ampliamente, el costo de extracción sube.
- **Política tenant DB no portable**: conexión tenant actual mezcla infraestructura app-specific y fallback local.
- **Auth y Doctrine efact**: ya están excluidos, pero los shims legacy pueden generar confusión si se tratan como candidatos Platform.
- **Exporters/settings/logo/dashboard**: aún requieren decisión de destino; podrían bloquear una frontera limpia si permanecen en Core.
- **Namespace final no decidido**: `App\Platform` es aceptable internamente, pero no para publicación.
- **Tests package-local faltantes**: el guard protege imports, pero no reemplaza una suite del paquete.

## 10. Checklist de “ready to extract”

- [ ] `src/Platform` sin imports prohibidos según `composer check:platform-boundary`.
- [ ] No hay dependencias a `App\Entity`, `App\Dto`, `App\Repository`, `App\ControlPlane` concreto ni `App\Efact` desde candidatos core.
- [ ] Auth, constantes, Doctrine efact, quota resolver concreto y tenant DB policy quedan fuera del paquete.
- [ ] Clases Symfony/Messenger/Monolog/HttpFoundation/Validator están en bridge o declaradas explícitamente como dependencias opcionales.
- [ ] Namespace/package final decidido y documentado.
- [ ] Composer metadata package-local creada con autoload propio.
- [ ] Test suite package-local pasa sin bootear la app efact.
- [ ] La app consume el paquete vía path repository antes de cualquier publicación.
- [ ] CI del paquete y CI de integración de la app pasan.
- [ ] Política SemVer y deprecations para `App\Core` publicada.
- [ ] `App\Core` reducido a shims con fecha/fase de remoción.

## 11. Mapeo a SPEC-004 AC-12 y ACs relacionados

| AC | Cobertura |
|---|---|
| **AC-12** | Este documento define la readiness Composer: contenidos candidatos, exclusiones, namespace/package tentativo, shape de `composer.json`, bridge Symfony, fases de extracción, riesgos y checklist. |
| **AC-1** | Complementa el mapa de frontera con una evaluación de qué puede pasar a paquete y qué debe seguir app-owned. |
| **AC de guardrails** | Resume los comandos `check:platform-boundary` y `test:platform-boundary-guard` y qué prueban. |
| **AC de compatibilidad** | Documenta shims `App\Core`, aliases DI, alias Core-primary de `TenantContext` y fachadas legacy de auth/constants/Doctrine. |
| **AC de aislamiento app-owned** | Explicita que auth, Efact constants, Doctrine efact, quota resolver, settings/dashboard/company/logo/exporters y tenant DB policy quedan fuera de Platform. |

## Referencias internas

- [`platform-boundary-map.md`](./platform-boundary-map.md) — inventario de frontera y clasificación por subárbol.
- [`auth-boundary.md`](./auth-boundary.md) — aislamiento de auth efact respecto a Platform.
- [`controlplane-boundary.md`](./controlplane-boundary.md) — relación entre ControlPlane y puertos Platform.
