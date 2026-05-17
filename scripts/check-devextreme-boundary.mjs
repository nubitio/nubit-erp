import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative, sep } from 'node:path';

const root = process.cwd();
const assetsDir = join(root, 'assets');

const sourceExtensions = new Set(['.ts', '.tsx', '.js', '.jsx']);
const vendorImportPattern =
  /from\s+['"](?:devextreme|devextreme-react)(?:\/[^'"]*)?['"]|import\s+['"](?:devextreme|devextreme-react)(?:\/[^'"]*)?['"]/;

function toPosix(path) {
  return path.split(sep).join('/');
}

function isAllowedAdapterPath(filePath) {
  const path = toPosix(relative(root, filePath));
  if (path.startsWith('assets/core/adapters/devextreme/')) {
    return true;
  }
  return /^assets\/features\/[^/]+\/adapters\/devextreme\//.test(path);
}

function isSourceFile(filePath) {
  const extension = filePath.slice(filePath.lastIndexOf('.'));
  return sourceExtensions.has(extension);
}

function walk(dir, files = []) {
  for (const entry of readdirSync(dir)) {
    const fullPath = join(dir, entry);
    const stat = statSync(fullPath);
    if (stat.isDirectory()) {
      walk(fullPath, files);
    } else if (isSourceFile(fullPath)) {
      files.push(fullPath);
    }
  }
  return files;
}

const violations = [];

for (const file of walk(assetsDir)) {
  if (isAllowedAdapterPath(file)) {
    continue;
  }

  const content = readFileSync(file, 'utf8');
  const lines = content.split('\n');
  lines.forEach((line, index) => {
    if (vendorImportPattern.test(line)) {
      violations.push(`${toPosix(relative(root, file))}:${index + 1}: ${line.trim()}`);
    }
  });
}

if (violations.length > 0) {
  console.error('DevExtreme boundary violation: import devextreme packages only inside adapters.');
  console.error('Allowed paths:');
  console.error('- assets/core/adapters/devextreme/**');
  console.error('- assets/features/*/adapters/devextreme/**');
  console.error('');
  console.error(violations.join('\n'));
  process.exit(1);
}

console.log('DevExtreme boundary OK');
