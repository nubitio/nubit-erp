import { useNavigate } from 'react-router-dom';

type AttentionStripProps = {
  lowStockCount: number;
  pendingSunatCount: number;
};

/** Compact "needs attention today" row — only shows items that have a problem. */
export function AttentionStrip({ lowStockCount, pendingSunatCount }: AttentionStripProps) {
  const navigate = useNavigate();

  const items = [
    lowStockCount > 0 && {
      key: 'stock',
      count: lowStockCount,
      label: lowStockCount === 1 ? 'producto bajo mínimo' : 'productos bajo mínimo',
      to: '/inventory/reorder',
    },
    pendingSunatCount > 0 && {
      key: 'sunat',
      count: pendingSunatCount,
      label:
        pendingSunatCount === 1
          ? 'comprobante pendiente SUNAT'
          : 'comprobantes pendientes SUNAT',
      to: '/billing/documents',
    },
  ].filter(Boolean) as Array<{ key: string; count: number; label: string; to: string }>;

  if (items.length === 0) {
    return (
      <p className="efact-attention efact-attention--ok">
        <i className="ph ph-check-circle" aria-hidden="true" /> Todo al día en este local.
      </p>
    );
  }

  return (
    <div className="efact-attention" role="list" aria-label="Requiere atención">
      {items.map((item) => (
        <button
          key={item.key}
          type="button"
          role="listitem"
          className="efact-attention__item"
          onClick={() => navigate(item.to)}
        >
          <i className="ph ph-warning" aria-hidden="true" />
          <span>
            <strong>{item.count}</strong> {item.label}
          </span>
          <i className="ph ph-arrow-right efact-attention__go" aria-hidden="true" />
        </button>
      ))}
    </div>
  );
}
