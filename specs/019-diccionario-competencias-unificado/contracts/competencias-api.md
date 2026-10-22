# Contrato: API de competencias unificada

Todas las rutas requieren autenticación (`401`). Las escrituras requieren el rol de administrador de Talento Humano (`403`). Sin `DELETE`. Los antiguos `/api/comportamientos` y `/api/preguntas` dejan de existir.

## Convenciones

- **Orden por posición**: los textos de grados, comportamientos y preguntas se envían como arreglos; el orden es la posición (a partir de 1). El primer grado es el más alto de la escala.
- **Tipo**: `Cardinal` o `Especifica` (sin tilde); la interfaz muestra "Específica".
- **Textos**: se recortan; un texto con solo espacios cuenta como vacío.
- **Versión**: toda respuesta de competencia incluye `version`. Los `PUT` la exigen.

## Objetos

### Competencia (detalle)

```json
{
  "id": 5,
  "codigo": "COMP-0005",
  "nombre": "Comunicación efectiva",
  "descripcion": "Transmite ideas con claridad y escucha activamente.",
  "tipo": "Cardinal",
  "estado": "Activo",
  "version": 3,
  "completa": true,
  "faltantes": [],
  "escala": { "tipo_grado_id": 3, "nombre": "Categorías" },
  "categorias": ["Excelente", "Muy buena", "Buena", "Normal"],
  "grados": [
    {
      "id": 17, "orden": 1, "etiqueta": "Excelente",
      "descripcion": "Comunica la visión institucional.",
      "comportamientos": ["…", "…", "…", "…"]
    }
  ],
  "preguntas": ["…", "…", "…", "…"],
  "auditoria": {
    "created_by": "Marta Ríos", "created_at": "2026-10-20T10:00:00Z",
    "updated_by": "Marta Ríos", "updated_at": "2026-10-21T09:30:00Z"
  }
}
```

- `auditoria`: solo se incluye para el administrador; para los demás usuarios la clave se omite. Los nombres se obtienen por el puerto `NombresDeUsuariosInterface` del módulo.
- `grados`: siempre ordenados por `orden`. Para una competencia incompleta heredada, `comportamientos` y `preguntas` pueden traer menos de cuatro textos.
- `categorias`: los cuatro nombres propios, o `null` si la competencia no los tiene (se usan los valores por defecto del catálogo).
- `completa` y `faltantes` (`"grados"`, `"comportamientos"`, `"preguntas"`): indican si puede activarse.

### Cuerpo de escritura (creación y edición de competencia)

| Campo | Regla |
|---|---|
| `nombre` | Obligatorio, ≤ 50, único sin distinguir mayúsculas, tildes ni espacios laterales |
| `descripcion` | Obligatoria, ≤ 500 |
| `tipo` | `Cardinal` o `Especifica` |
| `tipo_grado_id` | Id de una escala existente |
| `categorias` | Arreglo de exactamente 4 textos (≤ 20, sin vacíos ni repetidos). Obligatorio si la escala es Categorías; opcional en las demás (si se omite, no se tocan las guardadas) |
| `grados` | Arreglo de exactamente 4 textos (≤ 500) |

## Endpoints

### Crear — `POST /api/competencias`

Cuerpo: los campos anteriores más `comportamientos` (arreglo de exactamente 4 arreglos de exactamente 4 textos ≤ 300) y `preguntas` (exactamente 4 textos ≤ 500). Genera `codigo` y `nombre_clave`, crea la competencia `Activo` con `version` 1 y todo su contenido en una transacción, y registra `created_by`.
- `201`: competencia (detalle).
- `422`: validación o nombre duplicado.

### Listar — `GET /api/competencias`

Query: `q` (nombre; se normaliza igual que `nombre_clave`), `tipo` (`Cardinal` o `Especifica`), `estado` (`Activo`, `Inactivo` o `Todos`; opcional), `page`, `per_page` (1–50, por defecto 12). Orden por `codigo` ascendente.
- Administrador: sin `estado`, activas e inactivas. Resto de usuarios: siempre `Activo`, cualquiera sea el valor enviado.
- `200`: `{ "data": [{ "id", "codigo", "nombre", "tipo", "estado", "completa" }], "meta": { "total", "page", "per_page" } }`. Sin grados, comportamientos ni preguntas.

### Consultar — `GET /api/competencias/{id}`

- `200`: competencia (detalle), aunque esté inactiva (consulta desde históricos).
- `404`: no existe.

### Actualizar competencia y grados — `PUT /api/competencias/{id}`

Cuerpo: `version`, `nombre`, `descripcion`, `tipo`, `tipo_grado_id`, `grados` y, según la regla anterior, `categorias`. Conserva `codigo`, `estado` y `created_by`; recalcula `nombre_clave`; si cambia `tipo_grado_id`, actualiza los grados por orden y escribe el historial de escala; sube `version`; guarda `updated_by`.
- `200`: competencia (detalle).
- `409`: `version` desfasada. Mensaje: "Esta competencia cambió mientras la editabas. Recarga para ver la versión actual."
- `422`: validación, nombre duplicado en otra competencia, o el cuerpo incluye `codigo` o `estado`.
- `404`: no existe. Permitido en competencias inactivas.

### Actualizar comportamientos — `PUT /api/competencias/{id}/comportamientos`

Cuerpo: `version` y `comportamientos` (4 × 4 textos). Actualiza las filas existentes por grado y orden (inserta las que falten en una competencia incompleta); sube `version`.
- `200`, `409`, `422`, `404` como arriba. Permitido en inactivas.

### Actualizar preguntas — `PUT /api/competencias/{id}/preguntas`

Cuerpo: `version` y `preguntas` (4 textos). Mismo tratamiento.
- `200`, `409`, `422`, `404`. Permitido en inactivas.

### Aplicar escala — `PATCH /api/competencias/{id}/escala`

Cuerpo: `{ "tipo_grado_id": 2 }`. Cambia solo los nombres de los grados, escribe el historial de escala y sube `version`. No exige `version` de entrada.
- `200`: competencia (detalle).
- `422`: la escala ya es la solicitada o no existe.
- `404`: no existe. Permitido en inactivas.

### Cambiar estado — `PATCH /api/competencias/{id}/estado`

Cuerpo: `{ "estado": "Activo" | "Inactivo" }`. Escribe el historial de estado y sube `version`. No exige `version` de entrada.
- `200`: competencia (detalle).
- `422`: el estado ya es el solicitado, o se pide `Activo` con la competencia incompleta (mensaje: "No se puede activar: faltan los comportamientos y las preguntas.", con lo que falte).
- `404`: no existe.

### Listar escalas — `GET /api/tipo-grados`

- `200`: `[{ "id", "nombre", "activo", "valores": [{ "orden", "valor", "etiqueta" }] }]`, con las tres escalas y sus cuatro valores ordenados. Para el formulario, la vista previa y el selector. Cualquier usuario autenticado.

## Mensajes de validación (`422`), por campo

| Campo | Mensaje |
|---|---|
| `nombre` | "Ingresa el nombre." · "El nombre no puede superar los 50 caracteres." · "Ya existe una competencia con este nombre." |
| `descripcion` | "Ingresa la descripción." · "La descripción no puede superar los 500 caracteres." |
| `tipo` | "Selecciona el tipo." |
| `tipo_grado_id` | "Selecciona la escala de los grados." |
| `categorias` | "Escribe las cuatro categorías, sin repetir nombres." · "Un nombre de categoría no puede superar los 20 caracteres." |
| `grados` | "Completa los cuatro grados." · "La descripción de un grado no puede superar los 500 caracteres." |
| `comportamientos` | "Completa los cuatro comportamientos de cada grado." · "Un comportamiento no puede superar los 300 caracteres." |
| `preguntas` | "Completa las cuatro preguntas." · "Una pregunta no puede superar los 500 caracteres." |
| `estado` | "La competencia ya está en ese estado." · "No se puede activar: faltan …" |

Los errores de elemento usan la ruta completa (`grados.2`, `comportamientos.1.3`, `categorias.0`, `preguntas.3`) para que la interfaz los muestre junto al campo.
