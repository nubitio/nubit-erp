# ADR-016: Soft-delete SQLFilter default-on

**Status**: Accepted
**Date**: 2026-03-23
**Deciders**: adr-writer, spex-architect
**Related Specs**: SPEC-001
**Supersedes**:

## Context

SPEC-001 corrige inconsistencias de recursos API, endurece RBAC y normaliza comportamiento en
recursos existentes. Dentro de ese trabajo se necesita introducir soft-delete para `Purchase`,
`User` y `Media` sin romper el contrato HTTP esperado por consumidores actuales. Los consumidores
web existentes de usuarios y compras esperan que `DELETE` siga respondiendo `204 No Content`, por
lo que no es aceptable convertir esta operación en un error funcional o en un contrato alternativo.

La solución también debe evitar depender de `gedmo/doctrine-extensions`, mantener la lógica de
soft-delete explícita en el código del proyecto y asegurar que los registros borrados queden ocultos
por defecto en requests normales. En este alcance no se expondrá un bypass público del filtro.

Adicionalmente, `Media` debe mapearse sobre la tabla `media`. No existe cliente Android/Kotlin en
este repositorio y no se encontró consumidor local de `DELETE /api/media`, por lo que la principal
restricción de compatibilidad observada aplica a `User` y `Purchase`, aunque se busca uniformidad de
arquitectura entre las tres entidades.

## Decision Drivers

- Debe preservarse el contrato HTTP actual de `DELETE` devolviendo `204` para compatibilidad con la UI web existente.
- Los registros soft-deleted deben quedar ocultos por defecto en lecturas normales.
- La solución debe ser explícita, mantenible y sin dependencia de `gedmo/doctrine-extensions`.
- Debe aplicarse de forma uniforme a `Purchase`, `User` y `Media`.
- En este spec no debe existir un bypass público para consultar registros eliminados.

## Considered Options

1. **Native Doctrine SQLFilter + deletedAt fields + custom delete processors** — usar `SQLFilter` nativo, campos `deletedAt` por entidad y processors que traduzcan `DELETE` en soft-delete.
2. **gedmo/doctrine-extensions soft-delete** — delegar soft-delete a la extensión Gedmo/Doctrine Extensions.
3. **Repository/provider-level filtering without global SQLFilter** — filtrar eliminados en repositories/providers y resolver `DELETE` con lógica ad hoc por recurso.

## Decision Outcome

**Chosen option**: **Native Doctrine SQLFilter + deletedAt fields + custom delete processors**

**Rationale**: Esta opción preserva el contrato `DELETE -> 204`, mantiene el control del comportamiento dentro del proyecto y aplica una política global consistente para ocultar registros eliminados en requests normales. El `SQLFilter` nativo de Doctrine permite que `Purchase`, `User` y `Media` compartan la misma regla de exclusión por `deletedAt IS NULL`, mientras que processors personalizados convierten el borrado HTTP en actualización de `deletedAt` sin exponer diferencias al cliente. Gedmo simplificaría parte del trabajo, pero añade una dependencia descartada explícitamente en este contexto y abstrae decisiones que el equipo quiere mantener visibles. El filtrado solo en providers/repositories es más frágil, más fácil de omitir y no ofrece una garantía global equivalente.

## Pros and Cons of the Options

### Option A: Native Doctrine SQLFilter + deletedAt fields + custom delete processors

**Pros**:
- Preserva compatibilidad con consumidores actuales al mantener `DELETE` con respuesta `204`.
- Oculta registros eliminados por defecto con una regla global y uniforme.
- Evita dependencia externa y deja la implementación bajo control directo del proyecto.
- Permite una estrategia consistente para `Purchase`, `User` y `Media`.

**Cons**:
- Requiere wiring explícito de filtro, campos `deletedAt` y processors por recurso.
- Obliga a diseñar cuidadosamente casos administrativos o internos que en el futuro necesiten ver eliminados.

---

### Option B: gedmo/doctrine-extensions soft-delete

**Pros**:
- Reduce trabajo de implementación al apoyarse en una librería conocida.
- Proporciona una solución ya empaquetada para soft-delete en Doctrine.

**Cons**:
- Introduce una dependencia que este spec descarta explícitamente.
- Reduce visibilidad sobre detalles de integración y personalización del comportamiento API.
- Puede complicar la alineación exacta con processors y contratos HTTP existentes.

---

### Option C: Repository/provider-level filtering without global SQLFilter

**Pros**:
- Evita añadir un filtro global de Doctrine.
- Permite control localizado en queries y providers concretos.

**Cons**:
- Es fácil omitir filtros en nuevas queries, providers o extensiones futuras.
- Duplica reglas entre capas y recursos.
- No garantiza de forma global que los registros eliminados queden ocultos por defecto.
- Hace más difícil mantener un comportamiento homogéneo entre lectura, colección y relaciones.

## Consequences

### Positive
- `Purchase`, `User` y `Media` comparten una política de soft-delete coherente.
- Las lecturas normales no mostrarán registros eliminados porque el filtro estará activo por defecto en requests.
- La UI web actual puede seguir usando `DELETE` sin cambios de contrato para usuarios y compras.
- La solución queda implementada con primitives nativas de Doctrine y código propio del proyecto.

### Negative
- En este spec no habrá bypass público para recuperar o listar eliminados, lo que limita casos de soporte o auditoría expuestos por API.
- Será necesario recordar el filtro en tooling interno, consola o futuros procesos administrativos que sí necesiten ver eliminados.
- Los processors de borrado pasan a ser parte crítica del contrato de dominio para estas entidades.

### Risks
- Riesgo de que un contexto técnico interno necesite consultar eliminados y falle por el filtro activo por defecto — Mitigation: definir bypass interno/no público en un spec futuro si aparece un caso real.
- Riesgo de inconsistencias si una entidad participante no declara correctamente `deletedAt` o no queda cubierta por el filtro — Mitigation: aplicar la convención de forma uniforme y validar con tests funcionales de colección, item y delete.
- Riesgo de asumir compatibilidad no verificada en `Media` — Mitigation: mantener `DELETE 204` también en `Media`; no se encontró consumidor repo-local de `DELETE /api/media`, por lo que la decisión conserva el contrato más seguro.

## Implementation Notes

Implementar `deletedAt` en `Purchase`, `User` y `Media`; mapear `Media` sobre la tabla `media`; registrar un `SQLFilter` nativo de Doctrine que excluya filas con `deletedAt` no nulo y habilitarlo por defecto en requests. Resolver `DELETE` mediante processors personalizados que asignen `deletedAt` y devuelvan `204 No Content` para mantener compatibilidad. No introducir `gedmo/doctrine-extensions` y no exponer bypass público del filtro dentro de SPEC-001.

## References

- [Doctrine ORM Filters](https://www.doctrine-project.org/projects/doctrine-orm/en/current/reference/filters.html) — referencia del mecanismo nativo de `SQLFilter` usado en la decisión.
- SPEC-001 — spec que exige soft-delete consistente, compatibilidad HTTP y normalización de recursos.
- TASK-217 — tarea de documentación/decisión para formalizar la arquitectura de soft-delete.
