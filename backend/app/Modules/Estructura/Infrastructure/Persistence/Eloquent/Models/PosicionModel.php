<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Posición de la estructura organizacional. Tabla mínima reservada para la feature de posiciones: el catálogo de
 * puestos y las unidades organizacionales solo la leen para saber si tienen posiciones Ocupadas o Vacantes.
 */
class PosicionModel extends Model
{
    protected $table = 'posiciones';

    protected $fillable = ['puesto_id', 'unidad_organizacional_id', 'estado'];

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(PuestoModel::class, 'puesto_id');
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UnidadModel::class, 'unidad_organizacional_id');
    }
}
