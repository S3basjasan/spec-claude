---

description: "Tareas de 01b.3 · Nombre del rol «Gestor de Talento»"
---

# Tasks: Nombre del rol «Gestor de Talento» (01b.3)

**Input**: Documentos de diseño en `/specs/023-01b.3-rol-gestor-de-talento/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/politica-de-acceso.md, quickstart.md

**Tests**: Se incluyen porque la constitución exige cobertura automatizada de todo cambio de comportamiento (`puede()`), y el plan prevé ajustar la prueba de Vitest que compara el texto.

**Organization**: Tareas agrupadas por historia de usuario de la spec. Todo el cambio vive en el módulo `Compensaciones` / `compensaciones` y en una maqueta de `design/`; no hay migraciones ni endpoints nuevos.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Se puede ejecutar en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: Historia a la que pertenece (US1…US4)

## Path Conventions

- Backend: `backend/app/Modules/Compensaciones/`, pruebas en `backend/tests/Feature/Compensaciones/`
- Frontend: `frontend/src/modules/compensaciones/categorias/`, E2E en `frontend/e2e/`
- Diseño: `design/`

---

## Phase 1: Setup

**Purpose**: Fijar la línea base antes de cambiar nada

- [ ] T001 Ejecutar la suite actual `cd backend && ./vendor/bin/pest tests/Feature/Compensaciones` y `cd frontend && npx vitest run src/modules/compensaciones`, y anotar que todo pasa (línea base de CA-04)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Nada bloqueante común. Las historias tocan archivos distintos salvo US2 y US3, que comparten `PoliticaDeAcceso.php` (ver Dependencies).

*(sin tareas)*

---

## Phase 3: User Story 1 - El módulo habla de «Gestor de Talento» (Priority: P1) 🎯 MVP

**Goal**: Ningún texto visible del módulo contiene «Administrador de Talento Humano» ni «Talento Humano» como rol; el rol se nombra «Gestor de Talento».

**Independent Test**: Con `usuario@hrms.test` y el catálogo vacío, el aviso dice «Cuando el Gestor de Talento cree categorías, aparecerán aquí.»; la búsqueda de «Talento Humano» y «Administrador de Talento Humano» en el módulo y la maqueta devuelve 0 resultados.

- [x] T002 [US1] Actualizar la aserción del estado vacío a «Cuando el Gestor de Talento cree categorías, aparecerán aquí.» en `frontend/src/modules/compensaciones/categorias/pages/CategoriasReconocimientoPage.dom.test.tsx` (línea 158) y comprobar que falla antes del cambio de texto
- [x] T003 [US1] Cambiar `states.emptyUsuario` a «Cuando el Gestor de Talento cree categorías, aparecerán aquí.» en `frontend/src/modules/compensaciones/categorias/text.ts` (línea 48); revisar el resto de `text.ts` para que ningún texto visible nombre al rol como «Administrador» o «Talento Humano»
- [x] T004 [US1] **Primero** (constitución III: el diseño va antes del código). Actualizar `design/CategoriasReconocimiento.dc.html`: opción del selector de rol (línea 84), avatar de ejemplo (línea 900) y estado vacío (línea 1101) pasan a «Gestor de Talento» / «Cuando el Gestor de Talento cree categorías, aparecerán aquí.»
- [x] T005 [P] [US1] Revisar `frontend/e2e/categorias-reconocimiento.spec.ts` y ajustar solo si alguna aserción compara el texto cambiado (el comentario de la línea 4 y el título de la línea 32 pueden quedar; son nombres de usuarios de prueba)
- [x] T006 [US1] Ajustar a «Gestor de Talento» solo los comentarios de `backend/app/Modules/Compensaciones/Application/Services/PoliticaDeAcceso.php` (docblock de clase y de método); no se tocan comentarios de otros archivos ni identificadores (`esAdmin`, `role === 'admin'`, middleware `admin`). Se hace junto con T008.

**Checkpoint**: `npx vitest run src/modules/compensaciones` pasa y los textos visibles ya no mencionan el nombre anterior.

---

## Phase 4: User Story 2 - Los permisos del Gestor se conceden al rol Administrador existente (Priority: P1)

**Goal**: `PoliticaDeAcceso` responde `puede($usuario, 'categorias.gestionar')` verdadero cuando el token tiene `admin`, sin crear ni renombrar roles ni la capacidad `admin`.

**Independent Test**: `PoliticaDeAccesoTest` pasa: verdadero con `admin`, falso con un token sin `admin`; no existe ningún rol nuevo y `admin` no cambió de nombre.

- [x] T007 [US2] Escribir `backend/tests/Feature/Compensaciones/PoliticaDeAccesoTest.php` (Pest): `puede($usuarioAdmin, 'categorias.gestionar')` es verdadero y con un usuario sin `admin` es falso (crear el usuario con `Sanctum::actingAs(UserModel::factory()->admin()->create(), ['admin'])` y, para el caso sin `admin`, `Sanctum::actingAs(UserModel::factory()->create(), ['user'])`; pasar `auth()->user()` a `puede()`); debe fallar antes de T008
- [x] T008 [US2] En `backend/app/Modules/Compensaciones/Application/Services/PoliticaDeAcceso.php` agregar `public const CATEGORIAS_GESTIONAR = 'categorias.gestionar'` y `puede(object $usuario, string $permiso): bool` que resuelve `categorias.gestionar` con `tokenCan('admin')` (contrato en `contracts/politica-de-acceso.md`); actualizar el docblock de la clase para nombrar al «Gestor de Talento»
- [x] T009 [P] [US2] Reemplazar `puedeGestionar($request->user())` por `puede($request->user(), PoliticaDeAcceso::CATEGORIAS_GESTIONAR)` en `backend/app/Modules/Compensaciones/Presentation/Controllers/CategoriaController.php` (línea 37)
- [x] T010 [P] [US2] Igual cambio en `backend/app/Modules/Compensaciones/Presentation/Controllers/CompetenciasController.php` (línea 25)
- [x] T011 [P] [US2] Igual cambio en `backend/app/Modules/Compensaciones/Presentation/Resources/CategoriaResource.php` (línea 59, auditoría solo para quien gestiona)
- [x] T012 [US2] Retirar `puedeGestionar()` de `PoliticaDeAcceso.php` una vez que no queden usos (`grep -rn "puedeGestionar" backend/app backend/tests` devuelve 0) y retirar también `puedeVer()` (nadie lo usa; ver categorías no lleva permiso)

**Checkpoint**: `./vendor/bin/pest tests/Feature/Compensaciones` pasa completo, incluida `PoliticaDeAccesoTest`.

---

## Phase 5: User Story 3 - Permisos con formato recurso.acción (Priority: P2)

**Goal**: El único permiso del módulo es `categorias.gestionar` (forma recurso.acción); un permiso desconocido se deniega y ver categorías no requiere permiso.

**Independent Test**: Las pruebas confirman el nombre de la constante, la denegación de permisos desconocidos y que un Usuario sin `admin` sigue listando y consultando categorías.

- [x] T013 [US3] Ampliar `backend/tests/Feature/Compensaciones/PoliticaDeAccesoTest.php`: la constante `CATEGORIAS_GESTIONAR` vale `'categorias.gestionar'` y cumple el patrón `^[a-z]+\.[a-z]+$`; `puede($usuarioAdmin, 'otro.permiso')` es falso incluso con `admin` (denegar por defecto)
- [x] T014 [US3] Confirmar con `./vendor/bin/pest --filter="auditoría|created_by" tests/Feature/Compensaciones` que ya hay cobertura del Usuario sin `admin`: `ListarCategoriasTest.php` (no recibe `created_by`) y `ObtenerCategoriaTest.php` («incluye la auditoría solo para el Administrador»). No se escribe prueba nueva.
- [x] T015 [US3] Documentar en el docblock de `PoliticaDeAcceso.php` que `parametros.gestionar` llega en 01b.1 y que las specs siguientes agregan sus propias constantes recurso.acción

**Checkpoint**: Las pruebas de política de acceso pasan y no hay permisos con otro formato en `Compensaciones`.

---

## Phase 6: User Story 4 - Las pruebas existentes de inc-01 siguen pasando (Priority: P1)

**Goal**: Nada de inc-01 se rompe.

**Independent Test**: Suites completas de backend y frontend en verde.

- [ ] T016 [US4] Ejecutar la suite completa del backend: `cd backend && ./vendor/bin/pest` y `./vendor/bin/pint --test`; corregir solo regresiones atribuibles a este cambio
- [ ] T017 [US4] Ejecutar frontend: `cd frontend && npm run lint && npx tsc --noEmit && npx vitest run && npm run build`
- [ ] T018 [US4] Ejecutar el E2E `cd frontend && npx playwright test e2e/categorias-reconocimiento.spec.ts` (flujo del Administrador y del Usuario sin cambios)

---

## Phase 7: Polish & Cross-Cutting Concerns

- [x] T019 Verificación final de la spec: `grep -rn "Administrador de Talento Humano\|Talento Humano" frontend/src/modules/compensaciones backend/app/Modules/Compensaciones design/CategoriasReconocimiento.dc.html` devuelve 0 resultados, y `git diff` no toca otros módulos (posiciones, fichas, categorías documentales), roles ni el nombre `admin`
- [ ] T020 Recorrer `quickstart.md` de principio a fin y marcar cada paso como cumplido

---

## Dependencies & Execution Order

- **Phase 1** primero (línea base).
- **US1** (frontend/diseño) es independiente de **US2/US3** (backend); pueden hacerse en paralelo.
- **US3** depende de **US2** (comparten `PoliticaDeAcceso.php` y `PoliticaDeAccesoTest.php`; no en paralelo).
- **US4** va al final, después de US1–US3.
- Dentro de US1: T004 (maqueta) primero; luego T002 → T003; T005 en paralelo; T006 junto con T008.
- Dentro de US2: T007 → T008 → T009/T010/T011 [P] → T012.

### Parallel Example

```text
Pista frontend:  T004 → T002 → T003, con T005 en paralelo
Pista backend:   T007 → T008 → (T009, T010, T011) → T012 → T013 → T014 → T015
Luego:           T016, T017, T018 → T019 → T020
```

## Implementation Strategy

**MVP**: US1 (el cambio visible de nombre) más US2 (punto de decisión `puede()`), ambas P1 y sobre archivos distintos. Después US3 (cierre de convención) y US4 (regresión) antes de dar la feature por completa.
