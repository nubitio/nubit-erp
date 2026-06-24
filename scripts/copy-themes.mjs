/**
 * Copies eFact-owned theme stylesheets into public/themes/.
 * ThemeProvider loads them via themePrefix="app-theme-" and basePath="/themes/".
 */
import { copyFileSync, mkdirSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const source = join(root, 'assets', 'theme', 'styles');
const dest = join(root, 'public', 'themes');

mkdirSync(dest, { recursive: true });
copyFileSync(join(source, 'app-theme-light.css'), join(dest, 'app-theme-light.css'));
copyFileSync(join(source, 'app-theme-dark.css'), join(dest, 'app-theme-dark.css'));
console.log('copied eFact themes → public/themes/app-theme-{dark,light}.css');