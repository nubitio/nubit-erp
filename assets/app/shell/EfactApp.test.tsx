// @vitest-environment jsdom

import React from 'react';
import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const { contentSpy, fullScreenSpy, loadPanelSpy, locationState, unauthenticatedSpy, useAuthMock } =
  vi.hoisted(() => ({
    contentSpy: vi.fn(() => <div data-testid="content-shell">Content</div>),
    fullScreenSpy: vi.fn(() => <div data-testid="fullscreen-shell">POS</div>),
    loadPanelSpy: vi.fn((_props?: { visible?: boolean }) => (
      <div data-testid="shell-loading">Loading</div>
    )),
    locationState: {
      pathname: '/user',
    },
    unauthenticatedSpy: vi.fn(() => <div data-testid="unauthenticated-shell">Login</div>),
    useAuthMock: vi.fn(),
  }));

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom');

  return {
    ...actual,
    useLocation: () => locationState,
  };
});

vi.mock('devextreme-react/load-panel', () => ({
  default: (props: { visible?: boolean }) => loadPanelSpy(props),
}));

vi.mock('../../utils/media-query', () => ({
  useScreenSizeClass: () => 'screen-lg',
}));

vi.mock('../../theme/theme', () => ({
  useThemeContext: () => ({ isLoaded: true }),
}));

vi.mock('@app/providers/devextreme', () => ({
  configureDevExtreme: vi.fn(),
}));

vi.mock('@core/mercure', () => ({
  MercureManager: {
    getHubUrl: vi.fn(() => null),
    onHubUrlChange: vi.fn(() => vi.fn()),
  },
}));

vi.mock('../AppProviders', () => ({
  AppProviders: ({ children }: React.PropsWithChildren) => <>{children}</>,
}));

vi.mock('@app/contexts/auth', () => ({
  hasCommittedAuthSession: vi.fn(() => false),
  useAuth: () => useAuthMock(),
}));

vi.mock('./Content', () => ({
  Content: () => contentSpy(),
}));

vi.mock('./FullScreenContent', () => ({
  FullScreenContent: () => fullScreenSpy(),
}));

vi.mock('./UnauthenticatedContent', () => ({
  UnauthenticatedContent: () => unauthenticatedSpy(),
}));

import * as authContext from '@app/contexts/auth';
import { RootApp } from './EfactApp';

afterEach(() => {
  cleanup();
});

describe('RootApp authenticated shell readiness', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    locationState.pathname = '/user';
    useAuthMock.mockReturnValue({ loading: false, user: undefined });
    vi.mocked(authContext.hasCommittedAuthSession).mockReturnValue(false);
  });

  it('waits on the shell instead of redirecting back to login when committed auth storage is already present', () => {
    vi.mocked(authContext.hasCommittedAuthSession).mockReturnValue(true);

    render(<RootApp />);

    expect(screen.getByTestId('shell-loading')).toBeTruthy();
    expect(unauthenticatedSpy).not.toHaveBeenCalled();
    expect(contentSpy).not.toHaveBeenCalled();
  });

  it('renders the authenticated shell once the synced session reaches context state', () => {
    useAuthMock.mockReturnValue({ loading: false, user: { username: 'demo' } });

    render(<RootApp />);

    expect(screen.getByTestId('content-shell')).toBeTruthy();
    expect(unauthenticatedSpy).not.toHaveBeenCalled();
  });
});
