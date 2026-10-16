# Contrato: lectura del catálogo para otros módulos (GP-05)

Ningún módulo o funcionalidad accede a la tabla; se usa el puerto o la fachada de `GestionPersonal`.

## `CatalogoDeCategoriasPort` (Application\Ports)

| Método | Datos |
|---|---|
| `activas(int $tenantId): Lectura` | `list<{id, nombre}>` solo `Activa`, ordenadas por nombre; para el selector de cargas nuevas |
| `porIds(int $tenantId, array $ids): Lectura` | `[id => {id, nombre, estado}]` de cualquier estado, nombre vigente; ids ajenos o inexistentes se omiten; una consulta |
| `confirmarActiva(int $tenantId, int $categoriaId): void` | Lanza `CategoriaInactiva` si no existe, es de otro tenant o está inactiva; **debe ejecutarse dentro de la transacción que publica el documento** |

- Fallos de infraestructura en las lecturas ⇒ `Lectura::noDisponible()`, nunca excepción; con la feature instalada nunca `noInstalado()`.
- `confirmarActiva` ejecuta un `UPDATE` condicional (`SET version = version WHERE id AND tenant_id AND estado = 'Activa'`) que toma el bloqueo de fila hasta el commit del consumidor y compite con `CambiarEstadoCategoria`. No se mantiene durante la transferencia del archivo: el consumidor sube primero a almacenamiento privado no visible y solo llama a este método en la transacción corta de publicación.
- `confirmarActiva` no cambia `version`, `updated_by` ni `updated_at` (no es una modificación de la categoría).
- Ningún método consulta documentos ni modifica su visibilidad o estado.

## `PersonalFacade`

| Método | Descripción |
|---|---|
| `getCategoriasActivas(int $tenantId): array` | Delegado a `activas`; lanza `InformacionNoDisponible` si la lectura falla |
| `getCategoriasPorIds(int $tenantId, array $ids): array` | Delegado a `porIds`; mismo manejo de fallo |
| `confirmarCategoriaActiva(int $tenantId, int $categoriaId): void` | Delegado a `confirmarActiva`; lanza `CategoriaInactiva` |

`CategoriaInactiva` es una excepción pública del módulo para que el consumidor rechace la clasificación con un mensaje claro y sin cambiarla en silencio (FR-020).

## Contrato del frontend para GP-05

`frontend/src/modules/gestionPersonal/categorias/index.ts` exporta `categoriasKey` y `useCategoriasActivas()` (consulta a `GET /api/categorias-documentales?estado=Activa&per_page=100`). Es el único punto que otra funcionalidad puede importar; tras cualquier mutación del catálogo se invalida la familia `['categorias-documentales']`.
