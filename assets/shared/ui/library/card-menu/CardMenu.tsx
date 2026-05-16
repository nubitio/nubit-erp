import React, { useRef, useState, useEffect } from 'react';
import './CardMenu.scss';

interface CardMenuItem {
  text: string;
  onClick?: () => void;
}

interface CardMenuProps {
  items: CardMenuItem[];
  visible?: boolean;
}

export const CardMenu = ({ items, visible = true }: CardMenuProps) => {
  const [open, setOpen] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    const handler = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setOpen(false);
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [open]);

  if (!visible) return null;

  return (
    <div ref={containerRef} className="card-menu">
      <button
        type="button"
        className="app-icon-button card-menu__trigger"
        aria-haspopup="menu"
        aria-expanded={open}
        aria-label="Opciones"
        onClick={() => setOpen((v) => !v)}
      >
        <i className="ph ph-dots-three" aria-hidden="true" />
      </button>
      {open && (
        <ul className="card-menu__dropdown" role="menu">
          {items.map((item, idx) => (
            <li key={idx} role="none">
              <button
                type="button"
                role="menuitem"
                className="card-menu__item"
                onClick={() => {
                  setOpen(false);
                  item.onClick?.();
                }}
              >
                {item.text}
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
};
