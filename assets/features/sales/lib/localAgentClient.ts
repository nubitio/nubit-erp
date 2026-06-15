import { exceptionalFetch } from '@shared/api/network';

export const AGENT_BASE_URL = 'http://localhost:8765';

export type AgentHealth = {
  status: string;
  version: string;
  config_path?: string;
  services?: {
    printer?: { available: boolean };
    scale?: {
      enabled: boolean;
      connected: boolean;
      protocol: string;
      port: string | null;
    };
  };
};

export type AgentPrinterConfig = {
  usb_vendor_id?: string | null;
  usb_product_id?: string | null;
  system_printer_name?: string | null;
  prefer_system_backend?: boolean;
  chunk_size?: number;
};

export type AgentScaleConfig = {
  enabled: boolean;
  serial_port?: string | null;
  protocol: string;
  baud_rate: number;
  data_bits: number;
  parity: string;
  stop_bits: number;
  stable_reads: number;
  stable_window_ms: number;
};

export type AgentConfig = {
  port: number;
  printer: AgentPrinterConfig;
  scale: AgentScaleConfig;
};

export type ScaleProtocolInfo = {
  id: string;
  name: string;
  description: string;
  default_baud_rate: number;
};

export type ScaleWeightReading = {
  kg: number | null;
  value: number | null;
  stable: boolean;
  connected: boolean;
  protocol: string;
  port: string | null;
  raw: string | null;
  updated_at_ms: number;
};

export type ScaleStatus = {
  enabled: boolean;
  connected: boolean;
  protocol: string;
  port: string | null;
  last_error?: string | null;
};

export type SerialPortInfo = {
  name: string;
  description?: string | null;
};

export class LocalAgentNotAvailableError extends Error {
  constructor() {
    super(
      'efact-hardware-agent no está disponible. ' +
        'Instala y ejecuta el agente local en esta computadora.',
    );
    this.name = 'LocalAgentNotAvailableError';
  }
}

async function agentFetch(path: string, init?: RequestInit): Promise<Response> {
  try {
    return await exceptionalFetch(`${AGENT_BASE_URL}${path}`, { mode: 'cors', ...init }, 'sdk-required');
  } catch {
    throw new LocalAgentNotAvailableError();
  }
}

export const fetchAgentHealth = async (): Promise<AgentHealth> => {
  const res = await agentFetch('/health');
  if (!res.ok) throw new Error(`Health check failed: ${res.status}`);
  return res.json() as Promise<AgentHealth>;
};

export const isLocalAgentAvailable = async (): Promise<boolean> => {
  try {
    await fetchAgentHealth();
    return true;
  } catch {
    return false;
  }
};

export const fetchAgentConfig = async (): Promise<{ config: AgentConfig; path: string }> => {
  const res = await agentFetch('/config');
  if (!res.ok) throw new Error(`No se pudo leer la configuración (${res.status})`);
  return res.json() as Promise<{ config: AgentConfig; path: string }>;
};

export const saveAgentConfig = async (update: {
  printer?: Partial<AgentPrinterConfig>;
  scale?: Partial<AgentScaleConfig>;
}): Promise<{ config: AgentConfig; path: string; message?: string }> => {
  const res = await agentFetch('/config', {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(update),
  });
  if (!res.ok) {
    const body = await res.text().catch(() => res.statusText);
    throw new Error(body || `Error ${res.status}`);
  }
  return res.json() as Promise<{ config: AgentConfig; path: string; message?: string }>;
};

export const fetchAgentPrinters = async (): Promise<string[]> => {
  const res = await agentFetch('/printers');
  if (!res.ok) return [];
  const data = (await res.json()) as { printers?: string[] };
  return data.printers ?? [];
};

export const fetchScaleProtocols = async (): Promise<ScaleProtocolInfo[]> => {
  const res = await agentFetch('/scale/protocols');
  if (!res.ok) return [];
  const data = (await res.json()) as { protocols?: ScaleProtocolInfo[] };
  return data.protocols ?? [];
};

export const fetchScalePorts = async (): Promise<SerialPortInfo[]> => {
  const res = await agentFetch('/scale/ports');
  if (!res.ok) return [];
  const data = (await res.json()) as { ports?: SerialPortInfo[] };
  return data.ports ?? [];
};

export const fetchScaleStatus = async (): Promise<ScaleStatus> => {
  const res = await agentFetch('/scale/status');
  if (!res.ok) throw new Error(`No se pudo leer estado de balanza (${res.status})`);
  return res.json() as Promise<ScaleStatus>;
};

export const fetchScaleWeight = async (): Promise<ScaleWeightReading> => {
  const res = await agentFetch('/scale/weight');
  if (!res.ok) {
    const body = await res.text().catch(() => res.statusText);
    throw new Error(body || `Error ${res.status}`);
  }
  return res.json() as Promise<ScaleWeightReading>;
};

export const waitForStableWeight = async (
  options: { timeoutMs?: number; pollMs?: number } = {},
): Promise<number> => {
  const timeoutMs = options.timeoutMs ?? 15_000;
  const pollMs = options.pollMs ?? 200;
  const deadline = Date.now() + timeoutMs;

  while (Date.now() < deadline) {
    const reading = await fetchScaleWeight();
    if (reading.stable && reading.kg != null && reading.kg > 0) {
      return reading.kg;
    }
    await new Promise((resolve) => setTimeout(resolve, pollMs));
  }

  throw new Error('No se obtuvo un peso estable de la balanza a tiempo.');
};