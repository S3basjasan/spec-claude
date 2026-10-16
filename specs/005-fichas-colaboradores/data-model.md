# Data model: Fichas de colaboradores (GP-01)

Almacenamiento SQLite. Migraciones nuevas con fecha `2026_10_15_00000N`. Las equivalencias con el insumo (PostgreSQL)
están en [research.md](research.md) D-02. Ninguna tabla de Estructura se modifica.

## Tabla `tenants` (módulo Shared)

| Columna | Tipo | Restricción |
|---|---|---|
| id | integer autoincremental | PK |
| nombre | string(150) | NOT NULL |
| zona_horaria | string(64) | NOT NULL, por defecto `America/Guayaquil` |
| created_at, updated_at | timestamps | |

## Cambio en `users` (módulo Shared)

| Columna | Tipo | Restricción |
|---|---|---|
| tenant_id | unsignedBigInteger | NOT NULL, índice; las cuentas existentes reciben el tenant `1` |

Sin clave foránea declarada entre módulos; la integridad la mantiene el seeder y `SharedService`.

## Tabla `colaboradores` (módulo GestionPersonal)

| Columna | Tipo | Restricción / regla |
|---|---|---|
| id | integer autoincremental | PK; origen del código |
| tenant_id | unsignedBigInteger | NOT NULL; siempre desde el contexto de la cuenta |
| codigo | string(40) | NOT NULL; `EMP-` + `id` con mínimo 3 dígitos; inmutable; UNIQUE(tenant_id, codigo) |
| tipo_identificacion | string(12) | NOT NULL; `Cedula`, `Pasaporte` u `Otro` (UI: «Cédula») |
| numero_identificacion | string(50) | NOT NULL; valor recortado, tal como se presenta |
| identificacion_clave | string(50) | NOT NULL; NFC + minúsculas del número recortado; UNIQUE(tenant_id, tipo_identificacion, identificacion_clave) |
| nombres, apellidos | string(150) cada una | NOT NULL; recortados; nunca vacíos |
| nombres_busqueda, apellidos_busqueda | string(150) cada una | NOT NULL; minúsculas sin tildes |
| fecha_nacimiento | date | NULL; no futura según la zona del tenant |
| nacionalidad | string(100) | NULL |
| genero, estado_civil | string(80) cada una | NULL; se guardan como se escriben |
| numero_hijos | unsignedInteger | NULL; ≥ 0 (0 es un dato registrado) |
| direccion | string(500) | NULL |
| telefono | string(30) | NULL; `^\+?[0-9 ()\-]+$` |
| correo_contacto | string(254) | NULL; formato válido; **no** único, no es credencial |
| emergencia_nombre | string(150) | NULL; obligatorio si hay algún dato de emergencia |
| emergencia_relacion | string(100) | NULL; opcional |
| emergencia_telefono | string(30) | NULL; obligatorio si hay algún dato de emergencia |
| version | unsignedInteger | NOT NULL, por defecto 1; sube en cada edición con cambios |
| operacion_id | char(36) | NOT NULL; UNIQUE(tenant_id, operacion_id) |
| operacion_hash | char(64) | NOT NULL; SHA-256 del cuerpo canónico del alta |
| created_by, updated_by | unsignedBigInteger | NOT NULL; id de la cuenta, sin FK |
| created_at, updated_at | timestamps | |

Índices: los tres únicos anteriores y `(tenant_id, apellidos_busqueda, nombres_busqueda, id)` para el orden del
listado. `CHECK (numero_hijos >= 0)` solo si el constructor de esquema lo admite al crear la tabla; de lo contrario, la
regla vive en la aplicación (nunca bloquea la migración).

**No existen** `puesto_id`, `unidad_id`, `estado_laboral`, `usuario_id`, salario ni cuenta bancaria: la situación
laboral procede de lectores (D-09) y la cuenta se asocia en `colaborador_cuentas`.

### Reglas

- Identificación: la clave se recalcula en cada alta y edición con el mismo servicio. Dos claves iguales con tipos
  distintos conviven (Pasaporte «AB123» y Cédula «AB123»).
- Contacto de emergencia: el conjunto resultante tras aplicar un `PATCH` debe tener los tres campos vacíos, o nombre
  y teléfono llenos.
- `null` vacía un opcional; los obligatorios nunca son `null` ni cadena vacía.
- Una ficha nunca se elimina ni se desactiva. No existe estado propio.

### Transiciones

| Operación | Efecto |
|---|---|
| Alta | `version=1`, código `EMP-###`, `created_by=updated_by=actor`; sin cuenta, vínculo ni documentos |
| Edición con cambios | Reescribe claves y búsquedas; `version+1`; `updated_by`, `updated_at` |
| Edición sin cambios | Sin escritura |
| Edición con `version` obsoleta | 409; sin escritura |

## Tabla `colaborador_cuentas` (módulo GestionPersonal; solo lectura, sembrada)

| Columna | Tipo | Restricción |
|---|---|---|
| id | integer autoincremental | PK |
| tenant_id | unsignedBigInteger | NOT NULL; debe coincidir con el de la cuenta y el de la ficha |
| user_id | unsignedBigInteger | NOT NULL, UNIQUE; sin FK |
| colaborador_id | unsignedBigInteger | NOT NULL, UNIQUE; FK a `colaboradores.id` |
| puede_consultar_ficha | boolean | NOT NULL, por defecto false (≡ `personal.ficha_propia.consultar`) |
| puede_consultar_documentos | boolean | NOT NULL, por defecto false |
| puede_exportar | boolean | NOT NULL, por defecto false |
| created_at, updated_at | timestamps | |

Los tres permisos son independientes. La gestión de esta tabla pertenece a la feature de identidad y acceso.

## Entidades externas (solo por puertos)

| Entidad | Fuente | Qué lee GP-01 |
|---|---|---|
| Vínculo laboral y estado | GP-02 (`LectorLaboralPort`) | estado vigente, posición vigente, ids con vínculo |
| Hecho laboral | GP-02 / hechos (`LectorLaboralPort`) | `fecha_efectiva`, `tipo`, `posicion` o `cambio`, `programado` |
| Puesto, posición, unidad | Estructura (`LectorEstructuraPort`) | nombre de puesto y unidad por posición; unidades filtrables |
| Capacidades de sección | `LectorDeCapacidadesPort` | Desarrollo, Documentos, Registrar ingreso, Exportar instaladas |

## Datos iniciales (`PersonalSeeder`, después de `UsuariosInicialesSeeder`)

Datos sintéticos; contraseñas de `SEED_PASSWORD`.

| Tenant | Cuenta | Rol | Asociada a | Permisos |
|---|---|---|---|---|
| 1 «Altavista S.A.» | `admin@hrms.test` | admin | — | — |
| 1 | `usuario@hrms.test` | user | `EMP-001` | ficha: sí; documentos: no; exportar: no |
| 1 | `colaborador2@hrms.test` | user | `EMP-002` | ficha: sí |
| 1 | `sinficha@hrms.test` | user | — | — |
| 1 | `sinpermiso@hrms.test` | user | `EMP-003` | ninguno |
| 2 «Cordillera Andina S.A.» | `admin2@hrms.test` | admin | — | — |

| Tenant | Código | Identificación | Nombre | Notas |
|---|---|---|---|---|
| 1 | EMP-001 | Cédula 0102030405 | Ana Núñez Paredes | Datos complementarios completos y contacto de emergencia |
| 1 | EMP-002 | Cédula 0912345678 | Luis Núñez Andrade | Mismo apellido que EMP-001 |
| 1 | EMP-003 | Pasaporte AB123 | María Pérez Soto | Ficha mínima, sin complementarios |
| 1 | EMP-004 | Otro 0102-03 | José Ángel Mora | Separador interno |
| 1 | EMP-005 | Cédula 0000123456 | Carla Vera Lema | Ceros iniciales |
| 2 | EMP-00N | Cédula 0102030405 | Rosa Chávez Díaz | Misma identificación que EMP-001 en otro tenant |
| 2 | EMP-00N | Pasaporte ZX900 | Pablo Rivas Ortiz | |

Los códigos del tenant 2 continúan la secuencia global (saltos válidos). Los usuarios existentes pasan a
`tenant_id = 1` en la migración; `UsuariosInicialesSeeder` se amplía con `admin2@hrms.test` y las cuentas nuevas.
