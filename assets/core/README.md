# @nubit/core

Opinionated internal React core for Efact-style administrative apps.

This package is the current internal facade for reusable frontend infrastructure. It is not the long-term owner for every reusable frontend concern. The extraction plan separates visual primitives, admin shell, CRUD, and API Platform adapters into focused `@nubit/*` packages while eFact remains the dogfood consumer.

Consumers should import from the public facade:

```ts
import { CoreProvider, SmartCrudPage, defineResource, textField } from '@nubit/core';
```

Avoid importing implementation modules from `@core/*` in app/features. Those paths remain available while the existing app is migrated, but they are not the library contract.

Current public surface includes:

- `CoreProvider`
- `CoreHttpClient` and `createCoreHttpClient`
- `SmartCrudPage`, `CrudPage`, `defineResource`, `defineFields`
- field builders and field types
- form/grid handles and common data contracts
- compatibility re-exports for base UI primitives from `@nubit/ui`: `AppDialog`, `ConfirmDialog`

Planned package split:

- `@nubit/ui` for UI primitives, theme tokens, dialogs, buttons, cards, alerts, toasts, badges, popovers, and base styles.
- `@nubit/core` for runtime services, HTTP, events, i18n, locale/timezone configuration, and app-agnostic data contracts.
- `@nubit/forms` for field DSL and form behavior if it needs independent consumption.
- `@nubit/crud` for SmartCrud, CrudPage, data grid, resource configuration, permissions, and audit/preset behavior.
- `@nubit/api-platform` for Hydra/OpenAPI schema discovery and API Platform data-source adapters.
- `@nubit/admin` for the admin panel shell: sidebar, header, footer, layouts, menu rendering, and header/user/action slots.
- `@nubit/react-admin` as an optional umbrella package that re-exports the stable batteries-included stack.

See `docs/architecture/frontend-nubit-extraction-plan.md` for the full roadmap and package boundaries.
