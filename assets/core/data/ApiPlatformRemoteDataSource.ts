import { createCoreHttpClient, type CoreHttpClient } from '@core/http';
import type { GridData } from './GridData';
import { FilterRule } from '@core/field/FilterRule';
import { LoadOption } from '@core/field/Field';
import type { DataRecord } from './DataRecord';

export type RemoteFilterDescriptor = unknown[];
export type RemoteSortDescriptor = { selector?: string; desc?: boolean } | string;

export type RemoteLoadOptions = DataRecord & {
  filter?: RemoteFilterDescriptor;
  sort?: RemoteSortDescriptor[];
  appendData?: DataRecord[];
  prependData?: DataRecord[];
  searchExpr?: unknown;
  searchValue?: unknown;
  searchOperation?: unknown;
};

export type RemoteDataSourceOptions = {
  url: string;
  idField: string;
  byKeyUrl?: string | null;
  defaultFilterRules?: RemoteFilterDescriptor[];
  defaultSortRules?: RemoteSortDescriptor[];
  options?: LoadOption[];
  iriMode?: boolean;
  httpClient?: CoreHttpClient;
};

const defaultHttpClient = createCoreHttpClient({ baseUrl: '' });

function findPreloadedItem(
  loadOptions: RemoteLoadOptions,
  idField: string,
  id: unknown,
): DataRecord | null {
  const prependData = Array.isArray(loadOptions.prependData) ? loadOptions.prependData : [];
  const appendData = Array.isArray(loadOptions.appendData) ? loadOptions.appendData : [];
  const candidates = [...prependData, ...appendData];

  return candidates.find((item) => item[idField] === id || item['@id'] === id) ?? null;
}

function addIriField(url: string, item: DataRecord): DataRecord {
  const itemId = item['id'] ?? item['code'] ?? item['uuid'] ?? item['slug'];
  const iri =
    typeof item['@id'] === 'string'
      ? item['@id']
      : typeof itemId === 'string' || typeof itemId === 'number'
        ? `${url}/${itemId}`
        : undefined;
  return { ...item, _iri: iri };
}

function normalizeFilterRules(
  filterRules: RemoteFilterDescriptor,
  defaultFilterRules: RemoteFilterDescriptor[],
): RemoteFilterDescriptor[] {
  const filters: RemoteFilterDescriptor[] = [];
  defaultFilterRules.forEach((rule) => filters.push(rule));

  if (filterRules.length === 3 && typeof filterRules[0] !== 'object') {
    filters.push([filterRules[0], filterRules[1], filterRules[2]]);
  } else {
    filterRules.forEach((rule) => {
      filters.push(Array.isArray(rule) ? rule : [rule]);
    });
  }

  return filters;
}

function applyLoadOptionDefaults(
  loadOptions: RemoteLoadOptions,
  options: LoadOption[],
): RemoteLoadOptions {
  const nextOptions: RemoteLoadOptions = { ...loadOptions };

  options.forEach((option) => {
    for (const key in option) {
      if (Object.prototype.hasOwnProperty.call(option, key)) {
        nextOptions[key] = option[key];
      }
    }
  });

  return nextOptions;
}

function normalizeSortRules(
  sortRules: RemoteSortDescriptor[],
  defaultSortRules: RemoteSortDescriptor[],
  idField: string,
): RemoteSortDescriptor[] {
  const [firstSort] = sortRules;
  const firstSelector = typeof firstSort === 'string' ? firstSort : firstSort?.selector;

  if (sortRules.length === 1 && firstSelector === idField && defaultSortRules.length === 0) {
    return [];
  }

  if (sortRules.length === 1 && firstSelector === idField) {
    return [...defaultSortRules];
  }

  return sortRules;
}

function stripAdapterOnlyParams(loadOptions: RemoteLoadOptions): RemoteLoadOptions {
  const nextOptions: RemoteLoadOptions = { ...loadOptions };
  delete nextOptions.searchOperation;

  if (nextOptions.searchExpr === undefined) {
    delete nextOptions.searchValue;
  }

  return nextOptions;
}

function getFlatIriFilter(filter: unknown): unknown[] | null {
  if (!Array.isArray(filter)) return null;
  return filter.length === 1 && Array.isArray(filter[0]) ? filter[0] : filter;
}

export class ApiPlatformRemoteDataSource {
  private readonly httpClient: CoreHttpClient;

  constructor(private readonly config: RemoteDataSourceOptions) {
    this.httpClient = config.httpClient ?? defaultHttpClient;
  }

  makeFilterRules(filterRules: FilterRule[]): string {
    return filterRules
      .map((filter) => `filter[]=["${filter.field}","${filter.operator}","${filter.value}"]`)
      .join('&');
  }

  prepareLoadOptions(loadOptions: RemoteLoadOptions): RemoteLoadOptions {
    const withDefaults = applyLoadOptionDefaults(loadOptions, this.config.options ?? []);
    const normalizedFilters = normalizeFilterRules(
      (withDefaults.filter ?? []) as RemoteFilterDescriptor,
      this.config.defaultFilterRules ?? [],
    );

    return stripAdapterOnlyParams({
      ...withDefaults,
      filter: normalizedFilters,
      sort: normalizeSortRules(
        withDefaults.sort ?? [],
        this.config.defaultSortRules ?? [],
        this.config.idField,
      ),
    });
  }

  async load(loadOptions: RemoteLoadOptions): Promise<GridData<DataRecord>> {
    const preparedOptions = this.prepareLoadOptions(loadOptions);

    if (this.config.iriMode) {
      const flatFilter = getFlatIriFilter(preparedOptions.filter);
      if (
        Array.isArray(flatFilter) &&
        flatFilter.length === 3 &&
        flatFilter[0] === '_iri' &&
        flatFilter[1] === '=' &&
        typeof flatFilter[2] === 'string'
      ) {
        const item = await this.byKey(flatFilter[2]);
        return {
          data: item ? [addIriField(this.config.url, item)] : [],
          totalCount: item ? 1 : 0,
          summary: null,
        };
      }

      const result = await this.fetchAll(preparedOptions);
      return {
        ...result,
        data: result.data.map((item) => addIriField(this.config.url, item)),
      };
    }

    return this.fetchAll(preparedOptions);
  }

  async byKey(key: unknown): Promise<DataRecord | null> {
    const id = typeof key === 'object' && key !== null ? (key as DataRecord)[this.config.idField] : key;
    if (id === undefined) return null;

    const loadOptions = applyLoadOptionDefaults({}, this.config.options ?? []);
    const item = await this.fetchById(this.config.byKeyUrl ?? this.config.url, id, loadOptions);

    return this.config.iriMode && item ? addIriField(this.config.url, item) : item;
  }

  private async fetchAll(loadOptions: RemoteLoadOptions): Promise<GridData<DataRecord>> {
    const response = await this.httpClient.get<DataRecord[]>(this.config.url, { params: loadOptions });
    let data = response.data;

    if (loadOptions.appendData) {
      data = [...data, ...loadOptions.appendData];
    }
    if (loadOptions.prependData) {
      data = [...loadOptions.prependData, ...data];
    }

    const headerCount = Number(response.headers.get('x-total-count'));
    return {
      data,
      totalCount: Number.isFinite(headerCount) ? headerCount : data.length,
      summary: null,
    };
  }

  private async fetchById(
    url: string,
    id: unknown,
    loadOptions: RemoteLoadOptions,
  ): Promise<DataRecord | null> {
    const preloadedItem = findPreloadedItem(loadOptions, this.config.idField, id);
    if (preloadedItem) return preloadedItem;

    const resolvedUrl = typeof id === 'string' && id.startsWith('/') ? id : `${url}/${id}`;

    return this.httpClient
      .get<DataRecord>(resolvedUrl)
      .then((response) => response.data)
      .catch(() => findPreloadedItem(loadOptions, this.config.idField, id));
  }
}
