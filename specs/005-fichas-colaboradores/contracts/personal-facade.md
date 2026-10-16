# Contrato: `PersonalFacade` y puertos de lectura

`App\Modules\GestionPersonal\Presentation\Facades\PersonalFacade` es la única entrada para otros módulos. Los modelos
Eloquent no salen del módulo: se devuelven arreglos/DTO. Registro del alias en `GestionPersonalServiceProvider`
(`personal-facade`).

## Fachada pública

| Método | Descripción |
|---|---|
| `getColaborador(int $tenantId, int $id): ?array` | Ficha `{id, codigo, tipo_identificacion, numero_identificacion, nombres, apellidos, version}` del tenant, o `null` |
| `getColaboradoresPorIds(int $tenantId, array $ids): array` | Fichas existentes del tenant entre los ids dados |
| `buscarPorIdentificacion(int $tenantId, string $tipo, string $numero): ?array` | Ficha con esa identificación normalizada, o `null` |
| `registrarFicha(int $tenantId, int $actorId, string $claveDeOperacion, array $datos): array` | Reutiliza `RegistrarFicha` (GP-07); mismas validaciones, idempotencia y errores de dominio; el actor interno debe estar verificado por el llamador |

Todas exigen `tenantId` explícito: nunca se infiere. No hay método de eliminación ni de cambio de estado.

## Puertos de lectura (los implementan otras features)

Cada método devuelve `Lectura<T>`: `disponible($datos)`, `noInstalado()` o `noDisponible()`. El adaptador no debe
lanzar excepciones al módulo: las convierte a `noDisponible()`. Los adaptadores actuales de esta feature devuelven
siempre `noInstalado()`.

### `LectorLaboralPort` (GP-02 / hechos laborales)

| Método | Datos |
|---|---|
| `situacionVigente(int $tenantId, array $colaboradorIds, string $fecha): Lectura` | `[colaborador_id => {estado, posicion_id}]`; ausente = sin vínculo vigente |
| `idsConVinculoVigente(int $tenantId, DateTimeInterface $fecha): Lectura` | ids de fichas con vínculo vigente |
| `idsPorEstado(int $tenantId, string $estado, DateTimeInterface $fecha): Lectura` | ids con ese estado |
| `colaboradoresEnPosiciones(int $tenantId, array $posicionIds, string $fecha): Lectura` | ids de fichas asignadas a esas posiciones (búsqueda por puesto y filtro de unidad) |
| `trayectoria(int $tenantId, int $colaboradorId): Lectura` | `[{fecha_efectiva, tipo, posicion_id?, detalle?}]`, sin ordenar |

### `LectorEstructuraPort` (Estructura Organizacional)

| Método | Datos |
|---|---|
| `posiciones(array $posicionIds): Lectura` | `[posicion_id => {puesto, unidad_id, unidad}]` |
| `posicionesPorPuesto(string $texto): Lectura` | ids de posición cuyo puesto contiene el texto (normalizado) |
| `posicionesPorUnidad(int $unidadId): Lectura` | ids de posición de la unidad |
| `unidades(): Lectura` | `[{id, nombre}]` |

### `LectorDeCapacidadesPort`

| Método | Datos |
|---|---|
| `instalada(string $capacidad): bool` | `desarrollo`, `documentos`, `registrar_ingreso`, `exportar` |

### `ContextoDeTenantPort`

| Método | Datos |
|---|---|
| `deCuenta(int $userId): ContextoDeTenant` | `{tenantId, nombre, zonaHoraria}`; lo implementa un adaptador sobre `SharedFacade::getContextoDeTenant` |

### `AccesosDeCuentaPort`

| Método | Datos |
|---|---|
| `deCuenta(int $userId): ?AccesoDeCuenta` | `{tenantId, colaboradorId, puedeConsultarFicha, puedeConsultarDocumentos, puedeExportar}` o `null` |

## Contrato aditivo en `Shared`

| Cambio | Detalle |
|---|---|
| `SharedFacade::getContextoDeTenant(int $userId): array` | `{tenant_id, nombre, zona_horaria}` |
| `UsuarioDTO` / `UsuarioResource` | + `tenant: { id, nombre }` (aditivo) |

## Reglas de uso

- Un puerto `noInstalado` equivale a «sin dato»: «Sin vínculo vigente», «No registrado», trayectoria vacía.
- Un puerto `noDisponible` equivale a «Información no disponible»; con un filtro activo que depende de él, el
  listado responde 503 y no un resultado parcial.
- Las lecturas se piden **por lote** sobre la página, nunca fila por fila.
