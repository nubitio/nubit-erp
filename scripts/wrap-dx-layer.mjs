/**
 * Post-processes the compiled DevExtreme theme CSS files by wrapping all
 * rules inside `@layer dx-base { }`.
 *
 * Why: the theme stylesheets are injected dynamically at runtime via a
 * <link> element appended by theme.tsx, which means they arrive in the
 * browser AFTER the application bundle's <style> tags. For equal-specificity
 * selectors this would let DX override the project's styles.
 *
 * By placing all DX rules in a named layer, any unlayered CSS (the project's
 * styles.scss) wins automatically — regardless of specificity or source order.
 * This removes the need for !important in the project's overrides.
 */

import { readFileSync, writeFileSync } from 'node:fs';

const files = ['public/themes/app-theme-light.css', 'public/themes/app-theme-dark.css'];

for (const file of files) {
  const css = readFileSync(file, 'utf8');
  writeFileSync(file, `@layer dx-base {\n${css}}\n`);
  console.log(`  wrapped ${file} in @layer dx-base`);
}
