# Control Plane — Plan de modernización UI

Auditoría visual capturada con Playwright el 2026-07-04. Screenshots en `docs/ui-audit/screenshots/`.

## Estado actual (hallazgos)

| Pantalla | Screenshot | Problemas detectados |
|----------|------------|----------------------|
| Login | `00-login.png` | Pulido (Bootstrap + tema claro/oscuro). Desalineado con el SPA: acento verde vs azul del panel. |
| Dashboard | `01-dashboard.png` | Métricas planas en cards genéricas; sin jerarquía visual; secciones apiladas sin ritmo. |
| Tenants | `02-tenants-list.png` | Mezcla EN/ES; formulario y tabla en grid rígido; botones inconsistentes. |
| Plans | `03-plans.png` | Layout denso; cards de plan con muchos controles inline; copy en inglés. |
| Audit log | `05-audit-log.png` | Tabla ancha con JSON crudo; filtros básicos; modal de metadata pobre. |
| Tenant detail | `07-tenant-detail-general.png` | 9 tabs horizontales sin scroll elegante; header y acciones duplican lista. |
| Certificados | `08-tenant-certificates.png` | Formulario legacy; modal PEM sin estructura clara. |

### Problemas transversales

1. **Doble design system**: SCSS custom (`cp-*`) + Bootstrap Icons, mientras el tenant app usa `@nubitio/admin` + `@nubitio/ui` + Phosphor.
2. **Sin shell responsive**: sidebar fija 260px; sin colapso ni overlay móvil.
3. **Sin theme switcher** en el SPA (solo en login Twig).
4. **i18n incompleta**: ~40% de strings aún en inglés en forms y tabs.
5. **Componentes ad-hoc**: botones, badges, tablas y modales reinventados en SCSS.
6. **Densidad inconsistente**: padding 2rem fijo; cards con sombras distintas al resto de eFact.

## Dirección de diseño

**Alinear el Control Plane con el shell operativo del tenant** (`EfactAdminShell` → `AdminShell`), no inventar un tercer sistema visual.

### Principios

- **Un solo stack**: `@nubitio/admin` (shell) + `@nubitio/ui` (primitivos) + tokens `app-theme-*`.
- **Operador-first**: densidad media, lectura rápida de estado (KPI → tabla → detalle).
- **Español consistente**: `CP_STRINGS` como única fuente de copy UI.
- **Progressive disclosure**: tabs tipo Feature Hub; JSON/metadata en drawer/modal, no en tabla.
- **Accesibilidad**: focus visible, `aria-*` en tabs, contraste WCAG AA con temas light/dark.

### Paleta y tipografía

- Heredar tokens de `@nubitio/ui` (`--surface-*`, `--accent-color`, `--text-*`).
- Eliminar variables `--cp-*` duplicadas; mantener solo overrides mínimos en `control-plane.scss`.
- Iconografía: **Phosphor** (`ph ph-*`), deprecar Bootstrap Icons en el SPA.

## Arquitectura de componentes (target)

```
ControlPlaneShell          → AdminShell + menú por rol + ThemeSwitcher
├── layout/
│   ├── CpPage             → contenedor de página
│   ├── CpPageHeader       → título, breadcrumb, acciones
│   ├── CpMetricGrid       → KpiMetricRow wrapper
│   ├── CpHubTabs          → tabs estilo FeatureHub (query params)
│   ├── CpDataTable        → tabla con EmptyState
│   └── CpAlert            → alertas inline
└── pages/                 → consumen primitivos, sin clases cp-btn/cp-card
```

## Fases de implementación

### Fase 1 — Fundación (esta entrega)

- [x] Auditoría Playwright + este documento
- [x] `AdminShell` reemplaza `cp-sidebar` custom
- [x] Import `@nubitio/admin/style.css` + Phosphor
- [x] Primitivos `layout/*`
- [x] Rediseño: Dashboard, Tenant list, Tenant detail (header + tabs)
- [x] `TenantStatusBadge` → `Badge` de `@nubitio/ui`
- [x] Screenshots post-cambio en `screenshots/after/`

### Fase 2 — Pantallas restantes

- [x] Plans + Plan features → `Card`, `FormField`, `Button`, presets como `Chip`
- [x] Audit log → filtros con `FormField`, metadata en `Drawer`, paginación con `Button`
- [x] Users CRUD → formularios `@nubitio/ui` + `CpHubTabs` en edición
- [x] Tabs de tenant (General, Domains, …) — migradas a `@nubitio/ui`

### Fase 3 — Pulido

- [x] Login Twig alineado con tokens del SPA (`cp-theme`, `app-theme-*.css`, acento azul)
- [x] Toasts unificados via `CpToastProvider` + `ToastHost` (`@nubitio/admin`)
- [x] Estados vacíos con `EmptyState` en tabs migradas (Fase 2)
- [x] Skeleton loading (`CpPageSkeleton`) en páginas principales
- [x] Regresión visual Playwright (`e2e/control-plane-visual.spec.ts`)

## Criterios de aceptación

1. Shell idéntico en comportamiento al tenant app (colapso, overlay móvil, theme toggle).
2. Cero clases `cp-btn`, `cp-card`, `cp-table` en páginas migradas de Fase 1–2 (solo utilidades: `cp-data-table`, `cp-progress`, `cp-narrow`).
3. 100% copy UI en español (`CP_STRINGS`).
4. `yarn build` sin errores; `tests/ControlPlane/` verde.
5. Screenshots post-cambio en `docs/ui-audit/screenshots/after/`.

## Referencias

- ADR-005: `docs/adr/ADR-005-control-plane-react-spa-layered-rewrite.md`
- Shell tenant: `assets/app/shell/EfactAdminShell.tsx`
- Feature hub pattern: `assets/features/settings/SettingsHubLayout.tsx`