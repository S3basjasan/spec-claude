# Contrato: puertos consumidos y Facade expuesta

## Puertos de entrada (adaptadores sobre Facades de otros módulos)

### PuestosParaRutasInterface — adaptador `PuestosParaRutasDesdeEstructura` (`EstructuraFacade`)

| Método | Fuente | Devuelve |
|---|---|---|
| `perfil(int $puestoId): ?array` | `getPuesto` | `{ puesto: {id, codigo, nombre, activo}, competencias: [{competencia_id, codigo, nombre, tipo, grado}], requisitos: { formacion: [string], capacitacion: [string], experiencia: [string] } }` o null |
| `activos(): array` | `getPuestosActivos` | `[{id, codigo, nombre}]` |
| `estaActivo(int $puestoId): bool` | `puestoEstaActivo` | bool |

`requisitos` agrupa por tipo los requisitos de texto del puesto tal como los entrega `EstructuraFacade::getPuesto` (Formación, Capacitación, Experiencia): se muestran como información, sin grado ni diferencial (R3). El contrato **no expone conocimientos con grado**; la bandera `desarrollo.conocimientos_con_grado` (por defecto `false`, `config/desarrollo.php`) es solo el punto de extensión documentado y su rama `true` no se implementa. Memoiza por petición.

### DepartamentosInterface (existente) — `getUnidadesActivas`, `getUnidadesPorIds`, `unidadEstaActiva`.

### Puertos existentes que se reutilizan (no se crean equivalentes)

| Puerto existente (adaptador sobre la Facade) | Uso en rutas |
|---|---|
| `ColaboradoresVigentesInterface::buscar` (`PersonalFacade::buscarColaboradoresVigentes`) | Buscar colaboradores inscribibles (solo con vínculo laboral vigente) |
| `ColaboradoresVigentesInterface::vigentesPorIds` (`PersonalFacade::vigentesPorIds`) | Validar vigencia al inscribir y conciliar bajas (R5) |
| `CuentaColaboradorInterface::cuentaDe` (`PersonalFacade::getCuentaDeUsuario`) | Resolver el colaborador del usuario autenticado en «Mi ruta» |
| `NombresDeReferenciaInterface::colaboradores` / `puestos` / `unidades` | Nombres de colaboradores, puestos y departamentos en listas y detalle |
| `DepartamentoDeColaboradorInterface::actuales` (`PersonalFacade::getDepartamentoActual`) | Departamento actual del colaborador en la tabla de participantes |
| `NombresDeUsuarioInterface` | Nombre del creador, del último modificador y del autor de cada evento del historial |
| `DepartamentosInterface` | Departamentos activos del formulario y filtros |
| `RelojInterface` | Fecha de vigencia |

Si Personal o Estructura fallan, los adaptadores lanzan una excepción de información no disponible. Los casos de uso de lectura devuelven la parte disponible con `fuentes_no_disponibles` (por ejemplo `["estructura"]`) y nunca inventan requisitos ni nombres; la conciliación de bajas no retira a nadie si Personal falla.

### RelojInterface (existente) — fecha actual.

## Facade expuesta: `DesarrolloFacade` (ampliación aditiva, solo lectura)

```text
inscripcionesActivas(int $tenantId, int $colaboradorId): array
  → [{ inscripcion_id, ruta_id, ruta_codigo, ruta_nombre, ruta_tipo,
       nivel_actual: {id, orden, puesto_id, nombre}|null,
       nivel_objetivo: {id, orden, puesto_id, nombre, experiencia_minima_anios}|null }]

obtenerNivel(int $tenantId, int $nivelId): ?array
  → { nivel_id, ruta_id, ruta_nombre, orden, puesto_id, nombre, experiencia_minima_anios }
```

Consumidores: Adecuación a puesto futuro y Plan de Desarrollo Individual (features aún no implementadas) y Reportes (cantera). No incluyen competencias: el consumidor las lee de Estructura por `puesto_id`. Las inscripciones de quien ya no es vigente se consideran Retiradas y no se devuelven.

## Autorización: `AutorizadorDeRutas`

`permisosDe(Usuario $u): list<string>` — `admin` → las cinco de gestión/consulta; `user` → `desarrollo.rutas.ver_propio`. El middleware `permiso:<clave>` responde 403 si falta la clave. Único punto de decisión; los controladores no leen el rol.
