# @nubit/core

Opinionated internal React core for Efact-style administrative apps.

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
- base UI primitives: `AppDialog`, `ConfirmDialog`
