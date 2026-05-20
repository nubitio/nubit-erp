# Frontend Nubit Package Extraction Plan

## Goal

Extract the reusable React foundation from `assets/` into an opinionated Nubit frontend toolkit for future admin-style projects, while keeping eFact as the dogfood consumer during the migration.

The target is not a generic component dump. The target is a batteries-included stack for business/admin apps: UI primitives, runtime services, CRUD/forms/grid, admin shell, and API Platform adapters.

## Current State

The frontend already contains the beginning of the package boundary:

- `assets/core/package.json` declares `@nubit/core`.
- `assets/core/public.ts` is the intended public facade.
- `tsconfig.json` and `vite.config.js` map `@nubit/core` to `assets/core/public.ts`.
- `docs/adr/ADR-015-frontend-architecture-sanitization.md` defines the current frontend layers: `@app`, `@features`, `@shared`, `@core`, and `@offline`.
- `assets/core/README.md` already tells consumers to prefer the public facade over implementation imports.

The boundary is still internal and incomplete. Many features still import implementation modules through `@core/*`, and the current shell/UI code is split between `assets/app`, `assets/shared`, `assets/layouts`, `assets/theme`, and `assets/core`.

## Package Model

Use separate packages rather than one oversized `core` package. This keeps the batteries-included experience possible through an umbrella package without forcing every consumer to install the full admin stack.

| Package | Responsibility | Depends On | Initial Source Areas |
| --- | --- | --- | --- |
| `@nubit/ui` | Visual primitives, theme tokens, base styles, dialogs, buttons, cards, alerts, toasts, badges, popovers, toolbars, loading/error/empty states. | React, React DOM | `assets/core/ui`, `assets/core/components`, reusable parts of `assets/shared/ui`, `assets/shared/components`, `assets/theme` |
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

- `AppDialog` from `assets/core/ui/app-dialog`.
- `ConfirmDialog` from `assets/core/components`.
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

- Treat `assets/core/public.ts` as the only supported internal library contract.
- Move simple feature imports from `@core/*` to `@nubit/core`.
- Add or tighten lint rules so feature code stops importing `@core/*` directly once migrated.
- Keep implementation files in place until the public contract is stable.

### Phase 2: Extract `@nubit/ui` Internally

- Create a package boundary for UI primitives, still inside this repo.
- Move `AppDialog`, `ConfirmDialog`, card, toolbar, button/icon-button/badge styles, and basic states.
- Replace app/shared/core UI imports with `@nubit/ui` facade imports.
- Add component-level tests for dialog focus/close behavior and basic rendering.

### Phase 3: Harden `@nubit/core`

- Make locale/timezone/runtime/i18n explicit provider options.
- Keep `CoreHttpClient` as the canonical generic HTTP client.
- Deprecate or adapt `assets/shared/api/HttpClient.ts` into app-owned eFact integration code.
- Add tests for URL joining, param serialization, refresh retry, error parsing, and provider behavior.

### Phase 4: Split CRUD and API Platform Concerns

- Move form/grid/CRUD contracts behind package facades.
- Move Hydra/OpenAPI behavior behind `@nubit/api-platform`.
- Make Mercure integration optional and provider-driven.
- Add mapper/parser tests and minimal React tests for `SmartCrudPage` loading/error states.

### Phase 5: Extract Admin Shell

- Introduce `@nubit/admin` facade.
- Refactor shell components to receive menu/user/header/footer/notification/assistant behavior through slots or adapters.
- Keep eFact-specific `EfactApp`, auth provider, branch picker, and route selection in `assets/app`.
- Make eFact consume `@nubit/admin` internally before publishing.

### Phase 6: Optional Offline Package

- Inventory `assets/offline` for generic runtime vs POS/eFact sync rules.
- Extract only after adapters and domain payloads are isolated.

### Phase 7: Publishable Workspace Packages

- Add package build tooling (`tsup`, Vite library mode, or equivalent) only after internal facades are stable.
- Emit `.d.ts` and ESM builds.
- Mark React, React DOM, React Query, React Router, i18next, and Dexie as peer dependencies where appropriate.
- Dogfood through workspace/path dependencies before external publication.

## Readiness Checklist

- [ ] No package candidate imports from `@app/*`, `@features/*`, eFact services, or concrete business routes.
- [ ] Public imports go through `@nubit/*` facades.
- [ ] UI components have no data-fetching or auth assumptions.
- [ ] Shell components receive app-specific behavior through props, slots, or adapters.
- [ ] Locale, timezone, theme paths, storage keys, API base URL, auth refresh paths, and footer branding are configurable.
- [ ] Package-local tests exist for the promoted surface.
- [ ] eFact consumes the package boundary before publication.

## Known Risks

- Extracting shell too early can freeze eFact-specific assumptions into the package. Mitigate with slots/adapters first.
- A single oversized `@nubit/core` can become hard to reason about. Mitigate with `ui`, `core`, `crud`, `api-platform`, and `admin` boundaries.
- Theme styles can leak global class assumptions. Mitigate with documented CSS variables, stable class naming, and explicit style entrypoints.
- SmartCrud can become too API Platform-specific. Mitigate by keeping API Platform behavior in its own adapter package.
- Lack of unit tests around the current core can make extraction risky. Mitigate by adding package-local tests before moving files physically.

## Related Documents

- `assets/core/README.md`
- `docs/adr/ADR-014-smartcrud-sdui-hydra-metadata.md`
- `docs/adr/ADR-015-frontend-architecture-sanitization.md`
- `docs/adr/ADR-017-smartcrud-canonical-field-contract.md`
