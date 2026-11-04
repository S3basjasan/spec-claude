# Research: Puntaje atado al parámetro (01b.2)

No quedaron `NEEDS CLARIFICATION` en el contexto técnico. Se registran las decisiones verificadas contra el código actual.

## 1. Dónde calcular el máximo permitido
- **Decision**: método nuevo `puntuacionMaximaDeCategoria()` en `ParametrosDelModulo` (Aplicación): `min(valorVigente(PuntosPorColaborador, hoy), programado?->valor)`.
- **Rationale**: el servicio ya expone `valorVigente` y `programado` con la zona horaria del módulo; centralizar evita que `CrearCategoria`, `ActualizarCategoria`, `CambiarEstadoCategoria` y la respuesta de parámetros calculen distinto.
- **Alternatives considered**: calcularlo en cada caso de uso (duplicación); guardarlo como columna (dato derivado que se desfasaría al cambiar de mes sin job).

## 2. Cómo falla la validación de dominio
- **Decision**: reutilizar `DatosDeCategoriaInvalidos('puntuacion_base', mensaje)`; `CategoriaController::responder` ya la traduce a 422 con `errors.puntuacion_base`.
- **Rationale**: es la red de seguridad que usa hoy `PuntuacionBase` (verificado en el controlador); no hace falta excepción ni mapeo nuevo. El `FormRequest` conserva `between:1,1000` como límite exterior, por lo que un valor absurdo se rechaza antes de consultar el parámetro.
- **Alternatives considered**: regla de validación de Laravel con el máximo inyectado (mezcla lectura de parámetros en la capa HTTP; la constitución pide reglas de negocio en casos de uso).

## 3. Editar solo otros campos con la puntuación ya sobre el tope (FR-010)
- **Decision**: construir la propuesta con el máximo antes de comparar diferencias en `ActualizarCategoria`.
- **Rationale**: hoy `comoPropuesta` corre antes de `diferenciasCon`, así que la validación ya ocurre aunque no cambie la puntuación; basta pasarle el máximo. El 409 por versión desactualizada sigue evaluándose antes.
- **Alternatives considered**: validar solo si cambia la puntuación (rechazada en la clarificación del 2026-11-03).

## 4. Activar una categoría por encima del tope (FR-011)
- **Decision**: verificar en `CambiarEstadoCategoria` después de los chequeos de versión y de «mismo estado», solo para `Activo`.
- **Rationale**: la regla de 01b.1 solo mira categorías activas; sin esto la reactivación rompería el invariante. Pedir «Activo» a una categoría ya activa sigue devolviéndola sin cambios y sin auditoría, como hoy.
- **Alternatives considered**: validar también al desactivar (innecesario: desactivar no puede violar el tope); rechazar con 409 (no es un conflicto de versión).

## 5. Cómo obtiene el formulario el máximo
- **Decision**: agregar `meta.puntuacion_maxima_categoria` a `GET /api/compensaciones/parametros` y leerlo desde una query propia en `categorias/`.
- **Rationale**: lo pide el plan de origen y reutiliza un endpoint existente sin ruta nueva. La respuesta actual es `{data: [...]}`; `meta` es aditivo y no rompe a la pantalla de parámetros.
- **Riesgo**: el endpoint exige `parametros.gestionar` y el formulario `categorias.gestionar`. Hoy los dos recaen en el mismo rol (`admin`), por lo que no hay brecha; si se separan, el máximo debería viajar en la respuesta de categorías.
- **Alternatives considered**: endpoint dedicado `/categorias/puntuacion-maxima` (más superficie para el mismo dato); incluirlo en cada categoría (se repite por fila y no sirve para «crear»).

## 6. Frescura del máximo en pantalla
- **Decision**: consultar al abrir el formulario (`refetchOnMount: 'always'`); el servidor valida con el máximo vigente al guardar (FR-006).
- **Rationale**: el parámetro puede cambiar con el formulario abierto; el servidor es la autoridad y el 422 trae el máximo actual en el mensaje.
- **Alternatives considered**: polling o invalidación cruzada con la pantalla de parámetros (complejidad sin beneficio).

## 7. Datos históricos
- **Decision**: no migrar ni recalcular nada.
- **Rationale**: `reconocimientos.puntos` y `desafios.puntos` son copias al registrarse (`modelo-de-datos.dbml`); ajustar puntuaciones al cambiar el parámetro está fuera de alcance. Las categorías que ya excedan el tope se corrigen a mano al editarlas o antes de activarlas.
- **Cobertura parcial (FR-007 / SC-004)**: esas tablas aún no existen en el repositorio. Aquí se prueba que editar o activar solo escribe en `categorias_reconocimiento` y su auditoría; que los puntos se copian al registrarse lo comprueban las pruebas de las specs de inc-02 (desafíos) e inc-03 (reconocimientos).
