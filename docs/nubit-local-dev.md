# Developing @nubitio packages against Nubit ERP

Nubit ERP consumes the published `@nubitio/*` packages from npm. The package sources
live in [nubitio/nubit-react](https://github.com/nubitio/nubit-react).
Paths below assume `nubit-react` is at `../../nubit-react` relative to the ERP checkout,
matching the bind mount for the `vite` service in `docker-compose.yml`.

## Normal flow

Package changes ship via nubit-react releases:

```bash
cd ../../nubit-react  # from the ERP checkout
node scripts/set-version.mjs 0.2.0
git commit -am "release: v0.2.0" && git tag v0.2.0 && git push && git push --tags
# Once CI publishes, run from the ERP checkout:
corepack yarn up '@nubitio/*'
```

## Iterating locally (unpublished changes)

Point the ERP at the local build with Yarn's portal protocol — add these entries to
`package.json` `resolutions` (do not commit):

```json
"resolutions": {
  "@nubitio/core": "portal:../../nubit-react/packages/core",
  "@nubitio/ui": "portal:../../nubit-react/packages/ui",
  "@nubitio/admin": "portal:../../nubit-react/packages/admin",
  "@nubitio/crud": "portal:../../nubit-react/packages/crud",
  "@nubitio/hydra": "portal:../../nubit-react/packages/hydra",
  "@nubitio/react-admin": "portal:../../nubit-react/packages/react-admin"
}
```

Then:

```bash
# From the ERP checkout:
(cd ../../nubit-react && corepack pnpm build)
corepack yarn install
```

Remove the resolutions and `yarn install` again before committing.

The `vite` container runs `yarn install --immutable` every time it starts, so it follows
`yarn.lock` on its own. When you switch portals on or off, run `yarn install` on the host first
(so `yarn.lock` matches) and then `docker compose restart vite`. If Vite ever fails with
`Failed to resolve import "@nubitio/…"`, the `vite-node-modules` volume is stale:
`docker compose run --rm --no-deps vite sh -lc 'corepack enable && yarn install --immutable'`.
