import { act, cleanup, render, screen } from '@testing-library/react';
import { useState } from 'react';
import {
  Link,
  Navigate,
  Route,
  Routes,
  createMemoryRouter,
  useBlocker,
  useLocation,
} from 'react-router-dom';
import { afterEach, describe, expect, it } from 'vitest';
import { APP_ROUTE_OBJECTS, RouterRoot } from './RouterRoot';

const routerAt = (entry: string) =>
  createMemoryRouter(APP_ROUTE_OBJECTS, { initialEntries: [entry] });

const Where = () => <p data-testid="where">{useLocation().pathname + useLocation().search}</p>;

function Tree() {
  return (
    <Routes>
      <Route path="/billing/sales" element={<Where />} />
      <Route path="/credit-note/:id?" element={<Where />} />
      <Route path="/login" element={<Where />} />
      <Route path="*" element={<Navigate to="/login" replace />} />
    </Routes>
  );
}

describe('RouterRoot', () => {
  // Vitest globals are off, so Testing Library does not clean up on its own.
  afterEach(cleanup);

  it('keeps an existing <Routes> tree working under the splat route', () => {
    render(
      <RouterRoot router={routerAt('/billing/sales?highlight=7')}>
        <Tree />
      </RouterRoot>,
    );

    expect(screen.getByTestId('where').textContent).toBe('/billing/sales?highlight=7');
  });

  it('resolves optional params and the catch-all exactly as before', () => {
    const first = render(
      <RouterRoot router={routerAt('/credit-note')}>
        <Tree />
      </RouterRoot>,
    );
    expect(screen.getByTestId('where').textContent).toBe('/credit-note');
    first.unmount();

    render(
      <RouterRoot router={routerAt('/nowhere/at/all')}>
        <Tree />
      </RouterRoot>,
    );
    expect(screen.getByTestId('where').textContent).toBe('/login');
  });

  it('passes fresh children through to the already-created router', () => {
    const router = routerAt('/login');
    const view = render(
      <RouterRoot router={router}>
        <p>one</p>
      </RouterRoot>,
    );
    expect(screen.getByText('one')).toBeTruthy();

    view.rerender(
      <RouterRoot router={router}>
        <p>two</p>
      </RouterRoot>,
    );
    expect(screen.getByText('two')).toBeTruthy();
    expect(screen.queryByText('one')).toBeNull();
  });

  it('enables useBlocker, which HashRouter could not (the reason for this migration)', async () => {
    function Guarded() {
      const [dirty] = useState(true);
      const blocker = useBlocker(dirty);
      return (
        <div>
          <span data-testid="state">{blocker.state}</span>
          <Link to="/elsewhere">leave</Link>
        </div>
      );
    }

    const router = routerAt('/form');
    render(
      <RouterRoot router={router}>
        <Guarded />
      </RouterRoot>,
    );

    expect(screen.getByTestId('state').textContent).toBe('unblocked');
    await act(async () => {
      await router.navigate('/elsewhere');
    });
    expect(screen.getByTestId('state').textContent).toBe('blocked');
    expect(router.state.location.pathname).toBe('/form');
  });
});
