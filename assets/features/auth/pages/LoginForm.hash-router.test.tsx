// @vitest-environment jsdom

import React from 'react';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { of } from 'rxjs';
import { HashRouter, Route, Routes } from 'react-router-dom';

const { authServiceMock, branchServiceMock, requestAuthSessionSyncMock, storageSetItemMock } =
  vi.hoisted(() => ({
    authServiceMock: {
      login: vi.fn(),
      selectBranch: vi.fn(),
    },
    branchServiceMock: {
      getAll: vi.fn(),
      getSelectedBranchId: vi.fn(() => null),
      setSelectedBranchId: vi.fn(),
      setSelectedBranchName: vi.fn(),
    },
    requestAuthSessionSyncMock: vi.fn(),
    storageSetItemMock: vi.fn(),
  }));

vi.mock('./LoginForm.scss', () => ({}));

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
            // eslint-disable-next-line react-hooks/immutability -- test double intentionally mirrors DevExtreme mutating the bound formData object
            formData.username = event.target.value;
            onFieldDataChanged?.();
          }}
        />
        <input
          aria-label="Contraseña"
          defaultValue={formData.password}
          onChange={(event) => {
            // eslint-disable-next-line react-hooks/immutability -- test double intentionally mirrors DevExtreme mutating the bound formData object
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
    ButtonOptions: ({ children }: React.PropsWithChildren) => <>{children}</>,
    Item: ({ children }: React.PropsWithChildren) => <>{children}</>,
    RequiredRule: () => null,
  };
});

vi.mock('@features/auth', () => ({
  authService: authServiceMock,
}));

vi.mock('@features/branch', () => ({
  branchService: branchServiceMock,
}));

vi.mock('@shared/lib/StorageUtils', () => ({
  StorageUtils: {
    setItem: storageSetItemMock,
  },
}));

vi.mock('@shared/lib/authSessionSync', () => ({
  requestAuthSessionSync: requestAuthSessionSyncMock,
}));

vi.mock('@shared/ui/utils/branch-picker/BranchPicker', () => ({
  BranchPicker: () => null,
}));

import { LoginForm } from './LoginForm';

afterEach(() => {
  cleanup();
});

describe('LoginForm HashRouter redirect regression', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    window.history.replaceState({}, '', '/#/login?redirect=%2Fuser');

    authServiceMock.login.mockReturnValue(
      of({
        data: {
          user: { id: 'user-1' },
          branch: { id: 'branch-1', name: 'Central' },
        },
      }),
    );
    authServiceMock.selectBranch.mockReturnValue(of({}));
    branchServiceMock.getAll.mockReturnValue(of({ data: [] }));
  });

  it('resolves an explicit post-login redirect to the final hash-based URL', async () => {
    render(
      <HashRouter>
        <Routes>
          <Route path="/login" element={<LoginForm />} />
          <Route path="/user" element={<div data-testid="user-route">Users</div>} />
          <Route path="/" element={<div data-testid="home-route">Home</div>} />
        </Routes>
      </HashRouter>,
    );

    fireEvent.change(screen.getByLabelText('Usuario'), { target: { value: 'demo' } });
    fireEvent.change(screen.getByLabelText('Contraseña'), { target: { value: 'secret' } });
    fireEvent.submit(document.querySelector('form.login-form') as HTMLFormElement);

    await waitFor(() => {
      expect(authServiceMock.login).toHaveBeenCalledWith('demo', 'secret');
      expect(authServiceMock.selectBranch).toHaveBeenCalledWith('branch-1');
    });

    await waitFor(() => {
      expect(window.location.hash).toBe('#/user');
      expect(screen.getByTestId('user-route')).toBeTruthy();
    });

    expect(branchServiceMock.setSelectedBranchId).toHaveBeenCalledWith('branch-1');
    expect(branchServiceMock.setSelectedBranchName).toHaveBeenCalledWith('Central');
    expect(storageSetItemMock).toHaveBeenCalledWith('user', { id: 'user-1' });
    expect(requestAuthSessionSyncMock).toHaveBeenCalledTimes(1);
  });
});
