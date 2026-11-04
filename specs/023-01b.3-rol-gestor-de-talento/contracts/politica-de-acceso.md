# Contrato: PoliticaDeAcceso (módulo Compensaciones)

La API HTTP no cambia: mismas rutas, mismas respuestas, mismos códigos. Lo único que cambia es el contrato interno del punto de decisión de acceso.

## Antes
- `puedeVer(usuario): bool` (siempre verdadero)
- `puedeGestionar(usuario): bool` (`tokenCan('admin')`)

## Después
- `puede(object $usuario, string $permiso): bool`
  - `PoliticaDeAcceso::CATEGORIAS_GESTIONAR = 'categorias.gestionar'` → verdadero si el token tiene `admin`; falso si no.
  - Cualquier otro valor de `$permiso` → falso.
- Ver categorías no consulta permisos (cualquier usuario autenticado).
- `puedeVer()` y `puedeGestionar()` se retiran (`puedeVer()` no lo usa nadie); sus usos (listado, filtro de competencias, auditoría del recurso) llaman a `puede($usuario, CATEGORIAS_GESTIONAR)`.

## Sin cambios
- Rutas de gestión bajo `auth:sanctum` + `admin`.
- Capacidad `admin` y roles del sistema.
