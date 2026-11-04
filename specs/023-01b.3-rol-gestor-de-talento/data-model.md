# Data Model: Nombre del rol «Gestor de Talento» (01b.3)

Sin cambios de datos: no hay tablas, columnas ni migraciones nuevas, y ningún rol se crea ni se renombra.

## Conceptos (no persistidos)

| Concepto | Descripción | Regla |
|---|---|---|
| Rol Gestor de Talento | Nombre visible de quien gestiona categorías. En el repositorio de prueba es el rol Administrador existente. | No es un rol nuevo. |
| Permiso `categorias.gestionar` | Crear, editar, activar/desactivar, ver inactivas y ver la auditoría de categorías. | Se cumple cuando el token tiene la capacidad `admin`. Único permiso por ahora (`parametros.gestionar` llega en 01b.1). |
| Ver categorías | Listar y consultar. | Sin permiso: cualquier usuario autenticado. |
