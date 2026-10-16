# Contrato HTTP: Exportación individual de ficha

Prefijos de GP-01 (`/api/colaboradores`, `/api/mi-ficha`). La ruta administrativa va bajo `auth:sanctum` + `admin`; la propia, solo `auth:sanctum` (el caso de uso exige asociación válida y permiso de exportación). Solo `GET`: la exportación es una lectura, no usa `Idempotency-Key` ni versión, y repetirla genera otra representación sin crear ningún hecho. **No existen CSV, listados ni exportaciones masivas.** El tenant y el actor salen siempre de la sesión.

## Endpoints

| Método y ruta | Quién | Entrada | Respuestas |
|---|---|---|---|
| `GET /api/colaboradores/{colaborador}/exportacion.pdf` | Administrador | ninguna (solo el id de la ruta, dígitos) | 200 `application/pdf`; 404 `FICHA_NO_ENCONTRADA`; 403; 503 `EXPORTACION_NO_DISPONIBLE` / `EXPORTACION_DESHABILITADA` |
| `GET /api/mi-ficha/exportacion.pdf` | Cuenta con permiso de exportación | ninguna; nunca acepta otro id por ruta, consulta ni cuerpo | 200 `application/pdf`; 403 `CUENTA_SIN_FICHA` / `SIN_PERMISO_EXPORTACION`; 503 |

## Respuesta correcta (200)

Cabeceras:

```
Content-Type: application/pdf
Content-Disposition: attachment; filename="Ficha_EMP-001.pdf"
Content-Length: <bytes exactos>
Cache-Control: private, no-store
X-Content-Type-Options: nosniff
```

El nombre se forma con el código de la ficha saneado a `[A-Za-z0-9_-]`; nunca con texto del cliente. El cuerpo es el PDF completo. No hay URL pública, firmada ni permanente, ni redirecciones.

## Contenido del PDF

Título, código, secciones rotuladas (identificación, datos personales, situación laboral, formación académica, experiencia anterior, movilidad, trayectoria), secciones vacías identificadas, «No registrado» / «No informada», paginación («Página n de N») y el momento de generación. Cada registro es un bloque con saltos controlados: no se recorta nada ni se superpone texto. No incluye adjuntos, enlaces de descarga, referencias de acceso, información financiera ni indicadores calculados. Ver [data-model.md](../data-model.md).

## Reglas de respuesta

- **401** sin sesión. **403** al Colaborador en la ruta administrativa (middleware `admin`), sin revelar si el recurso existe; en la propia, **403** `CUENTA_SIN_FICHA` (sin asociación válida o de otro tenant) o `SIN_PERMISO_EXPORTACION` (sin `puede_exportar`, aunque tenga el de ficha o el de documentos).
- **404** idéntico (`FICHA_NO_ENCONTRADA`) para ficha inexistente o de otro tenant en la ruta administrativa; el Colaborador no puede apuntar a otra ficha.
- **503** `EXPORTACION_NO_DISPONIBLE` cuando no se puede obtener un dato necesario o renderizar; `EXPORTACION_DESHABILITADA` con el interruptor `hrms.gestion_personal.exportacion_habilitada` en falso. Estos errores se responden en JSON antes de enviar ningún byte y **nunca** como un PDF con un mensaje técnico dentro ni como un PDF parcial.
- **403** también si el permiso se revoca entre la solicitud y la entrega: el PDF generado se descarta y no se envía.
- Formato de error: `{message, code}` con mensajes en español sin datos internos.
- La operación no escribe nada: ni ficha, ni vínculos, ni documentos, ni registros de exportación; y no agrega el PDF al expediente documental.
