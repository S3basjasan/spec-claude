# Data Model: Categorías por unidad y visibles por rol (01b.4)

## Cambio en `categorias_reconocimiento` (existente)

| Columna | Tipo | Reglas |
|---|---|---|
| `unidades_ids` | JSON (nullable en BD) | Lista de enteros positivos **distintos y ordenados**, mínimo 1 al guardar. Nulo/vacío solo existe en categorías previas al comando inicial. Relación lógica con `unidades_organizacionales` (Estructura): sin FK. |

Sin otras columnas ni tablas nuevas. `updated_at` sigue siendo el token de concurrencia: cambiar unidades lo actualiza.

### Migración

`2026_11_04_000001_add_unidades_ids_to_categorias_reconocimiento`: agrega la columna vacía. No lee datos de otros módulos; los datos iniciales los asigna el comando `compensaciones:asignar-unidades-iniciales`.

### Comando inicial

- Para cada categoría con `unidades_ids` nulo o vacío, asigna todas las unidades activas (`UnidadesPort::activas()`).
- Idempotente: no toca las que ya tienen unidades.
- Registra una fila de auditoría `unidades` (anterior nulo, nuevo ids) con actor nulo.

## Objetos de dominio

- **`UnidadesAsignadas`** (objeto de valor): ≥ 1 ids enteros positivos, sin repetir, ordenados; `ids()`, `contiene(int)`, `comoJson()`. Lanza `DatosDeCategoriaInvalidos('unidades_ids', 'Elige al menos una unidad.')` si queda vacío o contiene algo no entero.
- **`Categoria`** (entidad): gana `unidades` (`UnidadesAsignadas|null`; nulo solo para datos previos). `diferenciasCon()` agrega el par `unidades` (JSON de ids ordenados). `esSeleccionable(bool $competenciaActiva)` no cambia; la pertenencia a una unidad la comprueba el servicio/consulta.
- **`FiltroCategorias`**: gana `unidadId` opcional (asignación directa).

## Vistas derivadas (no se guardan)

| Vista | Definición |
|---|---|
| Categorías seleccionables de una unidad | Activas, con competencia activa y `unidades_ids` ∋ unidad; vacío si la unidad no existe o está inactiva |
| Alcance de lectura de un usuario | `Gestor` (todas, con inactivas) · `Líder` (todas las activas) · `Unidad(id)` (activas de esa unidad) · `Ninguno` (sin ficha o sin unidad) |
| `aplica_a_todas` | Todas las unidades activas del momento están en `unidades_ids` |
| `sin_unidades_activas` | Ninguna unidad de `unidades_ids` está activa (o está vacío): advertencia para el Gestor |

## Auditoría de la categoría

Campo nuevo `unidades` (anterior → nuevo, JSON de ids ordenados) en `auditoria_categorias_reconocimiento`, con las mismas reglas que los demás campos: una fila por campo realmente modificado; guardar sin cambiar unidades no registra nada.

## Datos de otros módulos (solo lectura, por puerto)

| Dato | Puerto → Facade | Uso |
|---|---|---|
| Unidades activas / por ids (`id, codigo, nombre, estado, …`) | `UnidadesPort` → `EstructuraFacade::getUnidadesActivas / getUnidadesPorIds` | Validar y resolver nombres; selector |
| Colaborador de la cuenta, unidad actual | `ColaboradoresPort` → `PersonalFacade::getCuentaDeUsuario / getDepartamentoActual` | Alcance del colaborador |
| Líder del usuario | `EquipoPort::esLider` (provisional) | Alcance de líder |
| Tenant | `EmpresaActualPort` → `SharedFacade::getContextoDeTenant` / config | Llamadas a Personal |

## Transiciones y reglas

- Crear: `unidades_ids` obligatorio (lista o `todas_las_unidades`); todas deben existir y estar activas.
- Editar: reemplaza la lista completa; valida igual que crear; 409 por versión antes que 422.
- Activar/desactivar: no cambia unidades; una categoría desactivada conserva su asignación.
- Una unidad que pasa a inactiva conserva su asignación pero deja de contar para la visibilidad.
- Cambiar unidades no modifica `reconocimientos` ni `desafios` (copian `puntos`, `unidad_id` y `categoria_id` al registrarse).
