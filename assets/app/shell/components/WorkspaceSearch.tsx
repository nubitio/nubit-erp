import { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Combobox } from '@nubitio/ui';
import type { AdminMenuItem } from '@nubitio/admin';
import { useSettingsDialog, useSettingsNav } from '@app/profile';
import { parseSection } from '@app/profile/profileNav';
import { workspaceDestinations } from './workspaceNavigation';

const SETTINGS_PREFIX = 'settings:';

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
  const settingsGroups = useSettingsNav();
  const settingsDialog = useSettingsDialog();
  const options = useMemo(
    () => [
      ...workspaceDestinations(menu).map((item) => ({ value: item.path, label: item.text })),
      // Settings are not in the sidebar, so they are found here: a section of the dialog opens it,
      // the rest of the entries go to their own screen.
      ...settingsGroups
        .flatMap((group) => group.items)
        .map((item) => ({
          value: item.href ?? `${SETTINGS_PREFIX}${item.key}`,
          label: `Ajustes · ${item.label}`,
        })),
    ],
    [menu, settingsGroups],
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
      onChange={(value) => {
        if (!value || !options.some((option) => option.value === value)) {
          setSelected(value);
          return;
        }
        if (value.startsWith(SETTINGS_PREFIX)) {
          // The dialog opens over this page, so the box is left empty to search again.
          setSelected(null);
          settingsDialog.open(parseSection(value.slice(SETTINGS_PREFIX.length)));
        } else {
          setSelected(value);
          navigate(value);
        }
      }}
    />
  );
}
