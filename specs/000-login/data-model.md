# Data Model: Autenticación mínima (login)

## Usuario (`users`)

| Campo | Tipo | Reglas |
|---|---|---|
| `id` | entero autoincremental | Clave primaria |
| `name` | texto | Obligatorio; nombre visible |
| `email` | texto | Obligatorio, único; se guarda recortado y en minúsculas |
| `password` | texto | Obligatorio; hash no reversible; nunca se expone |
| `role` | `admin` \| `user` | Obligatorio; **columna nueva** (migración); por defecto `user` |
| `email_verified_at`, `remember_token`, `created_at`, `updated_at` | existentes | Sin uso funcional en esta feature |

Relaciones: un Usuario tiene muchas Sesiones (tokens).

## Rol

Valor de dominio con dos casos: `admin` (Administrador) y `user` (Usuario). Determina la capacidad
del token (`admin` solo para Administrador).

## Sesión (`personal_access_tokens`, existente)

| Campo | Notas |
|---|---|
| `tokenable_*` | Usuario dueño del token |
| `name` | Etiqueta del token (p. ej. `auth`) |
| `token` | Hash del token; el valor en claro solo se devuelve en el login |
| `abilities` | `["admin"]` o `["user"]` según rol |
| `expires_at` | Siempre nulo (sin expiración) |

Ciclo de vida: se crea en el login → válida hasta el logout (se elimina el token actual) →
cualquier uso posterior se rechaza con 401. Un usuario puede tener varias sesiones.

## Datos iniciales

| Nombre | Correo | Rol |
|---|---|---|
| Administrador de prueba | `admin@hrms.test` | `admin` |
| Usuario de prueba | `usuario@hrms.test` | `user` |

Seeder idempotente; contraseñas de desarrollo desde configuración, solo para pruebas.
