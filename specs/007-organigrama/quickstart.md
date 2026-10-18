# Quickstart: validar el organigrama

## Prerrequisitos

- Features 000-login a 006-posiciones funcionando (usuarios `admin@hrms.test` y `usuario@hrms.test`, contraseña de `SEED_PASSWORD`; puestos, 12 unidades y posiciones sembradas).
- **El módulo `Estructura` debe cargar**: resolver antes el conflicto de métodos duplicados en `EstructuraService` (ver research D-14).
- Dependencias instaladas en `backend/` y `frontend/`.
- Diseño actualizado (`design/Organigrama.dc.html`, `design/DESIGN.md` y `design/canvas.json`).

## Preparar el backend

```bash
cd backend
php artisan migrate:fresh --seed
```

## Validar la API (contrato en [contracts/organigrama-api.md](contracts/organigrama-api.md))

Con el token de cada rol (`POST /api/auth/login`):

1. Sin token: 401.
2. `GET /api/organigrama` con cualquier rol: 200, las mismas listas para ambos; solo posiciones Ocupadas y Vacantes (ninguna Suprimida); `posiciones` por código; una sola con `posicion_jefe_id` nulo; `ocupante` con nombre o `null`.
3. Suprimir una posición de ejemplo (en 006) y volver a llamar: ya no aparece.
4. Dejar sin posiciones vigentes (en una base de pruebas): 200 con `posiciones` vacía.
5. Forzar datos inválidos (dos posiciones vigentes sin supervisor en una base de pruebas): 500 con el mensaje genérico.
6. `POST`, `PUT`, `PATCH` y `DELETE` sobre `/api/organigrama`: 405 o 404.

## Validar la interfaz

Con `npm run dev` en `frontend/`:

1. **Usuario y Administrador** ven el mismo organigrama en `/organigrama`: título, subtítulo, «Ver listado de posiciones», resumen «N posiciones vigentes · N ocupadas · N vacantes», leyenda y «Las posiciones suprimidas no se muestran.»; el árbol de arriba abajo con todas las ramas expandidas; las Vacantes con borde discontinuo y fondo.
2. **Ramas**: «− Contraer» / «+ N posiciones»; «Expandir todo» y «Contraer todo» (solo queda la máxima autoridad).
3. **Zoom y desplazamiento**: Alejar y Acercar de 50 % a 150 % en pasos de 10 %, el porcentaje restablece 100 %; arrastrar desplaza (no sobre tarjetas ni botones); las flechas del teclado desplazan el área.
4. **Buscar y filtrar**: coincidencias resaltadas, el resto atenuado, ramas con coincidencias abiertas y sus botones deshabilitados; búsqueda y unidad a la vez coinciden solo si cumplen ambas; resumen «N posiciones coinciden de M vigentes»; «Limpiar filtros» solo con filtros y devuelve el árbol a como estaba.
5. **Detalle**: clic en una tarjeta abre el panel (en pantallas estrechas, una capa superpuesta); supervisor y subordinadas son enlaces que seleccionan y llevan al árbol hasta la posición; «Cerrar detalle» y Escape lo cierran; contraer una rama con la selección no cierra el panel.
6. **Estados**: cargando, vacío («Aún no hay posiciones vigentes para mostrar el organigrama.») y error con «Reintentar».
7. Teclado, tema oscuro y anchos 320 y 1440 px sin desplazamiento horizontal de la página.

## Ejecutar las pruebas

```bash
cd backend && vendor/bin/pint --test && vendor/bin/pest
cd ../frontend && npm run lint && npx tsc -b && npx vitest run && npm run build && ./node_modules/.bin/playwright test
```
