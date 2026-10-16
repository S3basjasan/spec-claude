# Contrato: puertos y Facade del módulo Desarrollo

Define cómo el módulo `Desarrollo` consume datos de módulos que **aún no existen** (Gestión de Personal y DNF) y cómo los
demás módulos consultan el catálogo formativo.

## Puertos de entrada de datos (Application/Ports)

Son interfaces propias del módulo; los casos de uso solo dependen de ellas. En este momento tienen adaptadores provisionales.

| Puerto | Método | Devuelve | Adaptador provisional |
|---|---|---|---|
| `NivelesDeColaboradorInterface` | `nivelesDe(int $usuarioId): array` | `[competencia_id => 'A'\|'B'\|'C'\|'D']` solo para el usuario indicado | `NivelesDeColaboradorSinDatos` → `[]` |
| `BrechasActivasInterface` | `brechasDe(int $usuarioId): array` | `[ {competencia_id, nivel_requerido} ]` del usuario indicado | `BrechasActivasSinDatos` → `[]` |
| `NombresDeUsuarioInterface` | `nombresDe(array $usuarioIds): array` | `[id => nombre]` para mostrar quién configuró y el historial | `NombresDeUsuarioDesdeShared`, que llama a `SharedFacade::getNombresDeUsuarios` (definida en [shared-facade-ampliacion.md](shared-facade-ampliacion.md)); es un adaptador definitivo, no provisional |

Reglas de uso:

- El caso de uso pasa **siempre** el id del usuario autenticado (nunca uno recibido por la petición): el colaborador solo ve lo propio.
- Si el puerto lanza una excepción, el caso de uso la registra (sin datos personales) y continúa con "sin dato": las tarjetas salen
  sin nivel resaltado y la sección de recomendados se omite (FR-034, FR-041).
- Cuando exista Gestión de Personal, se crea un adaptador que llama a su Facade (método de nivel por competencia del
  colaborador); cuando exista la DNF, otro que llama a su Facade de brechas activas. Solo cambian los adaptadores y el binding
  en `DesarrolloServiceProvider`; ningún caso de uso, recurso HTTP ni pantalla se modifica.
- Ambos módulos deben identificar al colaborador por el mismo id de usuario que usa el token de Sanctum.

## Facade del módulo (Presentation/Facades/DesarrolloFacade)

Única puerta de otros módulos hacia el catálogo formativo. Es de **solo lectura**: ningún módulo consumidor puede modificar
asociaciones. No expone modelos ni entidades, solo arreglos.

| Método | Devuelve | Notas |
|---|---|---|
| `getOfertaPorCompetencia(int $competenciaId): array` | `{competencia_id, area_formativa: {id, nombre}\|null, cursos: [{id, nombre, tipo, duracion_minutos, formador, url_destino}], microcontenidos: [...]}` | Solo recursos `Disponible`; arreglos vacíos si no hay oferta. Para el botón "Ver cursos sugeridos" de la DNF, que además navega a `/formacion?competencia=:id` |
| `competenciaTieneOferta(int $competenciaId): bool` | `true` si tiene ≥ 1 curso o micro-contenido `Disponible` | Para decidir si la DNF muestra el botón |

## Contrato inverso con las features dueñas de los recursos

Quien implemente **cursos** y **micro-contenidos** MUST:

- Ampliar con migraciones propias las tablas `cursos` y `microcontenidos` creadas aquí, sin recrearlas ni borrar sus columnas
  `id`, `nombre`, `tipo`, `duracion_minutos`, `formador`, `estado` (`Disponible` | `Archivado`) y `url_destino`.
- Archivar un recurso cambiando `estado` a `Archivado` (no eliminarlo si tiene vínculos): así deja de contar y de mostrarse al
  colaborador, y el vínculo se conserva.
- Rellenar `url_destino` con la ruta de su pantalla del recurso para que "Iniciar"/"Ver" dejen de mostrar "Próximamente".
- Reemplazar la siembra demostrativa de `CatalogoFormativoSeeder` por la suya.

## Garantías

- La Facade nunca expone entidades ni modelos Eloquent.
- No se exponen historial ni datos de auditoría.
- Las competencias, sus grados y su estado se obtienen siempre de `EstructuraFacade`; este módulo no guarda copias.
