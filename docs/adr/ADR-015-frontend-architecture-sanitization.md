# ADR-015: Saneamiento arquitectonico del frontend

**Status**: Accepted
**Date**: 2026-03-20
**Deciders**: spex-architect, adr-writer
**Related Specs**: SPEC-002
**Supersedes**:

## Context

El frontend de eFact ha evolucionado con una base util y productiva, pero con ownership difuso
entre `@app`, `@features`, `@shared`, `@core` y algunos agregadores historicos. En el estado
actual conviven rutas globales en `assets/app/`, features de negocio en `assets/features/`,
infraestructura reusable en `assets/core/`, piezas transversales en `assets/shared/` y un
subsistema offline en `assets/offline/`; sin embargo, tambien existen señales de deriva:

- `@shared` contiene piezas de shell y UI que ya conocen `@app` o dependen de features concretas.
- Agregadores como `catalog` y `sales` mezclan composicion con ownership real de pantallas y
  flujos, dificultando saber donde vive una ruta.
- El acceso a red no siempre entra por el cliente HTTP canonico, lo que abre puertas a manejo
  inconsistente de auth, errores, tracing y convenciones de la API.
- `offline` ya es un subsistema transversal valido, pero necesita mantenerse con adaptadores
  explicitos para no convertirse en una capa paralela que absorba reglas de dominio.

SPEC-002 exige sanear esta arquitectura sin reescribir el frontend desde cero y preservando una
migracion incremental. La decision debe dejar reglas canonicas de ownership, boundaries y acceso a
red que luego puedan endurecerse con aliases, lint y refactors graduales.

## Decision Drivers

- Debe existir un owner unico y reconocible por cada ruta, pantalla y flujo funcional.
- El saneamiento debe ser incremental y compatible con el codigo existente.
- Los boundaries entre `@app`, `@features`, `@shared`, `@core` y `@offline` deben ser
  auditables y mecanicamente reforzables.
- El acceso a red de negocio debe concentrar auth, errores y convenciones API en un punto
  canonico.
- El subsistema offline debe seguir siendo transversal sin capturar ownership de features.

## Considered Options

1. **Ownership canonico por feature + boundaries estrictos + cliente HTTP canonico** — `@app`
   conserva shell global; cada flujo funcional tiene un solo owner en `@features`; `shared`,
   `core` y `offline` se limitan por responsabilidad; la red de negocio entra por el cliente HTTP.
2. **Mantener el modelo hibrido actual con limpieza oportunista** — conservar ownership difuso,
   agregadores con peso propio y uso mixto de `HttpClient` y `fetch` directo segun cada modulo.
3. **Reorganizar el frontend por dominios/agregadores de alto nivel** — promover `catalog`,
   `sales` y modulos similares como owners permanentes de familias de rutas y mover mas logica de
   composicion a esos agregadores.

## Decision Outcome

**Chosen option**: **Ownership canonico por feature + boundaries estrictos + cliente HTTP canonico**

**Rationale**: Esta opcion es la unica que reduce la ambiguedad estructural sin exigir una
reescritura total. `@app` conserva bootstrap, shell, providers y routing global; cada
ruta/pantalla/flujo funcional debe tener un solo owner en `@features`; agregadores como `catalog`
o `sales` solo pueden existir como composicion temporal si no duplican ownership. Ademas, fija
boundaries claros: `shared` no conoce shell ni dominio, `core` es infraestructura reusable y no
depende de features concretas, y `offline` permanece como subsistema transversal con adaptadores
explicitamente justificados. Para IO, el cliente HTTP canonico pasa a ser la puerta principal para
llamadas de negocio; `fetch` directo queda restringido a excepciones documentadas como APIs nativas
del navegador, service worker o SDKs que lo exijan. La opcion 2 no resuelve la deuda y la opcion 3
institucionaliza owners ambiguos que SPEC-002 busca desmontar.

## Pros and Cons of the Options

### Option A: Ownership canonico por feature + boundaries estrictos + cliente HTTP canonico

**Pros**:
- Hace visible el owner real de cada ruta y facilita mantenimiento, onboarding y refactors.
- Permite enforcement progresivo con aliases, ESLint y wrappers sin romper toda la base actual.
- Centraliza auth, manejo de errores y contratos de negocio en el cliente HTTP canonico.
- Mantiene `offline` como capacidad transversal util sin convertirlo en una arquitectura paralela.

**Cons**:
- Requiere migraciones graduales y disciplina para mover piezas fuera de `shared` y agregadores.
- Algunas composiciones legacy necesitaran wrappers o re-exports temporales durante la transicion.
- Obliga a documentar y revisar las excepciones de `fetch` directo en lugar de permitirlas libremente.

---

### Option B: Mantener el modelo hibrido actual con limpieza oportunista

**Pros**:
- Minimiza cambios inmediatos y reduce friccion a corto plazo.
- Permite seguir entregando features sin decisiones estructurales adicionales.

**Cons**:
- Mantiene ownership ambiguo de rutas, pantallas y flujos funcionales.
- `shared` y otros modulos siguen acumulando dependencias impropias de shell o dominio.
- El acceso a red permanece inconsistente entre modulos y dificulta enforcement automatico.
- La deuda arquitectonica sigue creciendo y hace mas caro el saneamiento posterior.

---

### Option C: Reorganizar el frontend por dominios/agregadores de alto nivel

**Pros**:
- Agrupa pantallas relacionadas bajo nodos conceptuales conocidos por el equipo.
- Puede reducir algunos imports cruzados en el corto plazo dentro de dominios grandes.

**Cons**:
- Convierte agregadores como `catalog` y `sales` en owners permanentes y diluye la regla de owner
  unico por flujo.
- Mezcla composicion, navegacion y dominio en capas intermedias dificiles de gobernar.
- Hace mas dificil separar infraestructura reusable de reglas funcionales concretas.
- No resuelve por si sola la politica canonica de acceso a red ni los boundaries de `shared` y `core`.

## Consequences

### Positive
- Las futuras rutas y pantallas tienen una matriz de ownership clara: `@app` para shell global,
  `@features` para negocio.
- `shared` y `core` recuperan responsabilidades mas limpias y reutilizables.
- Las llamadas de negocio pasan por una puerta unica que facilita auth, observabilidad y manejo de errores.
- `offline` conserva su valor transversal sin erosionar la separacion por features.
- El equipo puede endurecer reglas con tooling sin discutir de nuevo la arquitectura base en cada PR.

### Negative
- Habra trabajo incremental de relocation, wrappers temporales y limpieza de imports legacy.
- Algunas features existentes quedaran temporalmente en estados mixtos mientras se completa la migracion.
- Los casos excepcionales de `fetch` directo exigen documentacion y criterio arquitectonico explicito.

### Risks
- Riesgo de enforcement prematuro que rompa modulos legacy antes de migrarlos — Mitigation:
  aplicar severidad incremental y compatibilidad temporal con aliases/re-exports aprobados.
- Riesgo de que agregadores temporales vuelvan a capturar ownership real — Mitigation: exigir que
  toda ruta nueva declare owner funcional unico en `@features` y limitar agregadores a composicion.
- Riesgo de excepciones de red sin criterio comun — Mitigation: documentar toda excepcion de
  `fetch` directo y revisarla contra la politica del cliente HTTP canonico.

## Implementation Notes

`@app` conserva bootstrap, shell, providers y routing global. Cada ruta, pantalla o flujo de
negocio debe vivir en una feature owner concreta dentro de `@features`, aunque existan nodos de
composicion temporales. `shared` solo puede alojar utilidades, UI y adaptadores transversales sin
conocer shell ni dominio. `core` aloja infraestructura reusable del frontend y no puede depender de
features especificas. `offline` se mantiene como subsistema transversal; cualquier adaptador hacia
features o red debe justificarse explicitamente. Para llamadas de negocio, usar el cliente HTTP
canonico; `fetch` directo solo es valido cuando la API nativa del navegador, el service worker o un
SDK externo lo requieran y la excepcion quede documentada.

## References

- `assets/app/` — bootstrap, shell, providers y routing global existentes
- `assets/features/` — ownership funcional por modulo de negocio
- `assets/shared/api/HttpClient.ts` — cliente HTTP canonico actual
- `assets/offline/` — subsistema transversal offline
- `assets/core/README.md` — rol de `@core` como libreria interna reusable
- ADR-014 — decision relacionada sobre evolucion de infraestructura reusable del frontend
