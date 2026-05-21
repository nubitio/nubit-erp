# Frontend Nubit Package Extraction Plan

## Goal

Extract the reusable React foundation from `assets/` into an opinionated Nubit frontend toolkit for future admin-style projects, while keeping eFact as the dogfood consumer during the migration.

The target is not a generic component dump. The target is a batteries-included stack for business/admin apps: UI primitives, runtime services, CRUD/forms/grid, admin shell, and API Platform adapters.

## Current State

Status as of the latest audit: the extraction is no longer at the initial facade stage. eFact now dogfoods multiple in-repo `@nubit/*` facades, package manifests exist, and package builds have started. The remaining work is mostly about physical separation, publishability, and contract hardening.

- `assets/core/package.json` declares `@nubit/core`.
- `assets/ui`, `assets/crud`, `assets/admin`, `assets/api-platform`, and `assets/react-admin` declare focused `@nubit/*` package facades.
- `assets/core/public.ts`, `assets/ui/public.ts`, `assets/crud/public.ts`, `assets/admin/public.ts`, `assets/api-platform/public.ts`, and `assets/react-admin/public.ts` are the active public entrypoints.
- `tsconfig.json` and `vite.config.js` map the active `@nubit/*` facades to their `public.ts` entrypoints.
- `tsdown.config.ts` builds the in-repo packages to `dist/` with ESM, CJS, and declaration outputs.
- `docs/adr/ADR-015-frontend-architecture-sanitization.md` defines the current frontend layers: `@app`, `@features`, `@shared`, `@core`, and `@offline`.
- `assets/core/README.md` already tells consumers to prefer the public facade over implementation imports.
- Application and feature code already imports broadly from `@nubit/core`, `@nubit/crud`, `@nubit/ui`, `@nubit/admin`, and `@nubit/api-platform`.
- No direct `@core/*` imports were found in `assets/**/*.ts(x)` during the audit.
- Package-local tests exist for promoted surfaces such as UI dialogs/theme, admin shell/header, core HTTP, API Platform parsing, and remote data sources.

The boundary is still internal and incomplete, but CRUD implementation has been physically moved under `assets/crud`, the old `assets/core/*` CRUD/form/field/datagrid/dialog shims have been removed, and the remaining legacy `assets/core/ui/CrudViews.ts` and `assets/core/data/ApiPlatformRemoteDataSource.ts` compatibility files have been removed. Publishability is not complete because package manifests remain `private: true` and package-level CRUD coverage still needs to be expanded.

## Phase Status

| Phase | Status | Notes |
| --- | --- | --- |
| Phase 1: Stabilize Internal Facades | Mostly complete | Public facades exist and app code has moved away from `@core/*`; lint rules restrict implementation imports, although some rules remain warnings outside package internals. |
| Phase 2: Extract `@nubit/ui` Internally | Mostly complete | `AppDialog`, `ConfirmDialog`, `Card`, `AppToolbar`, theme provider, and `ThemeSwitcher` live behind `@nubit/ui`; tests exist. |
| Phase 3: Harden `@nubit/core` | In progress | Runtime/config/HTTP/event/Mercure/date APIs exist. Locale/timezone are configurable, but compatibility exports such as `LIMA_TZ` remain. |
| Phase 4: Split CRUD and API Platform Concerns | Mostly complete | `@nubit/api-platform` has its own implementation; `@nubit/crud` now owns field, form, datagrid, dialog, and CRUD implementation under `assets/crud`. Old `assets/core/*` shims for these areas have been removed. |
| Phase 5: Extract Admin Shell | Mostly complete | `@nubit/admin` exists, has tests, and eFact consumes `AdminShell` through app-owned adapters. |
| Phase 6: Optional Offline Package | Not started | Offline remains deferred until generic runtime and eFact/POS sync rules are separated. |
| Phase 7: Publishable Workspace Packages | Started | `tsdown` build exists and `dist/` outputs are generated. Packages are still private and not dogfooded as real workspace dependencies. |

## Package Model

Use separate packages rather than one oversized `core` package. This keeps the batteries-included experience possible through an umbrella package without forcing every consumer to install the full admin stack.

| Package | Responsibility | Depends On | Initial Source Areas |
| --- | --- | --- | --- |
| `@nubit/ui` | Visual primitives, theme tokens, base styles, dialogs, buttons, cards, alerts, toasts, badges, popovers, toolbars, loading/error/empty states. | React, React DOM | `assets/ui`, reusable parts of `assets/shared/ui`, `assets/shared/components`, `assets/theme` |
| `@nubit/core` | Runtime services and app-agnostic infrastructure: HTTP client, runtime provider, event bus, i18n registration, date/locale helpers, base data contracts. | `@nubit/ui` only where needed | `assets/core/http`, `assets/core/runtime`, `assets/core/event`, `assets/core/i18n`, `assets/core/data`, configurable parts of `assets/core/date` |
| `@nubit/forms` | Field DSL, form engine, validation, computed values, submit handling, form layouts. Can remain inside `@nubit/crud` until it needs independent consumption. | `@nubit/ui`, `@nubit/core` | `assets/core/field`, `assets/core/form` |
| `@nubit/crud` | SmartCrud, CrudPage, data grid, resource config, permissions, presets, audit panel, routing hooks. | `@nubit/ui`, `@nubit/core`, `@nubit/forms`, React Query, React Router | `assets/core/crud`, `assets/core/datagrid`, `assets/core/dialog` |
| `@nubit/api-platform` | API Platform/Hydra/OpenAPI adapter: schema discovery, field inference, remote data source, IRI conventions. | `@nubit/core`, optionally `@nubit/crud` | `assets/core/crud/hydra`, `assets/core/data/ApiPlatformRemoteDataSource.ts` |
| `@nubit/admin` | Admin panel shell: sidebar layout, header, footer, route-aware menu, header actions, user menu slots, notification slots, fullscreen/authenticated/unauthenticated layouts. | `@nubit/ui`, `@nubit/core`, React Router | reusable parts of `assets/app/shell`, `assets/layouts`, `assets/utils/media-query.tsx` |
| `@nubit/offline` | Offline runtime, connectivity, outbox, sync, Dexie helpers. Extract later after POS/eFact adapters are isolated. | `@nubit/core`, Dexie | `assets/offline` |
| `@nubit/react-admin` | Umbrella package that re-exports the common batteries-included stack for fast project setup. | all stable packages | new package only |

## Dependency Direction

Packages must depend inward and never back into eFact app code.

```txt
@nubit/ui
  <- @nubit/core
  <- @nubit/forms
  <- @nubit/crud
  <- @nubit/api-platform
  <- @nubit/admin
  <- eFact app
```

`@nubit/react-admin` may re-export stable APIs from the other packages, but it should not become the owner of implementation logic.

## Public Contract Principles

- Public imports go through package facades only, not internal paths like `@core/*`.
- UI primitives must not know about auth, roles, menu services, API Platform, tenants, POS, SUNAT, or eFact routes.
- Core services must be app-agnostic and configured through providers or constructor options.
- CRUD can be opinionated, but API Platform/Hydra behavior should live behind an adapter package.
- Admin shell owns layout behavior, not business data. Menus, users, notifications, assistant widgets, and footer content are injected through props/slots/adapters.
- Offline extraction is deferred until eFact/POS-specific sync rules are separated from the generic offline runtime.

## Immediate Extraction Candidates

### `@nubit/ui`

Start here because it lowers coupling for every other package.

Candidates:

- `AppDialog` and `ConfirmDialog` now live in `assets/ui` and are exported by `@nubit/ui`.
- `Card` from `assets/shared/components/Card` or `assets/shared/ui` after removing `@app/types` coupling.
- `AppToolbar` from `assets/shared/ui/toolbar` after making labels/actions configurable.
- Base button/icon-button/badge styles from the current SCSS system.
- Common loading, error, empty, alert, toast, avatar, popover primitives.
- Theme tokens and `ThemeSwitcher` after making storage key, CSS path, theme names, and meta-color behavior configurable.

Must not include:

- `Settings`, `Constants`, `menuService`, `branchService`, `userInformation`, eFact auth, POS flows, or feature services.

### `@nubit/core`

Candidates:

- `CoreProvider`, `CoreRuntimeProvider`, `useCoreRuntime`.
- `CoreHttpClient`, `CoreHttpProvider`, `createCoreHttpClient`.
- Event bus helpers from `assets/core/event`.
- Data contracts from `assets/core/data`.
- `initCoreI18n`, `useCoreTranslation` after removing direct app i18n imports.
- `DateUtils` only after timezone and locale become configurable.

Known cleanup before publishing:

- Remove direct dependency on app-global i18n from `useHydraMetadata`.
- Make locale/timezone configurable; do not hardcode `es-PE` or `America/Lima` as package defaults unless the consumer opts in.
- Keep browser `fetch` usage contained in the HTTP client and documented.

### `@nubit/crud` and `@nubit/api-platform`

Candidates:

- `SmartCrudPage`, `CrudPage`, `defineResource`, `defineFields`.
- Field builders and field contracts.
- Form/grid engines and handles.
- Hydra/OpenAPI parser and field mapper.
- `ApiPlatformRemoteDataSource`.

Split rule:

- CRUD owns resource/page/form/grid behavior.
- API Platform owns schema discovery, IRI behavior, and metadata-to-field inference.

Current gap:

- `@nubit/api-platform` is physically separated under `assets/api-platform`.
- `@nubit/crud` owns field, form, datagrid, dialog, and CRUD implementation under `assets/crud`.
- Old compatibility shims under `assets/core/crud`, `assets/core/field`, `assets/core/form`, `assets/core/datagrid`, and `assets/core/dialog` have been removed after migrating remaining consumers to `@nubit/crud`.
- Legacy compatibility files `assets/core/ui/CrudViews.ts` and `assets/core/data/ApiPlatformRemoteDataSource.ts` have been removed; CRUD now imports the API Platform remote data source from `@nubit/api-platform`.

### `@nubit/admin`

Candidates:

- `SideNavOuterToolbar` as `AdminLayout`.
- `AppHeader` as `AdminHeader`, with header actions injected.
- `SideNavigationMenu` as `AdminSidebarMenu`, with items/loaders injected.
- `AppFooter` as configurable footer slot.
- `SingleCard` as `SingleCardLayout`.
- `useScreenSize` and responsive shell behavior.

Required decoupling:

- `AppHeader` must not import `@features/assistant`, `useNotifications`, or concrete `UserPanel`.
- `SideNavigationMenu` must not call `userInformation()` or `menuService`.
- Footer content must be provided by the consuming app.
- Branch selection, auth state, tenant/warehouse selection, and POS fullscreen route decisions stay in eFact app adapters.

Target shell shape:

```tsx
<AdminShell
  title="My Admin"
  menuItems={items}
  currentUser={user}
  headerActions={[
    {
      id: 'notifications',
      icon: 'ph ph-bell',
      label: 'Notifications',
      badge: unreadCount,
      renderPanel: ({ close }) => <NotificationsPanel onClose={close} />,
    },
  ]}
  renderUserMenu={({ close }) => <UserMenu onClose={close} />}
  footer={<span>Copyright © 2026 Nubit Labs</span>}
>
  <AppRoutes />
</AdminShell>
```

## Migration Plan

### Phase 1: Stabilize Internal Facades

Status: mostly complete.

- Treat `assets/core/public.ts` as the only supported internal library contract.
- Move simple feature imports from `@core/*` to `@nubit/core`.
- Add or tighten lint rules so feature code stops importing `@core/*` directly once migrated.
- Keep implementation files in place until the public contract is stable.

### Phase 2: Extract `@nubit/ui` Internally

Status: mostly complete.

- Create a package boundary for UI primitives, still inside this repo.
- Move `AppDialog`, `ConfirmDialog`, card, toolbar, button/icon-button/badge styles, and basic states.
- Replace app/shared/core UI imports with `@nubit/ui` facade imports.
- Add component-level tests for dialog focus/close behavior and basic rendering.

### Phase 3: Harden `@nubit/core`

Status: in progress.

- Make locale/timezone/runtime/i18n explicit provider options.
- Keep `CoreHttpClient` as the canonical generic HTTP client.
- Deprecate or adapt `assets/shared/api/HttpClient.ts` into app-owned eFact integration code.
- Add tests for URL joining, param serialization, refresh retry, error parsing, and provider behavior.

Remaining cleanup:

- Decide whether `LIMA_TZ` remains as documented compatibility or moves to an eFact-owned adapter before publication.
- Audit `@nubit/core` exports for app-specific defaults, labels, routes, and API paths.
- Convert broad lint warnings that protect public package boundaries into errors once migration exceptions are removed.

### Phase 4: Split CRUD and API Platform Concerns

Status: mostly complete.

- Move form/grid/CRUD contracts behind package facades.
- Move Hydra/OpenAPI behavior behind `@nubit/api-platform`.
- Make Mercure integration optional and provider-driven.
- Add mapper/parser tests and minimal React tests for `SmartCrudPage` loading/error states.

Remaining cleanup:

- Add package-level tests around `SmartCrudPage`, `CrudPage`, form/grid loading/error states, and permission behavior.

### Phase 5: Extract Admin Shell

Status: mostly complete.

- Introduce `@nubit/admin` facade.
- Refactor shell components to receive menu/user/header/footer/notification/assistant behavior through slots or adapters.
- Keep eFact-specific `EfactApp`, auth provider, branch picker, and route selection in `assets/app`.
- Make eFact consume `@nubit/admin` internally before publishing.

Remaining cleanup:

- Keep eFact-specific shell composition in `assets/app/shell` and prevent new imports from `@nubit/admin` internals.
- Add coverage for sidebar menu selection and header slot behavior if those APIs are intended to be stable.

### Phase 6: Optional Offline Package

Status: not started.

- Inventory `assets/offline` for generic runtime vs POS/eFact sync rules.
- Extract only after adapters and domain payloads are isolated.

### Phase 7: Publishable Workspace Packages

Status: started.

- Add package build tooling (`tsup`, Vite library mode, or equivalent) only after internal facades are stable.
- Emit `.d.ts` and ESM builds.
- Mark React, React DOM, React Query, React Router, i18next, and Dexie as peer dependencies where appropriate.
- Dogfood through workspace/path dependencies before external publication.

Remaining cleanup:

- Decide whether generated `dist/` files are committed artifacts or CI/release artifacts only.
- Add actual workspace/path dependency dogfooding if packages should behave like publishable packages before release.
- Flip package manifests from `private: true` only after dependency boundaries, CSS entrypoints, peer dependencies, and tests are stable.
- Include `@nubit/crud` in the `@nubit/react-admin` package dependencies before publishing; it is currently re-exported by the umbrella facade and must be an explicit dependency.

## Next Extraction Slices

1. Add package tests for CRUD rendering, loading/error states, permissions, and form/grid event behavior.
2. Tighten `no-restricted-imports` from warnings to errors for migrated public facade rules.
3. Audit package manifests and peer dependencies, especially CSS/style entrypoints.
4. Decide the release policy for generated `dist/` outputs.

## Readiness Checklist

- [x] eFact consumes the package boundary internally before publication.
- [x] Public application imports broadly go through `@nubit/*` facades.
- [x] No direct `@core/*` imports were found in `assets/**/*.ts(x)` during the audit.
- [x] `@nubit/ui` primitives avoid auth, feature, and app-shell dependencies.
- [x] Admin shell components receive app-specific behavior through props, slots, or adapters.
- [x] Package-local tests exist for several promoted surfaces.
- [x] CRUD implementation is physically owned by `assets/crud` rather than re-exported from `assets/core/*`.
- [ ] No package candidate imports from `@app/*`, `@features/*`, eFact services, or concrete business routes after the final physical move.
- [ ] Locale, timezone, theme paths, storage keys, API base URL, auth refresh paths, and footer branding are all configurable and documented.
- [ ] Package manifests are complete for publication, including explicit dependencies, peer dependencies, CSS entrypoints, and `private` policy.
- [ ] Lint rules enforce package boundaries as errors for migrated areas.

## Known Risks

- Extracting shell too early can freeze eFact-specific assumptions into the package. Mitigate with slots/adapters first.
- A single oversized `@nubit/core` can become hard to reason about. Mitigate with `ui`, `core`, `crud`, `api-platform`, and `admin` boundaries.
- Theme styles can leak global class assumptions. Mitigate with documented CSS variables, stable class naming, and explicit style entrypoints.
- SmartCrud can become too API Platform-specific. Mitigate by keeping API Platform behavior in its own adapter package.
- Lack of unit tests around the current core can make extraction risky. Mitigate by adding package-local tests before moving files physically.
- Facade-only extraction can hide real package coupling. Mitigate by moving implementation ownership into each package before treating the packages as publishable.
- Committed `dist/` files can drift from source. Mitigate by deciding whether they are release artifacts or checked-in build outputs and enforcing that policy in CI.

## Related Documents

- `assets/core/README.md`
- `docs/adr/ADR-014-smartcrud-sdui-hydra-metadata.md`
- `docs/adr/ADR-015-frontend-architecture-sanitization.md`
- `docs/adr/ADR-017-smartcrud-canonical-field-contract.md`
