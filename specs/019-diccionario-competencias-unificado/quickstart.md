# Quickstart: Diccionario de competencias unificado

Guía para validar la feature de punta a punta una vez implementada. Referencias: [data-model.md](data-model.md), [contracts/competencias-api.md](contracts/competencias-api.md) y [contracts/estructura-facade.md](contracts/estructura-facade.md).

## Prerrequisitos

- PHP 8.3, Composer, Node y las dependencias instaladas (`backend/` y `frontend/`).
- Cuentas de prueba sembradas por `UsuariosInicialesSeeder`: administrador `admin@hrms.test` y un usuario sin rol de administrador.
- Para probar la migración de datos, una copia de la base con datos de las funcionalidades 001–003 (competencias, comportamientos, preguntas y puestos con competencias asignadas).

## Preparar la base

```bash
cd backend
php artisan migrate:fresh --seed
```

Resultado esperado: las 3 escalas, las 7 competencias completas (COMP-0001 a COMP-0007, una inactiva) y los datos de los demás módulos.

## Validar la migración sobre datos existentes

```bash
cd backend
php artisan migrate
./vendor/bin/pest tests/Feature/Estructura/Competencias/MigracionUnificacionTest.php
```

Esperado: las competencias completas quedan intactas y activas; las incompletas quedan Inactivas con registro en el historial de estado; `puesto_competencias.competencia_grado_id` está relleno y ninguna asignación se perdió; las tablas "registro" ya no existen.

## Pruebas automáticas

```bash
cd backend && ./vendor/bin/pest tests/Feature/Estructura/Competencias tests/Unit/Estructura/Competencias && ./vendor/bin/pint --test
cd frontend && npm run lint && npx tsc --noEmit && npm run test -- competencias && npm run build
cd frontend && npx playwright test e2e/competencias.spec.ts
```

## Escenarios manuales (administrador)

1. **Listado**: abre Competencias. Ves las 7 competencias, una por fila (activas e inactivas), con código, nombre y tipo; COMP-0007 con el nombre atenuado. No hay filtro de estado. Bajo la lista dice "7 competencias".
2. **Búsqueda y filtros**: escribe "lidérazgo" y se filtra a Liderazgo; elige el tipo Cardinal y aparece "Limpiar filtros"; una búsqueda sin resultados muestra "Ninguna competencia coincide con los filtros."
3. **Detalle**: despliega "Comunicación efectiva". Pestaña Competencia: descripción y grados nombrados Excelente / Muy buena / Buena / Normal. Pestañas Comportamientos (cuatro por grado) y Preguntas (1 a 4).
4. **Vista previa de escala**: en Competencia elige "Letras": los grados pasan a Grado A–D con "(vista previa)" y aparecen "Aplicar esta escala" y "Descartar". Descartar restablece. Aplicar muestra "Escala de grados actualizada correctamente." y el cambio persiste al recargar; las descripciones y comportamientos no cambian.
5. **Crear**: "Nueva competencia", elige Categorías, escribe nombres propios (por ejemplo, "Maestría", "Avanzado", "Competente", "Inicial"), completa los 4 grados, 16 comportamientos y 4 preguntas y guarda. Aviso "Competencia creada correctamente." y aparece COMP-0008 activa. Intentar guardar con un campo vacío, o con nombres de categoría repetidos, muestra el error junto al campo. Un nombre ya existente ("lidérazgo ") se rechaza.
6. **Editar por sección**: edita solo los comportamientos y guarda ("Comportamientos actualizados correctamente."); edita solo las preguntas; edita la competencia (nombre, tipo, escala y grados). Cada una cambia solo su sección.
7. **Concurrencia**: abre la edición de una competencia en dos ventanas; guarda en la primera y luego en la segunda. La segunda recibe el aviso de que la competencia cambió y se pide recargar; tras recargar ve los cambios de la primera.
8. **Desactivar / Activar**: desactiva una competencia (aparece el diálogo de confirmación; Cancelar no cambia nada); confirma ("Competencia desactivada."). Reactívala sin confirmación ("Competencia activada."). Los comportamientos y las preguntas siguen el estado.
9. **Competencia heredada incompleta**: con la base migrada, una competencia sin preguntas está Inactiva y muestra el aviso de contenido incompleto. Intentar activarla indica qué falta; completa la sección y actívala.
10. **Puestos**: abre un puesto con competencias asignadas; el grado requerido sigue mostrándose y no cambió al aplicar otra escala.

## Escenarios manuales (usuario sin rol de administrador)

1. Solo ve las competencias activas (no ve COMP-0007), sin "Nueva competencia", sin Activar/Desactivar, sin lápices de edición y sin "Aplicar esta escala".
2. Puede desplegar tarjetas, recorrer las tres pestañas y usar la vista previa de escala.
3. Las peticiones de escritura directas al API responden `403`.

## Comprobaciones de contrato

```bash
curl -s -o /dev/null -w "%{http_code}\n" -X DELETE http://localhost:8000/api/competencias/1   # 404 o 405: no existe
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/api/comportamientos            # 404: ruta retirada
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/api/preguntas                  # 404: ruta retirada
```

Con un token válido: `GET /api/tipo-grados` devuelve las tres escalas; un `PUT` con `version` desfasada devuelve `409`; `PATCH …/estado` a `Activo` sobre una competencia incompleta devuelve `422`.

## Criterios de salida

- Todas las pruebas de backend (Pest, Pint y análisis estático) y de frontend (ESLint, TypeScript, Vitest y build) pasan, y el E2E de competencias pasa.
- Los escenarios anteriores se cumplen en tema claro y oscuro, entre 320 y 1440 px, sin scroll horizontal.
- Los módulos de comportamientos y preguntas del frontend, sus rutas y sus pruebas ya no existen, y ningún módulo depende de ellos.
