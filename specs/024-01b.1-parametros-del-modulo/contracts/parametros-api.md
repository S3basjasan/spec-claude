# Contrato: API de parámetros del módulo

Base: `/api/compensaciones`. Ambos endpoints requieren sesión Sanctum (`401` sin sesión) y el permiso `parametros.gestionar` (`403` para cualquier otro usuario; en el repositorio de prueba, la capacidad `admin`).

## GET /parametros

Devuelve las seis claves.

```json
{
  "data": [
    {
      "clave": "puntos_por_colaborador",
      "valor": 100,
      "valor_programado": { "valor": 150, "vigente_desde": "2026-12-01" },
      "minimo": 1,
      "maximo": 1000,
      "por_defecto": 100,
      "version": 12,
      "ultimo_cambio": {
        "por": "Ana Pérez",
        "en": "2026-11-03T15:20:11-05:00",
        "valor_anterior": 100,   // null si es el primer cambio de la clave
        "valor_nuevo": 150
      }
    }
  ]
}
```

- `valor_programado`: solo en `puntos_por_colaborador` y `null` si no hay cambio pendiente.
- `ultimo_cambio`: `null` si la clave nunca cambió (se muestra «Valor inicial»). Si es el primer cambio, `valor_anterior` es `null` y se muestra «de valor por defecto a {nuevo}».
- `version`: id de la última fila de la clave, `0` si nunca cambió.
- `sensibilidad_radar` usa `minimo: 1`, `maximo: 3` y valores 1 (Baja), 2 (Normal), 3 (Alta).

## PUT /parametros

Cuerpo:

```json
{ "cambios": [ { "clave": "plazo_anulacion_dias", "valor": 10, "version": 0 } ] }
```

Todo en una transacción: si cualquier cambio falla no se guarda ninguno.

| Estado | Cuándo | Cuerpo |
|---|---|---|
| 200 | Guardado (también si ningún valor cambió realmente) | mismo formato que el GET, ya actualizado |
| 401 | Sin sesión | — |
| 403 | Sin permiso `parametros.gestionar` | mensaje seguro |
| 409 | La `version` de alguna clave no es la actual | `{"message": "Otra persona cambió un parámetro mientras editabas. Recarga para ver los valores actuales."}` |
| 422 | Clave desconocida, valor no entero, fuera de rango, sensibilidad fuera de 1–3, o puntos por debajo de la categoría activa más alta | errores por campo: `cambios.N.valor`; para el piso: `No puede ser menor que la puntuación de la categoría «{nombre}» ({puntos} puntos).` |

Efectos: una fila nueva por clave cuyo valor cambió (`puntos_por_colaborador` con `vigente_desde` = día 1 del mes siguiente; las demás = hoy en la zona `compensaciones.zona_horaria`). Nunca se actualiza ni se borra una fila.

## Uso interno (otras specs)

Las specs del mismo módulo (01b.2, 02.2, 02.3, 03.4, 03.5, 07.2 y 07.3) usan el servicio de Aplicación `ParametrosDelModulo::valorVigente(string $clave, ?CarbonInterface $fecha = null): int`, con la regla de lectura de `data-model.md`. No existe Facade.
