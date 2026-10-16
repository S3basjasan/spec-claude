# Quickstart: validar el catálogo de categorías documentales

Guía de validación de extremo a extremo. Los detalles de datos y API están en [data-model.md](data-model.md) y [contracts/](contracts/).

## Prerrequisitos

- Backend y frontend instalados (`composer install`, `npm install`).
- Migraciones y semillas aplicadas en un entorno de pruebas, no en datos reales:

```bash
cd backend && php artisan migrate:fresh --seed --env=e2e
```

Las semillas crean en el tenant 1 las categorías Contratos y Certificados (Activas) y una Inactiva. Cuentas de ejemplo: `admin@hrms.test` (Administrador) y `usuario@hrms.test` (Colaborador), con la clave de pruebas del proyecto.

## Escenarios

1. **Crear y consultar (US1)**: como Administrador abrir «Categorías documentales»; ver solo las activas ordenadas por nombre; crear «Seguridad social» y comprobar que aparece Activa. Repetir con « SEGURIDAD SOCIAL » ⇒ error de nombre junto al campo y ninguna fila nueva.
2. **Unicidad entre tenants**: crear «Contratos» en el tenant 2 con `admin2@hrms.test` ⇒ se acepta; cada tenant ve solo las suyas.
3. **Corregir (US2)**: editar «Certificados» a «Certificados académicos»; el listado muestra el nombre nuevo. Editar una Inactiva ⇒ queda Inactiva. Dos pestañas editando la misma categoría ⇒ la segunda recibe «La información cambió» y conserva lo escrito.
4. **Desactivar y reactivar (US3)**: Desactivar muestra «La categoría dejará de estar disponible para nuevas cargas. Sus documentos se conservarán»; tras confirmar pasa a Inactiva y desaparece del filtro «Activas». Reactivar (con confirmación) la devuelve a Activa. No hay botón Eliminar en ningún sitio.
5. **Colaborador (FR-014)**: con `usuario@hrms.test`, `GET /api/categorias-documentales` y las escrituras responden 403; la navegación no muestra «Categorías documentales».
6. **Consumidor (US4)**: en una prueba, `PersonalFacade::getCategoriasActivas(1)` devuelve solo las activas; `getCategoriasPorIds` resuelve también la inactiva; `confirmarCategoriaActiva` lanza `CategoriaInactiva` tras desactivar.
7. **Sin documentos**: todo lo anterior funciona sin tabla de documentos en la base.

## Pruebas automatizadas

```bash
cd backend && vendor/bin/pint --dirty --format agent && php artisan test --compact --filter=Categoria
cd frontend && npm run lint && npx tsc -b && npm run test && npm run build
cd frontend && npx playwright test e2e/categorias-documentales.spec.ts
```

Cobertura esperada: unidad de `ReglasDeCategoria` y normalización (límites 100/101, espacios, tildes); feature de cada endpoint (401/403/404/422/409/503, 404 de otro tenant, búsqueda y filtro, orden, sin DELETE); dos creaciones simultáneas con el mismo nombre (una gana, la otra 422); versiones obsoletas; renombrar conserva el id; estado igual ⇒ 200 sin escritura; `confirmarActiva` frente a `CambiarEstado` (el segundo que llega pierde); lector sin acceso a tablas documentales; Vitest de validación, tabla, formulario (error junto al campo, envío único, 409 conserva lo escrito) y diálogos de estado; E2E del flujo principal y de acceso del Colaborador.
