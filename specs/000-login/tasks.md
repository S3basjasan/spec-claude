---

description: "Tareas de la feature 000-login (autenticación mínima)"
---

# Tasks: Autenticación mínima (login)

**Input**: Documentos de diseño en `/specs/000-login/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/auth-api.md

**Tests**: Incluidos. La constitución exige Pest, Vitest + Testing Library y un E2E real del flujo principal.

**Organization**: Tareas agrupadas por historia de usuario para implementarlas y probarlas de forma independiente.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Se puede ejecutar en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: Historia de usuario a la que pertenece (US1–US4)
- Rutas relativas a la raíz del repositorio

## Path Conventions

- Backend: `backend/app/Modules/Shared/…` (carpeta real; capas Domain, Application, Infrastructure, Presentation)
- Frontend: `frontend/src/shared/…` y `frontend/src/modules/…`
- Pruebas backend: `backend/tests/{Unit,Feature}/Shared/Auth/`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Decisiones de dependencias y herramientas de prueba

- [X] T001 Obtener aprobación explícita de las dependencias pendientes (`@tabler/icons-react`, herramienta E2E —se propone Playwright— y `jsdom` para Vitest) y registrar la decisión en `specs/000-login/research.md` (D-07, D-08); sin aprobación, usar SVG propios en `frontend/src/shared/ui/icons.tsx` — Aprobados Playwright y `jsdom`; `@tabler/icons-react` NO aprobado (iconos SVG propios en `frontend/src/shared/ui/icons.tsx`).
- [X] T002 [P] Instalar y configurar Vitest + Testing Library (preaprobados) en `frontend/package.json` y `frontend/vite.config.ts`, con script `test` y archivo `frontend/src/test/setup.ts` — Vitest y Testing Library instalados; pruebas de componentes (`*.dom.test.tsx`) con `jsdom` por archivo.
- [X] T003 [P] Habilitar `RefreshDatabase` para los tests Feature en `backend/tests/Pest.php`
- [X] T004 [P] Configurar la herramienta E2E aprobada en `frontend/` (config y script `e2e`), solo tras T001 — Playwright con el Edge instalado (`channel: 'msedge'`), backend con `backend/.env.e2e` y `database/e2e.sqlite`.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Modelo de usuario con rol, módulo `Shared` operativo y base del frontend. Bloquea todas las historias.

**⚠️ CRITICAL**: Ninguna historia puede empezar hasta completar esta fase

### Backend

- [X] T005 Crear migración `backend/database/migrations/<fecha>_add_role_to_users_table.php` que añade `role` (string, obligatorio, valores `admin`|`user`, por defecto `user`) a `users`
- [X] T006 [P] Crear value object `Rol` (`admin`, `user`) en `backend/app/Modules/Shared/Domain/ValueObjects/Rol.php`
- [X] T007 [P] Crear entidad `Usuario` (id, name, email, role; sin password expuesto) en `backend/app/Modules/Shared/Domain/Entities/Usuario.php`
- [X] T008 [P] Crear contrato `UsuarioRepositoryInterface` (`buscarPorCorreo`, `obtenerPorId`) en `backend/app/Modules/Shared/Domain/Repositories/UsuarioRepositoryInterface.php`
- [X] T009 [P] Crear excepción `CredencialesInvalidas` en `backend/app/Modules/Shared/Domain/Exceptions/CredencialesInvalidas.php`
- [X] T010 [P] Crear puertos `HasherInterface` y `EmisorTokenInterface` en `backend/app/Modules/Shared/Application/Ports/`
- [X] T011 [P] Crear DTO `CredencialesDTO`, `UsuarioDTO` y `SesionDTO` en `backend/app/Modules/Shared/Application/DTO/`
- [X] T012 Crear `UserModel` Eloquent con `HasApiTokens`, tabla `users` y `role` en `backend/app/Modules/Shared/Infrastructure/Persistence/Eloquent/Models/UserModel.php`, y apuntar `AUTH_MODEL` a él en `backend/config/auth.php`
- [X] T013 [P] Implementar `EloquentUsuarioRepository` en `backend/app/Modules/Shared/Infrastructure/Persistence/Eloquent/Repositories/EloquentUsuarioRepository.php` (depende de T008, T012)
- [X] T014 [P] Implementar `LaravelHasher` y `SanctumEmisorToken` (token sin expiración; capacidad `admin` si el rol es admin, `user` si no) en `backend/app/Modules/Shared/Infrastructure/Security/`
- [X] T015 Ampliar `SharedServiceProvider` con los bindings de puertos y repositorio en `backend/app/Modules/Shared/Infrastructure/Providers/SharedServiceProvider.php` y registrarlo en `backend/bootstrap/providers.php`
- [X] T016 Ajustar `backend/database/factories/UserFactory.php` a `UserModel` y `role`, y crear `backend/database/seeders/UsuariosInicialesSeeder.php` idempotente: Administrador `admin@hrms.test` (rol `admin`) y Usuario `usuario@hrms.test` (rol `user`), correos en minúsculas, contraseña de desarrollo tomada de la variable `SEED_PASSWORD` definida en `backend/.env.example`, sin datos reales; invocarlo desde `backend/database/seeders/DatabaseSeeder.php`
- [X] T017 [P] Prueba Pest del seeder (dos usuarios, roles correctos, idempotente, contraseña con hash) en `backend/tests/Feature/Shared/Auth/UsuariosInicialesSeederTest.php`

### Identidad actual (compartida por US2 y US3)

- [X] T045 Implementar caso de uso `ObtenerUsuarioActual` en `backend/app/Modules/Shared/Application/UseCases/ObtenerUsuarioActual.php`
- [X] T046 Añadir `AuthController@me` y la ruta `GET /api/auth/me` con `auth:sanctum` en `backend/app/Modules/Shared/Presentation/Controllers/AuthController.php` y `backend/app/Modules/Shared/Routes/api.php`
- [X] T039 [P] Crear hook `useCurrentUser` (consulta `GET /api/auth/me`, desactivada sin token, sin reintentos en 401) en `frontend/src/modules/auth/login/hooks/useCurrentUser.ts` (consume `GET /api/auth/me`)

### Frontend

- [X] T018 [P] Definir tokens de `design/DESIGN.md` §2 como variables CSS (claro/oscuro, incluido `--input-border`) y fuente Public Sans en `frontend/src/index.css`
- [X] T019 [P] Crear `tokenStorage` (leer, guardar y borrar token en `localStorage` con try/catch) en `frontend/src/shared/api/tokenStorage.ts`
- [X] T020 Crear cliente Axios compartido que adjunta `Authorization: Bearer`, normaliza errores y, ante 401 en rutas protegidas, limpia el token y emite un evento de sesión expirada, en `frontend/src/shared/api/httpClient.ts` (depende de T019); sin reintentos automáticos de escrituras
- [X] T021 [P] Crear `QueryClient` (sin reintentos en mutaciones) en `frontend/src/shared/lib/queryClient.ts` y tema claro/oscuro persistido en `frontend/src/shared/lib/theme.ts`
- [X] T022 [P] Crear componentes base `Button`, `FormField`/`Input`, `Alert`, `Skeleton`, `ErrorState` según `design/DESIGN.md` §4 en `frontend/src/shared/ui/`
- [X] T023 [P] Pruebas Vitest de `tokenStorage` y `httpClient` (adjunta Bearer, 401 limpia token) en `frontend/src/shared/api/httpClient.test.ts`

**Nota**: T039, T045 y T046 se movieron aquí desde US2/US3 y conservan su ID.

**Checkpoint**: Backend con usuarios sembrados e identidad actual, y frontend con base compartida; las historias pueden comenzar

---

## Phase 3: User Story 1 - Iniciar sesión (Priority: P1) 🎯 MVP

**Goal**: Una persona con cuenta precargada inicia sesión con correo y contraseña y accede con su rol.

**Independent Test**: Con credenciales válidas del administrador y del usuario de prueba se llega a la pantalla principal; con inválidas, vacías o mal formadas no se accede y se muestran los errores definidos.

### Tests for User Story 1

> Escribirlas primero y comprobar que fallan

- [X] T024 [P] [US1] Pest Feature de `POST /api/auth/login`: 200 con token, usuario y rol (admin y user); 401 idéntico para contraseña errónea y correo inexistente; 422 con errores por campo; correo con mayúsculas/espacios acepta; token de admin con capacidad `admin` en `backend/tests/Feature/Shared/Auth/LoginTest.php`
- [X] T025 [P] [US1] Pest Unit del caso de uso `IniciarSesion` con dobles de repositorio, hasher y emisor en `backend/tests/Unit/Shared/Auth/IniciarSesionTest.php`
- [X] T026 [P] [US1] Vitest de `LoginPage` (errores por campo, valores conservados, botón bloqueado durante el envío, alerta en 401, ErrorState con "Reintentar" ante fallo de red) en `frontend/src/modules/auth/login/pages/LoginPage.test.tsx`

### Implementation for User Story 1

- [X] T027 [US1] Implementar caso de uso `IniciarSesion` (normaliza correo; compara hash también si el correo no existe; lanza `CredencialesInvalidas`) en `backend/app/Modules/Shared/Application/UseCases/IniciarSesion.php`
- [X] T028 [P] [US1] Crear `LoginRequest` (`email` obligatorio y con formato de correo, recortado y en minúsculas; `password` obligatorio; mensajes en español) en `backend/app/Modules/Shared/Presentation/Requests/LoginRequest.php`
- [X] T029 [P] [US1] Crear `SesionResource` y `UsuarioResource` (id, name, email, role; nunca password) en `backend/app/Modules/Shared/Presentation/Resources/`
- [X] T030 [US1] Crear `AuthController@login` delgado (401 `{"message":"Correo o contraseña incorrectos."}`) en `backend/app/Modules/Shared/Presentation/Controllers/AuthController.php` y la ruta `POST /api/auth/login` pública en `backend/app/Modules/Shared/Routes/api.php`
- [X] T031 [P] [US1] Crear textos en español centralizados en `frontend/src/modules/auth/login/text.ts` (sin "¿Olvidaste tu contraseña?" ni "Mantener la sesión iniciada")
- [X] T032 [P] [US1] Crear esquema Zod (correo obligatorio con formato válido y recortado; contraseña obligatoria) en `frontend/src/modules/auth/login/validation.ts`
- [X] T033 [US1] Crear `api.ts` (`login`) y hook `useLogin` (TanStack Query, sin reintentos; guarda token y siembra `["auth","me"]`; mapea 422 a campos, 401 a alerta y errores de red a `ErrorState` con "Reintentar") en `frontend/src/modules/auth/login/api.ts` y `hooks/useLogin.ts`
- [X] T034 [US1] Crear `LoginPage` según `design/Login.dc.html` (tarjeta de 420 px, React Hook Form + Zod, `aria-invalid`/`aria-describedby`, `role="alert"`, foco visible, `ErrorState` ante fallo de red, 320–1440 px) en `frontend/src/modules/auth/login/pages/LoginPage.tsx`
- [X] T035 [US1] Crear `InicioPage` vacía en `frontend/src/modules/inicio/pages/InicioPage.tsx` y configurar router y providers en `frontend/src/App.tsx` y `frontend/src/main.tsx` (rutas `/login` y `/`; tras login navega a la ruta de origen o `/`)

**Checkpoint**: US1 funcional: se puede iniciar sesión como admin y usuario y llegar a la principal

---

## Phase 4: User Story 2 - Acceso restringido sin sesión (Priority: P1)

**Goal**: Sin sesión no se accede a ninguna pantalla ni operación, salvo el inicio de sesión.

**Independent Test**: Sin sesión, abrir `/` redirige a `/login` y una operación protegida responde 401; con sesión en `/login` se redirige a `/`.

### Tests for User Story 2

- [X] T036 [P] [US2] Pest Feature del middleware `EnsureAdmin` y de rutas protegidas: sin token 401, token de user en ruta admin 403, token de admin permitido, con una ruta de prueba registrada solo en el test, en `backend/tests/Feature/Shared/Auth/AccesoProtegidoTest.php`
- [X] T037 [P] [US2] Vitest de `RequireAuth` y `RedirectIfAuthenticated` (redirecciones, estado de carga, sesión expirada) en `frontend/src/modules/auth/login/components/guards.test.tsx`

### Implementation for User Story 2

- [X] T038 [US2] Crear middleware `EnsureAdmin` (autenticación Sanctum + capacidad `admin`, 403 en español si falta) en `backend/app/Modules/Shared/Presentation/Middleware/EnsureAdmin.php` y registrar su alias en `backend/bootstrap/app.php`
- [X] T040 [US2] Crear `RequireAuth` (Skeleton mientras carga, redirige a `/login` conservando origen, ErrorState con "Reintentar" ante fallo de red) y `RedirectIfAuthenticated` en `frontend/src/modules/auth/login/components/`
- [X] T041 [US2] Aplicar las guardas a las rutas y escuchar el evento de sesión expirada del cliente HTTP para volver a `/login` en `frontend/src/App.tsx`

**Checkpoint**: US1 + US2: la app solo es accesible con sesión

---

## Phase 5: User Story 3 - Conocer identidad y rol en cada petición (Priority: P2)

**Goal**: El sistema sabe quién es la persona y su rol en cada petición y puede consultarlo.

**Independent Test**: Con token de admin `GET /api/auth/me` devuelve su identidad y rol `admin`; con el de usuario, rol `user`; sin token, 401.

### Tests for User Story 3

- [X] T042 [P] [US3] Pest Feature de `GET /api/auth/me` (endpoint implementado en Foundational, T046) (200 admin, 200 user, 401 sin token y con token inválido, sin password en la respuesta) en `backend/tests/Feature/Shared/Auth/MeTest.php`
- [X] T043 [P] [US3] Pest Unit del caso de uso `ObtenerUsuarioActual` (T045) en `backend/tests/Unit/Shared/Auth/ObtenerUsuarioActualTest.php`
- [X] T044 [P] [US3] Vitest del `AppShell` presentacional (solo props, sin hooks): muestra nombre y rol recibidos, cambio de tema y botón "Cerrar sesión" en `frontend/src/shared/layouts/AppShell.test.tsx`

### Implementation for User Story 3

- [X] T047 [US3] Crear `AppShell` presentacional según `design/DESIGN.md` §3 y `Main.dc.html` (props `user`, `onLogout`, `isLoggingOut`, `navItems`; header con marca, cambio de tema y menú de usuario con nombre y rol; menú lateral declarativo con "Inicio"; `Outlet`; footer; sin "Mi perfil" ni "Ajustes"; sin importar ningún módulo) en `frontend/src/shared/layouts/AppShell.tsx`; crear `AuthenticatedLayout` en `frontend/src/modules/auth/login/components/AuthenticatedLayout.tsx` y usarlo en las rutas autenticadas de `frontend/src/App.tsx`
- [X] T048 [US3] Pasar nombre y rol (Administrador/Usuario) desde `useCurrentUser` a `AppShell` en `AuthenticatedLayout`, con textos en español centralizados en `frontend/src/shared/layouts/text.ts`

**Checkpoint**: US1–US3: identidad y rol visibles y verificables en cada petición

---

## Phase 6: User Story 4 - Cerrar sesión (Priority: P2)

**Goal**: La persona cierra su sesión y pierde el acceso hasta volver a iniciarla.

**Independent Test**: Iniciar sesión, cerrar sesión, comprobar que el token deja de valer (401 en `me`) y que las pantallas protegidas ya no se muestran.

### Tests for User Story 4

- [X] T049 [P] [US4] Pest Feature de `POST /api/auth/logout` (204, revoca solo el token actual, otras sesiones del mismo usuario siguen válidas, reutilizar el token da 401, sin token 401) en `backend/tests/Feature/Shared/Auth/LogoutTest.php`
- [X] T050 [P] [US4] Pest Unit del caso de uso `CerrarSesion` en `backend/tests/Unit/Shared/Auth/CerrarSesionTest.php`
- [X] T051 [P] [US4] Vitest de `useLogout` y del botón "Cerrar sesión" (limpia token y caché incluso si la llamada falla; navega a `/login`) en `frontend/src/modules/auth/login/hooks/useLogout.test.tsx`

### Implementation for User Story 4

- [X] T052 [US4] Implementar caso de uso `CerrarSesion` (revoca el token actual) en `backend/app/Modules/Shared/Application/UseCases/CerrarSesion.php`
- [X] T053 [US4] Añadir `AuthController@logout` (204) y la ruta `POST /api/auth/logout` con `auth:sanctum` en `backend/app/Modules/Shared/Presentation/Controllers/AuthController.php` y `backend/app/Modules/Shared/Routes/api.php`
- [X] T054 [US4] Crear `logout` en `frontend/src/modules/auth/login/api.ts` y el hook `useLogout` (mutación sin reintentos; en éxito o fallo borra token y caché y navega a `/login`) en `frontend/src/modules/auth/login/hooks/useLogout.ts`
- [X] T055 [US4] Conectar `onLogout` e `isLoggingOut` de `useLogout` en `AuthenticatedLayout` (el botón "Cerrar sesión", en `--danger` y con estado de cierre en curso, vive en `AppShell`) e impedir que el botón Atrás muestre datos protegidos tras cerrar sesión

**Checkpoint**: Las cuatro historias funcionan de forma independiente

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: E2E, calidad y cierre

- [X] T056 E2E del flujo principal login → principal → recarga con sesión persistente → logout → acceso denegado (incluido el botón Atrás sin datos protegidos), con administrador y con usuario, en `frontend/e2e/login.spec.ts` (requiere T001 y T004)
- [X] T057 [P] Verificar accesibilidad (WCAG 2.2 AA: foco visible, etiquetas, contraste en ambos temas) y ausencia de scroll horizontal entre 320 y 1440 px en `LoginPage` y `AppShell` — Contraste verificado por cálculo en ambos temas (todos los pares ≥ 4.5:1 y bordes de input ≥ 3:1); E2E: foco visible, tema oscuro persistente y sin scroll horizontal a 320 y 1440 px.
- [X] T058 [P] Revisar seguridad: respuestas y logs sin password ni hash, mensaje 401 idéntico, credenciales de prueba solo en configuración de desarrollo (`backend/database/seeders/UsuariosInicialesSeeder.php`, `backend/.env.example`)
- [X] T059 Ejecutar puertas de calidad del backend: `vendor/bin/pint --test` y `vendor/bin/pest` en `backend/` (análisis estático: no aplica, no está instalado)
- [X] T060 Ejecutar puertas de calidad del frontend: `npm run lint`, `npx tsc -b`, `npx vitest run` y `npm run build` en `frontend/`
- [X] T061 Ejecutar la validación de `specs/000-login/quickstart.md` de punta a punta — API ejecutada contra el servidor real (login admin/usuario, `me`, 401, 422, logout y reuso); interfaz cubierta por el E2E. El 403 de rutas de admin se verifica en Pest.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sin dependencias; T004 depende de T001, que bloquea el cierre de la feature (E2E obligatorio)
- **Foundational (Phase 2)**: depende de Setup (T002 y T003 para sus pruebas); bloquea todas las historias
- **US1 (Phase 3)**: tras Foundational
- **US2 (Phase 4)**: tras Foundational (incluye `me` y `useCurrentUser`); sus guardas se prueban de forma completa con US1 (login)
- **US3 (Phase 5)**: tras Foundational; `AppShell` presentacional, integrado en `AuthenticatedLayout`
- **US4 (Phase 6)**: tras Foundational; el botón vive en el `AppShell` de US3 (T055 depende de T047)
- **Polish (Phase 7)**: tras las historias deseadas; T056 depende de US1–US4

### User Story Dependencies

- **US1 (P1)**: sin dependencias de otras historias
- **US2 (P1)**: independiente tras Foundational; la verificación de punta a punta usa el login de US1
- **US3 (P2)**: independiente en backend; `AppShell` independiente de US4
- **US4 (P2)**: backend independiente; el botón de UI depende de `AppShell` (T047)

### Within Each User Story

- Las pruebas se escriben primero y deben fallar
- Dominio/aplicación → infraestructura → presentación; en frontend: texto/validación → api/hooks → páginas

### Parallel Opportunities

- Phase 1: T002, T003 y T004 en paralelo
- Phase 2: T006–T011 en paralelo; T013 y T014 en paralelo tras T012; T018–T023 en paralelo con el backend
- Cada historia: todas sus pruebas [P] a la vez; `LoginRequest`/Resources y textos/validación en paralelo
- Backend y frontend de una misma historia pueden avanzar en paralelo tras Foundational

---

## Parallel Example: User Story 1

```bash
# Pruebas de US1 en paralelo:
Task: "Pest Feature de POST /api/auth/login en backend/tests/Feature/Shared/Auth/LoginTest.php"
Task: "Pest Unit de IniciarSesion en backend/tests/Unit/Shared/Auth/IniciarSesionTest.php"
Task: "Vitest de LoginPage en frontend/src/modules/auth/login/pages/LoginPage.test.tsx"

# Piezas independientes de US1:
Task: "LoginRequest en backend/app/Modules/Shared/Presentation/Requests/LoginRequest.php"
Task: "Resources en backend/app/Modules/Shared/Presentation/Resources/"
Task: "text.ts y validation.ts en frontend/src/modules/auth/login/"
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Completar Phase 1 y Phase 2
2. Completar US1 (Phase 3)
3. **Detenerse y validar**: iniciar sesión como admin y usuario
4. Añadir US2 para que el sistema quede realmente protegido (también P1)

### Incremental Delivery

1. Setup + Foundational → base lista
2. US1 → validar; US2 → validar que nada es accesible sin sesión (MVP útil para los demás módulos)
3. US3 → identidad y rol visibles; US4 → ciclo de sesión completo
4. Polish: E2E, accesibilidad y puertas de calidad

---

## Notes

- [P] = archivos distintos, sin dependencias
- La etiqueta [Story] mapea cada tarea a su historia
- T001 bloquea el E2E y los iconos de Tabler; no instalar dependencias no aprobadas
- Confirmar que las pruebas fallan antes de implementar
- Hacer commit tras cada tarea o grupo lógico
