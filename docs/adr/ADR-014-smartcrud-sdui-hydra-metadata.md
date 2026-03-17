# ADR-014 — Adoptar SDUI con Hydra Metadata para SmartCrud

**Date:** 2026-03-17  
**Status:** proposed  
**Deciders:** spex-architect, spex-frontend  

---

## Context

El frontend de eFact (React 19 + TypeScript + DevExtreme + Vite) tiene un sistema CRUD
declarativo (`defineResource` + `<CrudPage>`) que elimina boilerplate a nivel de
componentes React, pero aún requiere que el desarrollador declare manualmente:

- Cada campo con su tipo, label, validaciones (`required`, `min`, `max`)
- Relaciones como dropdowns de entidad (detectar que `category` es una IRI hacia `/api/categories`)
- Reglas de formato y enum labels
- Lógica condicional de visibilidad/habilitación con llamadas imperativas a `formRef.current?.showField()`

El backend expone API Platform con JSON-LD/Hydra, que ya contiene toda esta información en
metadatos de máquina legible:

- `GET /api/` → Hydra entrypoint (lista de colecciones + IRI de cada recurso)
- `GET /api/docs.json` → OpenAPI 3.1 spec completa con tipos, validaciones y schemas
- `GET /api/contexts/{Resource}` → JSON-LD context por recurso
- Cada response incluye `@context`, `@type`, `@id` y campos con tipos Hydra

Hay 26 páginas aún en el patrón manual legado y 3 ya migradas al nuevo patrón `defineResource`.
La barrera principal para migrar las 26 restantes es que el desarrollador aún debe escribir
manualmente los campos aunque el schema de API Platform ya los describe completamente.

---

## Problem Statement

Decidir si aprovechar los metadatos Hydra/JSON-LD de API Platform para inferir automáticamente
los campos, tipos y validaciones del CRUD, eliminando la necesidad de declararlos manualmente.

---

## Alternatives Considered

### Option A: Server-Driven UI (SDUI) con Hydra Metadata ← **elegida**

El sistema carga `/api/docs.json` una vez (con cache) y construye automáticamente los
`Field[]` del grid y del form usando reglas de mapeo `HydraType → FieldType`. El developer
puede sobreescribir con overrides puntuales.

**Pros:**
- Zero config para recursos simples: `defineResource('/api/products')` es suficiente
- Single source of truth: tipos, required, min/max vienen de la misma fuente que el backend valida
- Las 26 páginas no migradas se reducen a ~5 líneas cada una
- Cambios en el schema del backend se propagan automáticamente al frontend
- Habilita reglas declarativas (`visibleWhen`, `disabledWhen`, `permissions`) sin código imperativo
- Consistente con la arquitectura existente (extiende `ResourceConfig` sin romper compatibilidad)

**Cons:**
- Request adicional a `/api/docs.json` en el inicio de la app (mitigado con cache)
- La inferencia automática puede producir UI sub-óptima en casos edge (mitigado con overrides)
- Mayor complejidad en el sistema de capas (HydraLoader + HydraMapper + schema merging)
- Riesgo de inconsistencia si el schema de API Platform cambia sin coordinación con el frontend

---

### Option B: Mantener declaración manual + mejorar FieldBuilder

Continuar con la declaración manual de campos pero mejorar el `FieldBuilder` con más utilidades
(snippets de IDE, tipos genéricos más fuertes) y agregar `visibleWhen`/`disabledWhen` al
`Field` interface.

**Pros:**
- Sin complejidad adicional en la capa de carga de schema
- Total control del desarrollador sobre cada campo
- Sin requests adicionales en startup

**Cons:**
- Las 26 páginas siguen requiriendo ~30+ líneas de declaración manual
- Duplicación permanente: tipos en PHP (API Platform) + tipos en TS (FieldBuilder)
- Cambios en el schema del backend requieren actualización manual en el frontend
- No resuelve el problema raíz: redundancia entre API Platform y código frontend

---

### Option C: Generación de código (code generation)

Usar un script de build que lea `/api/docs.json` y genere archivos TypeScript de campos
automáticamente (similar a `openapi-typescript`).

**Pros:**
- Sin complejidad de runtime
- Los campos generados son estáticos y type-safe
- Fácil de inspeccionar y depurar

**Cons:**
- Requiere ejecutar el script después de cada cambio de schema
- Los archivos generados son ruido en el repositorio (o deben excluirse del git)
- No habilita `visibleWhen`/`disabledWhen` dinámicos basados en datos de formulario
- Dos pasos de desarrollo: modificar schema → regenerar → ver resultado en UI
- Desacopla el ciclo de desarrollo backend/frontend: cambio en API Platform no se refleja automáticamente

---

## Decision

**Chosen option: Option A — SDUI con Hydra Metadata**

Implementar un sistema de 3 capas:
1. `HydraMetadataLoader` — carga y cachea `/api/docs.json` con SWR
2. `HydraToFieldMapper` — mapea tipos OpenAPI/Hydra → `FieldType` existente del sistema
3. `useResourceSchema` hook — combina schema inferido + overrides del developer → `Field[]` final

La API `defineResource` se extiende para aceptar tanto el path corto (`'/api/products'`) como
el objeto de overrides parciales, manteniendo retrocompatibilidad total con el `ResourceConfig`
actual.

---

## Rationale

La opción A es la única que elimina la duplicación estructural entre el schema del backend y la
declaración del frontend. API Platform ya es la fuente de verdad para tipos, validaciones y
relaciones; el frontend debería consumir esa información en lugar de replicarla. La opción B
perpetúa la duplicación indefinidamente. La opción C resuelve el problema parcialmente pero
introduce fricción en el ciclo de desarrollo y no habilita las capacidades dinámicas
(`visibleWhen`, `disabledWhen`) que son el otro pain point crítico identificado.

---

## Consequences

**Positive:**
- Las 26 páginas no migradas pueden implementarse en 1 semana (5 min por página)
- Single source of truth para tipos y validaciones (API Platform)
- Capacidades declarativas (`visibleWhen`, `disabledWhen`, `permissions`) sin código imperativo
- Reducción del boilerplate estimada: de ~30 líneas a 2-5 líneas por página simple
- URL/routing integration habilitada como feature opcional sin romper el patrón popup

**Negative / Trade-offs:**
- Request adicional a `/api/docs.json` en startup (mitigado: cache en memoria + SWR)
- Mayor complejidad en `@core/crud` (nuevas capas HydraLoader, HydraMapper)
- La UI inferida puede ser sub-óptima para campos complejos (mitigado: sistema de overrides)
- Acoplamiento entre el schema de API Platform y la UI generada

**Risks:**
- **Schema drift**: Si API Platform cambia un tipo silenciosamente, la UI puede comportarse de
  forma inesperada. Mitigación: TypeScript strict en el mapper, tests de snapshot de schema.
- **Hydra metadata incompleta**: Algunos recursos de API Platform pueden carecer de `hydra:title`
  o `x-labels`. Mitigación: fallbacks convencionales (camelCase → "Camel Case").
- **Performance en dev**: Hot reload con SWR puede re-fetchear `/api/docs.json` frecuentemente.
  Mitigación: TTL de 5 minutos en cache de desarrollo, infinito en producción (build time).

---

## Related

- Supersedes: none
- Related slices: SLICE-004, SLICE-005, SLICE-006
- Related artifacts: docs/design/ResourceConfig-API.md, docs/design/SmartCrud-SDUI-Architecture.md
- ADR-013: UTC Storage / Lima Display Contract (same codebase)
