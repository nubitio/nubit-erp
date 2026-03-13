# assets/core — Internal Platform Library

## What is this?

`assets/core/` is the internal platform library for the efact frontend. It contains:

- **Field system** — reusable field definitions, column descriptors, and editor factories used across all feature modules
- **Form builders** — higher-level form configuration helpers that wire DevExtreme form editors to domain schemas
- **DataGrid wrappers** — typed wrappers and column-builder utilities around DevExtreme DataGrid
- **Event bus** — a lightweight RxJS-based publish/subscribe bus for cross-feature communication (e.g. refresh triggers, outbox events)
- **Common utilities** — shared helpers like `UserInformation`, auth context readers, and other cross-cutting concerns

## Why does the `@platform/` alias exist?

The alias was introduced early in the project when this code was envisioned as a reusable "platform SDK" that could be extracted into its own package. The name `@platform` was chosen to signal that intent.

In practice the code has remained an internal library tightly coupled to this application's domain. The alias name is now misleading because it implies an external SDK, not internal code.

## Migration plan

> **For new code:** import from `@core/` instead of `@platform/`.
> The two aliases point to the same directory — both will keep working.

```ts
// Old (still works, do not break existing imports)
import { userInformation } from '@platform/common/UserInformation';

// New (preferred for all new code written after this commit)
import { userInformation } from '@core/common/UserInformation';
```

The `@platform/` alias is tracked for removal under **REFAC-002**. When all 259 active imports have been migrated to `@core/`, the `@platform` entry will be deleted from `vite.config.js` and `tsconfig.json`.
