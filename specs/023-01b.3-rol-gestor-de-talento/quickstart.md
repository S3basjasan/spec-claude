# Quickstart: validar 01b.3

## Prerrequisitos
- Dependencias instaladas en `backend/` y `frontend/`; usuarios de prueba `admin@hrms.test` y `usuario@hrms.test`.

## 1. Backend
```bash
cd backend && ./vendor/bin/pest tests/Feature/Compensaciones
```
Esperado: todas pasan, incluida `PoliticaDeAccesoTest` (`categorias.gestionar` verdadero con `admin`, falso sin él, permiso desconocido falso).

```bash
cd backend && ./vendor/bin/pest && ./vendor/bin/pint --test
```
Esperado: suite completa (inc-01 incluida) y estilo sin errores.

## 2. Frontend
```bash
cd frontend && npm run lint && npx tsc --noEmit && npx vitest run src/modules/compensaciones && npm run build
```
Esperado: sin errores; el estado vacío del usuario dice «Cuando el Gestor de Talento cree categorías, aparecerán aquí.»

## 3. E2E
```bash
cd frontend && npx playwright test e2e/categorias-reconocimiento.spec.ts
```
Esperado: flujo del Administrador y del Usuario sin cambios.

## 4. Verificación manual de textos
- Con `usuario@hrms.test`, vaciar el listado (búsqueda sin catálogo) y leer el aviso: nombra al «Gestor de Talento».
- Buscar «Administrador de Talento Humano» y «Talento Humano» en `frontend/src/modules/compensaciones`, `backend/app/Modules/Compensaciones` y `design/CategoriasReconocimiento.dc.html`: 0 resultados.
- Confirmar que `git diff` no toca roles, el nombre `admin` ni otros módulos.
