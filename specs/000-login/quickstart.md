# Quickstart: validar la autenticación mínima

## Prerrequisitos

- PHP 8.3, Composer y Node instalados; dependencias instaladas en `backend/` y `frontend/`.
- Migraciones aplicadas y datos iniciales sembrados (usuarios definidos en [data-model.md](data-model.md)).

## Preparar el backend

```bash
cd backend
php artisan migrate:fresh --seed
php artisan serve
```

## Validar la API (contrato en [contracts/auth-api.md](contracts/auth-api.md))

1. `POST /api/auth/login` con las credenciales del administrador → 200 con token, usuario y rol `admin`.
2. Con ese token, `GET /api/auth/me` → 200 con el mismo usuario y rol.
3. `GET /api/auth/me` sin token → 401.
4. `POST /api/auth/login` con contraseña errónea y con correo inexistente → 401 con el mismo mensaje.
5. `POST /api/auth/login` con correo mal formado o campos vacíos → 422 con errores por campo.
6. Repetir con el usuario de prueba y comprobar rol `user`; una ruta reservada al Administrador
   responde 403 con su token.
7. `POST /api/auth/logout` → 204; reutilizar el token en `me` → 401.

## Validar la interfaz

```bash
cd frontend
npm run dev
```

1. Abrir `/` sin sesión → redirige a `/login`.
2. Enviar el formulario vacío → errores por campo; con credenciales erróneas → alerta general.
3. Iniciar sesión como administrador → llega a la pantalla principal vacía con nombre y rol en el header.
4. Cerrar y reabrir el navegador → la sesión continúa (FR-007b).
5. Abrir `/login` con sesión → redirige a `/`.
6. Cerrar sesión desde el menú de usuario → vuelve a `/login`; el botón Atrás no muestra datos protegidos.
7. Revisar tema claro/oscuro y anchos de 320 a 1440 px sin scroll horizontal.

## Ejecutar las pruebas

```bash
cd backend && vendor/bin/pint --test && vendor/bin/pest
cd ../frontend && npm run lint && npx tsc -b && npx vitest run && npm run build
```

El E2E del flujo login → principal → logout se ejecuta con la herramienta que se apruebe
(ver Constitution Check en [plan.md](plan.md)).
