import { appendFileSync, readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import semanticRelease from 'semantic-release';

const require = createRequire(import.meta.url);
const config = JSON.parse(readFileSync('.releaserc.json', 'utf8'));
// Resolve plugins from the isolated, locked release tooling installation.
config.plugins = config.plugins.map((plugin) =>
  Array.isArray(plugin) ? [require.resolve(plugin[0]), plugin[1]] : require.resolve(plugin),
);
const result = await semanticRelease(config);
if (result) {
  appendFileSync(
    process.env.GITHUB_OUTPUT,
    `tag=${result.nextRelease.gitTag}\ncommit=${result.nextRelease.gitHead}\n`,
  );
}
