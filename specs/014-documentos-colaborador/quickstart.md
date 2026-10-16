# Quickstart: validar la gestión de documentos del colaborador

Guía de validación de extremo a extremo. Datos y API: [data-model.md](data-model.md) y [contracts/](contracts/).

## Prerrequisitos

- Backend y frontend instalados; la feature 013 (categorías) disponible.
- **Límites de PHP/proxy** por encima del multipart necesario (el entorno actual trae `upload_max_filesize=2M` y `post_max_size=8M`): configurar al menos `upload_max_filesize = 11M` y `post_max_size = 12M` en el `php.ini` del servidor de pruebas y en el proxy, para que un archivo de exactamente 10 000 000 bytes llegue y el rechazo por tamaño lo decida la aplicación.
- Disco privado de documentos (`hrms.gestion_personal.documentos.disco`, `local` por defecto) con permisos de escritura; el planificador de Laravel en ejecución si se quiere el barrido automático.
- Entorno de pruebas, no datos reales:

```bash
cd backend && php artisan migrate:fresh --seed --env=e2e
```

Semillas: en el tenant 1 las categorías Contratos y Certificados (Activas) y una Inactiva, y dos documentos sintéticos de Ana (uno visible y otro archivado). `admin@hrms.test` es el Administrador; `usuario@hrms.test` es el Colaborador de Ana con permiso documental; `colaborador2@hrms.test` pertenece a otra ficha.

## Escenarios

1. **Cargar (US1)**: como Administrador abrir la ficha de María → Documentos → Subir documento; título «Contrato», categoría Contratos, un PDF sintético válido; el documento aparece Disponible con tamaño y fecha, visibilidad desmarcada. Subir un `.pdf` que en realidad es una imagen, un archivo vacío y uno de 10 000 001 bytes ⇒ cada uno se rechaza con su motivo y no queda registrado.
2. **Sin categorías activas**: desactivar todas las categorías; Subir documento explica el requisito y ofrece ir a Categorías documentales.
3. **Carrera con la categoría**: abrir el diálogo, desactivar la categoría desde otra sesión y confirmar la carga ⇒ se rechaza (409 `CATEGORIA_INACTIVA`) sin reasignar y sin documento.
4. **Idempotencia**: repetir la misma petición con la misma `Idempotency-Key` ⇒ 200 con el mismo documento; la misma clave con otro archivo ⇒ 409.
5. **Consulta y descarga del Administrador (US2)**: buscar por título, filtrar por categoría, abrir y descargar un disponible y uno archivado desde la vista de archivados; la categoría inactiva se marca como tal.
6. **Visibilidad y archivo (US3)**: retirar la visibilidad ⇒ el Colaborador deja de abrir el documento en la siguiente petición; Archivar pide confirmación y deja el documento en archivados con quién y cuándo, fuera de la lista habitual; cambiar visibilidad de un archivado ⇒ 409; no hay Restaurar, Editar ni Eliminar.
7. **Colaborador (US4)**: con `usuario@hrms.test`, Mis documentos muestra solo los visibles y disponibles de Ana; un documento privado, archivado, de otra ficha o de otro tenant conocido por id responde 404 sin bytes; sin permiso documental (aunque tenga el de ficha) ⇒ 403 `SIN_PERMISO_DOCUMENTOS`; el Colaborador no ve controles de administración.
8. **Fallo del almacén**: con el disco no disponible, cargar ⇒ el usuario ve el fallo, no queda documento y no hay objeto huérfano; descargar ⇒ 503 sin bytes parciales.
9. **Barrido**: crear un objeto sin referencia con más de 24 horas y ejecutar `php artisan documentos:limpiar-huerfanos` ⇒ se elimina solo ese objeto; los documentos publicados y archivados permanecen.

## Pruebas automatizadas

```bash
cd backend && vendor/bin/pint --dirty --format agent && php artisan test --compact --filter=Documento
cd frontend && npm run lint && npx tsc -b && npm run test && npm run build
cd frontend && npx playwright test e2e/documentos.spec.ts
```

Cobertura esperada: unidad de `ReglasDeDocumento` y del validador de PDF (PDF válido, imagen renombrada, texto, vacío, truncado, sin `%%EOF`, sin `startxref`); feature de cada endpoint (401/403/404/409/413/422/503, 404 de otro tenant y de otra ficha, orden, búsqueda, filtro, paginación, sin DELETE); límite exacto, un byte más y vacío; idempotencia (misma clave y mismo contenido, misma clave con otro archivo, dos cargas concurrentes con el ganador intacto); fallos con `StoragePort` falso (subida, commit, limpieza) sin falso éxito ni documento parcial; desactivación concurrente de la categoría; propiedad y permiso documental independientes del de ficha; archivar retira el acceso propio y conserva el del Administrador; cabeceras de contenido (`nosniff`, `no-store`, nombre seguro) y ausencia de `object_key`; barrido que no borra el objeto ganador ni documentos; Vitest de validación, lista, formulario, confirmación y solo lectura; E2E del flujo principal, del Colaborador y de 320/1440 px.
