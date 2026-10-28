# Specification Quality Checklist: Registro de movimientos y novedades de personal

**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-10-21
**Feature**: [spec.md](../spec.md)

## Content Quality

- [x] No implementation details (languages, frameworks, APIs)
- [x] Focused on user value and business needs
- [x] Written for non-technical stakeholders
- [x] All mandatory sections completed

## Requirement Completeness

- [x] No [NEEDS CLARIFICATION] markers remain
- [x] Requirements are testable and unambiguous
- [x] Success criteria are measurable
- [x] Success criteria are technology-agnostic (no implementation details)
- [x] All acceptance scenarios are defined
- [x] Edge cases are identified
- [x] Scope is clearly bounded
- [x] Dependencies and assumptions identified

## Feature Readiness

- [x] All functional requirements have clear acceptance criteria
- [x] User scenarios cover primary flows
- [x] Feature meets measurable outcomes defined in Success Criteria
- [x] No implementation details leak into specification

## Notes

- Sin marcadores de aclaración: las decisiones que el insumo deja abiertas (contrato de evaluación del destino, reglas de fechas y superposición de novedades, saldos de vacaciones, corrección de movimientos efectivos y comunicación de movimientos internos) se registran como dependencias abiertas en Assumptions, con la regla de que los casos afectados no se habilitan hasta que existan; no se resuelven con suposiciones.
- Los casos RMN-CA01 a RMN-CA13 del insumo están cubiertos por los escenarios de aceptación de las cinco historias (CA01 → US4.1; CA02 → US4.2; CA03 → US5.1; CA04 → US5.3; CA05 → US1.1; CA06 → US1.2; CA07 → US3.1; CA08 → US2.3; CA09 → US2.4; CA10 → US1.4; CA11 → US2.5; CA12 → US1.8, US2.7, US5.6; CA13 → US5.5).
- Numeración: `020` porque 016 a 019 ya existen en `specs/` (incluida una 016 de otra rama); la política de personal de este mismo repositorio es la 016-politicas-operativas-personal.
- Clarificaciones del 2026-10-21 integradas en la spec: aplicación automática con reintento manual (FR-023b/c), cruce de períodos señalado y reconocido (FR-018b), entrega por etapas de movimientos (FR-022b), visibilidad del Colaborador solo de lo aprobado (FR-037) y retiro de solicitudes por el solicitante (FR-028b). Sin puntos abiertos.


## Reformulación 2026-10-28

Las casillas anteriores conservan la revisión documental original. La titularidad del diagnóstico queda asignada a Desarrollo y Formación; la captura de niveles requiere coordinación con ese módulo. Los criterios PI-01–PI-12 y FR-045–FR-052, el contrato nuevo y T063–T074 definen la ampliación pendiente.

- [ ] Contratos reales de evidencia y ocupación acordados y verificados con sus propietarios.
- [ ] Adecuación integrada con fuentes reales suficientes; contradicción histórica de procedencia de niveles resuelta.
- [ ] Diseño y aprobación/revalidación implementados con historia y permisos conservados.
- [ ] Aplicación atómica con ocupación coherente y pruebas de fallas/concurrencia.
- [ ] Seguimiento 005 integrado sin duplicar diagnósticos ni revelar información restringida.
- [ ] PI-01–PI-12 y regresiones verificadas, con resultados registrados.
