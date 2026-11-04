# Quickstart: validar 01b.4 (categorías por unidad y por rol)

Detalle de datos en [data-model.md](data-model.md) y de endpoints en [contracts/categorias-unidades-api.md](contracts/categorias-unidades-api.md).

## Prerrequisitos

- Backend y frontend instalados; semillas de Estructura (varias unidades activas y al menos una inactiva) y de Personal (`PersonalSeeder`) cargadas.
- `backend/config/compensaciones_prueba.php` con `tenant_id`, `equipos` (un líder con equipo) y `usuarios`, con ids que coinciden con `PersonalSeeder`.
- Usuarios de prueba: Administrador (Gestor), un líder, un colaborador de la unidad A y otro de la unidad B.

## Preparar los datos

```bash
php artisan migrate
php artisan compensaciones:asignar-unidades-iniciales
```

Comprobar: cada categoría previa queda con todas las unidades activas; volver a ejecutar el comando no cambia nada (idempotente).

## Pruebas automatizadas

```bash
php artisan test --filter=Compensaciones
./vendor/bin/pint --test
```

```bash
npm run lint
npm run typecheck
npm test
npm run build
npm run e2e -- categorias-reconocimiento
```

Debe pasar todo, incluidas las pruebas de 01b.1 y 01b.2 (sin regresiones).

## Escenarios manuales (cubren las historias de la spec)

1. **Asignar (US1)**: como Gestor, crear una categoría marcando solo la unidad A → queda con A. Editarla con «Todas las unidades» → quedan todas las activas. Intentar guardar sin unidades → error «Elige al menos una unidad.» junto al campo y nada se guarda.
2. **Auditoría (US1)**: tras cambiar unidades, el historial muestra el campo `unidades` con anterior y nuevo; guardar sin tocar unidades no agrega fila.
3. **Colaborador (US2)**: entrar como colaborador de la unidad B → no ve la categoría asignada solo a A; abrirla por URL directa responde 404; sin controles de gestión ni columna de unidades. Una unidad sin categorías → «Todavía no tienes categorías asignadas».
4. **Líder y Gerencia (US5)**: el líder ve todas las activas con «Aplica a: …» y sin acciones; `PUT`/`PATCH`/`POST` directos responden 403.
5. **Seleccionables (US3 y US4)**: desde `php artisan tinker`, `CategoriasFacade::seleccionablesParaUnidad(A)` devuelve solo las activas, con competencia activa y asignadas a A; con una unidad inactiva o inexistente devuelve `[]`.
6. **Existentes (US6)**: los reconocimientos y desafíos ya registrados conservan sus puntos y su categoría tras cambiar unidades.
7. **Unidad inactiva (borde)**: desactivar la unidad A → los colaboradores de A dejan de ver categorías asignadas solo a A; el Gestor ve la advertencia «sin unidades activas» en esas categorías.

## E2E principal

El Gestor asigna una categoría a la unidad A; un colaborador de la unidad B abre el listado y no la ve; un colaborador de A sí.

## Criterios de salida

SC-001 a SC-007 de la spec, con las decisiones aceptadas registradas en `plan.md` («Desviaciones y decisiones aceptadas»): sin conteo de personas por unidad (FR-014) y Gerencia = Administrador en el repo de prueba (SC-007 se verifica con el líder).
