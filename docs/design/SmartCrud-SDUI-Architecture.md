# Architecture Document: SmartCrud SDUI con API Platform Hydra Metadata

> **Autor:** spex-architect  
> **Fecha:** 2026-03-17  
> **Estado:** proposed — pendiente de aprobación humana  
> **ADR relacionado:** ADR-014  
> **Slices relacionados:** SLICE-004, SLICE-005, SLICE-006  

---

## 1. Diagrama de Capas

```
┌─────────────────────────────────────────────────────────────────────┐
│                        CAPA DE PRESENTACIÓN                         │
│                                                                     │
│   <SmartCrudPage resource={resource} routeParam="id" />             │
│         │                                                           │
│         ▼                                                           │
│   useSmartCrudPage(resource)                                        │
│   ├── useResourceSchema(apiUrl)   ← CAPA DE SCHEMA RESOLUTION       │
│   │     ├── useHydraMetadata()    ← CAPA DE METADATA                │
│   │     │     └── HydraMetadataLoader (SWR cache)                  │
│   │     ├── HydraToFieldMapper    ← CAPA DE MAPEO                   │
│   │     │     └── inferFields(hydraSchema) → Field[]               │
│   │     └── mergeOverrides(inferredFields, overrides) → Field[]    │
│   ├── useConditionalRules(fields, formData)  ← CAPA DE REGLAS       │
│   └── useFieldPermissions(fields, userRoles) ← CAPA DE RBAC        │
│                                                                     │
├─────────────────────────────────────────────────────────────────────┤
│                       CAPA DE CONFIGURACIÓN                         │
│                                                                     │
│   defineResource('/api/products')              ← Nivel 0 (zero cfg) │
│   defineResource('/api/products', { ... })     ← Nivel 1 (overrides)│
│   defineResource(ResourceConfig)               ← Nivel 2 (full ctrl)│
│   defineResource({ id, apiUrl, fields, ... })  ← Legacy compatible  │
│                                                                     │
├─────────────────────────────────────────────────────────────────────┤
│                       CAPA DE TIPOS (existente)                     │
│                                                                     │
│   ResourceConfig  SmartResourceConfig  FieldOverride  HydraSchema  │
│   Field[]         FieldBuilder (18 tipos)  SortDescriptor           │
│                                                                     │
├─────────────────────────────────────────────────────────────────────┤
│                     API PLATFORM (backend)                          │
│                                                                     │
│   GET /api/           → Hydra Entrypoint                            │
│   GET /api/docs.json  → OpenAPI 3.1 Spec (cache perpetuo en prod)   │
│   GET /api/contexts/{Resource} → JSON-LD context                    │
│   GET /api/products   → JSON-LD response con @type, @id             │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 2. Interfaces TypeScript Completas

### 2.1 `HydraSchema` — Estructura del schema inferido

```typescript
// assets/core/crud/hydra/HydraSchema.ts

/**
 * Schema de un campo individual, tal como se extrae de OpenAPI/Hydra.
 * Corresponde a una propiedad dentro de `components.schemas.{Resource}.properties`.
 */
export interface HydraFieldSchema {
  /** Nombre de la propiedad en la API (e.g. "categoryId", "name") */
  name: string;

  /** Tipo OpenAPI primitivo: 'string' | 'integer' | 'number' | 'boolean' | 'array' | 'object' */
  type: string;

  /** Formato OpenAPI: 'date-time' | 'date' | 'uri' | 'email' | 'uuid' | null */
  format?: string | null;

  /** Si la propiedad es requerida (viene de `required[]` del schema padre) */
  required: boolean;

  /** Si la propiedad puede ser null (nullable en OpenAPI 3.1: `type: ['string', 'null']`) */
  nullable: boolean;

  /** Label humano desde `hydra:title` o `x-title` en el schema */
  title?: string;

  /** Descripción desde `hydra:description` o `description` */
  description?: string;

  /** Valores de enum permitidos */
  enum?: (string | number)[];

  /** Labels de enum desde extensión `x-labels` de API Platform */
  enumLabels?: Record<string, string>;

  /** IRI de la entidad relacionada si es una relación (e.g. '/api/contexts/Category') */
  relationIri?: string;

  /** URL de la colección relacionada (para el dropdown) e.g. '/api/categories' */
  relationUrl?: string;

  /** Cardinalidad de la relación */
  relationType?: 'many-to-one' | 'many-to-many' | 'one-to-one';

  /** Restricciones de longitud/valor */
  minLength?: number;
  maxLength?: number;
  minimum?: number;
  maximum?: number;

  /** Si la propiedad es de solo lectura (e.g. `createdAt`) */
  readOnly?: boolean;

  /** Si la propiedad es de solo escritura (e.g. `password`) */
  writeOnly?: boolean;
}

/**
 * Schema completo de un recurso inferido desde OpenAPI/Hydra.
 */
export interface HydraResourceSchema {
  /** IRI del recurso (e.g. '/api/products') */
  apiUrl: string;

  /** Nombre del recurso en la spec (e.g. 'Product') */
  resourceName: string;

  /** Título humano del recurso desde `hydra:title` */
  title?: string;

  /** Campos del recurso */
  fields: HydraFieldSchema[];

  /** Campos requeridos (redundante con `field.required` pero útil para lookup rápido) */
  requiredFields: string[];

  /** Si el recurso soporta paginación Hydra (hydra:PagedCollection) */
  paginated: boolean;

  /** Si el recurso soporta filtros (presencia de `hydra:search`) */
  filterable: boolean;

  /** Operaciones soportadas (GET, POST, PUT, PATCH, DELETE) */
  operations: string[];
}

/**
 * Cache completo de la spec de API Platform.
 * Indexado por apiUrl para O(1) lookup por recurso.
 */
export interface HydraApiSpec {
  /** URL del endpoint de docs */
  docsUrl: string;

  /** Timestamp de la última carga */
  loadedAt: number;

  /** Map: apiUrl → HydraResourceSchema */
  resources: Record<string, HydraResourceSchema>;

  /** Versión de la API (si está disponible en la spec) */
  apiVersion?: string;
}
```

---

### 2.2 `FieldOverride` — Overrides declarativos de campo

```typescript
// assets/core/crud/SmartResourceConfig.ts

import { FieldType } from '@core/field/FieldType';

/**
 * Override declarativo para un campo individual.
 * Todos los campos son opcionales — solo se sobreescribe lo necesario.
 * El resto se infiere del schema Hydra.
 *
 * @template T — tipo del record completo del formulario
 */
export interface FieldOverride<T = Record<string, unknown>> {
  // ─── Layout / Display ──────────────────────────────────────────────────────

  /** Columnas del grid de layout (1–12, Bootstrap-style). Default: inferido por tipo. */
  col?: 1 | 2 | 3 | 4 | 6 | 8 | 12;

  /** Override del label visible en el form y la columna del grid */
  label?: string;

  /** Override del tipo de campo inferido */
  type?: FieldType;

  /** Ocultar el campo completamente (del form y del grid) */
  hidden?: boolean;

  /** Ocultar solo del grid (visible en form) */
  hiddenFromGrid?: boolean;

  /** Ocultar solo del form (visible en grid) */
  hiddenFromForm?: boolean;

  /** Orden en el form (menor = primero). Default: orden del schema. */
  order?: number;

  // ─── Opciones de entidad (para entityField) ────────────────────────────────

  /** URL de la colección para el dropdown (sobreescribe la inferida por Hydra) */
  entityUrl?: string;

  /** Propiedad del item relacionado a mostrar en el dropdown */
  displayField?: string;

  /** Propiedad del item relacionado a usar como value */
  valueField?: string;

  // ─── Enum labels ──────────────────────────────────────────────────────────

  /** Override de los labels de enum (sobreescribe los de x-labels en el schema) */
  enumLabels?: Record<string, string>;

  // ─── Reglas declarativas ──────────────────────────────────────────────────

  /**
   * Función que determina si el campo es visible dado el estado actual del form.
   * El campo se oculta del form cuando retorna false.
   * No afecta la columna del grid.
   *
   * @param data — datos actuales del formulario (parciales durante edición)
   */
  visibleWhen?: (data: Partial<T>) => boolean;

  /**
   * Función que determina si el campo está deshabilitado.
   *
   * @param data — datos actuales del formulario
   */
  disabledWhen?: (data: Partial<T>) => boolean;

  /**
   * Campos de los que este campo depende para recargar sus opciones.
   * Cuando cualquiera de los campos listados cambia, se refetch el entityUrl.
   * Útil para dropdowns en cascada (e.g. category → subcategory).
   *
   * @example dependsOn: ['category'] — recarga cuando cambia category
   */
  dependsOn?: string[];

  /**
   * Valor computado a partir de los datos del formulario.
   * El campo se convierte en readonly y muestra este valor.
   * Útil para campos derivados (e.g. total = price * quantity).
   *
   * @param data — datos actuales del formulario
   */
  computed?: (data: Partial<T>) => unknown;

  // ─── Permisos RBAC ────────────────────────────────────────────────────────

  /**
   * Control de acceso a nivel de campo.
   * Los roles son strings que coinciden con los roles del sistema de auth.
   */
  permissions?: {
    /** Roles que pueden ver el campo. Si está vacío, todos pueden verlo. */
    visible?: string[];
    /** Roles que pueden editar el campo. Si está vacío, todos pueden editarlo. */
    editable?: string[];
  };

  // ─── Validación extra ─────────────────────────────────────────────────────

  /** Override de si el campo es requerido (sobreescribe el schema Hydra) */
  required?: boolean;

  /** Override del valor por defecto */
  defaultValue?: unknown;
}

/**
 * Map de overrides indexado por nombre de campo.
 * @template T — tipo del record completo
 */
export type FieldOverrides<T = Record<string, unknown>> = {
  [fieldName in keyof T]?: FieldOverride<T>;
} & {
  [fieldName: string]: FieldOverride<T> | undefined;
};
```

---

### 2.3 `SmartResourceConfig` — Configuración extendida

```typescript
// assets/core/crud/SmartResourceConfig.ts (continuación)

import { ResourceConfig, SortDescriptor } from './ResourceConfig';
import { DxFilterRule } from '@core/common/FilterRule';
import { ToolbarButtonItem } from '@core/event/EventTypes';

/**
 * Configuración de un detail grid SDUI.
 * Similar a la config de un recurso principal pero orientada al grid anidado.
 */
export interface SmartDetailConfig<T = Record<string, unknown>> {
  /**
   * URL template del endpoint de detalle.
   * Use `{id}` como placeholder: `/api/products/{id}/items`
   */
  apiUrl: string;

  /** Overrides de campos del detail grid (se infieren del schema Hydra) */
  fields?: FieldOverrides<T>;

  /** Nombre de la propiedad en el payload que contiene el array de items */
  propertyName?: string;
}

/**
 * Configuración URL/routing para CrudPage.
 * Habilita deep-linking sin romper el patrón popup existente.
 */
export interface SmartRoutingConfig {
  /**
   * Nombre del parámetro de ruta que contiene el ID del recurso.
   * Si se especifica, el componente lee/escribe la URL al abrir/cerrar el dialog.
   * @example routeParam: 'id' → /products/:id/edit, /products/new
   */
  routeParam?: string;

  /**
   * Si true, sincroniza los filtros del grid con query params de la URL.
   * @example /products?filter[status]=ACTIVE
   */
  syncFiltersToUrl?: boolean;
}

/**
 * Configuración completa SmartCrud.
 *
 * Acepta tres formas (resueltas por el overloading de defineResource):
 *
 * 1. Zero config:   defineResource('/api/products')
 * 2. Con overrides: defineResource('/api/products', { title, fields, ... })
 * 3. Full control:  defineResource({ id, apiUrl, fields: Field[], ... }) ← legacy compatible
 */
export interface SmartResourceConfig<T = Record<string, unknown>> {
  // ─── Identity ─────────────────────────────────────────────────────────────

  /** ID único del recurso. Auto-derivado del apiUrl si se omite ('products'). */
  id?: string;

  /** Título humano. Auto-derivado de Hydra si se omite. */
  title?: string;

  /** URL del endpoint de la API (e.g. '/api/products') */
  apiUrl: string;

  // ─── Campos (overrides opcionales) ───────────────────────────────────────

  /**
   * Overrides puntuales de campos.
   * Solo se necesita especificar lo que difiere de la inferencia Hydra.
   * Los campos no mencionados se infieren completamente.
   */
  fields?: FieldOverrides<T>;

  // ─── Detail grid ─────────────────────────────────────────────────────────

  /** Configuración del detail grid anidado (master-detail pattern) */
  detail?: SmartDetailConfig;

  // ─── Grid behaviour ───────────────────────────────────────────────────────

  sort?: SortDescriptor[];
  filter?: DxFilterRule[];
  mode?: 'normal' | 'minimal';
  paging?: boolean;
  stateStoring?: boolean;

  // ─── CRUD permissions ─────────────────────────────────────────────────────

  allowAdd?: boolean;
  allowEdit?: boolean;
  allowDelete?: boolean;
  allowExport?: boolean;

  // ─── Dialog ───────────────────────────────────────────────────────────────

  dialogWidth?: number;
  dialogHeight?: number;

  // ─── Form ─────────────────────────────────────────────────────────────────

  format?: 'json' | 'multipart';

  // ─── Toolbar extras ───────────────────────────────────────────────────────

  toolbar?: ToolbarButtonItem[] | ToolbarButtonItem[][];

  // ─── Routing ──────────────────────────────────────────────────────────────

  routing?: SmartRoutingConfig;

  // ─── Lifecycle callbacks ──────────────────────────────────────────────────

  onSaveSuccess?: (response: unknown) => void;
  onSaveError?: (error?: unknown) => void;
  onDeleteSuccess?: (response: unknown) => void;
  onDeleteError?: (error?: unknown) => void;
}
```

---

### 2.4 `defineResource` — API con 3 niveles

```typescript
// assets/core/crud/defineResource.ts

import { ResourceConfig } from './ResourceConfig';
import { SmartResourceConfig } from './SmartResourceConfig';

// ─── Sobrecarga Nivel 0: Zero config ──────────────────────────────────────────
// defineResource('/api/products')
function defineResource(apiUrl: string): SmartResourceConfig;

// ─── Sobrecarga Nivel 1: Con overrides ────────────────────────────────────────
// defineResource('/api/products', { title, fields, ... })
function defineResource<T>(
  apiUrl: string,
  overrides: Omit<SmartResourceConfig<T>, 'apiUrl'>
): SmartResourceConfig<T>;

// ─── Sobrecarga Nivel 2 / Legacy: Full control ────────────────────────────────
// defineResource({ id, apiUrl, fields: Field[], ... })  ← retrocompatible
function defineResource(config: ResourceConfig): ResourceConfig;
function defineResource(config: SmartResourceConfig): SmartResourceConfig;

// ─── Implementación ───────────────────────────────────────────────────────────
function defineResource<T>(
  configOrApiUrl: string | ResourceConfig | SmartResourceConfig<T>,
  overrides?: Omit<SmartResourceConfig<T>, 'apiUrl'>
): ResourceConfig | SmartResourceConfig<T> {
  // Nivel 0 — Zero config
  if (typeof configOrApiUrl === 'string' && !overrides) {
    const apiUrl = configOrApiUrl;
    return {
      apiUrl,
      id: deriveIdFromUrl(apiUrl),   // '/api/products' → 'products'
    } satisfies SmartResourceConfig;
  }

  // Nivel 1 — Con overrides
  if (typeof configOrApiUrl === 'string' && overrides) {
    const apiUrl = configOrApiUrl;
    return {
      ...overrides,
      apiUrl,
      id: overrides.id ?? deriveIdFromUrl(apiUrl),
    } satisfies SmartResourceConfig<T>;
  }

  // Nivel 2 / Legacy — objeto completo (pasa through, type guard)
  return configOrApiUrl as ResourceConfig | SmartResourceConfig<T>;
}

/** Deriva un ID legible de la URL del recurso. */
function deriveIdFromUrl(apiUrl: string): string {
  // '/api/products'       → 'products'
  // '/api/sale_documents' → 'sale-documents'
  return apiUrl
    .replace(/^\/api\//, '')
    .replace(/_/g, '-')
    .toLowerCase();
}

export { defineResource };
```

---

### 2.5 `useResourceSchema` — Hook de resolución de schema

```typescript
// assets/core/crud/hydra/useResourceSchema.ts

import { useMemo } from 'react';
import { useHydraMetadata } from './useHydraMetadata';
import { HydraToFieldMapper } from './HydraToFieldMapper';
import { Field } from '@core/field/Field';
import { SmartResourceConfig, FieldOverrides } from '../SmartResourceConfig';
import { HydraResourceSchema } from './HydraSchema';

export interface ResourceSchemaResult {
  /** Campos resueltos (Hydra inferidos + overrides aplicados) */
  fields: Field[];

  /** Schema Hydra crudo (para inspección/debug) */
  hydraSchema: HydraResourceSchema | null;

  /** Si el schema está siendo cargado */
  loading: boolean;

  /** Error de carga del schema */
  error: Error | null;

  /** Si el schema fue inferido de Hydra (true) o si hay campos manuales (false) */
  isAutoInferred: boolean;
}

/**
 * Hook principal de resolución de schema para SmartCrud.
 *
 * Flujo de resolución:
 * 1. Carga la spec Hydra via useHydraMetadata (SWR — cached)
 * 2. Extrae el schema del recurso por apiUrl
 * 3. Infiere Field[] via HydraToFieldMapper
 * 4. Aplica los overrides del developer (FieldOverrides)
 * 5. Aplica filtros de permisos RBAC
 * 6. Retorna el Field[] final
 *
 * @param resource — configuración SmartResourceConfig (puede ser legacy ResourceConfig)
 * @param userRoles — roles del usuario actual (para filtros RBAC)
 */
export function useResourceSchema(
  resource: SmartResourceConfig | { fields: Field[]; apiUrl: string },
  userRoles?: string[]
): ResourceSchemaResult {
  // Si el resource tiene fields declarados manualmente (legacy o Nivel 2 full),
  // los usamos directamente sin ir a Hydra.
  const hasManualFields = 'fields' in resource &&
    Array.isArray((resource as { fields: unknown }).fields) &&
    (resource as { fields: unknown[] }).fields.length > 0 &&
    typeof (resource as { fields: unknown[] }).fields[0] === 'object' &&
    'type' in ((resource as { fields: unknown[] }).fields[0] as object);

  // Solo cargamos Hydra si no hay fields manuales
  const { data: apiSpec, isLoading, error } = useHydraMetadata(
    hasManualFields ? null : resource.apiUrl
  );

  const result = useMemo((): ResourceSchemaResult => {
    // Path legacy/Nivel2: usar fields declarados manualmente
    if (hasManualFields) {
      return {
        fields: (resource as { fields: Field[] }).fields,
        hydraSchema: null,
        loading: false,
        error: null,
        isAutoInferred: false,
      };
    }

    if (isLoading) {
      return { fields: [], hydraSchema: null, loading: true, error: null, isAutoInferred: true };
    }

    if (error) {
      return { fields: [], hydraSchema: null, loading: false, error, isAutoInferred: true };
    }

    if (!apiSpec) {
      return { fields: [], hydraSchema: null, loading: false, error: null, isAutoInferred: true };
    }

    // Buscar el schema del recurso en la spec
    const hydraSchema = apiSpec.resources[resource.apiUrl] ?? null;

    if (!hydraSchema) {
      console.warn(`[SmartCrud] No se encontró schema Hydra para: ${resource.apiUrl}`);
      return { fields: [], hydraSchema: null, loading: false, error: null, isAutoInferred: true };
    }

    // Inferir fields desde Hydra
    const mapper = new HydraToFieldMapper();
    const inferredFields = mapper.inferFields(hydraSchema);

    // Aplicar overrides del developer (solo para SmartResourceConfig, no legacy)
    const overrides = (resource as SmartResourceConfig).fields ?? {} as FieldOverrides;
    const mergedFields = applyOverrides(inferredFields, overrides);

    // Filtrar por permisos RBAC
    const finalFields = userRoles
      ? applyRbacFilter(mergedFields, overrides, userRoles)
      : mergedFields;

    return {
      fields: finalFields,
      hydraSchema,
      loading: false,
      error: null,
      isAutoInferred: true,
    };
  }, [apiSpec, isLoading, error, resource.apiUrl, hasManualFields, userRoles]);

  return result;
}
```

---

### 2.6 `HydraMetadataLoader` — Estrategia de caching

```typescript
// assets/core/crud/hydra/useHydraMetadata.ts

import useSWR, { SWRConfiguration } from 'swr';
import { HydraApiSpec } from './HydraSchema';
import { parseOpenApiSpec } from './openApiParser';

const DOCS_URL = '/api/docs.json';

/**
 * SWR config para la spec de Hydra.
 *
 * Estrategia de caching:
 * - En desarrollo: revalidate on focus + dedupingInterval de 30s
 *   (el developer quiere ver cambios al modificar el schema de API Platform)
 * - En producción: dedupingInterval de Infinity (la spec no cambia en runtime)
 *   El build debe fijar la versión de la spec como hash en la URL si es necesario.
 *
 * Cuándo cachear:
 * - La spec se carga UNA SOLA VEZ por sesión en producción.
 * - En desarrollo se recarga al hacer focus en la ventana (SWR default).
 * - El cache vive en memoria (SWR in-memory cache) — no en localStorage.
 *   Razón: localStorage tiene límite de ~5MB y la spec puede ser grande.
 *
 * Por qué SWR y no React Query:
 * - El proyecto ya usa SWR para otros fetches (menor bundle size incremental)
 * - La spec es un recurso puramente de lectura — no necesita mutations
 * - SWR tiene deduplicación automática: si 50 componentes llaman useHydraMetadata,
 *   solo se hace 1 request
 */
const SWR_CONFIG: SWRConfiguration = {
  dedupingInterval: import.meta.env.PROD ? Infinity : 30_000,
  revalidateOnFocus: import.meta.env.DEV,
  revalidateOnReconnect: false,
  shouldRetryOnError: true,
  errorRetryCount: 3,
  errorRetryInterval: 2_000,
};

async function fetchAndParseSpec(url: string): Promise<HydraApiSpec> {
  const response = await fetch(url, {
    headers: { Accept: 'application/json' },
    // Cache HTTP nativa del browser — en prod el server debe enviar
    // Cache-Control: public, max-age=3600 para la spec docs.json
    cache: import.meta.env.PROD ? 'force-cache' : 'no-cache',
  });

  if (!response.ok) {
    throw new Error(`Failed to load Hydra spec: ${response.status} ${url}`);
  }

  const openApiSpec = await response.json();
  return parseOpenApiSpec(openApiSpec, url);
}

/**
 * Hook de acceso a la spec Hydra de API Platform.
 *
 * @param apiUrl — Si es null, el hook no fetcha (para legacy ResourceConfig)
 */
export function useHydraMetadata(apiUrl: string | null) {
  // Cuando apiUrl es null (legacy mode), la key de SWR es null → SWR no fetcha
  return useSWR<HydraApiSpec, Error>(
    apiUrl ? DOCS_URL : null,
    fetchAndParseSpec,
    SWR_CONFIG
  );
}
```

---

## 3. Reglas Completas de Mapeo Hydra → FieldType

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ OpenAPI / Hydra Schema                    → FieldType del sistema           │
├────────────────────────┬────────────────────────────────────────────────────┤
│ CONDICIÓN              │ TIPO DE CAMPO INFERIDO                             │
├────────────────────────┼────────────────────────────────────────────────────┤
│ type: string           │ textField (default para strings)                   │
│ type: integer          │ numberField (isInteger: true)                      │
│ type: number           │ numberField (isInteger: false)                     │
│ type: boolean          │ switchField                                        │
│ type: string           │                                                    │
│   format: date-time    │ dateField (showTime: true)                         │
│   format: date         │ dateField (showTime: false)                        │
│   format: time         │ timeField                                          │
│   format: email        │ textField (mode: 'email')                          │
│   format: uri          │ textField (mode: 'url')                            │
│ type: string           │                                                    │
│   + enum: [...]        │ enumField (options: enum, labels: x-labels)        │
│ type: string           │                                                    │
│   format: uri          │                                                    │
│   + hydra:range (IRI)  │ entityField (url: inferido de range IRI)           │
│ type: array            │                                                    │
│   items.$ref → Entity  │ entityMultiSelectField (many-to-many)              │
│ type: string/integer   │                                                    │
│   name ends in 'Id'    │ entityField (fallback heurístico para FKs simples) │
│ x-media-type: image/*  │ imageField                                         │
│ x-media-type: *        │ fileField                                          │
│ readOnly: true         │ labelField (solo display, no editable)             │
│ name === 'id' o '@id'  │ identityField (hidden en form, visible en grid)    │
│ name: 'createdAt'      │ dateField (readOnly, hiddenFromForm)               │
│   / 'updatedAt'        │                                                    │
└────────────────────────┴────────────────────────────────────────────────────┘
```

### 3.1 Detección de relaciones many-to-one vs many-to-many

**Many-to-one** (dropdown simple):
- El campo tiene `$ref` que apunta a un schema con `@type`
- O el campo es `type: string, format: uri` con `hydra:range` apuntando a un IRI de colección
- O el nombre del campo termina en `Id` y hay un schema correspondiente sin `Id`

**Many-to-many** (multiselect):
- El campo es `type: array` y sus `items` tienen `$ref` o `format: uri` con `hydra:range`
- La IRI del range apunta a una colección paginada (Hydra PagedCollection)

### 3.2 Detección de nullable vs required

- `required: true` → campo marcado como requerido, asterisco en el label
- `nullable: true` (OpenAPI 3.1: `type: ['string', 'null']`) → campo puede estar vacío
- Si `readOnly: true` → campo no aparece en el form (solo en el grid)
- Si `writeOnly: true` → campo aparece en el form pero no en el grid

### 3.3 Mapeo de `col` por tipo inferido

```
textField, enumField, entityField   → col: 6  (media pantalla)
numberField, switchField            → col: 4  (un tercio)
dateField, timeField                → col: 4
imageField, fileField               → col: 12 (pantalla completa)
textareaField (maxLength > 200)     → col: 12
```

---

## 4. Implementación del `HydraToFieldMapper`

```typescript
// assets/core/crud/hydra/HydraToFieldMapper.ts

import {
  textField, numberField, switchField, dateField,
  enumField, entityField, imageField, identityField, labelField
} from '@core/field/FieldBuilder';
import { Field } from '@core/field/Field';
import { HydraResourceSchema, HydraFieldSchema } from './HydraSchema';

export class HydraToFieldMapper {
  /**
   * Infiere un Field[] completo desde el schema Hydra de un recurso.
   */
  inferFields(schema: HydraResourceSchema): Field[] {
    return schema.fields
      .filter(f => !this.isSystemField(f.name))
      .map(f => this.mapField(f))
      .filter((f): f is Field => f !== null);
  }

  private mapField(field: HydraFieldSchema): Field | null {
    // Campos de identidad (id, @id)
    if (field.name === 'id' || field.name === '@id') {
      return identityField().build();
    }

    // readOnly → solo display
    if (field.readOnly) {
      return labelField()
        .name(field.name)
        .label(this.inferLabel(field))
        .build();
    }

    // Enum
    if (field.enum && field.enum.length > 0) {
      const options = field.enum.map(v => ({
        value: v,
        text: field.enumLabels?.[String(v)] ?? String(v),
      }));
      return enumField()
        .name(field.name)
        .label(this.inferLabel(field))
        .required(field.required)
        .data(options)
        .build();
    }

    // Relación many-to-many (array)
    if (field.type === 'array' && field.relationUrl) {
      // TODO: entityMultiSelectField cuando exista en FieldBuilder
      return entityField()
        .name(field.name)
        .label(this.inferLabel(field))
        .url(field.relationUrl)
        .build();
    }

    // Relación many-to-one (entity dropdown)
    if (field.relationUrl) {
      return entityField()
        .name(field.name)
        .label(this.inferLabel(field))
        .url(field.relationUrl)
        .required(field.required)
        .build();
    }

    // Media/imagen
    if (field.format === 'binary' || field.name.toLowerCase().includes('image')) {
      return imageField()
        .name(field.name)
        .label(this.inferLabel(field))
        .build();
    }

    // Tipos por format
    switch (field.format) {
      case 'date-time':
        return dateField()
          .name(field.name)
          .label(this.inferLabel(field))
          .required(field.required)
          .showTime(true)
          .build();
      case 'date':
        return dateField()
          .name(field.name)
          .label(this.inferLabel(field))
          .required(field.required)
          .showTime(false)
          .build();
    }

    // Tipos primitivos
    switch (field.type) {
      case 'integer':
      case 'number':
        return numberField()
          .name(field.name)
          .label(this.inferLabel(field))
          .required(field.required)
          .min(field.minimum)
          .max(field.maximum)
          .build();
      case 'boolean':
        return switchField()
          .name(field.name)
          .label(this.inferLabel(field))
          .build();
      case 'string':
      default:
        return textField()
          .name(field.name)
          .label(this.inferLabel(field))
          .required(field.required)
          .maxLength(field.maxLength)
          .build();
    }
  }

  /** Convierte camelCase/snake_case a label humano con fallback a hydra:title */
  private inferLabel(field: HydraFieldSchema): string {
    if (field.title) return field.title;
    // camelCase → "Camel Case", snake_case → "Snake Case"
    return field.name
      .replace(/_/g, ' ')
      .replace(/([A-Z])/g, ' $1')
      .replace(/^\s/, '')
      .replace(/\b\w/g, c => c.toUpperCase());
  }

  /** Campos del sistema que no deben aparecer en el UI */
  private isSystemField(name: string): boolean {
    return ['@context', '@type', 'createdAt', 'updatedAt', 'deletedAt'].includes(name);
  }
}
```

---

## 5. Comparativa Antes / Después

### Nivel 0 — Zero config (caso nuevo, recurso simple)

**ANTES (patrón legado manual):**
```typescript
// productos/model/events.ts
export const productEvents = createCrudEvents('product');

// productos/pages/ProductFields.ts  (~20 líneas)
export const ProductFields = (): Field[] => [
  identityField().build(),
  textField().name('code').label('Código').required().col(4).build(),
  textField().name('name').label('Nombre').required().col(8).build(),
  numberField().name('price').label('Precio').required().min(0).col(4).build(),
  switchField().name('active').label('Activo').col(4).build(),
  entityField().name('categoryId').label('Categoría').url('/api/categories').col(6).build(),
];

// productos/pages/ProductPage.tsx  (~30 líneas)
export const ProductPage = () => {
  const events = productEvents;
  const fields = useMemo(() => ProductFields(), []);
  return (
    <div id="wrapper">
      <DxDataGrid id="product" title="Productos" url="/api/products"
        fields={fields} events={events} allowAdd allowEdit allowDelete />
      <DxDialog events={events}>
        <DxForm url="/api/products" fields={fields} events={events} />
      </DxDialog>
    </div>
  );
};
```

**DESPUÉS (Nivel 0 — zero config):**
```typescript
// ProductPage.tsx  (2 líneas)
const resource = defineResource('/api/products');
export default () => <SmartCrudPage resource={resource} />;
```

---

### Nivel 1 — Overrides puntuales

**DESPUÉS (Nivel 1):**
```typescript
const resource = defineResource('/api/products', {
  title: 'Productos',
  fields: {
    code:          { col: 4 },
    category:      { label: 'Categoría' },
    image:         { type: 'image' },
    internalNotes: { hidden: true },
  }
});
export default () => <SmartCrudPage resource={resource} />;
```

---

### Nivel 2 — Full control + reglas declarativas

**DESPUÉS (Nivel 2):**
```typescript
const resource = defineResource('/api/products', {
  fields: {
    status: {
      visibleWhen: (data) => data.type === 'PRODUCT',
      disabledWhen: (data) => !data.category,
    },
    price: {
      permissions: { visible: ['manager', 'admin'] }
    },
    subcategory: {
      dependsOn: ['category'],  // recarga opciones cuando cambia category
    }
  },
  detail: {
    apiUrl: '/api/products/{id}/items',
    fields: { quantity: { col: 4 } }
  },
  routing: {
    routeParam: 'id',
    syncFiltersToUrl: true,
  }
});
export default () => <SmartCrudPage resource={resource} />;
```

---

## 6. URL / Routing Integration

### Estrategia

Sin romper el patrón popup actual, el routing es **opt-in** via `routing.routeParam`.

```
/products          → list (dialog cerrado)
/products/new      → list + dialog en modo ADD abierto
/products/42/edit  → list + dialog en modo EDIT con id=42 cargado
/products?filter[status]=ACTIVE&filter[category]=3  → list con filtros activos
```

### Implementación en `SmartCrudPage`

```typescript
// assets/core/crud/SmartCrudPage.tsx  (sketch)

const SmartCrudPage = ({ resource }: { resource: SmartResourceConfig }) => {
  const { routeParam, syncFiltersToUrl } = resource.routing ?? {};
  const { id: routeId, action } = useParams<{ id?: string; action?: string }>();
  const [searchParams, setSearchParams] = useSearchParams();

  // Si routing está configurado, abrir el dialog automáticamente
  useEffect(() => {
    if (!routeParam) return;
    if (action === 'new') {
      eventBus.emit(events.ADD);
    } else if (routeId && action === 'edit') {
      eventBus.emit(events.EDIT, { id: routeId });
    }
  }, [routeParam, routeId, action]);

  // Sincronizar filtros de URL al grid
  const urlFilters = syncFiltersToUrl
    ? parseUrlFilters(searchParams)
    : resource.filter;

  return (
    <div id="wrapper">
      <DxDataGrid ... filter={urlFilters} />
      <DxDialog events={events} onOpen={id => {
        if (routeParam) navigate(id ? `/${resource.id}/${id}/edit` : `/${resource.id}/new`);
      }} onClose={() => {
        if (routeParam) navigate(`/${resource.id}`);
      }}>
        <DxForm ... />
      </DxDialog>
    </div>
  );
};
```

---

## 7. Plan de Migración en 3 Fases

### Fase 1 — Zero-config Migration (Semana 1)
**Objetivo:** Migrar las 26 páginas no migradas al patrón `SmartCrudPage` sin Hydra.
**Estrategia:** Usar el Level 2 (fields declarados manualmente) pero con el nuevo componente.

| Tarea | Agente | Días |
|-------|--------|------|
| Crear `SmartCrudPage` wrapper sobre `CrudPage` existente | spex-frontend | 1 |
| Extender `defineResource` con sobrecarga de string | spex-frontend | 0.5 |
| Migrar 26 páginas (rename + wrap) | spex-frontend | 3 |
| Tests de regresión visual | spex-qa | 1 |

**Criterio de éxito:** Las 26 páginas funcionan igual que antes, con menos boilerplate.

---

### Fase 2 — Hydra Auto-Discovery (Semanas 2–3)
**Objetivo:** Eliminar la necesidad de declarar `Field[]` para recursos simples.

| Tarea | Agente | Días |
|-------|--------|------|
| Implementar `HydraMetadataLoader` + `useHydraMetadata` (SWR) | spex-frontend | 2 |
| Implementar `openApiParser` (OpenAPI 3.1 → `HydraApiSpec`) | spex-frontend | 2 |
| Implementar `HydraToFieldMapper` (reglas de mapeo) | spex-frontend | 2 |
| Implementar `useResourceSchema` hook | spex-frontend | 1 |
| Actualizar `SmartCrudPage` para usar `useResourceSchema` | spex-frontend | 1 |
| Tests de mapeo de tipos Hydra → FieldType | spex-qa | 2 |

**Criterio de éxito:** `defineResource('/api/products')` genera una CRUD page funcional.

---

### Fase 3 — Declarative Rules + URL Integration (Semanas 4–5)
**Objetivo:** Habilitar `visibleWhen`, `disabledWhen`, `permissions` y URL routing.

| Tarea | Agente | Días |
|-------|--------|------|
| Implementar `useConditionalRules` (visibleWhen / disabledWhen) | spex-frontend | 2 |
| Implementar `useFieldPermissions` (RBAC por campo) | spex-frontend | 1 |
| Implementar `dependsOn` (cascade dropdowns) | spex-frontend | 2 |
| Implementar URL routing opt-in (`routing.routeParam`) | spex-frontend | 2 |
| Implementar `syncFiltersToUrl` | spex-frontend | 1 |
| Tests E2E de routing + filtros en URL | spex-qa | 2 |

**Criterio de éxito:** `visibleWhen` funciona, deep-links funcionan, filters en URL funcionan.

---

## 8. Riesgos y Mitigaciones

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|-------------|---------|------------|
| Schema de API Platform inconsistente o sin `hydra:title` | Media | Bajo | Fallback: camelCase → label. Nunca crashear, siempre degradar gracefully. |
| `/api/docs.json` lento en startup | Media | Medio | Mostrar skeleton en el grid mientras carga. Cache SWR evita refetches. |
| Inferencia produce tipo incorrecto (e.g. URL como textField) | Alta | Bajo | Override puntual en `fields`. Sistema de overrides siempre prevalece. |
| Romper compatibilidad con los 3 recursos ya migrados | Baja | Alto | `defineResource` acepta el `ResourceConfig` legacy intacto (sobrecarga). Tests de regresión antes de cada fase. |
| Acoplamiento al formato de `docs.json` de API Platform | Media | Medio | Aislar el parser en `openApiParser.ts`. Si cambia la spec format, solo se modifica ese archivo. |
| RBAC por campo expone datos sensibles si mal implementado | Baja | Alto | Los permisos son solo UI (hide/readonly). La autorización real siempre la hace el backend. Documentar claramente. |
| Routing rompe el patrón popup en navegadores sin react-router | Baja | Bajo | Routing es completamente opt-in. Sin `routing.routeParam`, el comportamiento es idéntico al actual. |

---

## 9. Bounded Contexts del Sistema SmartCrud

```
┌──────────────────────────┐   ┌───────────────────────────────┐
│  Schema Discovery        │   │  Field Rendering              │
│                          │   │                               │
│  HydraMetadataLoader     │──▶│  HydraToFieldMapper           │
│  openApiParser           │   │  FieldBuilder (18 tipos)      │
│  useHydraMetadata (SWR)  │   │  Field interface              │
└──────────────────────────┘   └───────────────────────────────┘
            │                               │
            ▼                               ▼
┌──────────────────────────────────────────────────────────────┐
│  Schema Resolution (useResourceSchema)                       │
│                                                              │
│  mergeOverrides(inferredFields, fieldOverrides)              │
│  applyRbacFilter(fields, overrides, userRoles)               │
│  applyConditionalRules(fields, formData, overrides)          │
└──────────────────────────────────────────────────────────────┘
            │
            ▼
┌──────────────────────────────────────────────────────────────┐
│  CrudPage Rendering                                          │
│                                                              │
│  SmartCrudPage → useSmartCrudPage → useCrudPage (existente)  │
│  DxDataGrid + DxDialog + DxForm                              │
└──────────────────────────────────────────────────────────────┘
```

---

*Document end — spex-architect · SmartCrud SDUI Architecture Design*
