// @vitest-environment jsdom

import React from 'react';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import type { Subscription } from 'rxjs';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { of } from 'rxjs';
import { HashRouter, useLocation } from 'react-router-dom';

const { authServiceMock, branchSelectionState, branchServiceMock, storageState, syncEventSpy } =
  vi.hoisted(() => {
    const branchSelectionState = {
      id: null as string | null,
      name: null as string | null,
    };

    return {
      authServiceMock: {
        login: vi.fn(),
        logout: vi.fn(),
        selectBranch: vi.fn(),
      },
      branchSelectionState,
      branchServiceMock: {
        getAll: vi.fn(),
        getSelectedBranchId: vi.fn(() => branchSelectionState.id),
        setSelectedBranchId: vi.fn((id: string) => {
          branchSelectionState.id = id;
        }),
        clearSelectedBranchId: vi.fn(() => {
          branchSelectionState.id = null;
        }),
        hasSelectedBranch: vi.fn(() => Boolean(branchSelectionState.id)),
        setSelectedBranchName: vi.fn((name: string) => {
          branchSelectionState.name = name;
        }),
        clearSelectedBranchName: vi.fn(() => {
          branchSelectionState.name = null;
        }),
        hasSelectedBranchName: vi.fn(() => Boolean(branchSelectionState.name)),
      },
      storageState: new Map<string, unknown>(),
      syncEventSpy: vi.fn(),
    };
  });

vi.mock('@app/providers/devextreme', () => ({
  configureDevExtreme: vi.fn(),
}));

vi.mock('./Content', () => ({
  Content: () => {
    const location = useLocation();

    return (
      <div
        data-testid="authenticated-shell"
        data-router-path={`${location.pathname}${location.search}`}
      >
        Authenticated shell
      </div>
    );
  },
}));

vi.mock('./FullScreenContent', () => ({
  FullScreenContent: () => <div data-testid="fullscreen-shell">POS</div>,
}));

vi.mock('devextreme-react/load-panel', () => ({
  default: ({ visible }: { visible?: boolean }) =>
    visible ? <div data-testid="shell-loading">Loading</div> : null,
}));

vi.mock('devextreme-react/load-indicator', () => ({
  default: () => <span data-testid="login-loading-indicator" />,
}));

vi.mock('devextreme-react/form', async () => {
  const React = await vi.importActual<typeof import('react')>('react');

  const Form = React.forwardRef<
    {
      instance: () => {
        validate: () => { isValid: boolean };
        getEditor: () => { focus: () => void };
      };
    },
    React.PropsWithChildren<{
      formData: { username: string; password: string };
      onFieldDataChanged?: () => void;
    }>
  >(({ formData, onFieldDataChanged, children }, ref) => {
    React.useImperativeHandle(ref, () => ({
      instance: () => ({
        validate: () => ({ isValid: true }),
        getEditor: () => ({ focus: vi.fn() }),
      }),
    }));

    return (
      <div>
        <input
          aria-label="Usuario"
          defaultValue={formData.username}
          onChange={(event) => {
            // eslint-disable-next-line react-hooks/immutability -- test double intentionally mirrors DevExtreme mutating bound form data
            formData.username = event.target.value;
            onFieldDataChanged?.();
          }}
        />
        <input
          aria-label="Contraseña"
          defaultValue={formData.password}
          onChange={(event) => {
            // eslint-disable-next-line react-hooks/immutability -- test double intentionally mirrors DevExtreme mutating bound form data
            formData.password = event.target.value;
            onFieldDataChanged?.();
          }}
        />
        {children}
      </div>
    );
  });

  Form.displayName = 'MockDxForm';

  return {
    default: Form,
    ButtonItem: ({ children }: React.PropsWithChildren) => <>{children}</>,
    ButtonOptions: ({ children }: React.PropsWithChildren) => (
      <button type="submit">{children}</button>
    ),
    Item: ({ children }: React.PropsWithChildren) => <>{children}</>,
    RequiredRule: () => null,
  };
});

vi.mock('devextreme-react', () => ({
  Button: ({
    text,
    onClick,
    disabled,
  }: {
    text: string;
    onClick: () => void;
    disabled?: boolean;
  }) => (
    <button type="button" onClick={onClick} disabled={disabled}>
      {text}
    </button>
  ),
}));

vi.mock('devextreme-react/list', () => ({
  default: ({
    dataSource,
    onSelectionChanged,
  }: {
    dataSource: { _array?: Array<{ id: string; name: string }> } | null;
    onSelectionChanged: (event: { addedItems?: Array<{ id: string; name: string }> }) => void;
  }) => (
    <div>
      {(dataSource?._array ?? []).map((branch) => (
        <button
          key={branch.id}
          type="button"
          onClick={() => onSelectionChanged({ addedItems: [branch] })}
        >
          {branch.name}
        </button>
      ))}
    </div>
  ),
}));

vi.mock('devextreme-react/popup', () => ({
  Popup: ({ visible, children }: React.PropsWithChildren<{ visible: boolean }>) =>
    visible ? <div>{children}</div> : null,
  ToolbarItem: ({ children }: React.PropsWithChildren) => <>{children}</>,
}));

vi.mock('devextreme/data/array_store', () => ({
  default: class MockArrayStore {
    _array: Array<{ id: string; name: string }>;

    constructor({ data }: { data: Array<{ id: string; name: string }> }) {
      this._array = data;
    }
  },
}));

vi.mock('@utils/media-query', () => ({
  useScreenSize: () => ({ isXSmall: false }),
  useScreenSizeClass: () => 'screen-lg',
}));

vi.mock('@theme/theme', () => ({
  useThemeContext: () => ({ isLoaded: true }),
}));

vi.mock('@features/auth', () => ({
  authService: authServiceMock,
}));

vi.mock('@features/branch', () => ({
  branchService: branchServiceMock,
}));

vi.mock('@shared/lib/StorageUtils', () => ({
  StorageUtils: {
    getItem: vi.fn((key: string) => storageState.get(key) ?? null),
    setItem: vi.fn((key: string, value: unknown) => {
      storageState.set(key, value);
    }),
    removeItem: vi.fn((key: string) => {
      storageState.delete(key);
    }),
  },
}));

vi.mock('@shared/lib/SharedUtils', () => ({
  default: vi.fn(),
}));

vi.mock('@features/auth/pages/LoginPage', async () => {
  const actual = await vi.importActual<typeof import('@features/auth/pages/LoginForm')>(
    '@features/auth/pages/LoginForm',
  );

  return {
    LoginPage: () => <actual.LoginForm />,
  };
});

vi.mock('@features/setup/pages/InitialSetupPage', () => ({
  InitialSetupPage: () => <div>Initial setup</div>,
}));

import { subscribeToAuthSessionSync } from '@shared/lib/authSessionSync';
import { AuthProvider } from '@app/contexts/auth';
import { RootApp } from './EfactApp';

let syncSubscription: Subscription | undefined;

afterEach(() => {
  syncSubscription?.unsubscribe();
  syncSubscription = undefined;
  cleanup();
});

describe('RootApp post-login routed regression coverage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    storageState.clear();
    branchSelectionState.id = null;
    branchSelectionState.name = null;

    authServiceMock.selectBranch.mockReturnValue(of({}));
    branchServiceMock.getAll.mockReturnValue(of({ data: [] }));

    syncSubscription = subscribeToAuthSessionSync(() => {
      syncEventSpy();
    });
  });

  const renderAuthenticatedFlow = () =>
    render(
      <HashRouter>
        <AuthProvider>
          <RootApp />
        </AuthProvider>
      </HashRouter>,
    );

  const submitLoginForm = async () => {
    fireEvent.change(await screen.findByLabelText('Usuario'), { target: { value: 'demo' } });
    fireEvent.change(screen.getByLabelText('Contraseña'), { target: { value: 'secret' } });
    fireEvent.submit(document.querySelector('form.login-form') as HTMLFormElement);

    await waitFor(() => {
      expect(authServiceMock.login).toHaveBeenCalledWith('demo', 'secret');
    });
  };

  it('leaves /login and mounts the authenticated shell immediately for direct login entry', async () => {
    window.history.replaceState({}, '', '/#/login');
    authServiceMock.login.mockReturnValue(
      of({
        data: {
          user: { id: 'user-1', username: 'demo' },
          branch: { id: 'branch-1', name: 'Central' },
        },
      }),
    );

    renderAuthenticatedFlow();
    await submitLoginForm();

    const shell = await screen.findByTestId('authenticated-shell');

    expect(shell.getAttribute('data-router-path')).toBe('/');
    expect(window.location.hash).toBe('#/');
    expect(document.querySelector('form.login-form')).toBeNull();
    expect(syncEventSpy).toHaveBeenCalled();
    expect(branchServiceMock.setSelectedBranchId).toHaveBeenCalledWith('branch-1');
    expect(branchServiceMock.setSelectedBranchName).toHaveBeenCalledWith('Central');
  });

  it('preserves redirect + branch selection and aligns shell router location after login', async () => {
    window.history.replaceState({}, '', '/#/login?redirect=%2Fuser');
    authServiceMock.login.mockReturnValue(
      of({
        data: {
          user: { id: 'user-2', username: 'demo' },
        },
      }),
    );
    branchServiceMock.getAll.mockReturnValue(
      of({
        data: [
          { id: 'branch-1', name: 'Central' },
          { id: 'branch-2', name: 'North' },
        ],
      }),
    );

    renderAuthenticatedFlow();
    await submitLoginForm();

    expect(window.location.hash).toBe('#/login?redirect=%2Fuser');

    fireEvent.click(await screen.findByRole('button', { name: 'Central' }));
    fireEvent.click(screen.getByRole('button', { name: 'Aceptar' }));

    const shell = await screen.findByTestId('authenticated-shell');

    expect(shell.getAttribute('data-router-path')).toBe('/user');
    expect(window.location.hash).toBe('#/user');
    expect(document.querySelector('form.login-form')).toBeNull();
    expect(syncEventSpy).toHaveBeenCalled();
    expect(authServiceMock.selectBranch).toHaveBeenCalledWith('branch-1');
    expect(branchServiceMock.setSelectedBranchId).toHaveBeenCalledWith('branch-1');
    expect(branchServiceMock.setSelectedBranchName).toHaveBeenCalledWith('Central');
  });
});
