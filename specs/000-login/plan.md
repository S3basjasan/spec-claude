# Implementation Plan: Autenticación mínima (login)

**Branch**: `000-login` | **Date**: 2026-10-09 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/000-login/spec.md`

## Summary

Autenticación temporal por correo y contraseña con dos roles (Administrador y Usuario) y datos
iniciales sembrados. El backend expone tres operaciones (`login`, `logout`, `me`) y emite un token
Bearer de Sanctum sin expiración que identifica a la persona y su rol (capacidad `admin` para el
Administrador). El frontend agrega la pantalla de inicio de sesión, un App Shell autenticado con
pantalla principal vacía, guardas de ruta de solo presentación y un cliente HTTP compartido que
gestiona el token. La sesión persiste hasta cerrar sesión explícitamente (FR-008).

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 13) en backend; TypeScript 6 estricto + React 19 en frontend

**Primary Dependencies**: Laravel Sanctum 4 (ya instalado); React Router 7, TanStack Query 5, Axios,
React Hook Form, Zod, Tailwind CSS 4 (ya instalados). Ninguna dependencia nueva preaprobada.
Pendientes de aprobación: ver Constitution Check.

**Storage**: SQLite. Tablas `users` (se añade columna `role`) y `personal_access_tokens` (ya existe)

**Testing**: Pest (backend); Vitest + Testing Library (frontend, preaprobados pero aún no
instalados); E2E real del flujo principal (herramienta pendiente de aprobación)

**Target Platform**: Navegadores modernos de escritorio y móvil (320–1440 px)

**Project Type**: Aplicación web (API Laravel + SPA React)

**Performance Goals**: Inicio de sesión completado en menos de 30 s por la persona (SC-001);
respuesta de `login`/`me`/`logout` imperceptible en desarrollo local

**Constraints**: Sin registro, recuperación/cambio de contraseña, gestión de usuarios, bloqueo por
intentos ni expiración por inactividad; token Bearer manejado solo en la capa HTTP compartida;
errores sin revelar si el correo existe; interfaz en español, WCAG 2.2 AA

**Scale/Scope**: 2 usuarios sembrados, 3 endpoints, 2 pantallas (login y principal vacía con
App Shell)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Evaluación |
|---|---|
| I. Backend modular / Clean Architecture | PASA. La capacidad vive en `backend/app/Modules/Shared/` (carpeta real; el insumo decía `shared`) con Dominio, Aplicación, Infraestructura y Presentación. Controladores delgados; casos de uso `IniciarSesion`, `CerrarSesion`, `ObtenerUsuarioActual`; repositorio por contrato. |
| I. Rutas bajo `/api/<modulo>/` | DESVIACIÓN JUSTIFICADA. Los endpoints son `/api/auth/*` por indicación explícita del insumo, aunque el código está en `Shared`. Ver Complexity Tracking. |
| I. Admin = Sanctum + capacidad `admin` | PASA. El token del Administrador se emite con la capacidad `admin`; se ofrece middleware reutilizable para los demás módulos. |
| I. Dominio/Aplicación sin Laravel/Eloquent | PASA. El modelo Eloquent y el hash viven en Infraestructura detrás de puertos. |
| II. Frontend modular | PASA. El código de autenticación va en `frontend/src/shared` (api, layouts, lib) por el insumo; el módulo de pantallas de login en `frontend/src/modules/auth/login/`. `shared` no importa módulos de negocio. |
| II. Axios solo en cliente compartido, Bearer solo en capa HTTP | PASA. |
| II. `shared` sin dependencias de módulos | PASA. `AppShell` es presentacional y recibe usuario y acciones por props; los hooks se conectan en `AuthenticatedLayout`, dentro del módulo. |
| II. App Shell único, estados loading/empty/error/success, formularios accesibles | PASA. |
| III. `design/` como fuente | PASA con ajustes (registrados en DESIGN.md §9). Se aplican `Login.dc.html` y `Main.dc.html` omitiendo "¿Olvidaste tu contraseña?" y "Mantener la sesión iniciada" (DESIGN.md §9) las entradas "Mi perfil"/"Ajustes" del menú de usuario y la restricción de "solo tema claro" (el login admite tema claro y oscuro); se conserva el texto "¿No tienes cuenta? Solicítala a tu administrador". Se usa `--input-border`. |
| IV. Dependencias | BLOQUEANTE, PENDIENTE DE APROBACIÓN: sin herramienta E2E aprobada no se cierra la feature; decidir antes de `/speckit-implement`. (a) `@tabler/icons-react` que pide DESIGN.md §6; (b) herramienta E2E; (c) `jsdom` como entorno de Vitest. No se instalan sin aprobación. Alternativa sin aprobar: iconos SVG propios en `shared/ui`. |
| IV. Pruebas, seguridad | PASA. Pest, Vitest y E2E por feature; contraseñas con hash; sin secretos reales en seeders (credenciales de prueba solo para desarrollo). |

**Re-evaluación tras Fase 1**: sin cambios; solo queda abierta la aprobación de dependencias.

## Project Structure

### Documentation (this feature)

```text
specs/000-login/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── auth-api.md
└── tasks.md             # Lo genera /speckit-tasks
```

### Source Code (repository root)

```text
backend/
├── app/Modules/Shared/
│   ├── Domain/
│   │   ├── Entities/Usuario.php
│   │   ├── ValueObjects/Rol.php
│   │   ├── Repositories/UsuarioRepositoryInterface.php
│   │   └── Exceptions/CredencialesInvalidas.php
│   ├── Application/
│   │   ├── UseCases/{IniciarSesion,CerrarSesion,ObtenerUsuarioActual}.php
│   │   ├── DTO/{CredencialesDTO,SesionDTO,UsuarioDTO}.php
│   │   └── Ports/{HasherInterface,EmisorTokenInterface}.php
│   ├── Infrastructure/
│   │   ├── Persistence/Eloquent/{Models/UserModel.php,Repositories/EloquentUsuarioRepository.php}
│   │   ├── Security/{LaravelHasher.php,SanctumEmisorToken.php}
│   │   └── Providers/SharedServiceProvider.php   (existente: se amplía)
│   ├── Presentation/
│   │   ├── Controllers/AuthController.php
│   │   ├── Requests/LoginRequest.php
│   │   ├── Resources/{SesionResource,UsuarioResource}.php
│   │   └── Middleware/EnsureAdmin.php
│   └── Routes/api.php                             (existente: se amplía con /api/auth)
├── config/auth.php                                (el modelo de usuario apunta a UserModel)
├── bootstrap/providers.php                        (registrar SharedServiceProvider)
├── database/
│   ├── migrations/<fecha>_add_role_to_users_table.php
│   ├── factories/UserFactory.php                  (ajustar a UserModel y rol)
│   └── seeders/{DatabaseSeeder,UsuariosIniciales}Seeder.php
└── tests/{Unit,Feature}/Shared/Auth/…

frontend/src/
├── shared/
│   ├── api/{httpClient.ts,tokenStorage.ts}
│   ├── lib/{queryClient.ts,theme.ts}
│   ├── layouts/{AppShell.tsx,text.ts}
│   └── ui/{Button,FormField,Alert,Skeleton,ErrorState}.tsx
├── modules/auth/login/
│   ├── api.ts
│   ├── hooks/{useLogin,useLogout,useCurrentUser}.ts
│   ├── pages/LoginPage.tsx
│   ├── components/{RequireAuth,RedirectIfAuthenticated,AuthenticatedLayout}.tsx
│   ├── validation.ts
│   └── text.ts
├── modules/inicio/pages/InicioPage.tsx           (pantalla principal vacía)
├── App.tsx, main.tsx                              (router + providers)
└── index.css                                       (tokens de design/ como variables CSS)
```

**Structure Decision**: Aplicación web con backend y frontend separados. El backend reutiliza el
módulo `Shared` ya existente para la capacidad de autenticación y el frontend reparte el código
entre `shared/` (infraestructura reutilizable) y `modules/auth/login/` (pantalla), siguiendo la
constitución. Ver research.md D-01 para la ubicación de módulos.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Rutas `/api/auth/*` en un módulo llamado `Shared` (la constitución pide `/api/<modulo>/`) | El insumo fija `/api/auth/*` y la autenticación es transversal, no un módulo de negocio | Usar `/api/shared/*` contradice el contrato indicado y expone un nombre interno poco claro al cliente |

## Diseño de frontend

### Rutas

| Ruta | Pantalla (del spec) | Roles con acceso |
|---|---|---|
| `/login` | Inicio de sesión | Solo sin sesión (con sesión redirige a `/`) |
| `/` | Pantalla principal vacía dentro del App Shell (incluye cierre de sesión) | Administrador y Usuario |
| `*` | Sin sesión → `/login`; con sesión → `/` | — |

### Componentes

- **Nuevos**: `LoginPage`, `RequireAuth`, `RedirectIfAuthenticated`, `InicioPage` y
  `AuthenticatedLayout` (en `modules/auth/login/components`; conecta `useCurrentUser` y `useLogout`
  con el App Shell). `AppShell` (en `shared/layouts`, presentacional y sin importar módulos)
  recibe `user: {name, role}`, `onLogout`, `isLoggingOut` y `navItems` por props; renderiza header
  con marca, tema y menú de usuario (nombre, rol y "Cerrar sesión"), menú lateral declarativo
  (solo "Inicio"), `Outlet` y footer.
- **Del design system** (`design/DESIGN.md` §4): Button, FormField, Input, Alert, Skeleton,
  ErrorState, IconButton. Se crean en `shared/ui` porque aún no existen.

### Datos y estado

- Login: `POST /api/auth/login` (mutación sin reintentos); guarda el token en `tokenStorage` y
  siembra la caché `["auth","me"]`.
- Usuario actual: `GET /api/auth/me` (consulta); fuente de verdad de la sesión al cargar la app.
  Un 401 limpia token y caché y redirige a `/login`.
- Logout: `POST /api/auth/logout` (mutación sin reintentos); siempre limpia token y caché
  localmente, incluso si la llamada falla, y navega a `/login`.
- Estado en URL: no aplica (sin filtros ni paginación). Se conserva la ruta de origen en el estado
  de navegación para volver tras iniciar sesión.

### Validación

- Correo: obligatorio, formato válido, sin espacios alrededor (se recorta); contraseña: obligatoria.
  Replican `LoginRequest` del backend.
- Mapeo de errores 422 a campos `email` y `password`; 401 a alerta general "Correo o contraseña
  incorrectos. Inténtalo de nuevo."; error de red a `ErrorState` con "Reintentar" en `LoginPage`.
