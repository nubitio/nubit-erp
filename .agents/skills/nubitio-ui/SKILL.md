---
name: nubitio-ui
description: Use before writing or editing any React UI in `assets/` that renders buttons, dialogs, forms, tables, alerts, layout or lookups with `@nubitio/*` (ui, crud, core, admin). Picks the existing component, applies the ERP's usage conventions, and verifies the props against the installed library.
---

# Building UI with `@nubitio`

`@nubitio/ui` is a **generic** primitives library. ERP-specific components live in the ERP
(ADR-019: promote to `@nubitio/ui` only when a second app needs it). Never hand-roll a raw
`<button>`, `<input>`, modal or inline-styled layout when a component below exists.

## Steps

1. **Look in the ERP first.** Done when you have checked `assets/shared/ui/` (`entity-form`:
   `Field`, `EntityDrawer`; `data-grid`: `DataGrid`), `assets/shared/components/` and the sibling
   files of the feature you are editing for an existing composition of what you need.
2. **Pick the library component.** Done when the need maps to one export in
   [`references/components.md`](references/components.md) (catalog of every `@nubitio/ui` export,
   plus `crud`, `core`, `admin`, `dashboard`). Read it whenever you choose a component.
3. **Verify the props against the installed types.** The catalog is a cache; the source of truth is
   `node_modules/@nubitio/ui/dist/index.d.mts` (and `crud`, `core`, `admin`). Done when every prop
   you pass appears in that file. Component names and variants differ between the library and
   the design-system artifact (`Button` has no `lg`; `Alert` has no `success`); the types win.
4. **Apply the conventions below.**
5. **Verify.** Done when all of these are clean on the touched files:
   `npx tsc --noEmit -p .`, `ESLINT_USE_FLAT_CONFIG=false npx eslint <files> --max-warnings 0`,
   `npx prettier --write <files>`, `npx vitest run assets/features/<area>`.
   To see it in the browser: `yarn build`, then hard-reload (the app is served from
   `public/build` and a service worker caches it).

## Conventions

**Actions and buttons**
- `Button` runs an action; `ButtonLink` navigates (`<ButtonLink as={Link} to="…">`). Opening a
  dialog is an action, so it is a `Button`.
- One `variant="primary"` per dialog or screen; it is the accent colour from the tokens. Cancel is
  `secondary`. Save/create is `primary`; `success` is for confirmations of a completed state.
  `danger` is destructive and goes through `useConfirm` / `ConfirmDialog` first.
- Pass `loading` while the request runs (blocks double submit). Verb + object labels in Spanish
  ("Guardar cliente"); never "Aceptar".
- `Button icon` takes the full Phosphor classes (`"ph ph-user-plus"`); `EmptyState icon` takes the
  name only (`"folder-open"`).

**Dialogs and drawers**
- Dialog actions go in `AppDialog`'s `footer` prop, which right-aligns them like every dialog in the
  app. `FormActions` inside the body is left-aligned and wrong there. Drawers use `Drawer`'s
  `footer` the same way.
- A submit button outside the `<form>` needs `form="<form id>"` so Enter and click both submit.
- While saving: `closeOnEscape={!saving}`, `showCloseButton={!saving}`, and guard `onClose`.
- Short form: `AppDialog`. Record edit alongside a list: `Drawer`. Long flow: a page.

**Forms**
- `FormField label required error helpText` wraps one control (`TextField`, `SelectField`,
  `TextAreaField`, `Combobox`, `DatePicker`). `required` on `FormField` is visual; also set
  `required` on the control. Mark the control `invalid` when it has an `error`.
- Lay out with `FormLayout`, `Grid cols={2}`, `Row`, `Stack`; do not write flex CSS.
- A relation to an API resource uses `EntityDropdown` from `@nubitio/crud` (`url`, `valueField`,
  `textField`, `searchExpr`, `filters`). A static or small list uses `Combobox`.
- Server validation: a 422 with `violations` is **not** toasted by `HttpClient`; the form must show
  it. Map with `applyServerErrors` (`@shared/lib/formErrors`, react-hook-form) or
  `mapApiViolations` (`@nubitio/crud`, plain state): field errors under the field, the rest in an
  `Alert tone="danger"`. Other failures are already toasted; keep the dialog open.

**Feedback**
- Transient result: `toast(message, 'success' | 'error' | 'warning' | 'info')` from
  `@shared/lib/SharedUtils` (default export).
- Persistent block-level state: `Alert` (`danger | warning | info` only). Field validation belongs
  on the field, not in an `Alert`.

**Access, text, style**
- Gate create/edit/delete with `hasPermission('<module>.<action>')` (`@shared/permissions`); the
  API enforces the same key.
- User-facing text is Spanish (Peru). Money through `formatMoney` / `NumberUtils`, never string
  concatenation.
- Colours, spacing and radii come from tokens (`var(--space-3)`, `var(--text-muted)`,
  `var(--border-subtle)`). Add no new hex values or one-off SCSS for something a component does.
- Reuse `data-testid` names that e2e specs already target; add one to each new trigger and submit.

## Gotchas

- `node_modules` can lag `package.json`. If an import is missing or a type error mentions a prop
  that is documented (`FormField required`, `QuantityStepper`), run
  `yarn install --frozen-lockfile` and check `node_modules/@nubitio/ui/package.json`.
- A failed `yarn build` empties `public/build` and the local site stops loading. Fix the build
  error and rebuild before reporting anything.
- `yarn build` regenerates the tracked `public/sw.js`; mention it before a commit.
- `Combobox` `value` is `string | null` and option ids are strings: stringify numeric ids.
- `Toggle` and `Tabs` are controlled; `Tabs` needs `aria-label`; `Tooltip` needs a child that
  forwards its ref (`Button` and `IconButton` do).
- The design-system artifact `https://claude.ai/artifact/9RkmDgN8LuZEUqzAUBHfcj` records the
  intended look and wording of ERP components (`DocumentIdInput`, `LineItemsEditor`, `Money`…).
  It is a spec, not code: build such components in the ERP using the primitives here.
