# Contrato: API de autenticación

Base: `/api/auth`. Formato JSON. Salvo `login`, requieren `Authorization: Bearer <token>`.

## POST /api/auth/login

Público.

**Petición**

```json
{ "email": "admin@hrms.test", "password": "••••••••" }
```

- `email`: obligatorio, formato de correo; se recorta y se pasa a minúsculas.
- `password`: obligatorio.

**Respuestas**

| Código | Cuándo | Cuerpo |
|---|---|---|
| 200 | Credenciales válidas | `{ "data": { "token": "<token>", "user": { "id": 1, "name": "…", "email": "…", "role": "admin" } } }` |
| 401 | Credenciales inválidas o cuenta inexistente | `{ "message": "Correo o contraseña incorrectos." }` (idéntico en ambos casos) |
| 422 | Campo faltante o con formato inválido | `{ "message": "…", "errors": { "email": ["…"], "password": ["…"] } }` |

## POST /api/auth/logout

Requiere token válido. Revoca solo el token usado en la petición.

| Código | Cuándo | Cuerpo |
|---|---|---|
| 204 | Token revocado | Sin cuerpo |
| 401 | Sin token válido | `{ "message": "No autenticado." }` |

## GET /api/auth/me

Requiere token válido.

| Código | Cuándo | Cuerpo |
|---|---|---|
| 200 | Token válido | `{ "data": { "id": 1, "name": "…", "email": "…", "role": "admin" } }` |
| 401 | Sin token válido | `{ "message": "No autenticado." }` |

## Reglas transversales

- `role` toma los valores `admin` o `user`.
- Nunca se devuelven `password` ni hashes. Los errores no revelan si el correo existe.
- Los demás módulos protegen sus rutas con `auth:sanctum`; las administrativas añaden el middleware
  `EnsureAdmin` (capacidad `admin`) y responden 403 si la persona autenticada no la tiene.
- Todo texto de error visible para la persona está en español.
