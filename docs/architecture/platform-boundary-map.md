# Mapa de frontera Platform (SPEC-004)

Referencia rápida de qué código es portable (`Nubit\Platform`) vs qué permanece en la aplicación efact. El plan de extracción completo está en [nubit-backend-extraction-plan.md](./nubit-backend-extraction-plan.md).

---

## Namespaces

| Namespace | Ubicación actual → objetivo | Publicable |
|-----------|----------------------------|------------|
| `Nubit\Platform\` | `src/Platform/` → `packages/platform/` | Sí (`nubit/platform`) |
| `Nubit\ApiPlatform\` | `src/Efact/` (bridge) → `packages/api-platform/` | Sí (`nubit/api-platform`) |
| `Nubit\AdminBundle\` | nuevo → `packages/admin-bundle/` | Sí (`nubit/admin-bundle`) |
| `App\Efact\` | infra efact-specific post-migración | No |
| `App\ControlPlane\` | SaaS multi-tenant | No |
| `App\Identity\` | efact prod; lógica genérica → bundle | Parcial |
| `App\{Billing,Sales,Catalog,...}\` | Dominio | No |

`src/Core/` está retirado; ver `src/Core/README.md`.

---

## Reglas (guard automático)

El script `php scripts/architecture/check-platform-boundary.php` falla si rutas candidatas importan:

- `App\Entity\`, `App\Repository\`, dominios concretos (`Billing`, `Inventory`, …)
- En `src/Platform` además: `App\Efact\Config\`, `App\Core\Doctrine\`, etc.

**Ejecutar en CI** tras cada cambio en `packages/platform`.

---

## Contratos Platform → implementación en efact

| Contrato (`Nubit\Platform`) | Implementación efact | Implementación starter |
|----------------------------|----------------------|-------------------------|
| `TenantRegistryInterface` | `ControlPlane\Tenant\Service\TenantManager` | Noop single-tenant |
| `TenantConnectionSwitcherInterface` | `ControlPlane\Tenant\Service\TenantConnectionSwitcher` | Noop |
| `FeatureCheckerInterface` | `ControlPlane\Billing\Feature\TenantFeatureChecker` | Allow-all / noop |
| `QuotaEnforcerInterface` | `ControlPlane\Billing\Service\TenantQuotaService` | Noop |
| `QuotaResourceResolverInterface` | `ControlPlane\Billing\Quota\EfactEntityQuotaResourceResolver` | Noop |

Bindings en `config/services.yaml` (sección SPEC-004/005).

---

## Bridge CRUD (no es Platform)

Estos componentes **no** viven en `Nubit\Platform`; pasan a `nubit/api-platform`:

- `DataGridFilter`
- `TranslatedDocumentationNormalizer` (`x-crud`)
- `ApiResponseListener` (`X-Total-Count`)

Sin ellos, `@nubit/hydra` y el grid de `@nubit/crud` no coinciden con el backend.

---

## Diagrama de dependencias permitidas

```mermaid
flowchart TB
  PLAT["nubit/platform"]
  API["nubit/api-platform"]
  BUNDLE["nubit/admin-bundle"]
  APP["App dominio efact"]
  CP["App ControlPlane"]

  API --> PLAT
  BUNDLE --> API & PLAT
  APP --> BUNDLE & PLAT
  CP --> PLAT
  APP -.->|override contratos| CP
```

**Prohibido:** `PLAT --> APP` o `PLAT --> CP`.