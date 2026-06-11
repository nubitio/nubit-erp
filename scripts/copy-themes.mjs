/**
 * Copies the @nubitio/ui theme stylesheets into public/themes/, renamed to the
 * legacy app-theme-* filenames that AppProviders passes to ThemeProvider
 * (themePrefix="app-theme-"), so existing users' stored theme keys keep working.
 */
import { copyFileSync, mkdirSync } from 'fs';
import { createRequire } from 'module';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const require = createRequire(import.meta.url);
const uiThemes = join(dirname(require.resolve('@nubitio/ui/package.json')), 'dist', 'themes');
const dest = join(dirname(fileURLToPath(import.meta.url)), '..', 'public', 'themes');

mkdirSync(dest, { recursive: true });
copyFileSync(join(uiThemes, 'nb-theme-dark.css'), join(dest, 'app-theme-dark.css'));
copyFileSync(join(uiThemes, 'nb-theme-light.css'), join(dest, 'app-theme-light.css'));
console.log('copied @nubitio/ui themes → public/themes/app-theme-{dark,light}.css');
