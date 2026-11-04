import { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Combobox } from '@nubitio/ui';
import type { AdminMenuItem } from '@nubitio/admin';
import { workspaceDestinations } from './workspaceNavigation';

export function WorkspaceSearch({ menu }: { menu: AdminMenuItem[] }) {
  const navigate = useNavigate();
  const input = useRef<HTMLInputElement>(null);
  const [selected, setSelected] = useState<string | null>(null);
  // The keyboard hint only makes sense (and fits) where a keyboard is likely.
  const [wide, setWide] = useState(() => window.matchMedia('(min-width: 768px)').matches);
  useEffect(() => {
    const query = window.matchMedia('(min-width: 768px)');
    const update = () => setWide(query.matches);
    query.addEventListener('change', update);
    return () => query.removeEventListener('change', update);
  }, []);
  const options = useMemo(
    () => workspaceDestinations(menu).map((item) => ({ value: item.path, label: item.text })),
    [menu],
  );

  useEffect(() => {
    const focusSearch = (event: KeyboardEvent) => {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        input.current?.focus();
      }
    };
    window.addEventListener('keydown', focusSearch);
    return () => window.removeEventListener('keydown', focusSearch);
  }, []);

  return (
    <Combobox
      ref={input}
      aria-label="Buscar secciones del ERP"
      placeholder={wide ? 'Buscar sección · Ctrl K' : 'Buscar…'}
      options={options}
      value={selected}
      onChange={(path) => {
        setSelected(path);
        if (path && options.some((option) => option.value === path)) navigate(path);
      }}
    />
  );
}
