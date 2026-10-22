# Contrato de Facades: Adecuación a puesto futuro

Todo es **solo lectura** y aditivo. Los llamadores (Plan de Desarrollo Individual, Reportes) no acceden a tablas ni a clases internas.

## Qué expone `DesarrolloFacade` (módulo Desarrollo)

| Método | Descripción | Usuarios |
|---|---|---|
| `ultimaAdecuacion(int $tenantId, int $colaboradorId, int $puestoObjetivoId): ?array` | Adecuación vigente: `{adecuacion_id, codigo, fecha, preparacion, umbral_meses, puesto_actual_id, puesto_objetivo_id, inscripcion_id, brechas: [{dimension, tipo_elemento, elemento_id, nombre, diferencia, tiempo_cierre_meses}]}` o `null`. | Plan de Desarrollo Individual (futuro) |
| `adecuacionesPorFiltros(int $tenantId, array $filtros = []): array` | Cabeceras de adecuaciones **vigentes** (filtros: `fecha_desde`, `fecha_hasta`, `colaborador_id`, `puesto_objetivo_id`, `preparacion`, `departamento_id`): `[{adecuacion_id, colaborador_id, puesto_objetivo_id, preparacion, brechas, tiempo_maximo_cierre_meses, fecha}]`. Sin nombres ni observaciones. | Reportes (018, indicadores de preparación) |

Se declaran en el docblock `@method` de `DesarrolloFacade`, junto a `inscripcionesActivas` y `obtenerNivel` de la 019, e implementadas en `DesarrolloService` por casos de uso explícitos (`ConsultarAdecuacionesParaOtrosModulos`).

## Qué consume esta feature

| Facade | Método | Uso |
|---|---|---|
| `EstructuraFacade` | `getPuesto`, `getPuestosPorIds`, `getPuestosActivos`, `puestoEstaActivo`, `getUnidadesPorIds` | Perfil de competencias del puesto objetivo/actual, opciones de puesto directo, nombre de departamento |
| `PersonalFacade` | `getColaborador`, `getColaboradoresPorIds`, `buscarColaboradoresVigentes`, `vigentesPorIds`, `getDepartamentoActual`, `getCuentaDeUsuario`, `getDesarrollo` | Elegibilidad, nombres, departamento, usuario→colaborador, experiencia acumulada |
| `PersonalFacade` | **`getPuestoActual(int $tenantId, array $colaboradorIds, string $fecha): array`** *(nuevo, aditivo)* | `colaborador_id => puesto_id\|null`: puesto de la posición de la asignación laboral vigente; lanza `InformacionNoDisponible` si falla Estructura |
| `DesarrolloFacade` (propia) | `inscripcionesActivas`, `obtenerNivel` | Puesto objetivo y experiencia mínima desde la ruta |

## Reglas de degradación
- Si `getDesarrollo` o `getPuestoActual` lanzan `InformacionNoDisponible`, la lectura devuelve la parte disponible con `fuentes_no_disponibles`; la **generación** solo se permite si el perfil del puesto objetivo y el colaborador se pudieron leer, y la experiencia/puesto actual no disponibles quedan como «Sin evaluación»/nulo.
- Las operaciones nunca devuelven datos inventados.

## Pruebas del contrato
- Una prueba de contrato por método nuevo (forma del arreglo, vigente = más reciente, filtros, tenant).
- Una prueba de arquitectura que verifica que `Desarrollo` solo usa clases de otros módulos mediante sus Facades.
