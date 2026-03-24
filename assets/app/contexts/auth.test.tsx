// @vitest-environment jsdom

import React from 'react';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { requestAuthSessionSync } from '@shared/lib/authSessionSync';

const { branchServiceMock, storageState } = vi.hoisted(() => ({
  branchServiceMock: {
    hasSelectedBranch: vi.fn(() => false),
    hasSelectedBranchName: vi.fn(() => false),
    clearSelectedBranchId: vi.fn(),
    clearSelectedBranchName: vi.fn(),
  },
  storageState: new Map<string, unknown>(),
}));

vi.mock('../../features/branch', () => ({
  branchService: branchServiceMock,
}));

vi.mock('../../features/auth', () => ({
  authService: {
    login: vi.fn(),
    logout: vi.fn(),
  },
}));

vi.mock('../../shared/lib/StorageUtils', () => ({
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

import { AuthProvider, useAuth } from './auth';

afterEach(() => {
  cleanup();
});

const AuthConsumer = () => {
  const { loading, user } = useAuth();

  if (loading) {
    return <span>loading</span>;
  }

  return (
    <>
      <span data-testid="auth-user">{user?.username ?? 'anonymous'}</span>
      <button type="button" onClick={() => requestAuthSessionSync()}>
        sync
      </button>
    </>
  );
};

describe('AuthProvider session synchronization', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    storageState.clear();
    branchServiceMock.hasSelectedBranch.mockReturnValue(false);
    branchServiceMock.hasSelectedBranchName.mockReturnValue(false);
  });

  it('hydrates the authenticated user from storage as soon as branch context is committed', async () => {
    render(
      <AuthProvider>
        <AuthConsumer />
      </AuthProvider>,
    );

    await waitFor(() => {
      expect(screen.getByTestId('auth-user').textContent).toBe('anonymous');
    });

    storageState.set('user', {
      username: 'demo',
    });
    branchServiceMock.hasSelectedBranch.mockReturnValue(true);
    branchServiceMock.hasSelectedBranchName.mockReturnValue(true);

    fireEvent.click(screen.getByRole('button', { name: 'sync' }));

    await waitFor(() => {
      expect(screen.getByTestId('auth-user').textContent).toBe('demo');
    });
  });
});
