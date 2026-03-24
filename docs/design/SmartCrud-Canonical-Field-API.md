# SmartCrud Canonical Field API

> Related ADR: ADR-017  
> Related spec/task: SPEC-004 / TASK-234

## Purpose

This guide documents the canonical SmartCrud field contract introduced by ADR-017 and implemented in SPEC-004. Use it when authoring new SmartCrud screens or migrating experimental field configuration to the supported runtime contract.

## Canonical entrypoints

Use `resource.fieldContract` as the authoritative field API.

- `resource.fields`: legacy/manual path kept for compatibility and migrations
- `fieldOverrides`: legacy Hydra override path kept for compatibility
- `resource.fieldContract`: canonical production-facing contract

When `resource.fieldContract` is present, SmartCrud ignores legacy `fieldOverrides` and resolves fields only from the contract.

```ts
import { defineFields, defineResource, SmartCrudPage } from '@core/crud';
```

## Contract shapes

The canonical API supports exactly two source modes.

### 1. Hydra-backed mode (`source: 'hydra'`)

Use this when Hydra/OpenAPI should stay the baseline source of truth and the frontend only augments that baseline.

```ts
const fieldContract = defineFields<UserRecord>({
  source: 'hydra',
  strategy: 'augment',
  directives: [
    { kind: 'override', key: 'email', patch: { label: 'Correo' } },
    { kind: 'remove', key: 'password' },
    { kind: 'prepend', field: tenantCodeField },
    { kind: 'append', field: plainPasswordField },
  ],
});
```

Resolution order is deterministic:

1. `prepend` synthetic fields
2. inferred Hydra baseline fields, after `override` / `remove`
3. `append` synthetic fields

Hydra mode always requires `strategy: 'augment'`.

### 2. Manual mode (`source: 'manual'`)

Use this when the screen should fully replace Hydra inference and provide the complete field list itself.

```ts
const fieldContract = defineFields<UserRecord>({
  source: 'manual',
  strategy: 'replace',
  fields: [
    identityField().build(),
    textField().name('email').label('Correo').required(true).build(),
    passwordField().name('plainPassword').label('Contraseña').build(),
  ],
});
```

Manual mode always requires `strategy: 'replace'`.

## Typed helper usage

Prefer `defineFields<T>()` (or `defineFieldContract<T>()`) so TypeScript constrains:

- Hydra `override` / `remove` keys to model keys
- manual and synthetic field payloads to `FieldDef`-compatible objects
- `operation` semantics to the supported create/edit structure

Example:

```ts
interface UserRecord {
  id: string;
  email: string;
  role: string;
  plainPassword?: string;
}

const fieldContract = defineFields<UserRecord>({
  source: 'hydra',
  strategy: 'augment',
  directives: [
    { kind: 'override', key: 'email', patch: { label: 'Correo' } },
  ],
});
```

Without `defineFields`, runtime validation still runs, but you lose the typed authoring guardrails.

## Synthetic fields

Synthetic fields are frontend-defined fields that do not exist in the inferred Hydra baseline, for example `plainPassword`.

In Hydra-backed mode, synthetic fields can only enter through:

- `prepend`
- `append`

If a synthetic field name collides with an inferred Hydra field name, SmartCrud throws a contract error and requires an `override` instead.

Prefer building synthetic fields with existing field builders for concise, valid definitions:

```ts
const plainPasswordField = passwordField()
  .name('plainPassword')
  .label('Contraseña')
  .helpText('Solo se envía cuando se define una nueva contraseña.')
  .build();
```

## `override` / `remove` / `prepend` / `append` semantics

### `override`

- targets an existing inferred Hydra field by `key`
- applies a shallow patch onto the inferred field
- can update normal `Field` properties and `operation`
- cannot target unknown Hydra fields

```ts
{ kind: 'override', key: 'email', patch: { required: true, label: 'Correo' } }
```

### `remove`

- removes an existing inferred Hydra field by `key`
- cannot target unknown Hydra fields

```ts
{ kind: 'remove', key: 'password' }
```

### `prepend`

- inserts a synthetic field before the resolved Hydra baseline
- preserves declaration order among other prepended fields

### `append`

- inserts a synthetic field after the resolved Hydra baseline
- preserves declaration order among other appended fields

Hydra directives are validated for determinism. A field cannot be targeted twice across `override`/`remove`, and a synthetic field name cannot be declared twice.

## Operation-aware create/edit behavior

Canonical fields may declare `operation` semantics in either overrides or manual/synthetic fields.

Supported shape:

```ts
operation: {
  only?: 'create' | 'edit' | Array<'create' | 'edit'>;
  visible?: boolean | { create?: boolean; edit?: boolean };
  required?: boolean | { create?: boolean; edit?: boolean };
  readonly?: boolean | { create?: boolean; edit?: boolean };
  disabled?: boolean | { create?: boolean; edit?: boolean };
  create?: { visible?: boolean; required?: boolean; readonly?: boolean; disabled?: boolean };
  edit?: { visible?: boolean; required?: boolean; readonly?: boolean; disabled?: boolean };
}
```

Practical rules from the runtime:

- `only: 'create'` means visible in create and hidden in edit unless overridden
- `only: 'edit'` means visible in edit and hidden in create unless overridden
- field defaults (`required`, `readonly`, `disabled`, `visible`/`hidden`) are the base state
- operation semantics project on top of that base state per active operation

In `SmartCrudPage`, operation semantics are applied when the dialog enters create/edit mode through the shared event bus and routing state.

Important runtime detail:

- `applyFieldOperationFormSemantics()` updates `visibleOnForm`, `required`, `readonly`, `disabled`
- it intentionally does **not** hide the grid column by changing `field.visible`
- hidden-on-operation fields are represented as `visibleOnForm: false` and `hidden: true`

So operation semantics are form-aware, not grid-column-aware.

## Validation and runtime error behavior

The canonical contract fails fast. Invalid authoring is not merged permissively.

Validation covers:

- wrong `source` / `strategy` combinations
- unknown keys on contracts, directives, patches, synthetic fields, and `operation`
- duplicate hydra targets
- duplicate synthetic/manual field names
- override/remove targets missing from the Hydra baseline
- synthetic names colliding with inferred Hydra names
- conflicting operation semantics
- impossible states such as `visible: false` plus `required: true`

Errors are surfaced as `SmartCrudFieldContractError` and include `issues[]`. `SmartCrudPage` renders the combined message instead of silently degrading.

## Users screen example (`plainPassword`)

Hydra-backed users screen with canonical augmentation:

```ts
import {
  defineFields,
  defineResource,
  SmartCrudPage,
} from '@core/crud';
import { passwordField } from '@core/field/FieldBuilders';

interface UserRecord {
  id: string;
  email: string;
  role: string;
  plainPassword?: string;
}

const plainPasswordField = passwordField()
  .name('plainPassword')
  .label('Contraseña')
  .col(12)
  .helpText('Visible solo al crear o al forzar cambio de contraseña.')
  .build();

const usersResource = defineResource<UserRecord>('/api/users', {
  title: 'Users',
  fields: [],
  fieldContract: defineFields<UserRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      {
        kind: 'override',
        key: 'email',
        patch: {
          label: 'Correo',
          operation: {
            create: { required: true },
            edit: { readonly: true },
          },
        },
      },
      {
        kind: 'append',
        field: {
          ...plainPasswordField,
          operation: {
            only: 'create',
            required: { create: true },
          },
        },
      },
    ],
  }),
});

export function UsersPage() {
  return <SmartCrudPage resource={usersResource} />;
}
```

Resulting behavior:

- Hydra still defines the baseline `id`, `email`, `role`, etc.
- `email` is relabeled to `Correo`
- `email` is required on create and readonly on edit
- `plainPassword` is synthetic and appended after the Hydra fields
- `plainPassword` is shown on create, hidden from the edit form, and required on create

## Manual mode example

Use manual mode when you do not want Hydra inference at all:

```ts
const usersResource = defineResource<UserRecord>('/api/users', {
  title: 'Users',
  fields: [],
  fieldContract: defineFields<UserRecord>({
    source: 'manual',
    strategy: 'replace',
    fields: [
      identityField().build(),
      textField().name('email').label('Correo').required(true).build(),
      passwordField()
        .name('plainPassword')
        .label('Contraseña')
        .build(),
    ],
  }),
});
```

In this mode, SmartCrud skips Hydra field inference and uses the declared fields as-is.

## Migration notes and caveats

- Prefer `fieldContract` for all new SmartCrud work.
- `fieldOverrides` remains legacy and is ignored whenever `fieldContract` is present.
- `resource.fields` still short-circuits Hydra only when it is a non-empty manual array and no canonical contract is supplied.
- Hydra mode requires a real inferred baseline; overriding or removing a missing field is a hard error.
- Operation semantics are evaluated for form runtime state; they do not remove grid columns.
- If you bypass `defineFields`, runtime validation still protects resolution, but failures move from compile time to runtime.
