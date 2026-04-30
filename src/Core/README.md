# Core (retired)

**This directory has been fully migrated. `src/Core` is now empty.**

All classes that lived here have been moved to their proper bounded contexts:

| Former location | New location |
|-----------------|-------------|
| `Core\Exception\*` | `Nubit\Platform\Exception\*` |
| `Core\Application\Command\TenantAwareCommand` | `Nubit\Platform\Symfony\Console\TenantAwareCommand` |
| `Core\Context\TenantContext` | `Nubit\Platform\Tenant\Context\TenantContext` |
| `Core\Cache\CacheManager` | `Nubit\Platform\Cache\CacheManager` |
| `Core\Filesystem\FileManager` | `Nubit\Platform\Filesystem\FileManager` |
| `Core\Logging\TenantLogProcessor` | `Nubit\Platform\Observability\Logging\TenantLogProcessor` |
| `Core\Messenger\Tenant*Middleware` | `Nubit\Platform\Messenger\Tenant*Middleware` |
| `Core\Platform\*Interface` | `Nubit\Platform\*\Contract\*Interface` |
| `Core\Util\{Array,Date,String,Number}Helper` | `Efact\Util\*` |
| `Core\Util\Money` | `Efact\Domain\Money` |
| `Core\Http\ApiResponse` | `Efact\Http\ApiResponse` |
| `Core\Annotation\IgnoreAutowire` | `Efact\Annotation\IgnoreAutowire` |
| `Core\Audit\*` | `Efact\Audit\*` |
| `Core\Security\CspNonceService` | `Efact\Web\CspNonceService` |
| `Core\EventListener\CspNonceListener` | `Efact\Web\CspNonceListener` |
| `Core\EventListener\{Api,Exception}*Listener` | `Efact\Http\*` |
| `Core\EventListener\QuotaEnforcementListener` | `ControlPlane\EventListener\*` |
| `Core\EventListener\SoftDeleteFilterListener` | `Efact\Doctrine\*` |
| `Core\EventListener\{Tenant,Console}*Listener` | `Efact\EventListener\*` |
| `Core\Extension\RemoteEscposImage` | `EInvoicing\Extension\*` |
| `Core\Filesystem\S3UrlResolver` | `Efact\Filesystem\*` |
| `Core\Message\TenantBackupMessage` | `ControlPlane\Message\*` |
| `Core\MessageHandler\TenantBackupMessageHandler` | `ControlPlane\MessageHandler\*` |
| `Core\Migrations\*` | `Efact\Migrations\*` |
| `Core\RateLimit\*` | `Efact\RateLimit\*` |
| `Core\Traits\*` | `Efact\Traits\*` |
| `Core\Validation\Validator` | `Efact\Validation\Validator` |
| `Core\Validator\UniqueEntity*` | `Efact\Validator\*` |
| `Core\{Color,ApiPlatform,Compiler,Controller,Entity}` | `Efact\{Util,ApiPlatform,Compiler,Controller,Domain}` |
