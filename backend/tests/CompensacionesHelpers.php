<?php

use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\CategoriaReconocimientoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;

/**
 * Cuerpo válido para crear (o, con `updated_at`, editar) una categoría de reconocimiento.
 *
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function datosCategoria(?int $competenciaId = null, array $cambios = []): array
{
    return array_replace([
        'nombre' => 'Trabajo en equipo',
        'descripcion' => 'Colabora de forma activa con sus compañeros.',
        'competencia_id' => $competenciaId ?? CompetenciaModel::factory()->create()->id,
        'puntuacion_base' => 100,
        'icono' => 'equipo',
        'indicadores' => ['Comparte información con su equipo', 'Ayuda a quien lo necesita'],
    ], $cambios);
}

/**
 * Cuerpo de edición con los datos actuales de la categoría y su versión leída; `$cambios` pisa lo que se quiera cambiar.
 *
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function cuerpoDeCategoria(CategoriaReconocimientoModel $categoria, array $cambios = []): array
{
    return array_replace([
        'nombre' => $categoria->nombre,
        'descripcion' => $categoria->descripcion,
        'competencia_id' => $categoria->competencia_id,
        'puntuacion_base' => $categoria->puntuacion_base,
        'icono' => $categoria->icono,
        'indicadores' => $categoria->indicadores,
        'updated_at' => versionDeCategoria($categoria->id),
    ], $cambios);
}

/**
 * Versión (`updated_at`) de una categoría tal como la serializa la API: ISO 8601 UTC con microsegundos.
 */
function versionDeCategoria(int $id): string
{
    $crudo = CategoriaReconocimientoModel::query()->findOrFail($id)->getRawOriginal('updated_at');

    return (new DateTimeImmutable($crudo, new DateTimeZone(config('app.timezone'))))
        ->setTimezone(new DateTimeZone('UTC'))
        ->format('Y-m-d\TH:i:s.u\Z');
}
