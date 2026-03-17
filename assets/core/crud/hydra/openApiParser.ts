import type { HydraApiDoc, HydraResourceSchema, HydraFieldSchema } from './types';

// ---------------------------------------------------------------------------
// Internal helpers
// ---------------------------------------------------------------------------

/**
 * Derives the API URL from an Entrypoint property ID.
 * "#Entrypoint/branch"              -> "/api/branches"
 * "#Entrypoint/cashMovementCategory" -> "/api/cash_movement_categories"
 */
function deriveApiUrl(entrypointPropertyId: string): string {
  const name = entrypointPropertyId.split('/').pop() ?? '';
  const snake = name.replace(/([A-Z])/g, '_$1').toLowerCase();
  const plural = snake.endsWith('s') ? snake : snake + 's';
  return `/api/${plural}`;
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Parse a raw `/api/docs.jsonld` Hydra JSON-LD response into a typed
 * Record<className, HydraResourceSchema>.
 *
 * @throws Error if doc is not a valid Hydra ApiDocumentation.
 */
export function parseHydraDoc(doc: HydraApiDoc): Record<string, HydraResourceSchema> {
  const result: Record<string, HydraResourceSchema> = {};

  // Build URL map from the Entrypoint class
  const urlMap: Record<string, string> = {};
  const entrypoint = doc.supportedClass.find((c) => c['@id'] === '#Entrypoint');
  if (entrypoint) {
    for (const sp of entrypoint.supportedProperty ?? []) {
      const range = sp.property?.range; // e.g. "#Branch"
      const propId = sp.property?.['@id']; // e.g. "#Entrypoint/branch"
      if (range && propId) {
        const className = range.replace('#', '');
        urlMap[className] = deriveApiUrl(propId);
      }
    }
  }

  for (const cls of doc.supportedClass) {
    if (cls['@id'] === '#Entrypoint') continue;

    const className = cls['@id'].replace('#', '');
    const fields: HydraFieldSchema[] = [];

    for (const sp of cls.supportedProperty ?? []) {
      const readable = sp.readable !== false;
      const writeable = sp.writeable !== false;

      // Skip fields that are not readable (write-only internal fields)
      if (!readable) continue;

      fields.push({
        name: sp.title ?? sp.property?.label ?? '',
        range: sp.property?.range,
        propertyType: sp.property?.['@type'] ?? 'rdf:Property',
        required: sp.required ?? false,
        readable,
        writeable,
      });
    }

    result[className] = {
      className,
      apiUrl: urlMap[className] ?? `/api/${className.toLowerCase()}s`,
      fields,
    };
  }

  return result;
}
