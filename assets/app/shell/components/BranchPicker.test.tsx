// @vitest-environment jsdom

import React from 'react';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { of } from 'rxjs';

const { authServiceMock, branchServiceMock, navigateMock, requestAuthSessionSyncMock, toastMock } =
  vi.hoisted(() => ({
    authServiceMock: {
      selectBranch: vi.fn(),
    },
    branchServiceMock: {
      getAll: vi.fn(),
      getSelectedBranchId: vi.fn(() => null),
      setSelectedBranchId: vi.fn(),
      setSelectedBranchName: vi.fn(),
    },
    navigateMock: vi.fn(),
    requestAuthSessionSyncMock: vi.fn(),
    toastMock: vi.fn(),
  }));

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom');

  return {
    ...actual,
    useNavigate: () => navigateMock,
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
    onSelectionChanged,
  }: {
    onSelectionChanged: (event: { addedItems?: Array<{ id: string; name: string }> }) => void;
  }) => (
    <div>
      <button
        type="button"
        onClick={() => onSelectionChanged({ addedItems: [{ id: 'branch-1', name: 'Central' }] })}
      >
        Central
      </button>
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

vi.mock('@features/auth', () => ({
  authService: authServiceMock,
}));

vi.mock('@features/branch', () => ({
  branchService: branchServiceMock,
}));

vi.mock('@shared/lib/authSessionSync', () => ({
  requestAuthSessionSync: requestAuthSessionSyncMock,
}));

vi.mock('@shared/lib/SharedUtils', () => ({
  default: toastMock,
}));

vi.mock('@utils/media-query', () => ({
  useScreenSize: () => ({ isXSmall: false }),
}));

import { BranchPicker } from './BranchPicker';

afterEach(() => {
  cleanup();
});

describe('BranchPicker redirect completion', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    window.history.replaceState({}, '', '/#/login?redirect=%2Fpos');
    authServiceMock.selectBranch.mockReturnValue(of({}));
    branchServiceMock.getAll.mockReturnValue(of({ data: [] }));
  });

  it('keeps the preserved redirect path instead of falling back to stale login hash state', async () => {
    render(
      <BranchPicker
        visible
        setVisible={vi.fn()}
        initialBranches={[{ id: 'branch-1', name: 'Central' }]}
        redirectPath="/user?tab=roles"
      />,
    );

    fireEvent.click(await screen.findByRole('button', { name: 'Central' }));
    fireEvent.click(screen.getByRole('button', { name: 'Aceptar' }));

    await waitFor(() => {
      expect(authServiceMock.selectBranch).toHaveBeenCalledWith('branch-1');
      expect(navigateMock).toHaveBeenCalledWith('/user?tab=roles', { replace: true });
    });

    expect(branchServiceMock.setSelectedBranchId).toHaveBeenCalledWith('branch-1');
    expect(branchServiceMock.setSelectedBranchName).toHaveBeenCalledWith('Central');
    expect(requestAuthSessionSyncMock).toHaveBeenCalledTimes(1);
  });
});
