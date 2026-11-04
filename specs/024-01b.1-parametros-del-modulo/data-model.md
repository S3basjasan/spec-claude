# Data Model: Parámetros del módulo (01b.1)

## parametros_modulo (nueva; la crea esta feature)

Solo inserciones: cada cambio es una fila nueva.

| Columna | Tipo | Reglas |
|---|---|---|
| `id` | bigint, PK | Autoincremental; también define la `version` de la clave |
| `clave` | enum `clave_parametro` | `puntos_por_colaborador`, `plazo_anulacion_dias`, `umbral_concentracion`, `plazo_max_desafio_dias`, `umbral_clima_unidad`, `sensibilidad_radar`; no nulo |
| `valor` | int | No nulo; entero dentro del rango de la clave |
| `vigente_desde` | date | No nula; `puntos_por_colaborador`: día 1 del mes siguiente; las demás: hoy (zona `compensaciones.zona_horaria`, America/Guayaquil) |
| `created_by` | bigint | No nulo; id de usuario sin FK (relación lógica con Shared) |
| `created_at` | timestamp | Momento del cambio |

Índice: `(clave, vigente_desde)`. Sin `updated_at`, sin soft delete y sin UPDATE/DELETE.

En SQLite el enum se guarda como `string` con validación en dominio.

## Claves: rango y valor por defecto

| Clave | Rango | Defecto | Aplica |
|---|---|---|---|
| `puntos_por_colaborador` | 1 a 1000 | mayor entre 100 y la puntuación de la categoría activa más alta | día 1 del mes siguiente |
| `plazo_anulacion_dias` | 1 a 30 | 7 | de inmediato |
| `umbral_concentracion` | 1 a 100 | 50 | de inmediato |
| `plazo_max_desafio_dias` | 1 a 90 | 30 | de inmediato; solo desafíos asignados después |
| `umbral_clima_unidad` | 1 a 100 | 33 | de inmediato |
| `sensibilidad_radar` | 1, 2 o 3 (Baja, Normal, Alta) | 2 | desde el siguiente cálculo semanal |

No se siembran filas iniciales: sin filas rige el defecto.

## Reglas derivadas

- **Valor vigente en una fecha F**: fila de la clave con `vigente_desde` más reciente que no supere F; con empate, la de mayor `id`. Sin fila: defecto.
- **Valor programado** (solo puntos): fila con `vigente_desde` posterior a hoy, la de mayor `id`. Un cambio posterior en el mismo mes lo reemplaza.
- **Version**: `id` de la última fila de la clave (0 si no hay).
- **Último cambio**: fila de mayor `id` de la clave: autor (nombre vía Shared), `created_at`, valor nuevo = `valor`, valor anterior = `valor` de la fila previa de la clave; si es la primera, no hay valor anterior (la pantalla dice «de valor por defecto a {nuevo}»).
- **Cambio sin efecto**: si el valor enviado es igual al último guardado de esa clave (programado si lo hay, vigente si no), no se inserta fila.

## categorias_reconocimiento (existente, solo lectura)

Se usa `puntuacion_base` y `nombre` de la categoría con `estado = Activo` de mayor puntuación, mediante el `CategoriaRepository` del módulo. Si hay empate, se nombra la de menor `id`. Sin categorías activas no hay piso.

## Entidades de dominio

- **CambioDeParametro**: `clave`, `valor`, `vigenteDesde`, `creadoPor`, `creadoEn`, `id`.
- **ClaveDeParametro** (value object/enum): conoce su rango, su defecto y si su vigencia es diferida.

## Transiciones

No hay estados; solo la secuencia de filas por clave. El paso de «programado» a «vigente» ocurre solo por el paso del tiempo (día 1 del mes siguiente), sin intervención.
