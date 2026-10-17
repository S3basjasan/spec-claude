# Quickstart: Micro-learning

Guía de validación de la feature. Los detalles de datos y endpoints están en [data-model.md](data-model.md) y
[contracts/](contracts/); aquí solo se describe cómo comprobar que funciona de punta a punta.

## Prerrequisitos

- PHP 8.3, Composer, Node y las dependencias instaladas (`backend/` y `frontend/`).
- Base de datos de desarrollo migrada y sembrada con los módulos previos (competencias, personal, catálogo, DNF).
- En el entorno de pruebas locales en Windows con OneDrive, consultar la nota de memoria «Entorno local de pruebas».

## Preparar los datos

```bash
cd backend
php artisan migrate
php artisan db:seed --class=MicroLearningSeeder
php artisan db:seed --class=CatalogoFormativoSeeder
```

`MicroLearningSeeder` debe ejecutarse antes que `CatalogoFormativoSeeder` (resuelve los micro-contenidos por código).

## Validación automatizada

```bash
cd backend
php artisan test --compact tests/Unit/Desarrollo tests/Feature/Desarrollo
vendor/bin/pint --test
cd ../frontend
npm run lint
npx tsc --noEmit
npm run test
npm run build
npm run e2e -- micro-learning.spec.ts
```

Resultado esperado: todo en verde. Las pruebas de 005 y 013 que referencian `nombre`/`Disponible` de micro-contenidos se ajustan
a `titulo`/`Publicado` y deben seguir pasando.

## Escenarios manuales (Administrador)

1. Entrar como administrador y abrir **Micro-learning**: se ve la cuadrícula con Borrador y Publicado por defecto.
2. **Nuevo contenido** → tipo Video sin URL → *Guardar borrador* funciona (título, tipo y duración presentes); *Publicar* muestra
   «faltan URL y competencias». Completar y publicar: estado Publicado, código `MICRO-NNNN`.
3. Crear un **Quiz** con 3 preguntas (una con 5 opciones, otra con 2), reordenar, marcar la correcta y publicar. Intentar agregar
   la pregunta 21 o la opción 6: se impide.
4. **Vista previa** del Quiz y responder todo: no cambia el número de completaciones ni crea progreso.
5. **Archivar** (confirmación) y **Publicar** de nuevo desde Archivado. Revisar el historial de estados.
6. **Eliminar** un Borrador sin progreso: desaparece. Un contenido con progreso no ofrece Eliminar.
7. En **Formación** (Catálogo), la competencia asociada muestra el micro-contenido y su historial registra «Asociado».

## Escenarios manuales (Colaborador)

1. Entrar como colaborador con una brecha activa en la DNF: **Mi aprendizaje** muestra en *Asignado* los contenidos vinculados.
2. Abrir un Video, avanzar, salir y volver: **Reanudar desde** la posición guardada. *Marcar como completado*: pasa a *Completado* y
   el botón desaparece.
3. Abrir un PDF o Artículo: el enlace abre la URL; marcarlo como completado.
4. Resolver el Quiz: «Pregunta 1 de N», retroalimentación inmediata, puntaje final «N de M». *Reintentar quiz* con un puntaje menor:
   el estado sigue Completado.
5. En *Completado*, **Descargar certificado**: el PDF trae nombre, título, competencias, duración y fecha.
6. Resumen: completados, en progreso, horas acumuladas y certificados coinciden con los registros.
7. Intentar abrir `/micro-learning/{id}` de un Borrador: no está disponible. Un contenido Archivado completado sigue visible en el
   historial.

## Verificación visual

Comparar con `design/MicroAdmin.dc.html`, `MicroColaborador.dc.html`, `MicroReproduccion.dc.html` y `MicroQuiz.dc.html` en tema
claro y oscuro, a 320 y 1440 px, con foco visible y sin desbordes horizontales.
