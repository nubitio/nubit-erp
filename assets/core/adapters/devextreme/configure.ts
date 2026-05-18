// DevExtreme configuration has been migrated away.
// Kept as an empty shim so call sites do not need updating until uiAdapter is refactored.

let isDevExtremeConfigured = false;

export function configureDevExtreme() {
  if (isDevExtremeConfigured) {
    return;
  }
  isDevExtremeConfigured = true;
}
