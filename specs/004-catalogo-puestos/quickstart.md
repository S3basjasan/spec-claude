# Quickstart: validar el catálogo de puestos

## Prerrequisitos

- Features 000-login, 001-diccionario-competencias, 002 y 003 funcionando (usuarios `admin@hrms.test` y
  `usuario@hrms.test`, contraseña de `SEED_PASSWORD`; siete competencias, `COMP-0007` inactiva).
- Dependencias instaladas en `backend/` y `frontend/`.
- Diseño actualizado (`design/DESIGN.md` y `design/PuestosFormulario.dc.html`: fila desplegable de puesto, fila repetible
  de tipo y texto, reordenación, fila de competencia y diálogo de desactivación).
- Recomendado: contrastar las migraciones de esta feature con el DBML real del proyecto (ver
  [research.md](research.md) D-02).

## Preparar el backend

```bash
cd backend
php artisan migrate:fresh --seed
php artisan serve
```

Siembra usuarios, competencias, registros de comportamientos y preguntas, y seis puestos (ver
[data-model.md](data-model.md)): `PU-001` Analista de datos, `PU-002` Jefe de talento humano, `PU-003` Desarrollador de
software, `PU-004` Ejecutivo de gestión comercial, y los inactivos `PU-005` Asistente contable y `PU-006` Coordinador de
operaciones.

## Validar la API (contrato en [contracts/puestos-api.md](contracts/puestos-api.md))

1. Sin token, `GET /api/puestos` → 401.
2. Como Usuario, `GET /api/puestos` → 200 con **4** puestos activos, ordenados por nombre, con `id`, `codigo`, `nombre`,
   `descripcion` y `estado`, sin misión, funciones, requisitos ni competencias.
3. Como Usuario, `?estado=Todos` y `?estado=Inactivo` → el mismo resultado (4).
4. Como Administrador: sin parámetros → 4; `?estado=Inactivo` → 2; `?estado=Todos` → 6; `?q=pu-00` (código) y
   `?q=gestion` y `?q=GESTIÓN` (nombre, sin distinguir tildes ni mayúsculas): ambos devuelven 1 puesto, «Ejecutivo de gestión comercial».
5. `GET /api/puestos/1` → 200 con `mision`, `version`, funciones por `orden`, requisitos por tipo y competencias con
   código, nombre, tipo, estado y grado; para un puesto inactivo (id 5) también 200 con el Usuario; para un id
   inexistente → 404.
6. Como Usuario, `POST`, `PUT`, `PATCH …/estado` y `GET …/competencias-disponibles` → 403; `DELETE` → 404/405.
7. Como Administrador, `GET …/competencias-disponibles` → 200 con las seis competencias activas y sus cuatro grados, sin
   `Adaptabilidad`.
8. `POST` con un puesto completo y válido → 201 con `codigo` `PU-007`, `estado` `Activo` y `version` 1. Fallan con 422 y
   errores por campo: nombre repetido (también con otras mayúsculas o espacios alrededor, o igual a un puesto inactivo),
   nombre, descripción o misión vacíos o demasiado largos; sin función `Esencial`; sin requisito de `Formacion` o de
   `Experiencia`; sin competencias; una función o un requisito del mismo tipo repetido; más de 20 elementos en una lista;
   una competencia repetida, inexistente o inactiva; un grado que no pertenece a la competencia.
9. `PUT` con la `version` actual y un cambio → 200 y `version` +1 (con `codigo` y `created_by` intactos); el mismo cuerpo
   otra vez → 200 sin subir la versión; con la `version` anterior → 409; con `codigo` distinto → 422; sobre un puesto
   inactivo → 200; sobre un id inexistente → 404.
10. `PUT` que solo reordena las funciones → 200, `version` +1 y el nuevo orden en el detalle.
11. `PUT` sobre `PU-004` (con `COMP-0007` inactiva): mantener esa competencia con su grado → 200; cambiarle el grado →
    422 en `competencias.N.grado_requerido`; quitarla → 200 y ya no puede volver a agregarse.
12. Estado: `PATCH /api/puestos/1/estado` con `Inactivo` → 200 (`PU-001` no tiene posiciones); con `Activo` → 200;
    repetir el mismo estado → 200 sin historial. `PU-002` (Ocupadas y Vacantes) con `Inactivo` → 422 con
    `posiciones_en_uso` y sigue `Activo`. `PU-003` (solo Suprimidas) con `Inactivo` → 200. `PU-005` con `Activo` → 200.
    El cambio de estado no altera `version`.
13. Auditoría (consulta directa a `auditoria_cambios`): una fila por cada dato y elemento al crear (valor anterior
    nulo); una fila por campo, función, requisito o competencia cambiados; una fila `estado` por cada cambio efectivo;
    **ninguna** por reordenar funciones ni por guardar sin cambios.

## Validar la interfaz

```bash
cd frontend
npm run dev
```

1. Como Usuario: "Puestos" en el menú; el listado muestra Código, Nombre, Descripción y Estado (badge) solo de puestos
   activos, sin filtro de estado ni botones de gestión.
2. Buscar por código ("PU-00") y por nombre sin tildes ("gestion" encuentra «Ejecutivo de gestión comercial»); sin resultados aparece "Ningún puesto coincide con la
   búsqueda."; recargar conserva búsqueda y página.
3. Desplegar una fila muestra misión, funciones numeradas con su tipo, requisitos agrupados por tipo y la tabla de
   competencias (competencia, tipo y grado requerido); desplegar otra cierra la primera.
4. Como Administrador: aparece el filtro de estado y "Nuevo puesto"; en "Inactivos" se ven `Asistente contable` y
   `Coordinador de operaciones` con badge Inactivo; en el detalle aparecen Editar y Activar/Desactivar (el texto
   alterna). `Ejecutivo de gestión comercial` muestra su competencia inactiva señalada.
5. Crear: cuatro secciones (Datos generales con "el código se asigna al guardar", Funciones, Requisitos, Competencias);
   agregar, quitar y reordenar funciones con Subir y Bajar (con anuncio de la nueva posición); el selector de competencia
   solo ofrece activas aún no elegidas y el grado solo los de la competencia; errores por campo con el formulario
   incompleto, con textos repetidos o con una lista de más de 20 (el botón Agregar se deshabilita en 20); valores
   conservados al corregir.
6. Editar: el código en solo lectura; la competencia inactiva con grado bloqueado (solo Quitar); guardar sin cambios
   vuelve al listado sin error; abrir dos pestañas y guardar la segunda muestra "El puesto cambió" con "Recargar".
7. Desactivar `Analista de datos` pide confirmación que nombra el puesto; confirmar lo pasa a Inactivo y desaparece del
   listado por defecto. Desactivar `Jefe de talento humano` muestra el rechazo con la cantidad de posiciones y el puesto
   sigue Activo. Activar no pide confirmación.
8. No hay botón de eliminar; estados vacío, cargando y error; ambos temas y anchos de 320 a 1440 px sin scroll
   horizontal.

## Ejecutar las pruebas

```bash
cd backend && vendor/bin/pint --test && vendor/bin/pest
cd ../frontend && npm run lint && npx tsc -b && npx vitest run && npm run build && npx playwright test
```
