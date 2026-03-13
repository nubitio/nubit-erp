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

    // TypeScript rules
    '@typescript-eslint/explicit-module-boundary-types': 'off',
    '@typescript-eslint/no-explicit-any': 'warn', // Gradually eliminating explicit `any` — see REFAC-001
    '@typescript-eslint/no-unused-vars': ['warn', { argsIgnorePattern: '^_' }],

    // General rules
    'no-console': ['warn', { allow: ['warn', 'error'] }],
    'prefer-const': 'warn',
    'no-var': 'warn',
    'no-restricted-imports': [
      'warn',
      {
        patterns: [
          {
            group: ['**/assets/service/*', './service/*', '../service/*', '../../service/*'],
            message:
              'Use `assets/features/*` exports directly; `assets/service/*` is legacy compatibility.',
          },
          {
            group: ['**/assets/routes/*', './routes/*', '../routes/*', '../../routes/*'],
            message:
              'Use feature-owned route modules (`assets/features/*/routes`) or `@app/router/*`.',
          },
          {
            group: [
              '**/assets/core/http/*',
              './core/http/*',
              '../core/http/*',
              '../../core/http/*',
            ],
            message: 'Use `assets/shared/api/*`; `assets/core/http/*` is a compatibility layer.',
          },
        ],
        paths: [
          {
            name: '@widgets/utils/toolbar/AppToolbar',
            message: 'Use `@shared/ui/toolbar/AppToolbar` directly.',
          },
          {
            name: '@widgets/utils/async-state/AsyncState',
            message: 'Use `@shared/ui/async-state/AsyncState` directly.',
          },
        ],
      },
    ],
  },
  ignorePatterns: ['node_modules/', 'public/', 'var/', 'vendor/', 'dist/', '*.min.js'],
  overrides: [
    {
      files: ['assets/shared/**/*.{ts,tsx,js,jsx}'],
      rules: {
        'no-restricted-imports': [
          'warn',
          {
            patterns: [
              {
                group: ['**/assets/service/*', './service/*', '../service/*', '../../service/*'],
                message:
                  'Use `assets/features/*` exports directly; `assets/service/*` is legacy compatibility.',
              },
              {
                group: ['**/assets/routes/*', './routes/*', '../routes/*', '../../routes/*'],
                message:
                  'Use feature-owned route modules (`assets/features/*/routes`) or `@app/router/*`.',
              },
              {
                group: [
                  '**/assets/core/http/*',
                  './core/http/*',
                  '../core/http/*',
                  '../../core/http/*',
                ],
                message:
                  'Use `assets/shared/api/*`; `assets/core/http/*` is a compatibility layer.',
              },
              {
                group: ['@features/*', '**/features/**'],
                message: 'Layer violation: `shared` must not import `features`.',
              },
              {
                group: ['@pages/*', '**/pages/**'],
                message: 'Layer violation: `shared` must not import `pages`.',
              },
            ],
            paths: [
              {
                name: '@widgets/utils/toolbar/AppToolbar',
                message: 'Use `@shared/ui/toolbar/AppToolbar` directly.',
              },
              {
                name: '@widgets/utils/async-state/AsyncState',
                message: 'Use `@shared/ui/async-state/AsyncState` directly.',
              },
            ],
          },
        ],
      },
    },
    {
      files: ['assets/features/**/*.{ts,tsx,js,jsx}'],
      rules: {
        'no-restricted-imports': [
          'warn',
          {
            patterns: [
              {
                group: ['**/assets/service/*', './service/*', '../service/*', '../../service/*'],
                message:
                  'Use `assets/features/*` exports directly; `assets/service/*` is legacy compatibility.',
              },
              {
                group: ['**/assets/routes/*', './routes/*', '../routes/*', '../../routes/*'],
                message:
                  'Use feature-owned route modules (`assets/features/*/routes`) or `@app/router/*`.',
              },
              {
                group: [
                  '**/assets/core/http/*',
                  './core/http/*',
                  '../core/http/*',
                  '../../core/http/*',
                ],
                message:
                  'Use `assets/shared/api/*`; `assets/core/http/*` is a compatibility layer.',
              },
              {
                group: ['@pages/*', '../../../pages/**', '../../../../pages/**', '../../pages/**'],
                message:
                  'Layer violation: feature internals should not import top-level `pages` (route composition only).',
              },
            ],
            paths: [
              {
                name: '@widgets/utils/toolbar/AppToolbar',
                message: 'Use `@shared/ui/toolbar/AppToolbar` directly.',
              },
              {
                name: '@widgets/utils/async-state/AsyncState',
                message: 'Use `@shared/ui/async-state/AsyncState` directly.',
              },
            ],
          },
        ],
      },
    },
  ],
};
