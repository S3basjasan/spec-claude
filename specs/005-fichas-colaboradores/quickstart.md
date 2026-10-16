# Quickstart: validar las fichas de colaboradores

## Prerrequisitos

- Features 000 a 004 funcionando (`admin@hrms.test`, `usuario@hrms.test`, contraseña de `SEED_PASSWORD`).
- Dependencias instaladas en `backend/` y `frontend/`.
- Diseño actualizado: `design/DESIGN.md` y `design/ColaboradorFicha.dc.html` (ver el plan, «Diseño de frontend»).
- Confirmado en la primera prueba Unit: `Normalizer` disponible (polyfill) y NFD ≡ NFC.

## Preparar el backend

```bash
cd backend
php artisan migrate:fresh --seed
php artisan serve
```

Siembra dos tenants, las cuentas y las siete fichas de [data-model.md](data-model.md). Contrato de la API en
[contracts/colaboradores-api.md](contracts/colaboradores-api.md).

## Validar la API

1. Sin token, `GET /api/colaboradores` → 401. Como `usuario@hrms.test`, `GET /api/colaboradores` y `POST` → 403.
2. Como `admin@hrms.test`: `GET /api/colaboradores` → 5 fichas del tenant 1 (ninguna del 2), orden Mora, Núñez
   Andrade, Núñez Paredes, Pérez, Vera; `meta.per_page` 20; todas con `estado` `SinVinculoVigente`, `puesto` y `unidad`
   `null`.
3. `?q=nunez` y `?q=NÚÑEZ` → 2 fichas; `?q=emp-003` → 1; `?q=analista` → 0; `?estado=SinVinculoVigente` → 5;
   `?unidad_id=1` → 422 (Estructura no instalada).
4. `GET /api/colaboradores/filtros` → `estados` `Todos` y `SinVinculoVigente`; `unidades` vacío.
5. `POST` ficha mínima con `Idempotency-Key` nueva → 201, `codigo` `EMP-0NN`, `version` 1, sin cuenta ni vínculo.
   Repetir la misma petición → 200 con la misma ficha; mismo UUID con otro cuerpo → 422 `CLAVE_DE_OPERACION_REUTILIZADA`;
   sin cabecera → 422 `CLAVE_DE_OPERACION_REQUERIDA`.
6. `POST` con Cédula `" 0102030405 "` → 422 `IDENTIFICACION_DUPLICADA` con `ficha_existente`; Pasaporte `ab123` → 422;
   Cédula `AB123` u Otro `AB123` → 201; `0102-03` y `010203` son distintas.
7. `POST` con `tenant_id`, `codigo` o `estado` en el cuerpo → 422 `CAMPO_NO_PERMITIDO`; fecha de nacimiento de mañana,
   hijos `-1`, correo inválido, solo `emergencia_relacion` → 422 por campo. Mismo correo en dos fichas → 201.
8. `GET /api/colaboradores/{id}` de EMP-003 → 200 con complementarios `null`, `trayectoria.hechos` vacío y
   `capacidades` en `false`; id del tenant 2 o inexistente → 404 idéntico.
9. `PATCH` corrigiendo la identificación de EMP-004 con `version` vigente → 200, mismo `codigo`, `version` +1; a una
   combinación ocupada → 422 y sin cambios; con la `version` anterior → 409 `FICHA_MODIFICADA` sin sobrescribir; sin
   cambios reales → 200 sin alterar `version` ni `updated_at`. `DELETE` → 404/405.
10. Como `admin2@hrms.test`: la Cédula `0102030405` ya existe en su tenant (otra persona) y se acepta en cada tenant.
11. Como `usuario@hrms.test`: `GET /api/mi-ficha` → 200 EMP-001 con `acciones.editar=false`; como `sinficha@hrms.test`
    → 403 `CUENTA_SIN_FICHA`; como `sinpermiso@hrms.test` → 403 `SIN_PERMISO_FICHA`; pedir la ficha de otra persona por
    `/api/colaboradores/{id}` → 403.
12. Con `GESTION_PERSONAL_ESCRITURA_HABILITADA=false`, `POST`/`PATCH` → 503 y los `GET` siguen funcionando.

## Validar el frontend

```bash
cd frontend
npm run dev
```

1. Administrador → «Colaboradores»: listado con cargando, vacío, sin coincidencias («Ningún colaborador coincide con
   la búsqueda») y error con Reintentar; búsqueda con y sin tildes; filtros y página en la URL.
2. «Nueva ficha»: cuatro grupos, código «Se asigna al guardar», errores por campo, valores conservados, botón
   bloqueado durante el guardado; duplicado con «Abrir ficha existente»; al guardar abre el detalle con aviso.
3. Detalle: datos ausentes como «No registrado», `0` hijos como `0`, estado «Sin vínculo vigente», trayectoria vacía,
   sin Desarrollo ni Documentos, sin Registrar ingreso ni Exportar, sin eliminar.
4. Editar en dos pestañas: el segundo guardado muestra «La ficha cambió» y conserva lo escrito.
5. Colaborador → «Mi ficha» solo lectura; sin «Colaboradores» en el menú; `/colaboradores` muestra «sin permisos».
6. 320 px y 1440 px sin desplazamiento horizontal; tema claro y oscuro.

## Puertas de calidad

```bash
cd backend && vendor/bin/pest && vendor/bin/pint --test
cd ../frontend && npm run lint && npx tsc -b && npm test -- --run && npm run build && npx playwright test fichas
```
