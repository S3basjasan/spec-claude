# Research: Autenticación mínima (login)

Sin `NEEDS CLARIFICATION` abiertos. Decisiones tomadas a partir del insumo, la constitución y el
estado real del repositorio.

## D-01 Ubicación de la capacidad en el backend

- **Decision**: `backend/app/Modules/Shared/` (carpeta real con mayúscula). Rutas `/api/auth/*`.
- **Rationale**: El insumo indica "Modules/shared" y la autenticación es transversal. El módulo
  `Shared` ya existe con su `ServiceProvider`, `Routes/api.php` y esqueleto; solo falta registrar
  su provider en `bootstrap/providers.php` (hoy solo está `EstructuraServiceProvider`).
- **Alternatives considered**: Crear módulo `Auth` (más fiel a `/api/<modulo>/`, pero añade un módulo
  de negocio no pedido); mantener `/api/shared/auth` (rompe el contrato del insumo).

## D-02 Rol y capacidades

- **Decision**: Columna `role` (`admin` | `user`) en `users`. Al iniciar sesión se emite un token
  Sanctum con capacidad `admin` si el rol es Administrador, o `user` si no. Middleware
  `EnsureAdmin` exige autenticación Sanctum y capacidad `admin`.
- **Rationale**: Cumple "autenticación Sanctum y capacidad admin" de la constitución, sin tablas de
  roles para una solución temporal.
- **Alternatives considered**: Tabla de roles/permisos (excesivo); paquete de permisos
  (dependencia nueva no aprobada).

## D-03 Modelo de usuario

- **Decision**: Crear `UserModel` Eloquent en `Shared/Infrastructure/Persistence/Eloquent/Models/`
  con `HasApiTokens`, y apuntar `config/auth.php` (`AUTH_MODEL`) a él. Actualizar
  `UserFactory`/`DatabaseSeeder`, que hoy referencian `App\Models\User`, clase que no existe.
- **Rationale**: Mantiene Eloquent en infraestructura y deja el dominio puro.
- **Alternatives considered**: Recrear `App\Models\User` (fuera de la estructura modular).

## D-04 Token y persistencia de sesión

- **Decision**: Token Bearer de Sanctum sin expiración (`sanctum.expiration = null`, ya es el
  valor actual). El frontend lo guarda en `localStorage` dentro de `tokenStorage` (solo capa HTTP
  compartida) para que persista al cerrar el navegador (FR-008). Logout revoca el token actual
  (204). Varias sesiones simultáneas = varios tokens por usuario.
- **Rationale**: Es lo que pide el insumo y la clarificación. Riesgo de XSS con `localStorage`
  aceptado por ser una solución temporal de pruebas; se mitiga sin renderizar HTML no confiable.
- **Alternatives considered**: Cookie httpOnly con SPA stateful (más segura, pero el insumo define
  token en la respuesta y exige configuración de dominios); `sessionStorage` (contradice FR-008).

## D-05 Normalización y errores de credenciales

- **Decision**: El correo se recorta y pasa a minúsculas en `LoginRequest` y en el seeder.
  Credenciales inválidas o cuenta inexistente devuelven el mismo 401 con mensaje genérico;
  se ejecuta una comparación de hash también cuando el correo no existe para evitar enumeración
  por tiempo. Campos faltantes o formato inválido devuelven 422 por campo.
- **Rationale**: FR-004, FR-005 y la clarificación sobre el correo.
- **Alternatives considered**: 422 para credenciales inválidas (contradice el insumo, que pide 401).

## D-06 Acceso sin sesión

- **Decision**: Backend: toda ruta de negocio usa `auth:sanctum`; `login` es la única pública. El
  401 de API es JSON (ya configurado en `bootstrap/app.php`). Frontend: `RequireAuth` consulta
  `me` y redirige a `/login`; las guardas son solo de presentación.
- **Rationale**: La constitución da la autoridad al backend (FR-011).

## D-07 Diseño y dependencias

- **Decision**: Implementar Login y App Shell según `design/Login.dc.html`, `Main.dc.html` y
  `DESIGN.md`: tokens como variables CSS en `index.css`, tema claro/oscuro persistido, Public Sans.
  Iconos: DESIGN.md pide Tabler (`@tabler/icons-react`), que no está preaprobada ni instalada.
  Se solicita aprobación; mientras tanto se usan SVG propios en `shared/ui`.
- **Rationale**: Constitución IV (dependencias) y III (seguir `design/`).
- **Alternatives considered**: Instalar Tabler sin aprobar (prohibido).

## D-08 Estrategia de pruebas

- **Decision**: Backend con Pest: Feature para los tres endpoints, middleware admin y seeder;
  Unit para casos de uso con dobles. Frontend con Vitest + Testing Library para `LoginPage`,
  guardas y `httpClient`. E2E real del flujo login → principal → logout. Herramienta E2E no
  definida: requiere aprobación explícita (constitución IV); se propone Playwright.
- **Rationale**: Constitución II y IV exigen E2E por feature.
- **Alternatives considered**: Omitir E2E (viola la constitución).

## D-09 Datos iniciales

- **Decision**: Seeder idempotente con un administrador y un usuario de prueba con correos
  ficticios (`admin@hrms.test`, `usuario@hrms.test`) y contraseña de desarrollo tomada de
  configuración/entorno, sin datos personales reales. La contraseña no se imprime en logs.
- **Rationale**: FR-002, FR-013, FR-015.

## Aprobaciones de dependencias (2026-10-09)

- Aprobados: Playwright (E2E, con el Edge instalado) y `jsdom` (entorno de Vitest).
- No aprobado: `@tabler/icons-react`; iconos SVG propios.
