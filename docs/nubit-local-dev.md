# Developing @nubitio packages against efact

efact consumes the published `@nubitio/*` packages from npm. The package sources
live in [nubitio/nubit-react](https://github.com/nubitio/nubit-react)
(local clone: `~/Projects/nubit-react`).

## Normal flow

Package changes ship via nubit-react releases:

```bash
cd ~/Projects/nubit-react
node scripts/set-version.mjs 0.2.0
git commit -am "release: v0.2.0" && git tag v0.2.0 && git push && git push --tags
# CI publishes; then in efact:
yarn up '@nubitio/*'
```

## Iterating locally (unpublished changes)

Point efact at the local build with yarn's portal protocol — add to
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
cd ~/Projects/nubit-react && pnpm build   # rebuild dist after each change
cd ~/Projects/efact-project/efact && yarn install
```

Remove the resolutions and `yarn install` again before committing.
