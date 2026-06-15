import { exceptionalFetch } from '@shared/api/network';

/** Use 127.0.0.1 — on macOS, localhost (::1) may hit Docker/Symfony on :8765 instead of the agent. */
export const AGENT_BASE_URL = 'http://127.0.0.1:8765';

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

/** Agent serializes printer fields flattened at the top level of `config`. */
type RawAgentConfig = Partial<AgentConfig> &
  Partial<AgentPrinterConfig> & {
    usb_endpoint?: number;
  };

const DEFAULT_PRINTER: AgentPrinterConfig = {
  usb_vendor_id: null,
  usb_product_id: null,
  system_printer_name: null,
  prefer_system_backend: false,
  chunk_size: 4096,
};

const DEFAULT_SCALE: AgentScaleConfig = {
  enabled: false,
  serial_port: null,
  protocol: 'generic',
  baud_rate: 9600,
  data_bits: 8,
  parity: 'none',
  stop_bits: 1,
  stable_reads: 3,
  stable_window_ms: 200,
};

export const normalizeAgentConfig = (raw: RawAgentConfig): AgentConfig => {
  if (raw.printer) {
    return {
      port: raw.port ?? 8765,
      printer: { ...DEFAULT_PRINTER, ...raw.printer },
      scale: { ...DEFAULT_SCALE, ...raw.scale, protocol: 'generic' },
    };
  }

  return {
    port: raw.port ?? 8765,
    printer: {
      usb_vendor_id: raw.usb_vendor_id ?? null,
      usb_product_id: raw.usb_product_id ?? null,
      system_printer_name: raw.system_printer_name ?? null,
      prefer_system_backend: raw.prefer_system_backend ?? false,
      chunk_size: raw.chunk_size ?? 4096,
    },
    scale: { ...DEFAULT_SCALE, ...raw.scale, protocol: 'generic' },
  };
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
  const data = (await res.json()) as { config: RawAgentConfig; path: string };
  return { config: normalizeAgentConfig(data.config), path: data.path };
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
  const data = (await res.json()) as {
    config: RawAgentConfig;
    path: string;
    message?: string;
  };
  return { ...data, config: normalizeAgentConfig(data.config) };
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