// @vitest-environment jsdom

import React from 'react';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { of } from 'rxjs';

const {
  authServiceMock,
  branchServiceMock,
  branchPickerPropsSpy,
  locationState,
  navigateMock,
  requestAuthSessionSyncMock,
  storageSetItemMock,
} = vi.hoisted(() => ({
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
  branchPickerPropsSpy: vi.fn(),
  locationState: {
    search: '',
  },
  navigateMock: vi.fn(),
  requestAuthSessionSyncMock: vi.fn(),
  storageSetItemMock: vi.fn(),
}));

vi.mock('./LoginForm.scss', () => ({}));

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom');

  return {
    ...actual,
    useLocation: () => locationState,
    useNavigate: () => navigateMock,
  };
});

vi.mock('@shared/lib/authSessionSync', () => ({
  requestAuthSessionSync: requestAuthSessionSyncMock,
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
            // eslint-disable-next-line react-hooks/immutability -- test double must emulate DevExtreme mutating the provided formData object
            formData.username = event.target.value;
            onFieldDataChanged?.();
          }}
        />
        <input
          aria-label="Contraseña"
          defaultValue={formData.password}
          onChange={(event) => {
            // eslint-disable-next-line react-hooks/immutability -- test double must emulate DevExtreme mutating the provided formData object
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

vi.mock('@shared/ui/utils/branch-picker/BranchPicker', () => ({
  BranchPicker: (props: {
    visible: boolean;
    redirectPath?: string;
    initialBranches?: Array<{ id: string; name: string }>;
  }) => {
    branchPickerPropsSpy(props);

    if (!props.visible) {
      return null;
    }

    return (
      <div
        data-testid="branch-picker"
        data-redirect-path={props.redirectPath ?? ''}
        data-branch-count={props.initialBranches?.length ?? 0}
      />
    );
  },
}));

import { LoginForm } from './LoginForm';

afterEach(() => {
  cleanup();
});

describe('LoginForm redirect regression coverage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    locationState.search = '';
    window.history.replaceState({}, '', '/#/login');

    authServiceMock.selectBranch.mockReturnValue(of({}));
    branchServiceMock.getAll.mockReturnValue(of({ data: [] }));
  });

  const submitLoginForm = async () => {
    fireEvent.change(screen.getByLabelText('Usuario'), { target: { value: 'demo' } });
    fireEvent.change(screen.getByLabelText('Contraseña'), { target: { value: 'secret' } });
    fireEvent.submit(document.querySelector('form') as HTMLFormElement);

    await waitFor(() => {
      expect(authServiceMock.login).toHaveBeenCalledWith('demo', 'secret');
    });
  };

  const renderAndSubmitWithResolvedRedirect = async (search: string) => {
    locationState.search = search;
    window.history.replaceState({}, '', `/#/login${search}`);

    authServiceMock.login.mockReturnValue(
      of({
        data: {
          user: { id: 'user-1' },
          branch: { id: 'branch-1', name: 'Central' },
        },
      }),
    );

    render(<LoginForm />);
    await submitLoginForm();

    await waitFor(() => {
      expect(authServiceMock.selectBranch).toHaveBeenCalledWith('branch-1');
    });
  };

  it.each([
    ['defaults to the canonical landing route', '', '/'],
    ['applies an explicit internal redirect', '?redirect=%2Fuser', '/user'],
    [
      'falls back for unsafe external redirect input',
      '?redirect=https%3A%2F%2Fevil.example%2Fsteal',
      '/',
    ],
    ['prevents redirecting back into login', '?redirect=%2Flogin', '/'],
  ])('%s after successful login', async (_label, search, expectedPath) => {
    await renderAndSubmitWithResolvedRedirect(search);

    await waitFor(() => {
      expect(requestAuthSessionSyncMock).toHaveBeenCalledTimes(1);
      expect(navigateMock).toHaveBeenCalledWith(expectedPath, { replace: true });
    });

    expect(branchServiceMock.setSelectedBranchId).toHaveBeenCalledWith('branch-1');
    expect(branchServiceMock.setSelectedBranchName).toHaveBeenCalledWith('Central');
    expect(storageSetItemMock).toHaveBeenCalledWith('user', { id: 'user-1' });
  });

  it('preserves the resolved redirect when branch selection is required', async () => {
    locationState.search = '?redirect=%2Fuser%3Ftab%3Droles';
    window.history.replaceState({}, '', '/#/login?redirect=%2Fuser%3Ftab%3Droles');

    authServiceMock.login.mockReturnValue(
      of({
        data: {
          user: { id: 'user-2' },
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

    render(<LoginForm />);
    await submitLoginForm();

    await waitFor(() => {
      const branchPicker = screen.getByTestId('branch-picker');

      expect(branchPicker.getAttribute('data-redirect-path')).toBe('/user?tab=roles');
      expect(branchPicker.getAttribute('data-branch-count')).toBe('2');
    });

    expect(authServiceMock.selectBranch).not.toHaveBeenCalled();
  });
});
