<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Posición de la estructura organizacional. Tabla mínima reservada para la feature 005: el catálogo de puestos
 * solo la lee para saber si un puesto tiene posiciones Ocupadas o Vacantes.
 */
class PosicionModel extends Model
{
    protected $table = 'posiciones';

    protected $fillable = ['puesto_id', 'estado'];

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(PuestoModel::class, 'puesto_id');
    }
}
