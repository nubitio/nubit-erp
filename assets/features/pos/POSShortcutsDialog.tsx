import { AppDialog } from '@nubitio/ui';

interface POSShortcutsDialogProps {
  open: boolean;
  onClose: () => void;
  /** Ctrl+K only exists while the copilot is enabled. */
  assistantEnabled?: boolean;
}

type Shortcut = [keys: string, description: string];

/** Single source of truth for what the POS binds; keep in sync with the handler in POSPage. */
export const posShortcutGroups = (
  assistantEnabled: boolean,
): { title: string; items: Shortcut[] }[] => [
  {
    title: 'Venta',
    items: [
      ['F2  ·  /', 'Enfocar el buscador de productos'],
      ['Lector → Enter', 'Añadir producto por código de barras'],
      ['F8', 'Editar la cantidad de la última línea'],
      ['Esc', 'Vaciar el carrito (con confirmación)'],
    ],
  },
  {
    title: 'Cliente',
    items: [
      ['F3', 'Seleccionar cliente'],
      ['Shift + F3', 'Crear cliente nuevo'],
    ],
  },
  {
    title: 'Cobro',
    items: [
      ['F9  ·  Enter', 'Cobrar; dentro del cobro, confirma'],
      ['F4', 'Poner la venta en espera'],
      ['Shift + F4', 'Ver ventas en espera'],
    ],
  },
  {
    title: 'General',
    items: [
      ['?', 'Mostrar esta lista'],
      ...(assistantEnabled ? ([['Ctrl / ⌘ + K', 'Asistente de IA']] as Shortcut[]) : []),
    ],
  },
];

export function POSShortcutsDialog({
  open,
  onClose,
  assistantEnabled = false,
}: POSShortcutsDialogProps) {
  return (
    <AppDialog open={open} title="Atajos de teclado" onClose={onClose}>
      {posShortcutGroups(assistantEnabled).map((group) => (
        <section key={group.title} aria-label={group.title}>
          <h3 className="pos-shortcuts-group">{group.title}</h3>
          <ul className="pos-shortcuts-list">
            {group.items.map(([keys, description]) => (
              <li key={keys} className="pos-shortcuts-list__item">
                <kbd className="pos-shortcuts-list__key">{keys}</kbd>
                <span>{description}</span>
              </li>
            ))}
          </ul>
        </section>
      ))}
    </AppDialog>
  );
}
