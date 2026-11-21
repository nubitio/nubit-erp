import type { AdminMenuItem, AdminBottomNavigationItem } from '@nubitio/admin';

/** Consume the already authorized menu; never invent destinations for the shell. */
export function workspaceDestinations(menu: AdminMenuItem[]): AdminBottomNavigationItem[] {
  const result = new Map<string, AdminBottomNavigationItem>();
  const add = (item: { text: string; path?: string; icon?: string }, fallbackIcon?: string) => {
    if (!item.path) return;
    // Internal routes only. Menu data must not turn search into an external redirect.
    const path = `/${item.path.replace(/^\/+|\/+$/g, '')}`;
    if (item.path.includes(':') || item.path.startsWith('//') || /[?#\\]/.test(path)) return;
    if (!result.has(path))
      result.set(path, { text: item.text, path, icon: item.icon ?? fallbackIcon });
  };
  menu.forEach((item) => {
    add(item);
    item.items?.forEach((child) => add(child, item.icon));
  });
  return [...result.values()];
}

export function mobileWorkspaceDestinations(menu: AdminMenuItem[]): AdminBottomNavigationItem[] {
  const allowed = workspaceDestinations(menu);
  return ['/dashboard', '/pos', '/billing', '/customer', '/catalog'].flatMap((path) =>
    allowed.filter((item) => item.path === path),
  );
}

/**
 * Section labels for grouped routes. They are listed here, not read from the menu, because a screen
 * reachable by URL can be absent from the user's menu (capability off) and would otherwise show the
 * branch name as its title. Menu authorization remains independent.
 */
export function workspaceSectionTitle(
  pathname: string,
  menu: AdminMenuItem[],
  fallback: string,
): string {
  const sections: Array<[string, string]> = [
    ['/config', 'Administración'],
    ['/cashbox', 'Caja'],
    ['/cash-session', 'Caja'],
    ['/cash-register', 'Caja'],
    ['/accounts-payable', 'Finanzas'],
    ['/accounts-receivable', 'Finanzas'],
    ['/supplier-bill', 'Finanzas'],
    ['/expense-category', 'Finanzas'],
    ['/treasury', 'Finanzas'],
    ['/finance-summary', 'Finanzas'],
    ['/bank-reconciliation', 'Finanzas'],
    ['/bank-accounts', 'Finanzas'],
    ['/bank-statement-imports', 'Finanzas'],
    ['/financial-reports', 'Finanzas'],
    ['/expense-reports', 'Finanzas'],
    ['/fixed-assets', 'Finanzas'],
    ['/accounting', 'Finanzas'],
    ['/inventory', 'Inventario'],
    ['/purchases', 'Compras'],
  ];
  const section = sections.find(([path]) => pathname === path || pathname.startsWith(`${path}/`));
  return (
    section?.[1] ??
    workspaceDestinations(menu)
      .filter((item) => pathname === item.path || pathname.startsWith(`${item.path}/`))
      .sort((a, b) => b.path.length - a.path.length)[0]?.text ??
    fallback
  );
}
