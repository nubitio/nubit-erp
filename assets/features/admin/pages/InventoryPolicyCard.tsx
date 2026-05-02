import { useCallback, useEffect, useState } from 'react';
import SelectBox from 'devextreme-react/select-box';
import { Button } from 'devextreme-react';
import { Constants } from '@core/common/Constants';
import { businessHttpClient } from '@shared/api/network';
import { lastValueFrom } from 'rxjs';
import toast from '@shared/lib/SharedUtils';

// ─── Types ───────────────────────────────────────────────────────────────────

type StockPolicy = 'STRICT' | 'WARNING_ONLY' | 'ALLOW_NEGATIVE';

interface InventoryPolicy {
  policy: StockPolicy;
  tenantId?: string;
  updatedAt?: string | null;
}

const POLICY_OPTIONS: Array<{ value: StockPolicy; text: string; description: string }> = [
  {
    value: 'STRICT',
    text: 'Estricto',
    description: 'Bloquea la venta si no hay stock suficiente.',
  },
  {
    value: 'WARNING_ONLY',
    text: 'Solo advertencia',
    description: 'Permite continuar la venta mostrando una alerta de stock bajo.',
  },
  {
    value: 'ALLOW_NEGATIVE',
    text: 'Permitir negativo',
    description: 'Permite ventas aunque el stock quede en negativo. Útil para restaurantes y distribuidoras.',
  },
];

const API_URL = Constants.API_BASE_URL + 'inventory-policy';

// ─── Component ───────────────────────────────────────────────────────────────

export const InventoryPolicyCard = () => {
  const [policy, setPolicy]     = useState<StockPolicy>('STRICT');
  const [pending, setPending]   = useState<StockPolicy>('STRICT');
  const [loading, setLoading]   = useState(true);
  const [saving, setSaving]     = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await lastValueFrom(businessHttpClient.get<InventoryPolicy>(API_URL));
      setPolicy(res.data.policy);
      setPending(res.data.policy);
    } catch {
      // default STRICT on error — non-fatal
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { void load(); }, [load]);

  const handleSave = async () => {
    setSaving(true);
    try {
      await lastValueFrom(
        businessHttpClient.post<InventoryPolicy>(API_URL, { policy: pending }),
      );
      setPolicy(pending);
      toast('Política de inventario actualizada', 'success');
    } catch {
      toast('Error al guardar la política', 'error');
    } finally {
      setSaving(false);
    }
  };

  const current = POLICY_OPTIONS.find((o) => o.value === pending);
  const dirty   = pending !== policy;

  if (loading) return null;

  return (
    <div style={{
      background: 'var(--base-bg, #fff)',
      border: '1px solid var(--base-border-color, #e2e8f0)',
      borderRadius: 8,
      padding: '20px 24px',
      marginBottom: 16,
    }}>
      <div style={{ marginBottom: 12 }}>
        <span style={{ fontWeight: 600, fontSize: 14 }}>Política de stock</span>
        <p style={{ fontSize: 12, color: '#64748b', margin: '4px 0 0' }}>
          Define el comportamiento cuando se intenta vender un producto sin stock suficiente.
        </p>
      </div>

      <div style={{ display: 'flex', gap: 12, alignItems: 'flex-end', flexWrap: 'wrap' }}>
        <div style={{ flex: '0 0 260px' }}>
          <SelectBox
            dataSource={POLICY_OPTIONS}
            displayExpr="text"
            valueExpr="value"
            value={pending}
            stylingMode="outlined"
            onValueChanged={(e) => setPending(e.value as StockPolicy)}
          />
        </div>

        <Button
          text={saving ? 'Guardando…' : 'Guardar'}
          type="default"
          stylingMode="contained"
          disabled={!dirty || saving}
          onClick={handleSave}
        />
      </div>

      {current && (
        <p style={{ fontSize: 12, color: '#64748b', marginTop: 8 }}>
          {current.description}
        </p>
      )}
    </div>
  );
};
