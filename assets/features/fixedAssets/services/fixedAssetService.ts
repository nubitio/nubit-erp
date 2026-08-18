import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();

type FixedAssetActionResponse = unknown;

const fixedAssetService = () => {
  const dispose = (id: string, disposalNote?: string) => {
    return httpClient.post<FixedAssetActionResponse>(API + `fixed-assets/${id}/dispose`, { id, disposalNote });
  };

  return { dispose };
};

export default fixedAssetService;
