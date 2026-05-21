const legacyCompatibilityPatterns = [
  {
    group: ['@platform/*'],
    message: 'Deprecated alias: use `@core/*`; `@platform/*` remains temporary compatibility only.',
  },
  {
    group: ['@pages/*'],
    message:
      'Deprecated alias: use feature-owned route modules or `@app/router/*`; `@pages/*` is legacy only.',
  },
];

const relativeLayerPatterns = (layerDir) => [
  `./${layerDir}`,
  `./${layerDir}/*`,
  `./${layerDir}/**`,
  `../${layerDir}`,
  `../${layerDir}/*`,
  `../${layerDir}/**`,
  `../../${layerDir}`,
  `../../${layerDir}/*`,
  `../../${layerDir}/**`,
  `../../../${layerDir}`,
  `../../../${layerDir}/*`,
  `../../../${layerDir}/**`,
  `../../../../${layerDir}`,
  `../../../../${layerDir}/*`,
  `../../../../${layerDir}/**`,
  `../../../../../${layerDir}`,
  `../../../../../${layerDir}/*`,
  `../../../../../${layerDir}/**`,
  `assets/${layerDir}`,
  `assets/${layerDir}/*`,
  `assets/${layerDir}/**`,
  `**/assets/${layerDir}/**`,
];

const layerImportPatterns = (alias, layerDir) => [
  alias,
  `${alias}/*`,
  `${alias}/**`,
  ...relativeLayerPatterns(layerDir),
];

const appShellImportPatterns = [
  '@app/Settings',
  '@app/Settings/*',
  '@app/Settings/**',
  '@app/contexts/*',
  '@app/contexts/**',
  '@app/providers/*',
  '@app/providers/**',
  '@app/shell/*',
  '@app/shell/**',
  ...relativeLayerPatterns('app/Settings'),
  ...relativeLayerPatterns('app/contexts'),
  ...relativeLayerPatterns('app/providers'),
  ...relativeLayerPatterns('app/shell'),
];

const topLevelPagesImportPatterns = [
  '@pages',
  '@pages/*',
  '@pages/**',
  '../pages',
  '../pages/*',
  '../pages/**',
  '../../pages',
  '../../pages/*',
  '../../pages/**',
  '../../../pages',
  '../../../pages/*',
  '../../../pages/**',
  '../../../../pages',
  '../../../../pages/*',
  '../../../../pages/**',
  '../../../../../pages',
  '../../../../../pages/*',
  '../../../../../pages/**',
  'assets/pages',
  'assets/pages/*',
  'assets/pages/**',
  '**/assets/pages/**',
];

const restrictedImportsRule = (severity, patterns, paths = baseRestrictedPaths) => [
  severity,
  {
    patterns: [...baseRestrictedPatterns, ...patterns],
    paths,
  },
];

// eFact-specific @core/* paths that are NOT candidates for @nubit/* and should
// eventually move to @app/* or be replaced by i18n keys / injected services.
const efactCoreDeprecationPaths = [
  {
    name: '@core/common/Constants',
    message:
      '`Constants` is eFact-specific (hardcoded Spanish labels and API paths). ' +
      'Migrate display strings to i18n keys and config to `@app/Settings`. ' +
      'Do not add new usages.',
  },
  {
    name: '@core/common/UserInformation',
    message:
      '`userInformation()` reads raw localStorage; prefer `useAuth()` from `@app/contexts/auth` ' +
      'in React components. Only use this in non-React service code until the auth adapter is complete.',
  },
];

// Paths that have been promoted to @nubit/* facades and should no longer be
// imported directly from their implementation location.
const nubitFacadePatterns = [
  {
    group: [
      '@core',
      '@core/*',
      '@core/**',
    ],
    message:
      'Use `@nubit/core` for public core APIs. Inside assets/core, use relative imports instead of the app-level @core alias.',
  },
  {
    group: [
      '@core/ui/app-dialog',
      '@core/ui/app-dialog/*',
      '@core/components/ConfirmDialog',
      '**/assets/core/ui/app-dialog',
      '**/assets/core/ui/app-dialog/**',
      '**/assets/core/components/ConfirmDialog',
    ],
    message: 'Use `@nubit/ui` for UI primitives (AppDialog, ConfirmDialog).',
  },
  {
    group: [
      '**/assets/admin',
      '**/assets/admin/*',
      '**/assets/admin/**',
    ],
    message: 'Use `@nubit/admin` instead of direct paths into assets/admin/.',
  },
  {
    group: [
      '**/assets/react-admin',
      '**/assets/react-admin/*',
      '**/assets/react-admin/**',
    ],
    message: 'Use `@nubit/react-admin` instead of direct paths into assets/react-admin/.',
  },
];

const baseRestrictedPatterns = [
  ...legacyCompatibilityPatterns,
  ...nubitFacadePatterns,
  {
    group: ['**/assets/service/*', './service/*', '../service/*', '../../service/*'],
    message:
      'Use `assets/features/*` exports directly; `assets/service/*` is legacy compatibility.',
  },
  {
    group: ['**/assets/routes/*', './routes/*', '../routes/*', '../../routes/*'],
    message: 'Use feature-owned route modules (`assets/features/*/routes`) or `@app/router/*`.',
  },
  {
    group: ['**/assets/core/http/*', './core/http/*', '../core/http/*', '../../core/http/*'],
    message: 'Use `assets/shared/api/*`; `assets/core/http/*` is a compatibility layer.',
  },
];

const baseRestrictedPaths = [
  ...efactCoreDeprecationPaths,
  {
    name: '@widgets/utils/toolbar/AppToolbar',
    message: 'Use `@shared/ui/toolbar/AppToolbar` directly.',
  },
  {
    name: '@widgets/utils/async-state/AsyncState',
    message: 'Use `@shared/ui/async-state/AsyncState` directly.',
  },
];

const directFetchMessage =
  'Use `@shared/api/network` (`businessHttpClient` or wrappers over it) for business API calls. Direct `fetch` is reserved for documented exceptions via `exceptionalFetch()`.';

const sharedBoundaryPatterns = [
  {
    group: layerImportPatterns('@app', 'app'),
    message:
      'Layer violation: `shared` must stay shell-agnostic. Move shell-aware code to `@app/*`, or inject auth/navigation/settings from `@app` or the owning feature.',
  },
  {
    group: layerImportPatterns('@features', 'features'),
    message:
      'Layer violation: `shared` must not import feature code. Move this component/hook into the owning feature, or pass feature-specific data/actions into `shared` via props.',
  },
  {
    group: topLevelPagesImportPatterns,
    message:
      'Layer violation: `shared` must not own routes/pages. Move route-aware code to `@features/*` or `@app/router/*` and keep `shared` generic.',
  },
];

const featureBoundaryPatterns = [
  {
    group: topLevelPagesImportPatterns,
    message:
      'Layer violation: feature internals should not import top-level `pages`. Prefer feature-owned route modules or `@app/router/*` for global composition.',
  },
  {
    group: appShellImportPatterns,
    message:
      'Incremental boundary: feature code should avoid importing app shell internals (`Settings`, `contexts`, `providers`). Extract a shared contract/adaptor or inject the dependency from `@app`.',
  },
];

const coreBoundaryPatterns = [
  {
    group: layerImportPatterns('@app', 'app'),
    message:
      'Layer violation: `core` must not depend on `@app`. Move shell-aware behavior to `@app` or pass the dependency into `@core` through an explicit contract.',
  },
  {
    group: layerImportPatterns('@features', 'features'),
    message:
      'Layer violation: `core` must not depend on concrete features. Move domain-specific logic to the owning feature or introduce a reusable abstraction owned by `@core`/`@shared`.',
  },
];

const offlineBoundaryPatterns = [
  {
    group: layerImportPatterns('@app', 'app'),
    message:
      'Layer violation: `offline` must not depend on `@app` shell code. Keep adapters explicit and inject shell state from `@app` when needed.',
  },
  {
    group: layerImportPatterns('@features', 'features'),
    message:
      'Incremental boundary: `offline` should not depend directly on feature pages/helpers. Extract shared adapters/types, or keep the exception documented while migrating the owner feature.',
  },
];

module.exports = {
  root: true,
  env: {
    browser: true,
    es2021: true,
    node: true,
  },
  extends: [
    'eslint:recommended',
    'plugin:react/recommended',
    'plugin:react-hooks/recommended',
    'plugin:@typescript-eslint/recommended',
    'prettier', // Make sure this is last to override other configs
  ],
  parser: '@typescript-eslint/parser',
  parserOptions: {
    ecmaFeatures: {
      jsx: true,
    },
    ecmaVersion: 'latest',
    sourceType: 'module',
  },
  plugins: ['react', '@typescript-eslint', 'react-hooks'],
  settings: {
    react: {
      version: 'detect',
    },
  },
  rules: {
    // React rules
    'react/react-in-jsx-scope': 'off', // Not needed in React 17+
    'react/prop-types': 'off', // Not needed when using TypeScript

    // React Hooks rules
    'react-hooks/rules-of-hooks': 'error',
    'react-hooks/exhaustive-deps': 'warn',
    // v7 rules: widespread existing patterns — track as warnings until codebase is migrated
    'react-hooks/refs': 'warn',
    'react-hooks/set-state-in-effect': 'warn',

    // TypeScript rules
    '@typescript-eslint/explicit-module-boundary-types': 'off',
    '@typescript-eslint/no-explicit-any': 'warn', // Gradually eliminating explicit `any` — see REFAC-001
    '@typescript-eslint/no-unused-vars': ['warn', { argsIgnorePattern: '^_' }],

    // General rules
    'no-console': ['warn', { allow: ['warn', 'error'] }],
    'prefer-const': 'warn',
    'no-var': 'warn',
    'no-restricted-globals': ['error', { name: 'fetch', message: directFetchMessage }],
    'no-restricted-imports': [
      'warn',
      {
        patterns: baseRestrictedPatterns,
        paths: baseRestrictedPaths,
      },
    ],
  },
  ignorePatterns: ['node_modules/', 'public/', 'var/', 'vendor/', 'dist/', '*.min.js'],
  overrides: [
    {
      files: ['assets/shared/api/network.ts'],
      rules: {
        'no-restricted-globals': 'off',
      },
    },
    {
      // fetch() inside page.evaluate() runs in the Playwright browser context — not Node.js.
      // The no-restricted-globals rule does not apply here.
      files: ['e2e/**/*.{ts,tsx,js}'],
      rules: {
        'no-restricted-globals': 'off',
      },
    },
    {
      files: ['assets/shared/**/*.{ts,tsx,js,jsx}'],
      rules: {
        'no-restricted-imports': restrictedImportsRule('warn', sharedBoundaryPatterns),
      },
    },
    {
      files: ['assets/features/**/*.{ts,tsx,js,jsx}'],
      rules: {
        'no-restricted-imports': restrictedImportsRule('warn', featureBoundaryPatterns),
      },
    },
    {
      files: ['assets/core/**/*.{ts,tsx,js,jsx}'],
      rules: {
        'no-restricted-imports': restrictedImportsRule('error', coreBoundaryPatterns),
      },
    },
    {
      files: ['assets/core/common/UserInformation.ts', 'assets/core/crud/SmartCrudPage.tsx'],
      rules: {
        'no-restricted-imports': restrictedImportsRule('warn', coreBoundaryPatterns),
      },
    },
    {
      files: ['assets/offline/**/*.{ts,tsx,js,jsx}'],
      rules: {
        'no-restricted-imports': restrictedImportsRule('error', offlineBoundaryPatterns),
      },
    },
    {
      files: ['assets/offline/hooks/useOfflineCatalog.ts'],
      rules: {
        'no-restricted-imports': restrictedImportsRule('warn', offlineBoundaryPatterns),
      },
    },
    {
      // @nubit/admin package internals: must not import @app/* or @features/*.
      // Allowed to import @core/* and @shared/* during the internal extraction phase.
      files: ['assets/admin/**/*.{ts,tsx,js,jsx}'],
      rules: {
        'no-restricted-imports': restrictedImportsRule('error', [
          {
            group: layerImportPatterns('@app', 'app'),
            message:
              'Layer violation: `@nubit/admin` must not depend on `@app` shell code. Inject app-specific behavior through props or slots.',
          },
          {
            group: layerImportPatterns('@features', 'features'),
            message:
              'Layer violation: `@nubit/admin` must not depend on concrete feature code. Pass behavior through props, slots, or adapters.',
          },
        ]),
      },
    },
    {
      // @nubit/ui package internals: must not import @app/*, @features/*, or @core/crud/*.
      files: ['assets/ui/**/*.{ts,tsx,js,jsx}'],
      rules: {
        'no-restricted-imports': restrictedImportsRule('error', [
          {
            group: layerImportPatterns('@app', 'app'),
            message:
              'Layer violation: `@nubit/ui` primitives must not depend on `@app` shell code.',
          },
          {
            group: layerImportPatterns('@features', 'features'),
            message:
              'Layer violation: `@nubit/ui` primitives must not depend on concrete feature code.',
          },
        ]),
      },
    },
  ],
};
