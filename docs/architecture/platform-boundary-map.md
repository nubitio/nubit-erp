# Platform boundary map para `src/Core`

## Estado

Inventario práctico para `SPEC-004` — Refine Core into Platform bounded context and Composer-ready package boundary.

Revisión complementaria: [`platform-composer-readiness-review.md`](./platform-composer-readiness-review.md) resume el estado Composer-ready posterior a `SPEC-004`, candidatos de paquete, exclusiones, estrategia de compatibilidad y fases restantes de extracción.

## Propósito y alcance

`src/Core` hoy mezcla capacidades reutilizables de plataforma, adaptadores Symfony/Doctrine/Messenger, utilidades compartidas y reglas específicas de efact. El objetivo de `SPEC-004` es convertir ese espacio en un bounded context **Platform** que pueda evolucionar hacia un paquete Composer sin arrastrar dominio de facturación, ventas, compras, inventario, autenticación efact-specific ni detalles internos del `ControlPlane`.

Este documento no mueve código. Define el mapa de frontera para orientar refactors posteriores:

- qué partes de `src/Core` son candidatas a `Platform` reutilizable;
- qué partes deben quedar como adaptadores Symfony o app adapters dentro del monolito;
- qué acoplamientos bloquean una extracción Composer;
- qué reglas de dependencias deben aplicar los próximos cambios.

## Leyenda de clasificación

| Clasificación | Significado |
|---|---|
| **Platform core** | Código reusable y portable, con dependencias mínimas y sin referencias a entidades/DTO/repositorios concretos de efact. Candidato directo al paquete Composer. |
| **Symfony adapter** | Integración con Symfony, Doctrine, Messenger, Monolog, Twig o bundles. Puede vivir en un subpaquete/bridge o en la app, pero no en el núcleo portable. |
| **Efact app adapter / domain-specific** | Código que conoce entidades, DTOs, repositorios, reglas comerciales o políticas operativas propias de efact. Debe salir de `Core` o quedar detrás de puertos. |
| **Shared utility candidate** | Helper/utilidad potencialmente reusable, pero requiere revisión de semántica, API pública y dependencias antes de promoverlo a Platform. |
| **Transitional compatibility** | Pieza temporal para mantener BC durante la migración. Debe tener plan explícito de remoción o reubicación. |
| **Excluded from future package** | No debe entrar al futuro paquete Composer de Platform. Puede permanecer en la aplicación principal. |

## Inventario de `src/Core`

| Subárbol / archivo | Clasificación propuesta | Acción recomendada | Notas |
|---|---|---|---|
| `Core/Context/TenantContext.php` | **Platform core** | Mantener como contrato/estado de tenant genérico. | Es candidato fuerte. No debe conocer entidades concretas de tenant. |
| `Core/Platform/Tenant/*` | **Platform core** | Mantener como puertos públicos de Platform. | `TenantRegistryInterface`, `TenantConnectionSwitcherInterface`, `TenantBackupRunnerInterface` son frontera correcta entre Platform y ControlPlane. |
| `Core/Platform/Billing/QuotaEnforcerInterface.php` | **Platform core** | Mantener como puerto, revisar nombre si billing queda fuera del core portable. | La implementación actual vive en ControlPlane vía `TenantQuotaService`. |
| `Core/Messenger/TenantStamp.php`, `ActorStamp.php` | **Platform core** | Mantener como mensajes/value objects de infraestructura. | Deben seguir serializables y sin dependencia de app. |
| `Core/Messenger/TenantStampMiddleware.php`, `TenantContextMiddleware.php` | **Symfony adapter** | Mantener como bridge Messenger o mover a `PlatformSymfonyBridge`. | Dependen de Messenger middleware y tenant switching. Correctos como adaptador. |
| `Core/Message/TenantBackupMessage.php` | **Platform core** / transitional | Mantener si el mensaje es genérico; si expresa política de backup efact, mover a ControlPlane. | Revisar payload y naming antes de publicar. |
| `Core/MessageHandler/TenantBackupMessageHandler.php` | **Symfony adapter** | Mantener como handler de integración o mover a ControlPlane adapter. | Ya usa `TenantBackupRunnerInterface`; no debe depender de servicios concretos. |
| `Core/Application/Command/TenantAwareCommand.php` | **Symfony adapter** | Mantener como base command en bridge Symfony Console. | Candidato fuerte de adaptador; no debe importar ControlPlane concreto. |
| `Core/EventListener/TenantListener.php` | **Symfony adapter** | Mantener como listener HTTP usando puertos Platform. | Ya apunta a interfaces Platform. |
| `Core/EventListener/TenantRateLimitListener.php` | **Symfony adapter** | Mantener como bridge HTTP + RateLimit. | Requiere revisar config de rutas/exclusiones. |
| `Core/EventListener/TenantMetricsListener.php` | **Symfony adapter** | Mantener como observabilidad si no conoce dominio. | Verificar que métricas sean genéricas. |
| `Core/EventListener/ApiResponseListener.php`, `ExceptionListener.php`, `CspNonceListener.php`, `ConsoleContextListener.php`, `AppSecretBootListener.php` | **Symfony adapter** | Mantener en bridge o app según genericidad. | `AppSecretBootListener` suena a bootstrap app-specific: revisar antes de extracción. |
| `Core/EventListener/QuotaEnforcementListener.php` | **Symfony adapter** / transitional | Mantener detrás de puertos Platform; el mapeo concreto vive en app adapter. | `TASK-017` elimina imports `App\Entity` y delega la resolución de recursos a `QuotaResourceResolverInterface`; el adapter efact vive en `ControlPlane/Billing/Quota`. |
| `Core/EventListener/SoftDeleteFilterListener.php` | **Symfony adapter** / transitional | Mantener temporalmente; revisar con filtros Doctrine. | Debe quedar genérico o ir a app. |
| `Core/RateLimit/*` | **Platform core** + **Symfony adapter** | Separar modelo (`RateLimitResult`) de integración (`TenantRateLimiter`). | Candidato fuerte si no depende de rutas/reglas efact. |
| `Core/Logging/TenantLogProcessor.php` | **Symfony adapter** | Mantener como bridge Monolog. | Debe depender solo de `TenantContext`. |
| `Core/Cache/CacheManager.php` | **Platform core** | Mantener si sólo depende de contratos PSR/Symfony cache. | API reusable de plataforma. |
| `Core/Filesystem/FileManager.php` | **Platform core** | Mantener si sólo envuelve Flysystem de forma genérica. | Candidato de paquete. |
| `Core/Filesystem/S3UrlResolver.php` | **Symfony adapter** / shared utility candidate | Revisar si es genérico o configuración efact. | S3 concreto puede ir a bridge/adaptador. |
| `Core/Http/ApiResponse.php` | **Platform core** | Mantener como DTO/helper HTTP portable. | Evitar semántica de negocio en códigos/mensajes. |
| `Core/Exception/*` | **Platform core** | Mantener excepciones genéricas. | `QuotaExceededException` es aceptable si quota es un capability Platform. |
| `Core/Validation/Validator.php` | **Platform core** | Mantener si envuelve Symfony Validator de forma genérica. | Si requiere Symfony, ubicar en bridge o documentar dependencia. |
| `Core/Doctrine/DBAL/TenantConnection.php` | **Transitional compatibility** | No publicar en paquete core; extraer contrato/política. | Bloqueador: embebe política efact de dbname/user desde tenant connection y fallback YAML. |
| `Core/Doctrine/Filter/BranchFilter.php` | **Efact app adapter / domain-specific** | Mover fuera de Core o parametrizar por contrato. | Branch es concepto app/tenant, no plataforma universal. |
| `Core/Doctrine/Filter/SoftDeleteFilter.php` | **Efact app adapter / domain-specific** | Eliminar referencias concretas o mover a app. | Bloqueador: conoce `Media`, `Purchase`, `User`. |
| `Core/Doctrine/Filter/DevExtremeFilter.php` | **Efact app adapter / domain-specific** | Mover a módulo UI/app o parametrizar virtual fields. | Bloqueador: conoce `TaxDocument`, `SalePayment` y campos virtuales de ventas. |
| `Core/Migrations/ControlPlaneMigrationFactory.php`, `TenantMigrationFactory.php` | **Symfony adapter** / transitional | Mantener como integración Doctrine Migrations hasta definir bridge. | No deben conocer servicios concretos fuera de puertos. |
| `Core/Security/CspNonceService.php` | **Platform core** / Symfony adapter | Mantener si es servicio genérico. | Listener asociado es Symfony adapter. |
| `Core/Security/Auth/*` | **Excluded from future package** salvo piezas genéricas | Mover a módulo Auth/app o rediseñar por contratos. | Bloqueador: depende de `User`, `Branch`, `RefreshToken`, `AuthResponseDto`, `RefreshTokenRepository`. |
| `Core/Symfony/Security/SecurityService.php` | **Excluded from future package** | Mover a app auth adapter. | Conoce `AuthUserDto` y `User`. |
| `Core/Application/Session/Security.php` | **Excluded from future package** | Mover a app auth/session. | Conoce `AuthUserDto`. |
| `Core/Service/SettingsService.php` | **Efact app adapter / domain-specific** | Mover o invertir detrás de `SettingsProviderInterface`. | Settings actuales son parte de reglas/config app. |
| `Core/Service/DashboardService.php` | **Efact app adapter / domain-specific** | Mover a app/reporting. | Dashboard no es capability de plataforma genérica. |
| `Core/Service/CompanyContext.php` | **Efact app adapter / domain-specific** | Mover a app/company context. | Depende de `Company` y `CompanyRepository`. |
| `Core/Service/LogoProvider.php` | **Efact app adapter / domain-specific** | Mover a app/branding o parametrizar. | Usa constants/settings/tenant filesystem con semántica efact. |
| `Core/Service/BinlogBackupService.php` | **Efact app adapter / domain-specific** / transitional | Mover a ControlPlane/ops o app infra. | Usa constants y política operativa concreta. |
| `Core/Service/PdfExporter.php`, `XlsExporter.php` | **Shared utility candidate** | Mantener temporalmente; revisar dependencias y API. | Podrían ser package opcional si son genéricos; si contienen formato efact, excluir. |
| `Core/Service/MessengerServiceInterface.php` | **Platform core** / transitional | Mantener si es puerto genérico; revisar necesidad. | Debe quedar como contrato mínimo, no wrapper innecesario. |
| `Core/Audit/*` | **Platform core** + **Symfony adapter** | Separar modelo/mensaje/puerto de handler/repository. | `AuditLogger` ya usa `TenantRegistryInterface`; repositorio/handler pueden requerir bridge. |
| `Core/Entity/AuditableEntity.php` | **Shared utility candidate** | Revisar si se publica como trait/base opcional. | Base entity impone herencia; preferir traits/contract para paquete. |
| `Core/Traits/TimestampableTrait.php`, `SoftDeletableTrait.php` | **Shared utility candidate** | Mantener como traits opcionales si no conocen app. | Publicar sólo si API estable. |
| `Core/Base/BaseController.php` | **Symfony adapter** / excluded | No incluir en core portable; si se conserva, poner en bridge Symfony. | Base controllers tienden a acoplar app y framework. |
| `Core/Compiler/RemoveUnwantedServices.php` | **Symfony adapter** | Mantener en bridge/app. | Compiler pass es integración framework. |
| `Core/Extension/*` | **Symfony adapter** / Efact app adapter | Revisar uno por uno; probablemente app/Twig bridge. | `SettingsExtension` y ESC/POS image pueden ser efact-specific. |
| `Core/Annotation/IgnoreAutowire.php` | **Symfony adapter** | Mantener en bridge si sigue siendo necesario. | Atributo ligado al container/autowiring. |
| `Core/Color/ColorUtils.php` | **Shared utility candidate** | Mantener si API estable. | Portable, pero no esencial a Platform bounded context. |
| `Core/Util/ArrayHelper.php`, `DateHelper.php`, `NumberHelper.php`, `StringHelper.php`, `Money.php` | **Shared utility candidate** | Revisar y publicar sólo helpers con semántica universal. | Evitar meter reglas de moneda/localización efact en helpers. |
| `Core/Util/ReceiptPayload.php` | **Efact app adapter / domain-specific** | Mover fuera de Core. | Payload de comprobante/recibo no es plataforma genérica. |
| `Core/Util/Constants.php` | **Transitional compatibility** / excluded | Mantener sólo como fachada BC hacia `App\Efact\Config\EfactConstants`; no usar en código nuevo. | `TASK-019` movió SUNAT, ventas, compras, inventario, CPE, settings, storage dirs y feature flags al namespace app-owned. |
| `Core/README.md` | **Transitional compatibility** | Actualizar después de los refactors. | Hoy describe Core como librería amplia; deberá alinearse con Platform + bridges. |

## Reglas de dirección de dependencias

1. **Platform core no puede depender de app concreta**: prohibidos imports desde `App\Entity`, `App\Dto`, `App\Repository`, `App\ControlPlane\...` concreto, `App\Setup`, `App\EInvoicing` o módulos de negocio.
2. **Platform core puede definir puertos** (`Interface`, DTOs/value objects, mensajes genéricos) que la app o `ControlPlane` implementan.
3. **Adaptadores Symfony dependen hacia adentro**: pueden depender de Platform core y de Symfony/Doctrine/Messenger/Monolog, pero no de dominio efact salvo que estén explícitamente clasificados como app adapters.
4. **ControlPlane implementa puertos Platform, no al revés**: bindings actuales aceptados en Symfony DI:
   - `App\Core\Platform\Tenant\TenantRegistryInterface` => `App\ControlPlane\Tenant\Service\TenantManager`
   - `App\Core\Platform\Tenant\TenantConnectionSwitcherInterface` => `App\ControlPlane\Tenant\Service\TenantConnectionSwitcher`
   - `App\Core\Platform\Tenant\TenantBackupRunnerInterface` => `App\ControlPlane\Tenant\Service\TenantBackupService`
   - `App\Core\Platform\Billing\QuotaEnforcerInterface` => `App\ControlPlane\Billing\Service\TenantQuotaService`
5. **Dominio efact no entra a Platform**: ventas, compras, inventario, SUNAT/CPE, compañía, sucursal y autenticación concreta deben vivir en bounded contexts/app adapters.
6. **Compatibilidad primero, extracción después**: durante la migración se permite mantener clases en `App\Core` si delegan a nuevas ubicaciones o puertos, pero deben quedar marcadas como transitional y sin nuevas dependencias prohibidas.

## Guardrail estático de Platform

`TASK-014` añade un check local/CI-friendly para congelar la frontera inicial de Platform sin mover código. El guard revisa los subárboles candidatos actuales:

- `src/Core/Context`
- `src/Core/Platform`
- `src/Core/Messenger`
- `src/Core/Logging`
- `src/Core/RateLimit`
- `src/Core/Cache`
- `src/Core/Filesystem`
- `src/Core/Http`
- `src/Core/Exception`
- `src/Core/Validation`
- `src/Core/Application/Command`
- `src/Core/EventListener`
- `src/Platform`

Comando local:

```bash
composer check:platform-boundary
```

El check falla con ruta, línea y patrón cuando un candidato Platform referencia namespaces concretos de efact (`App\Entity\`, `App\Repository\`, `App\Dto\`, `App\ControlPlane\`, `App\EInvoicing\`, `App\Inventory\`, `App\Notification\`, `App\State\`, `App\Infrastructure\`). Desde `TASK-018`, el check también falla si `src/Platform` referencia la auth transicional app-owned (`App\Core\Security\Auth\`, `App\Core\Symfony\Security\`, `App\Core\Application\Session\Security`). Desde `TASK-019`, `src/Platform` tampoco puede importar la fachada legacy `App\Core\Util\Constants` ni el namespace app-owned `App\Efact\Config\`. Desde `TASK-020`, `src/Platform` tampoco puede importar los adaptadores Doctrine efact (`App\Efact\Doctrine\`) ni las fachadas legacy `App\Core\Doctrine\`. Para validar el comportamiento negativo del guard sin dejar fixtures en rutas productivas:

```bash
composer test:platform-boundary-guard
```

El self-test crea fixtures temporales fuera del repo productivo y prueba ambos lados de la regla: casos negativos que deben fallar y adaptadores app-owned que deben quedar fuera del alcance del scanner. La cobertura negativa explícita incluye imports desde `src/Platform` hacia `App\Entity\User`, `App\Core\Security\Auth\JWTAuthenticator`, `App\Core\Util\Constants`, `App\Efact\Config\EfactConstants`, `App\Efact\Doctrine\DBAL\TenantConnection` y `App\Core\Doctrine\DBAL\TenantConnection`. La cobertura positiva explícita comprueba que fixtures de adaptadores en `src/ControlPlane/...` y `src/Efact/...` pueden referenciar entidades/configuración/doctrine efact sin producir violaciones, porque esos adaptadores están deliberadamente fuera del futuro paquete Platform.

Si un subárbol nuevo se promueve a Platform, debe añadirse a `scripts/architecture/check-platform-boundary.php` y quedar limpio antes de ampliar el futuro paquete Composer.

## Adaptadores Doctrine efact

`TASK-020` mueve la política Doctrine específica de la aplicación fuera de `src/Core` hacia `src/Efact/Doctrine`:

- `App\Efact\Doctrine\Filter\SoftDeleteFilter`: conserva el filtrado por `deleted_at` para `Purchase`, `User` y `Media`.
- `App\Efact\Doctrine\Filter\DevExtremeFilter`: conserva los campos virtuales de ventas/documentos (`fullNumber`, `invoiceFullNumber`, `paymentMethod`) y referencias a entidades efact.
- `App\Efact\Doctrine\Filter\BranchFilter`: conserva la semántica app-specific de asociación `branch`/`branch_id`.
- `App\Efact\Doctrine\DBAL\TenantConnection`: conserva la política tenant actual (`dbname` y `user` desde `tenant.connection`, fallback YAML `config/tenants.yaml`).

Las clases `App\Core\Doctrine\...` quedan sólo como shims transicionales de compatibilidad y no deben ser importadas desde nuevas clases Platform. `config/packages/doctrine.yaml` apunta a las implementaciones `App\Efact\Doctrine\...` para que la frontera Platform no sea dueña de esta política efact.

## Bindings Symfony transicionales de TASK-021

`TASK-021` consolida los aliases DI para que consumidores legacy `App\Core\...` y consumidores nuevos `App\Platform\...` resuelvan hacia los mismos servicios mientras la migración de imports sigue gradual.

Decisiones de binding activas en `config/services.yaml`:

- Los service IDs legacy permanecen como definiciones primarias para `TenantRegistryInterface`, `TenantConnectionSwitcherInterface`, `TenantBackupRunnerInterface` y `QuotaEnforcerInterface` porque todavía hay consumidores `App\Core\...` y servicios ControlPlane implementando esos contratos legacy.
- Los contratos nuevos `App\Platform\Tenant\Contract\*` y `App\Platform\Quota\Contract\QuotaEnforcerInterface` son aliases a los IDs legacy, evitando instancias duplicadas o diferencias de wiring durante la transición.
- `App\Platform\Tenant\Context\TenantContext` aliasa a `App\Core\Context\TenantContext`. Esto es intencional: el contexto es stateful por request/message y ambos namespaces deben compartir exactamente la misma instancia.
- `App\Platform\Quota\Contract\QuotaResourceResolverInterface` se vincula a `App\ControlPlane\Billing\Quota\EfactEntityQuotaResourceResolver`, un adapter app-owned que conoce entidades efact y queda excluido del futuro paquete Platform.
- `App\Platform\Observability\Logging\TenantLogProcessor`, `App\Platform\Cache\CacheManager` y `App\Platform\Filesystem\FileManager` aliasan a los IDs legacy correspondientes para preservar tags/servicios existentes mientras los consumidores migran.

Migración futura recomendada: cuando los consumidores legacy sean minoría, invertir las definiciones primarias para que `App\Platform\...` sea el service ID canónico y dejar `App\Core\...` sólo como aliases/shims deprecated hasta su remoción.

## Namespace `App\Platform` y contratos de compatibilidad

`TASK-015` introduce el esqueleto interno Composer-ready bajo `src/Platform` sin mover clases productivas de `App\Core`:

- `src/Platform/Tenant/Contract/TenantRegistryInterface`: contrato transicional con las mismas firmas array-based del registry actual.
- `src/Platform/Tenant/Contract/TenantDescriptorRegistryInterface`: puerto tipado para nuevos consumidores que quieran `TenantDescriptor` sin depender del shape legacy de arrays.
- `src/Platform/Tenant/Model/TenantDescriptor`: value object portable con `id`, `name`, `connectionName`, `primaryDomain`, `plan`, `status` y `attributes` para metadata del provider.
- `src/Platform/Tenant/Contract/TenantConnectionSwitcherInterface`: puerto portable para cambio de conexión tenant.
- `src/Platform/Tenant/Contract/TenantBackupRunnerInterface`: puerto portable para ejecución de backups tenant con la respuesta legacy estable.
- `src/Platform/Quota/Contract/QuotaEnforcerInterface`: puerto portable de enforcement por nombre de recurso; reemplaza el naming `Billing` para el futuro bounded context.
- `src/Platform/Quota/Contract/QuotaResourceResolverInterface`: puerto futuro para que adaptadores app traduzcan objetos/eventos de dominio a nombres de recursos de quota.

Compatibilidad aplicada:

1. Las interfaces legacy `App\Core\Platform\Tenant\*` y `App\Core\Platform\Billing\QuotaEnforcerInterface` extienden los nuevos contratos cuando las firmas son idénticas.
2. Los bindings Symfony existentes se mantienen y se añaden aliases para los nuevos contratos `App\Platform\...`, por lo que las implementaciones actuales de ControlPlane siguen satisfaciendo ambos namespaces.
3. `TASK-017` vincula `App\Platform\Quota\Contract\QuotaResourceResolverInterface` a `App\ControlPlane\Billing\Quota\EfactEntityQuotaResourceResolver`, que conserva el mapeo efact `User`, `Customer`, `Product`, `TaxDocument` y `Branch` fuera de `Core`/`Platform`.
4. No se cambia `TenantManager`, `TenantContext`, filtros Doctrine, auth, constantes ni clases de dominio. La migración a `TenantDescriptorRegistryInterface` queda para adaptadores posteriores.

## Promociones incrementales de TASK-016

`TASK-016` promueve capacidades reutilizables de bajo riesgo hacia `src/Platform` sin eliminar la API legacy `App\Core\...`:

| Capability | Nuevo namespace Platform | Compatibilidad legacy |
|---|---|---|
| Tenant context | `App\Platform\Tenant\Context\TenantContext` | `App\Core\Context\TenantContext` extiende la clase Platform. Symfony DI aliasa el servicio Platform al servicio Core para evitar dos instancias stateful. |
| Messenger stamps | `App\Platform\Messenger\TenantStamp`, `ActorStamp` | Los stamps legacy `App\Core\Messenger\*Stamp` quedan intactos por ser DTOs `final readonly` y para evitar cambios de serialización de mensajes existentes. |
| Messenger middleware | `App\Platform\Messenger\TenantStampMiddleware`, `TenantContextMiddleware` | Los middleware legacy siguen usando stamps legacy, pero dependen de `App\Platform\Tenant\Context` y contratos Platform. Los service IDs legacy y tags Messenger permanecen estables. |
| Logging | `App\Platform\Observability\Logging\TenantLogProcessor` | `App\Core\Logging\TenantLogProcessor` extiende el processor Platform y conserva el tag `monolog.processor`; el servicio Platform aliasa al legacy. |
| Cache | `App\Platform\Cache\CacheManager` | `App\Core\Cache\CacheManager` extiende la implementación Platform. |
| Filesystem | `App\Platform\Filesystem\FileManager` | `App\Core\Filesystem\FileManager` extiende la implementación Platform. |
| Rate limit | `App\Platform\RateLimit\RateLimitResult`, `TenantRateLimiter` | Las clases legacy quedan intactas para no cambiar el tipo retornado ni serialización/contratos existentes. |
| HTTP response | `App\Platform\Http\ApiResponse` | `App\Core\Http\ApiResponse` queda intacto porque sus factories devuelven `self`; cambiarlo a herencia podría devolver el tipo base Platform y romper callers. |
| Exceptions | `App\Platform\Exception\*` | `App\Core\Exception\*` queda intacto por factories `self` y expectativas de catch/import legacy. |
| Validation | `App\Platform\Validation\Validator` | `App\Core\Validation\Validator` queda intacto para continuar lanzando `App\Core\Exception\ValidationException`. |
| Tenant-aware console command | `App\Platform\Symfony\Console\TenantAwareCommand` | `App\Core\Application\Command\TenantAwareCommand` extiende la base Platform; comandos existentes pueden seguir importando Core. |

Elementos diferidos deliberadamente:

- No se migran imports de consumidores app/domain desde `App\Core\...` hacia `App\Platform\...`; eso se hará gradualmente después de consolidar tests y deprecations.
- No se tocan auth, constants, Doctrine filters ni la política concreta de tenant connection.
- No se convierten los stamps legacy en aliases o subclases porque son `final readonly` y pueden estar serializados en transports Messenger.
- No se reemplazan excepciones/API response legacy por herencia debido a factories con retorno `self` y semántica de catching existente.

## Aislamiento de auth de TASK-018

`TASK-018` clasifica la implementación actual de autenticación como **Efact app auth** y la deja fuera de la frontera Platform. No se mueven namespaces en esta tarea porque `App\Core\Security\Auth\*`, `App\Core\Symfony\Security\SecurityService` y `App\Core\Application\Session\Security` están referenciados por Symfony security config, service definitions y consumidores existentes.

Ubicación transicional excluida del paquete futuro:

- `src/Core/Security/Auth/*`
- `src/Core/Symfony/Security/SecurityService.php`
- `src/Core/Application/Session/Security.php`

Target app-owned recomendado para una migración posterior: `src/Auth/*` o `src/Efact/Auth/*`, manteniendo aliases/shims para service IDs legacy durante la transición.

No se añaden contratos `App\Platform\Security\Contract` en esta tarea: no hay todavía un puerto project-agnostic probado que justifique una API pública. La regla activa es negativa: `src/Platform` no puede importar la auth legacy de Efact.

Documento complementario: `docs/architecture/auth-boundary.md`.

## Constantes app-owned de TASK-019

`TASK-019` extrae las constantes de dominio/configuración desde `App\Core\Util\Constants` hacia `App\Efact\Config\EfactConstants`. El nuevo namespace es dueño de:

- rutas lógicas de storage (`INVOICE_DIR`, `IMAGE_DIR`, `BACKUP_DIR`, `BINLOG_DIR`);
- claves de settings SUNAT/CPE, ventas, compras, inventario, impresión y caja;
- feature flags app-owned como `FEATURE_WEBUSB_PRINT`.

Compatibilidad aplicada:

1. `App\Core\Util\Constants` queda como fachada transicional `@deprecated` que extiende `App\Efact\Config\EfactConstants` para no romper consumidores legacy.
2. Consumidores app/domain de bajo riesgo fueron migrados a `use App\Efact\Config\EfactConstants as Constants;` para mantener el nombre local `Constants::...` sin cambios funcionales.
3. Se dejaron imports legacy únicamente en clases aún ubicadas dentro de `src/Core/Service` (`BinlogBackupService`, `LogoProvider`) para evitar ampliar el alcance del task; esas clases ya están clasificadas como app/domain-specific y deberán moverse o aislarse en una fase posterior.
4. El guard de Platform prohíbe `App\Core\Util\Constants` y `App\Efact\Config\` desde `src/Platform`, asegurando que el paquete Composer futuro no dependa de settings efact.

No se modifican auth, filtros Doctrine ni política de conexión tenant en esta tarea.

## Candidato de futuro paquete Composer

Nombre tentativo: `efact/platform` o `efact/php-platform`.

### Boundary mínimo recomendado

Incluir en el paquete core portable:

- `Platform/Tenant/*` como contratos de tenant.
- `Platform/Billing/QuotaEnforcerInterface` o renombrado a capability genérica de quotas.
- `Context/TenantContext`.
- Value objects/mensajes genéricos: `Messenger/TenantStamp`, `Messenger/ActorStamp`, posiblemente `Message/TenantBackupMessage` si queda libre de política efact.
- `Http/ApiResponse`.
- `Exception/*` genéricas.
- `Cache/CacheManager`, `Filesystem/FileManager`, `Validation/Validator` si se acepta su set de dependencias.
- Modelos/puertos de audit y rate limit que no dependan de Symfony directamente.

Separar en paquete bridge opcional, por ejemplo `efact/platform-symfony`:

- listeners HTTP/Console;
- middleware Messenger;
- Monolog processors;
- Doctrine migration factories y filtros genéricos;
- compiler passes, annotations/attributes de container, Twig extensions genéricas;
- base command `TenantAwareCommand`.

### Exclusiones explícitas del paquete Composer

No incluir:

- `Core/Security/Auth/*` actual ni `Core/Symfony/Security/SecurityService.php`.
- `Core/Application/Session/Security.php` mientras su API devuelva `AuthUserDto` de Efact.
- `Core/Util/Constants.php` como fachada BC, `Efact/Config/EfactConstants.php` y cualquier catálogo SUNAT/CPE/ventas/compras/inventario/settings/feature flags.
- adaptadores de quota que conozcan entidades concretas, como `ControlPlane/Billing/Quota/EfactEntityQuotaResourceResolver`.
- filtros Doctrine con referencias a `Media`, `Purchase`, `User`, `TaxDocument`, `SalePayment`, `Branch` u otros modelos efact.
- `SettingsService`, `DashboardService`, `CompanyContext`, `LogoProvider` salvo rediseño como puertos/adaptadores app.
- exporters si incorporan formatos de reporte/comprobante de efact.
- `TenantConnection` actual mientras embeba política concreta de conexión/fallback YAML.

## Fases de migración y compatibilidad

| Fase | Objetivo | Compatibilidad esperada |
|---|---|---|
| 0. Inventario | Congelar este mapa y usarlo como criterio de revisión. | Sin cambios de código. `App\Core` sigue funcionando. |
| 1. Guardrails | Añadir checks de arquitectura para imports prohibidos en Platform core. | Fallar nuevos acoplamientos, no necesariamente todos los legacy de golpe. |
| 2. Puertos y contratos | Completar puertos para tenant, quota, backup, settings, auth/session si aplica. | Mantener aliases DI y clases legacy delegando. |
| 3. Mover app adapters | Reubicar security/auth, settings, dashboard, company, logo, filters y constants a bounded contexts/app. | Deprecations controladas; mantener facades temporales si consumidores son muchos. |
| 4. Separar Symfony bridge | Agrupar listeners, middleware, commands, compiler passes y Doctrine integrations. | Namespace nuevo con aliases desde `App\Core` durante una release interna. |
| 5. Package skeleton | Crear composer metadata/autoload para Platform y bridge. | Pruebas en monolito usando path repository antes de publicar. |
| 6. Extracción | Publicar paquete o subtree split cuando los checks estén limpios. | Remover aliases legacy sólo después de migrar consumidores. |

## Acoplamientos conocidos a eliminar

1. `Core/EventListener/QuotaEnforcementListener.php` ya no usa entidades efact directamente; el acoplamiento restante del mapeo `Branch`, `Customer`, `Product`, `TaxDocument`, `User` vive en `ControlPlane/Billing/Quota/EfactEntityQuotaResourceResolver` como app adapter.
2. `Core/Security/Auth/*` depende de `App\Entity\User`, `Branch`, `RefreshToken`, `App\Dto\Auth\AuthResponseDto`, `App\Repository\RefreshTokenRepository`. Debe salir del paquete Platform.
3. `Core/Util/Constants.php` es una fachada BC hacia `Efact/Config/EfactConstants.php`; los consumidores restantes en `Core/Service` deben migrarse cuando esos servicios salgan de Core o se inviertan detrás de puertos.
4. `Core/Doctrine/Filter/SoftDeleteFilter.php` referencia `Media`, `Purchase`, `User`; `DevExtremeFilter.php` referencia `TaxDocument`, `SalePayment` y campos virtuales de ventas. Deben moverse a app o parametrizarse.
5. `Core/Doctrine/DBAL/TenantConnection.php` embebe política efact de conexión de tenant (`dbname`/`user`) y fallback YAML. Debe separarse en contrato + estrategia app.
6. `SettingsService`, `DashboardService`, `CompanyContext`, `LogoProvider` y exporters están mezclados en Core pero no son necesariamente Platform. Clasificar y mover antes de extracción Composer.
7. `Core/Symfony/Security/SecurityService.php` y `Core/Application/Session/Security.php` conocen DTOs/entidades de auth de efact. Deben migrar a app auth/session.
8. `Core/Util/ReceiptPayload.php` expresa payload de comprobante/recibo y debe salir de Platform.

## Mapeo a acceptance criteria de SPEC-004

| AC | Cobertura en este documento |
|---|---|
| **AC-1** | Este inventario clasifica los subárboles/archivos actuales de `src/Core`, identifica qué es Platform core, adaptador Symfony, app adapter/domain-specific, utilidad compartida, compatibilidad transicional o exclusión, y lista acciones recomendadas. |
| **AC-12** | Define la frontera Composer futura, exclusiones explícitas, reglas de dirección de dependencias y fases de migración compatibles para preparar extracción sin mover código en esta tarea. |

## Validación ligera

Para cambios de frontera Platform/Auth, validación recomendada:

```bash
docker compose exec -T web composer check:platform-boundary
docker compose exec -T web composer test:platform-boundary-guard
docker compose exec -T web php bin/console lint:container
```

Checks útiles para tareas posteriores:

```bash
rg '^use App\\(Entity|Dto|Repository|ControlPlane|Setup|EInvoicing)' src/Core
rg '^use App\\ControlPlane' src/Core
```
