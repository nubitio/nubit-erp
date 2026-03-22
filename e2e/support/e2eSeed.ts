import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { request as httpRequest } from 'node:http';
import { request as httpsRequest } from 'node:https';

type E2ESeedPayload = {
  baseUrl: string;
  password: string;
  saleId: string;
  username: string;
};

const DEFAULT_BASE_URL = 'http://localhost:8080';
const E2E_RUNTIME_DIR = 'var/e2e';
const E2E_RUNTIME_FILE = `${E2E_RUNTIME_DIR}/seed.json`;

let seedPromise: Promise<E2ESeedPayload> | null = null;

function getBaseUrl(): string {
  return process.env.E2E_BASE_URL ?? DEFAULT_BASE_URL;
}

function readEnvSeed(): E2ESeedPayload | null {
  const username = process.env.E2E_USERNAME;
  const password = process.env.E2E_PASSWORD;
  const saleId = process.env.E2E_SALE_ID;

  if (!username || !password || !saleId) {
    return null;
  }

  return {
    baseUrl: getBaseUrl(),
    password,
    saleId,
    username,
  };
}

async function readRuntimeSeed(): Promise<E2ESeedPayload | null> {
  try {
    const raw = await readFile(E2E_RUNTIME_FILE, 'utf8');
    const payload = JSON.parse(raw) as Partial<E2ESeedPayload>;

    if (!payload.username || !payload.password || !payload.saleId) {
      return null;
    }

    return {
      baseUrl: payload.baseUrl ?? getBaseUrl(),
      password: payload.password,
      saleId: String(payload.saleId),
      username: payload.username,
    };
  } catch {
    return null;
  }
}

async function persistSeed(payload: E2ESeedPayload): Promise<void> {
  await mkdir(E2E_RUNTIME_DIR, { recursive: true });
  await writeFile(E2E_RUNTIME_FILE, JSON.stringify(payload, null, 2));
}

async function seedFromApi(): Promise<E2ESeedPayload> {
  const baseUrl = getBaseUrl();
  const url = new URL('/api/test/e2e/seed', baseUrl);
  const response = await new Promise<{
    body: string;
    ok: boolean;
    status: number;
    statusText: string;
  }>((resolve, reject) => {
    const requestImpl = url.protocol === 'https:' ? httpsRequest : httpRequest;
    const req = requestImpl(
      url,
      {
        method: 'POST',
      },
      (res) => {
        const chunks: Buffer[] = [];

        res.on('data', (chunk) => {
          chunks.push(Buffer.isBuffer(chunk) ? chunk : Buffer.from(chunk));
        });

        res.on('end', () => {
          const body = Buffer.concat(chunks).toString('utf8');
          resolve({
            body,
            ok: (res.statusCode ?? 500) >= 200 && (res.statusCode ?? 500) < 300,
            status: res.statusCode ?? 500,
            statusText: res.statusMessage ?? 'Unknown Error',
          });
        });
      },
    );

    req.on('error', reject);
    req.end();
  });

  if (!response.ok) {
    throw new Error(
      `Unable to seed E2E data via ${baseUrl}/api/test/e2e/seed (${response.status} ${response.statusText}). ` +
        'Ensure the test stack is running with E2E_SEED_ENABLED=1 or provide E2E_USERNAME, E2E_PASSWORD, and E2E_SALE_ID.',
    );
  }

  const payload = JSON.parse(response.body) as Partial<E2ESeedPayload>;

  if (!payload.username || !payload.password || !payload.saleId) {
    throw new Error('E2E seed response is missing username, password, or saleId.');
  }

  const resolvedPayload: E2ESeedPayload = {
    baseUrl,
    password: payload.password,
    saleId: String(payload.saleId),
    username: payload.username,
  };

  process.env.E2E_USERNAME = resolvedPayload.username;
  process.env.E2E_PASSWORD = resolvedPayload.password;
  process.env.E2E_SALE_ID = resolvedPayload.saleId;

  await persistSeed(resolvedPayload);

  return resolvedPayload;
}

export function getE2EBaseUrl(): string {
  return getBaseUrl();
}

export function getE2ESeedRuntimePath(): string {
  return E2E_RUNTIME_FILE;
}

export async function getE2ESeed(): Promise<E2ESeedPayload> {
  const envSeed = readEnvSeed();
  if (envSeed) {
    await persistSeed(envSeed);
    return envSeed;
  }

  const runtimeSeed = await readRuntimeSeed();
  if (runtimeSeed) {
    process.env.E2E_USERNAME = runtimeSeed.username;
    process.env.E2E_PASSWORD = runtimeSeed.password;
    process.env.E2E_SALE_ID = runtimeSeed.saleId;

    return runtimeSeed;
  }

  if (!seedPromise) {
    seedPromise = seedFromApi();
  }

  return seedPromise;
}
