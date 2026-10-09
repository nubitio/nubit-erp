import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';

const BASE = `${getCoreApiBaseUrl()}settings/integration`;

export interface ApiKeyDto {
  id: string;
  prefix: string;
  last_used_at: string | null;
  revoked_at: string | null;
  created_at: string;
}

export interface ApiClientDto {
  id: string;
  name: string;
  scopes: string[];
  created_at: string;
  keys: ApiKeyDto[];
}

export interface ApiClientListDto {
  grantable_scopes: string[];
  data: ApiClientDto[];
}

export interface WebhookEndpointDto {
  id: string;
  url: string;
  types: string[];
  status: 'enabled' | 'disabled';
  disabled_reason: string | null;
  consecutive_failures: number;
  created_at: string;
}

export interface WebhookEndpointListDto {
  event_types: string[];
  data: WebhookEndpointDto[];
}

export interface WebhookDeliveryDto {
  id: string;
  event_id: string;
  event_type: string;
  status: 'pending' | 'delivered' | 'failed' | 'dead';
  attempt: number;
  response_status: number | null;
  last_error: string | null;
  next_retry_at: string | null;
  created_at: string;
  delivered_at: string | null;
}

class IntegrationService {
  listApiClients() {
    return httpClient.get<ApiClientListDto>(`${BASE}/api-keys`).then((r) => r.data);
  }

  createApiClient(name: string, scopes: string[]) {
    return httpClient
      .post<ApiClientDto & { secret: string }>(`${BASE}/api-keys`, { name, scopes })
      .then((r) => r.data);
  }

  rotateApiKey(clientId: string) {
    return httpClient
      .post<ApiClientDto & { secret: string }>(`${BASE}/api-keys/${clientId}/rotate`)
      .then((r) => r.data);
  }

  revokeApiKey(keyId: string) {
    return httpClient.delete<ApiKeyDto>(`${BASE}/api-keys/keys/${keyId}`).then((r) => r.data);
  }

  listWebhooks() {
    return httpClient.get<WebhookEndpointListDto>(`${BASE}/webhook-endpoints`).then((r) => r.data);
  }

  createWebhook(url: string, types: string[]) {
    return httpClient
      .post<WebhookEndpointDto & { secret: string }>(`${BASE}/webhook-endpoints`, { url, types })
      .then((r) => r.data);
  }

  rotateWebhookSecret(id: string) {
    return httpClient
      .post<WebhookEndpointDto & { secret: string }>(
        `${BASE}/webhook-endpoints/${id}/rotate-secret`,
      )
      .then((r) => r.data);
  }

  toggleWebhook(id: string) {
    return httpClient
      .post<WebhookEndpointDto>(`${BASE}/webhook-endpoints/${id}/toggle`)
      .then((r) => r.data);
  }

  deleteWebhook(id: string) {
    return httpClient.delete<null>(`${BASE}/webhook-endpoints/${id}`).then((r) => r.data);
  }

  listDeliveries(id: string) {
    return httpClient
      .get<{ data: WebhookDeliveryDto[] }>(`${BASE}/webhook-endpoints/${id}/deliveries`)
      .then((r) => r.data.data);
  }

  retryDelivery(deliveryId: string) {
    return httpClient
      .post<WebhookDeliveryDto>(`${BASE}/webhook-endpoints/deliveries/${deliveryId}/retry`)
      .then((r) => r.data);
  }
}

export const integrationService = new IntegrationService();
