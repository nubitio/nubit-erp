import { useEffect, useState } from 'react';
import { lastValueFrom } from 'rxjs';
import { httpClient } from '@shared/api/HttpClient';
import toast from '@shared/lib/SharedUtils';

interface ExchangeRateData {
  currency: string;
  rate: string;
  updated_at: string | null;
}

const API_URL = 'api/v1/exchange-rate';

export const ExchangeRatePage = () => {
  const [data, setData] = useState<ExchangeRateData | null>(null);
  const [rateInput, setRateInput] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [syncing, setSyncing] = useState(false);

  const load = async () => {
    try {
      const response = await lastValueFrom(httpClient.get<ExchangeRateData>(API_URL));
      setData(response.data);
      setRateInput(response.data.rate);
    } catch {
      toast('Error al cargar el tipo de cambio', 'error');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    void load();
  }, []);

  const handleSave = async () => {
    const rate = Number(rateInput);
    if (!rateInput || isNaN(rate) || rate <= 0) {
      toast('Ingresa un tipo de cambio válido mayor a 0', 'error');
      return;
    }
    setSaving(true);
    try {
      const response = await lastValueFrom(
        httpClient.put<ExchangeRateData>(API_URL, { rate: rateInput }),
      );
      setData(response.data);
      setRateInput(response.data.rate);
      toast('Tipo de cambio actualizado', 'success');
    } catch {
      toast('Error al guardar el tipo de cambio', 'error');
    } finally {
      setSaving(false);
    }
  };

  const handleSync = async () => {
    setSyncing(true);
    try {
      const response = await lastValueFrom(
        httpClient.post<ExchangeRateData>(`${API_URL}/sync`),
      );
      setData(response.data);
      setRateInput(response.data.rate);
      toast('Tipo de cambio sincronizado desde BCRP', 'success');
    } catch {
      toast('No se pudo sincronizar desde BCRP. Intente más tarde.', 'warning');
    } finally {
      setSyncing(false);
    }
  };

  return (
    <div style={{ maxWidth: 480, margin: '2rem auto', padding: '0 1rem' }}>
      <h2 style={{ marginBottom: '0.5rem' }}>Tipo de Cambio USD/PEN</h2>
      {data?.updated_at && (
        <p style={{ color: 'var(--color-text-muted)', marginBottom: '1.5rem', fontSize: '0.875rem' }}>
          Última actualización: {new Date(data.updated_at).toLocaleString('es-PE')} (UTC)
        </p>
      )}

      {loading ? (
        <p>Cargando...</p>
      ) : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          <div>
            <label htmlFor="exchange-rate-input" style={{ display: 'block', marginBottom: '0.4rem', fontWeight: 500 }}>
              1 USD =
            </label>
            <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'center' }}>
              <input
                id="exchange-rate-input"
                type="number"
                min={0.01}
                step="0.01"
                value={rateInput}
                onChange={(e) => setRateInput(e.target.value)}
                style={{ width: 140, padding: '0.4rem 0.6rem', fontSize: '1.1rem' }}
              />
              <span style={{ fontWeight: 600, fontSize: '1rem' }}>PEN</span>
            </div>
          </div>

          <div style={{ display: 'flex', gap: '0.75rem', flexWrap: 'wrap' }}>
            <button
              type="button"
              onClick={() => void handleSave()}
              disabled={saving}
              style={{ padding: '0.5rem 1.25rem' }}
            >
              {saving ? 'Guardando...' : 'Guardar'}
            </button>
            <button
              type="button"
              onClick={() => void handleSync()}
              disabled={syncing}
              style={{ padding: '0.5rem 1.25rem' }}
            >
              {syncing ? 'Sincronizando...' : 'Sincronizar desde BCRP'}
            </button>
          </div>
        </div>
      )}
    </div>
  );
};
